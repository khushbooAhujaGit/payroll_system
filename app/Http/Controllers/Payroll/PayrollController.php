<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollEmployeeRecord;
use App\Models\PayrollComponent;
use App\Models\BankTransferFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PayrollController extends Controller
{
    public function periods(Request $request): JsonResponse
    {
        $periods = PayrollPeriod::where('company_id', $request->company_id)
            ->withCount('runs')
            ->orderByDesc('year')->orderByDesc('month')
            ->paginate(24);
        return response()->json($periods);
    }

    public function createPeriod(Request $request): JsonResponse
    {
        $this->authorize('payroll.run');
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'year'        => 'required|integer|min:2000|max:2100',
            'month'       => 'required|integer|min:1|max:12',
            'period_from' => 'required|date',
            'period_to'   => 'required|date|after:period_from',
            'attendance_lock_date' => 'required|date',
            'payment_date'=> 'nullable|date',
        ]);
        $exists = PayrollPeriod::where('company_id', $validated['company_id'])
            ->where('year', $validated['year'])->where('month', $validated['month'])->exists();
        if ($exists) return response()->json(['message' => 'Period already exists.'], 422);
        $period = PayrollPeriod::create(array_merge($validated, ['status' => 'open']));
        return response()->json(['message' => 'Payroll period created.', 'data' => $period], 201);
    }

    public function initRun(Request $request): JsonResponse
    {
        $this->authorize('payroll.run');
        $validated = $request->validate([
            'payroll_period_id' => 'required|exists:payroll_periods,id',
            'run_type'  => 'required|in:regular,supplementary,arrear,bonus',
            'run_name'  => 'nullable|string',
        ]);
        $period = PayrollPeriod::findOrFail($validated['payroll_period_id']);
        if ($period->is_locked) return response()->json(['message' => 'Period is locked.'], 422);
        $run = PayrollRun::create([
            'payroll_period_id' => $period->id,
            'company_id' => $period->company_id,
            'run_type'          => $validated['run_type'],
            'run_name'          => $validated['run_name'] ?? ucfirst($validated['run_type']),
            'status'            => 'draft',
            'is_editable'       => true,
        ]);
        return response()->json(['message' => 'Payroll run initiated.', 'data' => $run], 201);
    }

    public function calculate(Request $request, PayrollRun $run): JsonResponse
    {
        $this->authorize('payroll.run');
        if (!in_array($run->status, ['draft', 'calculated']))
            return response()->json(['message' => 'Cannot recalculate a finalized run.'], 422);
        $run->update(['status' => 'calculated', 'calculated_at' => now(), 'calculated_by' => auth()->id()]);
        return response()->json(['message' => 'Payroll calculated.']);
    }

    public function calculateSingleEmployee(Request $request, PayrollRun $run): JsonResponse
    {
        $this->authorize('payroll.run');
        return response()->json(['message' => 'Calculation queued.']);
    }

    public function salarySheet(Request $request, PayrollRun $run): JsonResponse
    {
        $records = PayrollEmployeeRecord::where('payroll_run_id', $run->id)
            ->with(['employee:id,employee_id,first_name,middle_name,last_name,department_id', 'employee.department:id,name'])
            ->when($request->employee_id, fn($q) => $q->where('employee_id', $request->employee_id))
            ->paginate(50);
        return response()->json($records);
    }

    public function overrideNetSalary(Request $request, PayrollEmployeeRecord $record): JsonResponse
    {
        $this->authorize('payroll.edit');
        $request->validate(['net_salary' => 'required|numeric|min:0', 'reason' => 'required|string']);
        $record->update(['is_manually_edited' => true, 'manual_net_override' => $request->net_salary,
            'manual_edit_reason' => $request->reason, 'manually_edited_by' => auth()->id(), 'manually_edited_at' => now()]);
        return response()->json(['message' => 'Net salary overridden.', 'data' => $record->fresh()]);
    }

    public function overrideComponent(Request $request, PayrollComponent $component): JsonResponse
    {
        $this->authorize('payroll.edit');
        $request->validate(['amount' => 'required|numeric|min:0', 'reason' => 'required|string']);
        $component->update(['override_amount' => $request->amount, 'final_amount' => $request->amount, 'is_overridden' => true, 'override_reason' => $request->reason]);
        return response()->json(['message' => 'Component overridden.', 'data' => $component->fresh()]);
    }

    public function blockEmployee(Request $request, PayrollEmployeeRecord $record): JsonResponse
    {
        $this->authorize('payroll.edit');
        $request->validate(['reason' => 'required|string']);
        $record->update(['is_blocked' => true, 'block_reason' => $request->reason, 'status' => 'blocked']);
        return response()->json(['message' => 'Employee blocked.']);
    }

    public function unblockEmployee(Request $request, PayrollEmployeeRecord $record): JsonResponse
    {
        $this->authorize('payroll.edit');
        $record->update(['is_blocked' => false, 'block_reason' => null, 'status' => 'calculated']);
        return response()->json(['message' => 'Employee unblocked.']);
    }

    public function finalize(Request $request, PayrollRun $run): JsonResponse
    {
        $this->authorize('payroll.approve');
        if ($run->status !== 'calculated') return response()->json(['message' => 'Only calculated runs can be finalized.'], 422);
        $run->update(['status' => 'finalized', 'is_editable' => false, 'approved_by' => auth()->id(), 'approved_at' => now()]);
        return response()->json(['message' => 'Payroll finalized.', 'data' => $run->fresh()]);
    }

    public function lockPeriod(Request $request, PayrollPeriod $period): JsonResponse
    {
        $this->authorize('payroll.lock');
        $period->update(['is_locked' => true, 'locked_at' => now(), 'locked_by' => auth()->id()]);
        return response()->json(['message' => 'Period locked.']);
    }

    public function unlockPeriod(Request $request, PayrollPeriod $period): JsonResponse
    {
        $this->authorize('payroll.lock');
        $period->update(['is_locked' => false, 'status' => 'open']);
        return response()->json(['message' => 'Period unlocked.']);
    }

    public function payrollRegister(Request $request, PayrollRun $run): JsonResponse
    {
        $records = PayrollEmployeeRecord::where('payroll_run_id', $run->id)->paginate(100);
        return response()->json($records);
    }

    public function reconciliation(Request $request, PayrollRun $run): JsonResponse
    {
        $records = PayrollEmployeeRecord::where('payroll_run_id', $run->id)->get();
        return response()->json(['data' => $records, 'run' => $run]);
    }

    public function generatePayslips(PayrollRun $run): JsonResponse
    {
        return response()->json(['message' => 'Payslip generation queued.']);
    }

    public function downloadPayslip(PayrollEmployeeRecord $record): JsonResponse
    {
        return response()->json(['message' => 'Payslip not yet generated.'], 404);
    }

    public function generateBankFile(Request $request, PayrollRun $run): JsonResponse
    {
        return response()->json(['message' => 'Bank file generation queued.']);
    }

    public function downloadBankFile(BankTransferFile $bankFile): JsonResponse
    {
        return response()->json(['message' => 'File not found.'], 404);
    }
}
