<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\ImportsExcel;
use App\Http\Controllers\Controller;
use App\Imports\SalaryRevisionImporter;
use App\Models\Employee;
use App\Models\EmployeeHistory;
use App\Models\EmployeeSalary;
use App\Models\Entity;
use App\Models\SalaryRevision;
use App\Services\ExcelImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebRevisionController extends Controller
{
    use ImportsExcel;
    public function __construct(protected ExcelImportService $excelImportService) {}

    public function index(Request $request)
    {
        $entityId = $this->resolveEntityId($request);

        $revisions = SalaryRevision::with([
            'employee:id,employee_id,first_name,last_name,department_id',
            'employee.department:id,name',
        ])
        ->whereHas('employee', fn($q) => $q->where('entity_id', $entityId))
        ->when($request->status, fn($q) => $q->where('status', $request->status))
        ->orderByDesc('created_at')
        ->paginate(25)->withQueryString();

        $employees = Employee::where('entity_id', $entityId)
            ->where('employee_status', 'active')
            ->orderBy('first_name')->get();

        return view('revisions.index', compact('revisions', 'employees', 'entityId'));
    }

    public function store(Request $request)
    {
        $entityId = $this->resolveEntityId($request);

        $validated = $request->validate([
            'employee_id'    => 'required|exists:employees,id',
            'new_ctc'        => 'required|numeric|min:1',
            'effective_date' => 'required|date',
            'reason'         => 'required|string|max:500',
        ]);

        $employee = Employee::with('currentSalary')->findOrFail($validated['employee_id']);
        $oldCtc   = $employee->currentSalary?->ctc_annual ?? 0;
        $newCtc   = $validated['new_ctc'];
        $incPct   = $oldCtc > 0 ? round((($newCtc - $oldCtc) / $oldCtc) * 100, 2) : 0;

        SalaryRevision::create([
            'employee_id'          => $validated['employee_id'],
            'old_ctc'              => $oldCtc,
            'new_ctc'              => $newCtc,
            'increment_amount'     => $newCtc - $oldCtc,
            'increment_percentage' => $incPct,
            'effective_date'       => $validated['effective_date'],
            'reason'               => $validated['reason'],
            'status'               => 'pending_approval',
            'created_by'           => auth()->id(),
        ]);

        return redirect()->route('revisions')->with('success', 'Salary revision initiated.');
    }

    public function approve(Request $request, SalaryRevision $revision)
    {
        $request->validate(['action' => 'required|in:approved,rejected']);

        if ($request->action === 'rejected') {
            $revision->update(['status' => 'rejected']);
            return redirect()->route('revisions')->with('success', 'Revision rejected.');
        }

        if ($revision->status !== 'pending_approval') {
            return redirect()->back()->withErrors(['revision' => 'Only pending revisions can be approved.']);
        }

        DB::beginTransaction();
        try {
            EmployeeSalary::where('employee_id', $revision->employee_id)
                ->where('is_current', true)
                ->update(['is_current' => false, 'effective_to' => now()->subDay()->format('Y-m-d')]);

            $newCtcMonthly = round($revision->new_ctc / 12, 2);
            $newSalary = EmployeeSalary::create([
                'employee_id'    => $revision->employee_id,
                'ctc_annual'     => $revision->new_ctc,
                'ctc_monthly'    => $newCtcMonthly,
                'gross_monthly'  => round($newCtcMonthly * 0.9, 2),
                'effective_from' => $revision->effective_date,
                'is_current'     => true,
                'revision_type'  => 'increment',
                'remarks'        => $revision->reason,
                'approved_by'    => auth()->id(),
                'created_by'     => auth()->id(),
            ]);

            $revision->update([
                'status'              => 'implemented',
                'approved_by'         => auth()->id(),
                'approved_at'         => now(),
                'employee_salary_id'  => $newSalary->id,
            ]);

            EmployeeHistory::create([
                'employee_id'    => $revision->employee_id,
                'event_type'     => 'increment',
                'effective_date' => $revision->effective_date,
                'old_values'     => ['ctc_annual' => $revision->old_ctc],
                'new_values'     => ['ctc_annual' => $revision->new_ctc],
                'remarks'        => $revision->reason,
                'created_by'     => auth()->id(),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()->route('revisions')->with('success', 'Salary revision approved and implemented.');
    }

    public function template()
    {
        return $this->excelImportService->generateTemplate(SalaryRevisionImporter::class);
    }

    public function import(Request $request)
    {
        $entityId = $this->resolveEntityId($request);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        $result = $this->excelImportService->import(
            $request->file('file'),
            'salary_revision',
            SalaryRevisionImporter::class,
            ['entity_id' => $entityId]
        );

        return $this->handleImportResult($result, 'revisions', 'salary revision');
    }
}
