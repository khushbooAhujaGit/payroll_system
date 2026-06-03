<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\EmployeeSalaryComponent;
use App\Models\SalaryRevision;
use App\Models\EmployeeHistory;
use App\Services\ExcelImportService;
use App\Imports\SalaryRevisionImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalaryRevisionController extends Controller
{
    public function __construct(protected ExcelImportService $excelImportService) {}

    // ─────────────────────────────────────────────
    // LIST REVISIONS
    // ─────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $revisions = SalaryRevision::with([
            'employee:id,employee_id,first_name,middle_name,last_name,department_id',
            'employee.department:id,name',
            'createdBy:id,name',
            'approvedBy:id,name',
        ])
        ->whereHas('employee', fn($q) => $q->where('company_id', $request->company_id))
        ->when($request->status, fn($q) => $q->where('status', $request->status))
        ->when($request->employee_id, fn($q) => $q->where('employee_id', $request->employee_id))
        ->orderByDesc('created_at')
        ->paginate(25);

        return response()->json($revisions);
    }

    // ─────────────────────────────────────────────
    // INITIATE REVISION
    // ─────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $this->authorize('salary_revision.create');

        $validated = $request->validate([
            'employee_id'     => 'required|exists:employees,id',
            'new_ctc'         => 'required|numeric|min:1',
            'effective_date'  => 'required|date',
            'reason'          => 'required|string|max:500',
            'component_split' => 'nullable|array',   // optional custom component breakdown
        ]);

        $employee   = Employee::with('currentSalary')->findOrFail($validated['employee_id']);
        $oldCtc     = $employee->currentSalary?->ctc_annual ?? 0;
        $newCtc     = $validated['new_ctc'];
        $increment  = $newCtc - $oldCtc;
        $incrementPct = $oldCtc > 0 ? round(($increment / $oldCtc) * 100, 2) : 0;

        $revision = SalaryRevision::create([
            'employee_id'          => $validated['employee_id'],
            'old_ctc'              => $oldCtc,
            'new_ctc'              => $newCtc,
            'increment_amount'     => $increment,
            'increment_percentage' => $incrementPct,
            'effective_date'       => $validated['effective_date'],
            'reason'               => $validated['reason'],
            'status'               => 'pending_approval',
            'created_by'           => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Salary revision initiated and sent for approval.',
            'data'    => $revision->load('employee'),
        ], 201);
    }

    // ─────────────────────────────────────────────
    // APPROVE & IMPLEMENT
    // ─────────────────────────────────────────────

    public function approve(Request $request, SalaryRevision $revision): JsonResponse
    {
        $this->authorize('salary_revision.approve');

        if ($revision->status !== 'pending_approval') {
            return response()->json(['message' => 'Only pending revisions can be approved.'], 422);
        }

        $request->validate([
            'component_split' => 'nullable|array',
            'remarks'         => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Mark old salary inactive
            EmployeeSalary::where('employee_id', $revision->employee_id)
                ->where('is_current', true)
                ->update([
                    'is_current'  => false,
                    'effective_to' => \Carbon\Carbon::parse($revision->effective_date)->subDay()->format('Y-m-d'),
                ]);

            // Create new salary record (versioned)
            $employee         = Employee::findOrFail($revision->employee_id);
            $newCtcAnnual     = $revision->new_ctc;
            $newCtcMonthly    = round($newCtcAnnual / 12, 2);

            // Default gross is 90% of CTC monthly (remaining is employer contributions)
            $grossMonthly = $request->gross_monthly ?? round($newCtcMonthly * 0.9, 2);

            $newSalary = EmployeeSalary::create([
                'employee_id'        => $revision->employee_id,
                'salary_structure_id'=> $employee->currentSalary?->salary_structure_id,
                'ctc_annual'         => $newCtcAnnual,
                'ctc_monthly'        => $newCtcMonthly,
                'gross_monthly'      => $grossMonthly,
                'effective_from'     => $revision->effective_date,
                'is_current'         => true,
                'revision_type'      => 'increment',
                'remarks'            => $revision->reason,
                'approved_by'        => auth()->id(),
                'created_by'         => auth()->id(),
            ]);

            // If component split provided, save it
            if ($request->component_split) {
                foreach ($request->component_split as $componentId => $amounts) {
                    EmployeeSalaryComponent::updateOrCreate(
                        ['employee_salary_id' => $newSalary->id, 'salary_component_id' => $componentId],
                        [
                            'monthly_amount' => $amounts['monthly'],
                            'annual_amount'  => $amounts['monthly'] * 12,
                        ]
                    );
                }
            } else {
                // Auto-split based on salary structure
                $this->autoSplitComponents($newSalary, $grossMonthly);
            }

            // Update revision status
            $revision->update([
                'status'           => 'implemented',
                'approved_by'      => auth()->id(),
                'approved_at'      => now(),
                'employee_salary_id'=> $newSalary->id,
            ]);

            // Employee history
            EmployeeHistory::create([
                'employee_id'    => $revision->employee_id,
                'event_type'     => 'increment',
                'effective_date' => $revision->effective_date,
                'old_values'     => ['ctc_annual' => $revision->old_ctc],
                'new_values'     => ['ctc_annual' => $revision->new_ctc, 'increment_pct' => $revision->increment_percentage],
                'remarks'        => $revision->reason,
                'created_by'     => auth()->id(),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json([
            'message' => 'Salary revision approved and implemented.',
            'data'    => $revision->fresh(['employee', 'employeeSalary']),
        ]);
    }

    public function reject(Request $request, SalaryRevision $revision): JsonResponse
    {
        $this->authorize('salary_revision.approve');

        $request->validate(['reason' => 'required|string']);

        $revision->update(['status' => 'rejected']);

        return response()->json(['message' => 'Salary revision rejected.']);
    }

    // ─────────────────────────────────────────────
    // BULK REVISION via Excel
    // ─────────────────────────────────────────────

    public function uploadTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $this->excelImportService->generateTemplate(SalaryRevisionImporter::class);
    }

    public function import(Request $request): JsonResponse
    {
        $this->authorize('salary_revision.create');

        $request->validate([
            'file'      => 'required|file|mimes:xlsx,xls|max:10240',
            'company_id' => 'required|exists:companies,id',
        ]);

        $result = $this->excelImportService->import(
            $request->file('file'),
            'salary_revision',
            SalaryRevisionImporter::class,
            ['company_id' => $request->company_id]
        );

        return response()->json($result);
    }

    // ─────────────────────────────────────────────
    // SALARY HISTORY (for an employee)
    // ─────────────────────────────────────────────

    public function employeeSalaryHistory(Employee $employee): JsonResponse
    {
        $salaries = EmployeeSalary::where('employee_id', $employee->id)
            ->with(['components.salaryComponent', 'approvedBy:id,name'])
            ->orderByDesc('effective_from')
            ->get();

        return response()->json(['data' => $salaries]);
    }

    // ─────────────────────────────────────────────
    // HELPER
    // ─────────────────────────────────────────────

    protected function autoSplitComponents(EmployeeSalary $salary, float $gross): void
    {
        if (!$salary->salary_structure_id) return;

        $structure = $salary->salaryStructure->components()->with('salaryComponent')->get();

        foreach ($structure as $structureComponent) {
            $sc     = $structureComponent->salaryComponent;
            $amount = match($sc->calculation_type) {
                'fixed'      => $structureComponent->fixed_value ?? 0,
                'percentage' => round(($gross * ($structureComponent->percentage_value ?? 0)) / 100, 2),
                default      => 0,
            };

            EmployeeSalaryComponent::updateOrCreate(
                ['employee_salary_id' => $salary->id, 'salary_component_id' => $sc->id],
                ['monthly_amount' => $amount, 'annual_amount' => $amount * 12]
            );
        }

        // Update gross/net on salary
        $totalEarnings = EmployeeSalaryComponent::where('employee_salary_id', $salary->id)
            ->whereHas('salaryComponent', fn($q) => $q->where('component_type', 'earning'))
            ->sum('monthly_amount');

        $salary->update(['gross_monthly' => $totalEarnings]);
    }
}
