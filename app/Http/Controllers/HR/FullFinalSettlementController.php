<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ExitRequest;
use App\Models\FullFinalSettlement;
use App\Models\EmployeeLoan;
use App\Models\LoanInstallment;
use App\Models\LeaveBalance;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FullFinalSettlementController extends Controller
{
    public function __construct(protected AttendanceService $attendanceService) {}

    // ─────────────────────────────────────────────
    // LIST F&F SETTLEMENTS
    // ─────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $this->authorize('employee.view');

        $settlements = FullFinalSettlement::with([
            'employee:id,employee_id,first_name,last_name,department_id',
            'employee.department:id,name',
            'exitRequest',
            'approvedBy:id,name',
        ])
        ->whereHas('employee', fn($q) => $q->where('entity_id', $request->entity_id))
        ->when($request->status, fn($q) => $q->where('status', $request->status))
        ->orderByDesc('created_at')
        ->paginate(25);

        return response()->json($settlements);
    }

    // ─────────────────────────────────────────────
    // INITIATE EXIT REQUEST
    // ─────────────────────────────────────────────

    public function initiateExit(Request $request): JsonResponse
    {
        $this->authorize('employee.edit');

        $validated = $request->validate([
            'employee_id'    => 'required|exists:employees,id',
            'exit_type'      => 'required|in:resignation,termination,retirement,end_of_contract,absconding',
            'notice_date'    => 'required|date',
            'last_working_date' => 'required|date|after:notice_date',
            'exit_reason'    => 'nullable|string|max:1000',
            'remarks'        => 'nullable|string',
        ]);

        $employee      = Employee::findOrFail($validated['employee_id']);
        $noticeDays    = Carbon::parse($validated['notice_date'])->diffInDays(Carbon::parse($validated['last_working_date']));

        $exit = ExitRequest::create(array_merge($validated, [
            'notice_period_days'  => $noticeDays,
            'status'              => 'initiated',
        ]));

        // Update employee status
        $employee->update(['employee_status' => 'on_notice']);

        return response()->json(['message' => 'Exit initiated.', 'data' => $exit], 201);
    }

    // ─────────────────────────────────────────────
    // COMPUTE F&F (auto-calculate all components)
    // ─────────────────────────────────────────────

    public function compute(Request $request, ExitRequest $exitRequest): JsonResponse
    {
        $this->authorize('employee.edit');

        $employee = $exitRequest->employee->load([
            'currentSalary.components.salaryComponent',
            'entity',
            'statutoryDetails',
            'activeLoans.installments',
        ]);

        $lwd       = Carbon::parse($exitRequest->last_working_date);
        $doj       = Carbon::parse($employee->date_of_joining);
        $serviceYears  = $doj->diffInYears($lwd);
        $serviceMonths = $doj->diffInMonths($lwd);

        // ── 1. Salary Payable (for partial month) ────────────────
        $lastMonthFrom = $lwd->copy()->startOfMonth()->toDateString();
        $lastMonthTo   = $lwd->toDateString();

        $attendance      = $this->attendanceService->getMonthlySummary($employee->id, $lastMonthFrom, $lastMonthTo);
        $grossMonthly    = $employee->currentSalary?->gross_monthly ?? 0;
        $workingDaysInMonth = Carbon::parse($lastMonthFrom)->daysInMonth;
        $payableDays     = $attendance['working_days'] - $attendance['lop_days'];
        $salaryPayable   = round($grossMonthly / $workingDaysInMonth * $payableDays, 2);

        // ── 2. Leave Encashment ──────────────────────────────────
        $earnedLeaveBalance = LeaveBalance::where('employee_id', $employee->id)
            ->whereHas('leaveType', fn($q) => $q->where('is_encashable', true))
            ->where('year', now()->year)
            ->get()
            ->sum(fn($b) => $b->available);

        $basicMonthly       = $employee->currentSalary?->components
            ->where('salaryComponent.code', 'BASIC')->first()?->monthly_amount ?? 0;
        $leaveEncashment    = round(($basicMonthly / 26) * min($earnedLeaveBalance, 30), 2); // max 30 days

        // ── 3. Gratuity ──────────────────────────────────────────
        // Eligible after 5 years of continuous service
        $gratuityAmount = 0;
        if ($serviceYears >= 5) {
            $gratuityAmount = round(($basicMonthly * 15 / 26) * $serviceYears, 2);
        }

        // ── 4. Notice Pay ────────────────────────────────────────
        $noticePeriodRequired = $employee->notice_period_days ?? 30;
        $actualNoticeDays     = $exitRequest->notice_period_days;

        $noticeDaysSalary = round($grossMonthly / 30, 2);
        $noticePayPayable  = 0;
        $noticePayRecovery = 0;

        if ($actualNoticeDays > $noticePeriodRequired) {
            // Extra notice — company pays for excess
            $noticePayPayable = round($noticeDaysSalary * ($actualNoticeDays - $noticePeriodRequired), 2);
        } elseif ($actualNoticeDays < $noticePeriodRequired) {
            // Short notice — employee pays buyout
            $noticePayRecovery = round($noticeDaysSalary * ($noticePeriodRequired - $actualNoticeDays), 2);
        }

        // ── 5. Loan Recovery ─────────────────────────────────────
        $loanRecovery = \App\Models\EmployeeLoan::where('employee_id', $employee->id)
            ->where('status', 'active')
            ->sum('outstanding_amount');

        // ── 6. Bonus Recovery (if any sign-on or retention bonus with clawback) ─
        $bonusRecovery = 0;  // configurable per policy

        // ── 7. TDS on F&F ────────────────────────────────────────
        $totalPayable  = $salaryPayable + $leaveEncashment + $gratuityAmount + $noticePayPayable;
        $totalDeductible = $loanRecovery + $noticePayRecovery + $bonusRecovery;
        // TDS simplified at marginal rate — full TDS engine applies
        $tdsPct        = 30; // placeholder: actual TDS from EmployeeTdsComputation
        $tdsOnFnf      = round(max(0, $salaryPayable) * $tdsPct / 100, 2);

        $netPayable    = round($totalPayable - $totalDeductible - $tdsOnFnf, 2);

        $computation = [
            'employee'             => $employee->only('id', 'employee_id', 'first_name', 'last_name'),
            'service_years'        => $serviceYears,
            'last_working_date'    => $exitRequest->last_working_date,
            // Payable
            'salary_payable'       => $salaryPayable,
            'payable_days'         => $payableDays,
            'leave_encashment'     => $leaveEncashment,
            'earned_leave_days'    => $earnedLeaveBalance,
            'gratuity_amount'      => $gratuityAmount,
            'notice_pay_payable'   => $noticePayPayable,
            'total_payable'        => $totalPayable,
            // Deductions
            'loan_recovery'        => $loanRecovery,
            'notice_pay_recovery'  => $noticePayRecovery,
            'bonus_recovery'       => $bonusRecovery,
            'tds_deduction'        => $tdsOnFnf,
            'total_recoverable'    => $totalDeductible + $tdsOnFnf,
            // Net
            'net_payable'          => $netPayable,
        ];

        return response()->json(['data' => $computation]);
    }

    // ─────────────────────────────────────────────
    // SAVE / FINALIZE F&F
    // ─────────────────────────────────────────────

    public function finalize(Request $request, ExitRequest $exitRequest): JsonResponse
    {
        $this->authorize('employee.edit');

        $validated = $request->validate([
            'last_working_date'    => 'required|date',
            'settlement_year'      => 'required|integer',
            'settlement_month'     => 'required|integer|min:1|max:12',
            'payable_days'         => 'required|numeric',
            'salary_payable'       => 'required|numeric',
            'leave_encashment'     => 'nullable|numeric',
            'gratuity_amount'      => 'nullable|numeric',
            'notice_pay_recovery'  => 'nullable|numeric',
            'notice_pay_payable'   => 'nullable|numeric',
            'bonus_payable'        => 'nullable|numeric',
            'bonus_recovery'       => 'nullable|numeric',
            'loan_recovery'        => 'nullable|numeric',
            'advance_recovery'     => 'nullable|numeric',
            'other_deductions'     => 'nullable|numeric',
            'other_payments'       => 'nullable|numeric',
            'tds_deduction'        => 'nullable|numeric',
            'total_payable'        => 'required|numeric',
            'total_recoverable'    => 'required|numeric',
            'net_payable'          => 'required|numeric',
            'remarks'              => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $settlement = FullFinalSettlement::updateOrCreate(
                ['employee_id' => $exitRequest->employee_id, 'exit_request_id' => $exitRequest->id],
                array_merge($validated, ['status' => 'draft', 'approved_by' => null])
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json(['message' => 'F&F settlement saved.', 'data' => $settlement]);
    }

    // ─────────────────────────────────────────────
    // APPROVE F&F
    // ─────────────────────────────────────────────

    public function approve(Request $request, FullFinalSettlement $settlement): JsonResponse
    {
        $this->authorize('employee.edit');

        $request->validate(['payment_date' => 'required|date']);

        DB::transaction(function () use ($settlement, $request) {
            $settlement->update([
                'status'       => 'approved',
                'payment_date' => $request->payment_date,
                'approved_by'  => auth()->id(),
            ]);

            // Deactivate employee
            $employee = $settlement->employee;
            $employee->update(['employee_status' => 'inactive']);

            // Close active loans
            \App\Models\EmployeeLoan::where('employee_id', $employee->id)
                ->where('status', 'active')
                ->update(['status' => 'closed', 'outstanding_amount' => 0]);
        });

        return response()->json(['message' => 'F&F approved. Employee deactivated.', 'data' => $settlement->fresh()]);
    }

    // ─────────────────────────────────────────────
    // SHOW SINGLE
    // ─────────────────────────────────────────────

    public function show(FullFinalSettlement $settlement): JsonResponse
    {
        $settlement->load(['employee.department', 'employee.designation', 'exitRequest', 'approvedBy:id,name']);
        return response()->json(['data' => $settlement]);
    }
}
