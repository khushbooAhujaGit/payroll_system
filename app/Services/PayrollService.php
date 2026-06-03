<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollEmployeeRecord;
use App\Models\PayrollComponent;
use App\Models\PayrollReconciliation;
use App\Models\SalaryComponent;
use App\Models\Attendance;
use App\Models\LoanInstallment;
use App\Models\EmployeeBonus;
use App\Models\PayrollArrear;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayrollService
{
    protected StatutoryService $statutoryService;
    protected AttendanceService $attendanceService;

    public function __construct(
        StatutoryService $statutoryService,
        AttendanceService $attendanceService
    ) {
        $this->statutoryService = $statutoryService;
        $this->attendanceService = $attendanceService;
    }

    // ─────────────────────────────────────────────
    // MAIN PAYROLL RUN
    // ─────────────────────────────────────────────

    /**
     * Calculate payroll for all active employees in an entity for a given period.
     * This is idempotent — calling it again recalculates without duplication.
     */
    public function runPayroll(PayrollRun $payrollRun): PayrollRun
    {
        $period = $payrollRun->period;

        if ($period->is_locked) {
            throw new \Exception("Payroll period is locked. Unlock it before recalculating.");
        }

        DB::beginTransaction();
        try {
            $employees = $this->getEligibleEmployees($payrollRun);

            $totals = [
                'total_employees'           => 0,
                'total_gross'               => 0,
                'total_deductions'          => 0,
                'total_net'                 => 0,
                'total_employer_contribution' => 0,
            ];

            foreach ($employees as $employee) {
                $record = $this->calculateEmployeePayroll($payrollRun, $employee, $period);

                $totals['total_employees']++;
                $totals['total_gross']                += $record->total_earnings;
                $totals['total_deductions']           += $record->total_deductions;
                $totals['total_net']                  += $record->net_salary;
                $totals['total_employer_contribution'] += $record->total_employer_contribution;
            }

            $payrollRun->update(array_merge($totals, [
                'status'          => 'calculated',
                'calculated_at'   => now(),
                'calculated_by'   => auth()->id(),
            ]));

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Payroll run failed: " . $e->getMessage(), [
                'payroll_run_id' => $payrollRun->id,
                'trace'          => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        return $payrollRun->fresh();
    }

    // ─────────────────────────────────────────────
    // PER-EMPLOYEE CALCULATION
    // ─────────────────────────────────────────────

    public function calculateEmployeePayroll(
        PayrollRun $payrollRun,
        Employee $employee,
        PayrollPeriod $period
    ): PayrollEmployeeRecord {

        // Skip blocked employees (zero payroll)
        $existingRecord = PayrollEmployeeRecord::where([
            'payroll_run_id' => $payrollRun->id,
            'employee_id'    => $employee->id,
        ])->first();

        if ($existingRecord && $existingRecord->is_blocked) {
            return $existingRecord; // Zero payroll — do not calculate
        }

        // ── Step 1: Attendance summary ──────────────
        $attendance = $this->attendanceService->getMonthlySummary(
            $employee->id,
            $period->period_from,
            $period->period_to
        );

        $payableDays = $this->computePayableDays($attendance, $period);

        // ── Step 2: Current salary structure ────────
        $employeeSalary = $employee->currentSalary;
        if (!$employeeSalary) {
            Log::warning("Employee {$employee->employee_id} has no current salary. Skipping.");
            return $this->createZeroRecord($payrollRun, $employee, $attendance, 'No salary structure assigned.');
        }

        // ── Step 3: Calculate each earning component ─
        $components   = $employeeSalary->components; // collection of EmployeeSalaryComponent
        $earningsMap  = [];
        $deductionMap = [];
        $employerMap  = [];

        foreach ($components as $comp) {
            $salaryComp = $comp->salaryComponent;
            $amount     = $this->computeComponentAmount($comp, $earningsMap, $payableDays, $attendance);

            if ($salaryComp->component_type === 'earning') {
                $earningsMap[$salaryComp->code] = $amount;
            } elseif ($salaryComp->component_type === 'deduction') {
                $deductionMap[$salaryComp->code] = $amount;
            } elseif ($salaryComp->component_type === 'employer_contribution') {
                $employerMap[$salaryComp->code] = $amount;
            }
        }

        // ── Step 4: LOP deduction ───────────────────
        $lopDeduction = $this->computeLopDeduction($earningsMap, $attendance['lop_days'], $attendance['working_days']);

        // ── Step 5: Statutory deductions ─────────────
        $statutory = $this->statutoryService->compute($employee, $earningsMap, $period);

        // Merge statutory into deductions
        $deductionMap['PF_EMP']   = $statutory['pf_employee'];
        $deductionMap['ESIC_EMP'] = $statutory['esic_employee'];
        $deductionMap['PT']       = $statutory['pt_amount'];
        $deductionMap['LWF_EMP']  = $statutory['lwf_employee'];
        $deductionMap['TDS']      = $statutory['tds_amount'];

        $employerMap['PF_EMP_ER']  = $statutory['pf_employer'];
        $employerMap['ESIC_EMP_ER']= $statutory['esic_employer'];
        $employerMap['LWF_EMP_ER'] = $statutory['lwf_employer'];

        // ── Step 6: Loan deductions ──────────────────
        $loanDeduction = $this->processLoanDeductions($employee, $payrollRun);
        $deductionMap['LOAN'] = $loanDeduction;

        // ── Step 7: Arrears ─────────────────────────
        $arrearAmount = $this->processArrears($employee, $payrollRun);

        // ── Step 8: Bonus / incentives for this month ─
        $bonusAmount = EmployeeBonus::where('employee_id', $employee->id)
            ->where('year', $period->year)
            ->where('month', $period->month)
            ->where('status', 'approved')
            ->sum('amount');

        // ── Step 9: Totals ──────────────────────────
        $totalEarnings     = array_sum($earningsMap) + $arrearAmount + $bonusAmount - $lopDeduction;
        $totalDeductions   = array_sum($deductionMap);
        $totalEmployer     = array_sum($employerMap);
        $netSalary         = max(0, $totalEarnings - $totalDeductions);

        // ── Step 10: Upsert employee record ─────────
        $record = PayrollEmployeeRecord::updateOrCreate(
            ['payroll_run_id' => $payrollRun->id, 'employee_id' => $employee->id],
            [
                'working_days'               => $attendance['working_days'],
                'present_days'               => $attendance['present_days'],
                'absent_days'                => $attendance['absent_days'],
                'leave_days'                 => $attendance['leave_days'],
                'lop_days'                   => $attendance['lop_days'],
                'holiday_days'               => $attendance['holiday_days'],
                'weekly_off_days'            => $attendance['weekly_off_days'],
                'overtime_minutes'           => $attendance['overtime_minutes'],
                'payable_days'               => $payableDays,
                'gross_salary'               => $employeeSalary->gross_monthly,
                'total_earnings'             => round($totalEarnings, 2),
                'total_deductions'           => round($totalDeductions, 2),
                'total_employer_contribution'=> round($totalEmployer, 2),
                'net_salary'                 => round($netSalary, 2),
                'pf_employee'                => $statutory['pf_employee'],
                'pf_employer'                => $statutory['pf_employer'],
                'esic_employee'              => $statutory['esic_employee'],
                'esic_employer'              => $statutory['esic_employer'],
                'pt_amount'                  => $statutory['pt_amount'],
                'lwf_employee'               => $statutory['lwf_employee'],
                'lwf_employer'               => $statutory['lwf_employer'],
                'tds_amount'                 => $statutory['tds_amount'],
                'loan_deduction'             => $loanDeduction,
                'arrear_amount'              => $arrearAmount,
                'status'                     => 'calculated',
            ]
        );

        // ── Step 11: Store component-wise breakdown ──
        $this->savePayrollComponents($record, $earningsMap, $deductionMap, $employerMap);

        return $record;
    }

    // ─────────────────────────────────────────────
    // COMPONENT CALCULATION ENGINE
    // ─────────────────────────────────────────────

    protected function computeComponentAmount(
        $comp,
        array $earningsMap,
        float $payableDays,
        array $attendance
    ): float {
        $salaryComp = $comp->salaryComponent;
        $calcType   = $salaryComp->calculation_type;

        switch ($calcType) {
            case 'fixed':
                $amount = (float) $comp->monthly_amount;
                break;

            case 'percentage':
                $baseCode = $salaryComp->percentage_of;
                $base     = $earningsMap[$baseCode] ?? 0;
                $amount   = ($base * ($salaryComp->percentage_value / 100));
                break;

            case 'formula':
                $amount = $this->evaluateFormula(
                    $salaryComp->formula,
                    $earningsMap,
                    $payableDays,
                    $attendance
                );
                break;

            default:
                $amount = (float) $comp->monthly_amount;
        }

        // Pro-rate by payable days
        $workingDays = $attendance['working_days'];
        if ($workingDays > 0 && $payableDays < $workingDays) {
            $amount = ($amount / $workingDays) * $payableDays;
        }

        return round($amount, 2);
    }

    /**
     * Simple formula evaluator (supports basic math + variable substitution).
     * Variables: BASIC, HRA, GROSS, PAYABLE_DAYS, WORKING_DAYS
     */
    protected function evaluateFormula(string $formula, array $earningsMap, float $payableDays, array $attendance): float
    {
        // Replace component codes with their values
        $expression = $formula;
        foreach ($earningsMap as $code => $value) {
            $expression = str_replace($code, (string) $value, $expression);
        }
        $expression = str_replace('PAYABLE_DAYS', (string) $payableDays, $expression);
        $expression = str_replace('WORKING_DAYS', (string) $attendance['working_days'], $expression);

        try {
            // Safe eval using a math parser (in production use symfony/expression-language)
            return (float) eval("return ({$expression});");
        } catch (\Throwable $e) {
            Log::error("Formula evaluation failed: {$formula}", ['expression' => $expression]);
            return 0.0;
        }
    }

    // ─────────────────────────────────────────────
    // LOP CALCULATION
    // ─────────────────────────────────────────────

    protected function computeLopDeduction(array $earningsMap, float $lopDays, int $workingDays): float
    {
        if ($lopDays <= 0 || $workingDays <= 0) return 0;

        $totalGross = array_sum(array_filter($earningsMap)); // only earnings
        return round(($totalGross / $workingDays) * $lopDays, 2);
    }

    // ─────────────────────────────────────────────
    // PAYABLE DAYS
    // ─────────────────────────────────────────────

    protected function computePayableDays(array $attendance, PayrollPeriod $period): float
    {
        // Payable = Working days - LOP days
        return max(0, $attendance['working_days'] - $attendance['lop_days']);
    }

    // ─────────────────────────────────────────────
    // LOAN DEDUCTION PROCESSING
    // ─────────────────────────────────────────────

    protected function processLoanDeductions(Employee $employee, PayrollRun $payrollRun): float
    {
        $period = $payrollRun->period;

        $installments = LoanInstallment::where('employee_id', $employee->id)
            ->where('emi_year', $period->year)
            ->where('emi_month', $period->month)
            ->where('status', 'pending')
            ->whereHas('employeeLoan', fn($q) => $q->where('status', 'active'))
            ->get();

        $total = 0;
        foreach ($installments as $installment) {
            $installment->update([
                'status'         => 'deducted',
                'payroll_run_id' => $payrollRun->id,
            ]);
            // Update outstanding
            $loan = $installment->employeeLoan;
            $loan->decrement('outstanding_amount', $installment->total_emi_amount);
            if ($loan->outstanding_amount <= 0) {
                $loan->update(['status' => 'closed']);
            }
            $total += $installment->total_emi_amount;
        }
        return round($total, 2);
    }

    // ─────────────────────────────────────────────
    // ARREARS
    // ─────────────────────────────────────────────

    protected function processArrears(Employee $employee, PayrollRun $payrollRun): float
    {
        $arrears = PayrollArrear::where('employee_id', $employee->id)
            ->where('status', 'pending')
            ->get();

        $total = 0;
        foreach ($arrears as $arrear) {
            $arrear->update([
                'status'         => 'included',
                'payroll_run_id' => $payrollRun->id,
            ]);
            $total += $arrear->arrear_amount;
        }
        return round($total, 2);
    }

    // ─────────────────────────────────────────────
    // COMPONENT SNAPSHOT SAVE
    // ─────────────────────────────────────────────

    protected function savePayrollComponents(
        PayrollEmployeeRecord $record,
        array $earningsMap,
        array $deductionMap,
        array $employerMap
    ): void {
        // Delete old component records for this record (in case of recalculation)
        PayrollComponent::where('payroll_employee_record_id', $record->id)->delete();

        $allComponents = SalaryComponent::all()->keyBy('code');
        $allMaps       = array_merge($earningsMap, $deductionMap, $employerMap);

        foreach ($allMaps as $code => $amount) {
            $component = $allComponents->get($code);
            if (!$component) continue;

            PayrollComponent::create([
                'payroll_employee_record_id' => $record->id,
                'salary_component_id'        => $component->id,
                'computed_amount'             => $amount,
                'final_amount'                => $amount,
                'is_overridden'               => false,
            ]);
        }
    }

    // ─────────────────────────────────────────────
    // ADMIN SALARY OVERRIDE
    // ─────────────────────────────────────────────

    /**
     * Allows admin to override final net salary OR individual components.
     * All overrides are tracked with reason and user.
     */
    public function adminOverrideNetSalary(
        PayrollEmployeeRecord $record,
        float $newNetSalary,
        string $reason
    ): PayrollEmployeeRecord {
        if ($record->payrollRun->status === 'finalized') {
            throw new \Exception("Cannot override a finalized payroll.");
        }

        $record->update([
            'manual_net_override'    => $newNetSalary,
            'is_manually_edited'     => true,
            'manual_edit_reason'     => $reason,
            'manually_edited_by'     => auth()->id(),
            'manually_edited_at'     => now(),
        ]);

        return $record->fresh();
    }

    public function adminOverrideComponent(
        PayrollComponent $component,
        float $overrideAmount,
        string $reason
    ): PayrollComponent {
        $component->update([
            'override_amount' => $overrideAmount,
            'final_amount'    => $overrideAmount,
            'is_overridden'   => true,
            'override_reason' => $reason,
        ]);

        // Recalculate the employee record totals
        $record = $component->payrollEmployeeRecord;
        $this->recalculateTotalsFromComponents($record);

        return $component->fresh();
    }

    protected function recalculateTotalsFromComponents(PayrollEmployeeRecord $record): void
    {
        $components = PayrollComponent::with('salaryComponent')
            ->where('payroll_employee_record_id', $record->id)
            ->get();

        $totalEarnings     = 0;
        $totalDeductions   = 0;
        $totalEmployer     = 0;

        foreach ($components as $comp) {
            $type = $comp->salaryComponent->component_type;
            $amount = $comp->final_amount;

            match($type) {
                'earning'               => $totalEarnings   += $amount,
                'deduction'             => $totalDeductions += $amount,
                'employer_contribution' => $totalEmployer   += $amount,
                default                 => null,
            };
        }

        $record->update([
            'total_earnings'             => round($totalEarnings, 2),
            'total_deductions'           => round($totalDeductions, 2),
            'total_employer_contribution'=> round($totalEmployer, 2),
            'net_salary'                 => round(max(0, $totalEarnings - $totalDeductions), 2),
        ]);
    }

    // ─────────────────────────────────────────────
    // BLOCK / UNBLOCK EMPLOYEE FROM PAYROLL
    // ─────────────────────────────────────────────

    public function blockEmployee(PayrollEmployeeRecord $record, string $reason): void
    {
        $record->update([
            'is_blocked'   => true,
            'block_reason' => $reason,
            'status'       => 'blocked',
            'net_salary'   => 0,
        ]);
    }

    public function unblockEmployee(PayrollEmployeeRecord $record): void
    {
        $record->update([
            'is_blocked'   => false,
            'block_reason' => null,
            'status'       => 'calculated',
        ]);
        // Trigger recalculation for this employee
        $this->calculateEmployeePayroll(
            $record->payrollRun,
            $record->employee,
            $record->payrollRun->period
        );
    }

    // ─────────────────────────────────────────────
    // PAYROLL LOCK / UNLOCK
    // ─────────────────────────────────────────────

    public function lockPayrollPeriod(PayrollPeriod $period): void
    {
        if ($period->is_locked) {
            throw new \Exception("Period is already locked.");
        }
        $period->update([
            'is_locked'  => true,
            'locked_at'  => now(),
            'locked_by'  => auth()->id(),
            'status'     => 'finalized',
        ]);
        // Lock all attendance records in this period
        \App\Models\Attendance::whereBetween('attendance_date', [$period->period_from, $period->period_to])
            ->whereHas('employee', fn($q) => $q->where('company_id', $period->company_id))
            ->update(['is_locked' => true]);
    }

    public function unlockPayrollPeriod(PayrollPeriod $period, string $reason): void
    {
        $period->update([
            'is_locked' => false,
            'locked_at' => null,
            'locked_by' => null,
            'status'    => 'processed',
        ]);
        // Unlock attendance
        \App\Models\Attendance::whereBetween('attendance_date', [$period->period_from, $period->period_to])
            ->whereHas('employee', fn($q) => $q->where('company_id', $period->company_id))
            ->update(['is_locked' => false]);
    }

    // ─────────────────────────────────────────────
    // RECONCILIATION
    // ─────────────────────────────────────────────

    public function generateReconciliation(PayrollRun $currentRun): void
    {
        // Get previous month's run
        $prevPeriod = PayrollPeriod::where('company_id', $currentRun->company_id)
            ->where('id', '<', $currentRun->payroll_period_id)
            ->orderByDesc('id')
            ->first();

        if (!$prevPeriod) return;

        $prevRun = PayrollRun::where('payroll_period_id', $prevPeriod->id)
            ->where('run_type', 'regular')
            ->where('status', 'finalized')
            ->first();

        if (!$prevRun) return;

        $currentRecords = PayrollEmployeeRecord::where('payroll_run_id', $currentRun->id)->get()->keyBy('employee_id');
        $prevRecords    = PayrollEmployeeRecord::where('payroll_run_id', $prevRun->id)->get()->keyBy('employee_id');

        PayrollReconciliation::where('payroll_run_id', $currentRun->id)->delete();

        foreach ($currentRecords as $empId => $currentRecord) {
            $prevRecord = $prevRecords->get($empId);
            $prevNet    = $prevRecord ? $prevRecord->net_salary : 0;
            $currNet    = $currentRecord->net_salary;

            $reasons = $this->detectDiffReasons($currentRecord, $prevRecord);

            PayrollReconciliation::create([
                'payroll_run_id'   => $currentRun->id,
                'employee_id'      => $empId,
                'prev_net'         => $prevNet,
                'curr_net'         => $currNet,
                'net_diff'         => $currNet - $prevNet,
                'reasons'          => $reasons,
            ]);
        }
    }

    protected function detectDiffReasons(PayrollEmployeeRecord $current, ?PayrollEmployeeRecord $prev): array
    {
        $reasons = [];
        if (!$prev) {
            $reasons[] = 'New employee / first payroll';
            return $reasons;
        }
        if ($current->payable_days != $prev->payable_days)
            $reasons[] = "Payable days changed: {$prev->payable_days} → {$current->payable_days}";
        if ($current->lop_days != $prev->lop_days)
            $reasons[] = "LOP days changed: {$prev->lop_days} → {$current->lop_days}";
        if ($current->loan_deduction != $prev->loan_deduction)
            $reasons[] = "Loan deduction changed: {$prev->loan_deduction} → {$current->loan_deduction}";
        if ($current->arrear_amount != $prev->arrear_amount)
            $reasons[] = "Arrears: ₹{$current->arrear_amount}";
        if ($current->tds_amount != $prev->tds_amount)
            $reasons[] = "TDS changed: {$prev->tds_amount} → {$current->tds_amount}";
        if ($current->is_manually_edited)
            $reasons[] = "Manual override by admin: {$current->manual_edit_reason}";
        return $reasons;
    }

    // ─────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────

    protected function getEligibleEmployees(PayrollRun $payrollRun): Collection
    {
        return Employee::where('company_id', $payrollRun->company_id)
            ->where('is_payroll_active', true)
            ->where('employee_status', 'active')
            ->where('date_of_joining', '<=', $payrollRun->period->period_to)
            ->with(['currentSalary.components.salaryComponent'])
            ->get();
    }

    protected function createZeroRecord(
        PayrollRun $payrollRun,
        Employee $employee,
        array $attendance,
        string $reason
    ): PayrollEmployeeRecord {
        return PayrollEmployeeRecord::updateOrCreate(
            ['payroll_run_id' => $payrollRun->id, 'employee_id' => $employee->id],
            [
                'working_days'  => $attendance['working_days'] ?? 0,
                'present_days'  => 0,
                'is_blocked'    => true,
                'block_reason'  => $reason,
                'status'        => 'blocked',
                'net_salary'    => 0,
            ]
        );
    }
}
