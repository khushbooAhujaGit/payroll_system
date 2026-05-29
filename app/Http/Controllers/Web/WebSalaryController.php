<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\ImportsExcel;
use App\Http\Controllers\Controller;
use App\Imports\SalaryImporter;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\SalaryRawUpload;
use App\Services\ExcelImportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class WebSalaryController extends Controller
{
    use ImportsExcel;

    public function __construct(protected ExcelImportService $excelImportService) {}

    public function index(Request $request)
    {
        $entityId   = $this->resolveEntityId($request);
        $isAdmin    = auth()->user()?->hasRole('admin') ?? true;
        $monthParam = $request->get('month'); // YYYY-MM or null
        $isHistoric = false;
        $monthLabel = null;

        // Validate month format
        if ($monthParam && preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
            $isHistoric = true;
            $monthLabel = Carbon::parse($monthParam . '-01')->format('F Y');
            $asOf       = Carbon::parse($monthParam . '-01')->endOfMonth()->toDateString();
        }

        $employees = Employee::with(['department', 'designation'])
            ->where('entity_id', $entityId)
            ->where('employee_status', 'active')
            ->when($request->search, fn($q) => $q->where(fn($sq) =>
                $sq->where('first_name', 'like', "%{$request->search}%")
                   ->orWhere('employee_id', 'like', "%{$request->search}%")
            ))
            ->orderBy('employee_id')
            ->paginate(25)->withQueryString();

        // Load the appropriate salary per employee and attach as 'salaryForMonth'
        $empIds = $employees->pluck('id');

        if ($isHistoric) {
            // For each employee, find the salary that was effective at end-of-month
            // = latest effective_from that is on or before $asOf
            $salaryMap = EmployeeSalary::whereIn('employee_id', $empIds)
                ->where('effective_from', '<=', $asOf)
                ->orderBy('effective_from', 'desc')
                ->get()
                ->groupBy('employee_id')
                ->map(fn($records) => $records->first());

            $employees->each(fn($emp) =>
                $emp->setRelation('salaryForMonth', $salaryMap->get($emp->id))
            );
        } else {
            // Current view — load is_current = true (same as currentSalary)
            $currentMap = EmployeeSalary::whereIn('employee_id', $empIds)
                ->where('is_current', true)
                ->get()
                ->keyBy('employee_id');

            $employees->each(fn($emp) =>
                $emp->setRelation('salaryForMonth', $currentMap->get($emp->id))
            );
        }

        return view('salary.index', compact(
            'employees', 'entityId', 'isAdmin',
            'monthParam', 'isHistoric', 'monthLabel'
        ));
    }

    public function setSalary(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'ctc_annual'    => 'required|numeric|min:1',
            'gross_monthly' => 'required|numeric|min:1',
            'effective_from'=> 'required|date',
        ]);

        $gross      = (float) $validated['gross_monthly'];
        $ctcAnnual  = (float) $validated['ctc_annual'];
        $ctcMonthly = round($ctcAnnual / 12, 2);

        $grossEnc   = Crypt::encrypt((string) $gross);
        $ctcAnnEnc  = Crypt::encrypt((string) $ctcAnnual);
        $ctcMonEnc  = Crypt::encrypt((string) $ctcMonthly);

        \Illuminate\Support\Facades\DB::transaction(function () use (
            $employee, $grossEnc, $ctcAnnEnc, $ctcMonEnc, $validated
        ) {
            EmployeeSalary::where('employee_id', $employee->id)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            // Plaintext stored as 0 — real values only in encrypted columns
            EmployeeSalary::create([
                'employee_id'       => $employee->id,
                'ctc_annual'        => 0,
                'ctc_monthly'       => 0,
                'gross_monthly'     => 0,
                'gross_monthly_enc' => $grossEnc,
                'ctc_annual_enc'    => $ctcAnnEnc,
                'ctc_monthly_enc'   => $ctcMonEnc,
                'effective_from'    => $validated['effective_from'],
                'is_current'        => true,
                'revision_type'     => 'manual',
                'created_by'        => auth()->id(),
            ]);
        });

        // Audit record
        SalaryRawUpload::create([
            'employee_id'       => $employee->id,
            'emp_id_text'       => $employee->employee_id,
            'gross_monthly_enc' => $grossEnc,
            'ctc_annual_enc'    => $ctcAnnEnc,
            'ctc_monthly_enc'   => $ctcMonEnc,
            'effective_from'    => $validated['effective_from'],
            'import_month'      => null,
            'source'            => 'manual',
            'uploaded_by'       => auth()->id(),
        ]);

        return redirect()->route('salary')->with('success', 'Salary set for ' . $employee->first_name . ' ' . $employee->last_name . '.');
    }

    public function destroySalary(EmployeeSalary $salary)
    {
        $salary->delete();
        return redirect()->route('salary')->with('success', 'Salary record deleted.');
    }

    public function template()
    {
        return $this->excelImportService->generateTemplate(SalaryImporter::class);
    }

    public function import(Request $request)
    {
        $entityId = $this->resolveEntityId($request);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        // import_month (YYYY-MM) → first day used as effective_from when row has none
        $importMonth = $request->get('import_month');
        $effectiveFrom = null;
        if ($importMonth && preg_match('/^\d{4}-\d{2}$/', $importMonth)) {
            $effectiveFrom = $importMonth . '-01';
        }

        $result = $this->excelImportService->import(
            $request->file('file'),
            'salary',
            SalaryImporter::class,
            [
                'entity_id'     => $entityId,
                'uploaded_by'   => auth()->id(),
                'effective_from'=> $effectiveFrom,
                'import_month'  => $importMonth,
            ]
        );

        return $this->handleImportResult($result, 'salary', 'salary record');
    }
}
