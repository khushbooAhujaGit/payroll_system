<?php

namespace App\Http\Controllers\Leave;

use App\Http\Controllers\Controller;
use App\Models\LeaveApplication;
use App\Models\LeaveApproval;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\CompOff;
use App\Models\Employee;
use App\Services\PublicFileStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class LeaveController extends Controller
{
    public function __construct(protected PublicFileStore $publicFileStore) {}

    // ─────────────────────────────────────────────
    // LEAVE TYPES
    // ─────────────────────────────────────────────

    public function leaveTypes(Request $request): JsonResponse
    {
        $types = LeaveType::where('company_id', $request->company_id)
            ->where('is_active', true)
            ->with('policies')
            ->get();

        return response()->json(['data' => $types]);
    }

    public function storeLeaveType(Request $request): JsonResponse
    {
        $this->authorize('leave.manage');

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name'          => 'required|string|max:100',
            'code'          => 'required|string|max:10',
            'category'      => 'required|string',
            'is_paid'       => 'boolean',
            'is_carry_forward' => 'boolean',
            'max_carry_forward'=> 'integer|min:0',
            'is_encashable' => 'boolean',
            'is_half_day_allowed' => 'boolean',
            'gender_specific' => 'in:all,male,female',
        ]);

        $leaveType = LeaveType::create($validated);

        return response()->json(['data' => $leaveType], 201);
    }

    // ─────────────────────────────────────────────
    // BALANCES
    // ─────────────────────────────────────────────

    public function myBalances(Request $request): JsonResponse
    {
        $employee = auth()->user()->employee;
        $year     = $request->get('year', now()->year);

        $balances = LeaveBalance::where('employee_id', $employee->id)
            ->where('year', $year)
            ->with('leaveType:id,name,code,color,is_encashable,is_carry_forward')
            ->get()
            ->map(fn($b) => array_merge($b->toArray(), ['available' => $b->available]));

        return response()->json(['data' => $balances]);
    }

    public function teamBalances(Request $request): JsonResponse
    {
        $this->authorize('leave.view');

        $balances = LeaveBalance::with(['employee:id,employee_id,first_name,middle_name,last_name', 'leaveType:id,name,code'])
            ->whereHas('employee', fn($q) => $q->where('company_id', $request->company_id))
            ->where('year', $request->get('year', now()->year))
            ->get();

        return response()->json(['data' => $balances]);
    }

    public function adjustBalance(Request $request): JsonResponse
    {
        $this->authorize('leave.manage');

        $validated = $request->validate([
            'employee_id'   => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'year'          => 'required|integer',
            'days'          => 'required|numeric',   // positive = credit, negative = debit
            'reason'        => 'required|string',
        ]);

        $balance = LeaveBalance::where([
            'employee_id'   => $validated['employee_id'],
            'leave_type_id' => $validated['leave_type_id'],
            'year'          => $validated['year'],
        ])->first();

        if (!$balance) {
            $balance = LeaveBalance::create([
                'employee_id'   => $validated['employee_id'],
                'leave_type_id' => $validated['leave_type_id'],
                'year'          => $validated['year'],
            ]);
        }

        $balance->increment('adjusted', $validated['days']);
        $balance->update(['closing_balance' => $balance->available]);

        return response()->json(['message' => "Balance adjusted by {$validated['days']} days.", 'data' => $balance->fresh()]);
    }

    // ─────────────────────────────────────────────
    // APPLY FOR LEAVE
    // ─────────────────────────────────────────────

    public function apply(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'from_date'     => 'required|date|after_or_equal:today',
            'to_date'       => 'required|date|after_or_equal:from_date',
            'is_half_day'   => 'boolean',
            'half_day_type' => 'required_if:is_half_day,true|in:first_half,second_half',
            'reason'        => 'nullable|string|max:500',
            'document'      => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $employee  = auth()->user()->employee;
        $leaveType = LeaveType::findOrFail($validated['leave_type_id']);

        // Calculate days (excluding holidays & weekends)
        $days = $this->calculateLeaveDays(
            $validated['from_date'],
            $validated['to_date'],
            $validated['is_half_day'] ?? false,
            $employee
        );

        if ($days <= 0) {
            return response()->json(['message' => 'No valid working days in selected date range.'], 422);
        }

        // Validate minimum notice period
        if ($leaveType->notice_days_required > 0) {
            $noticeDays = Carbon::today()->diffInDays(Carbon::parse($validated['from_date']), false);
            if ($noticeDays < $leaveType->notice_days_required) {
                return response()->json(['message' => "This leave type requires {$leaveType->notice_days_required} days advance notice."], 422);
            }
        }

        // Check balance
        $balance = LeaveBalance::where('employee_id', $employee->id)
            ->where('leave_type_id', $validated['leave_type_id'])
            ->where('year', now()->year)
            ->first();

        $available = $balance?->available ?? 0;

        if (!$leaveType->is_lop_type && $available < $days && !($balance?->leaveType?->allow_negative_balance ?? false)) {
            return response()->json([
                'message'   => "Insufficient leave balance. Available: {$available} days, Requested: {$days} days.",
            ], 422);
        }

        DB::beginTransaction();
        try {
            $documentPath = null;
            if ($request->hasFile('document')) {
                $documentPath = $this->publicFileStore->store($request->file('document'), 'leave_docs');
            }

            $application = LeaveApplication::create([
                'application_number' => $this->generateApplicationNumber(),
                'employee_id'        => $employee->id,
                'leave_type_id'      => $validated['leave_type_id'],
                'from_date'          => $validated['from_date'],
                'to_date'            => $validated['to_date'],
                'days'               => $days,
                'is_half_day'        => $validated['is_half_day'] ?? false,
                'half_day_type'      => $validated['half_day_type'] ?? null,
                'reason'             => $validated['reason'] ?? null,
                'document_path'      => $documentPath,
                'status'             => 'pending',
                'applied_at'         => now(),
            ]);

            // Create L1 approval (reporting manager)
            if ($employee->reporting_manager_id) {
                $manager = Employee::findOrFail($employee->reporting_manager_id);
                if ($manager->user) {
                    LeaveApproval::create([
                        'leave_application_id' => $application->id,
                        'approver_id'          => $manager->user->id,
                        'level'                => 1,
                        'action'               => 'pending',
                    ]);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json([
            'message' => 'Leave application submitted.',
            'data'    => $application->load('leaveType'),
        ], 201);
    }

    // ─────────────────────────────────────────────
    // MY APPLICATIONS
    // ─────────────────────────────────────────────

    public function myApplications(Request $request): JsonResponse
    {
        $employee = auth()->user()->employee;

        $applications = LeaveApplication::where('employee_id', $employee->id)
            ->with(['leaveType:id,name,code,color', 'approvals.approver:id,name'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->year, fn($q) => $q->whereYear('from_date', $request->year))
            ->orderByDesc('applied_at')
            ->paginate(20);

        return response()->json($applications);
    }

    // ─────────────────────────────────────────────
    // TEAM APPLICATIONS (Manager / HR view)
    // ─────────────────────────────────────────────

    public function teamApplications(Request $request): JsonResponse
    {
        $this->authorize('leave.view');

        $applications = LeaveApplication::with([
            'employee:id,employee_id,first_name,middle_name,last_name,department_id',
            'employee.department:id,name',
            'leaveType:id,name,code,color',
            'approvals.approver:id,name',
        ])
        ->whereHas('employee', fn($q) => $q->where('company_id', $request->company_id))
        ->when($request->status, fn($q) => $q->where('status', $request->status))
        ->when($request->department_id, fn($q) => $q->whereHas('employee', fn($q2) => $q2->where('department_id', $request->department_id)))
        ->when($request->employee_id, fn($q) => $q->where('employee_id', $request->employee_id))
        ->orderByDesc('applied_at')
        ->paginate(50);

        return response()->json($applications);
    }

    // Pending approvals FOR the logged-in manager
    public function pendingMyApprovals(): JsonResponse
    {
        $user = auth()->user();

        $applications = LeaveApplication::whereHas('approvals', fn($q) => $q->where('approver_id', $user->id)->where('action', 'pending'))
            ->with([
                'employee:id,employee_id,first_name,middle_name,last_name',
                'leaveType:id,name,code',
            ])
            ->where('status', 'pending')
            ->orderBy('applied_at')
            ->get();

        return response()->json(['data' => $applications]);
    }

    // ─────────────────────────────────────────────
    // APPROVE / REJECT
    // ─────────────────────────────────────────────

    public function approve(Request $request, LeaveApplication $application): JsonResponse
    {
        $validated = $request->validate([
            'action'  => 'required|in:approved,rejected',
            'remarks' => 'nullable|string|max:500',
        ]);

        $user      = auth()->user();
        $approval  = LeaveApproval::where('leave_application_id', $application->id)
            ->where('approver_id', $user->id)
            ->where('action', 'pending')
            ->first();

        if (!$approval) {
            return response()->json(['message' => 'You are not an approver for this application.'], 403);
        }

        DB::beginTransaction();
        try {
            $approval->update([
                'action'      => $validated['action'],
                'remarks'     => $validated['remarks'] ?? null,
                'actioned_at' => now(),
            ]);

            if ($validated['action'] === 'approved') {
                // Check if more levels pending
                $pendingApprovals = LeaveApproval::where('leave_application_id', $application->id)
                    ->where('action', 'pending')
                    ->where('level', '>', $approval->level)
                    ->exists();

                if (!$pendingApprovals) {
                    // All levels approved
                    $application->update(['status' => 'approved']);
                    $this->deductLeaveBalance($application);
                    $this->updateAttendance($application);
                }
            } elseif ($validated['action'] === 'rejected') {
                $application->update(['status' => 'rejected']);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json(['message' => "Leave {$validated['action']}."]);
    }

    public function cancel(Request $request, LeaveApplication $application): JsonResponse
    {
        $employee = auth()->user()->employee;

        if ($application->employee_id !== $employee->id) {
            return response()->json(['message' => 'Cannot cancel another employee\'s leave.'], 403);
        }

        if (!in_array($application->status, ['pending', 'approved'])) {
            return response()->json(['message' => 'Only pending or approved leaves can be cancelled.'], 422);
        }

        DB::transaction(function () use ($application) {
            // Restore balance if approved
            if ($application->status === 'approved') {
                $this->restoreLeaveBalance($application);
                $this->removeAttendanceLeave($application);
            }

            $application->update(['status' => 'cancelled']);
        });

        return response()->json(['message' => 'Leave application cancelled.']);
    }

    // ─────────────────────────────────────────────
    // COMP-OFF
    // ─────────────────────────────────────────────

    public function applyCompOff(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'worked_on_date' => 'required|date',
            'credited_days'  => 'required|in:0.5,1',
            'reason'         => 'nullable|string',
        ]);

        $employee = auth()->user()->employee;

        $compOff = CompOff::create(array_merge($validated, [
            'employee_id' => $employee->id,
            'expiry_date' => Carbon::parse($validated['worked_on_date'])->addDays(90)->toDateString(),
            'status'      => 'pending',
        ]));

        return response()->json(['message' => 'Comp-off request submitted.', 'data' => $compOff], 201);
    }

    public function approveCompOff(Request $request, CompOff $compOff): JsonResponse
    {
        $this->authorize('leave.manage');

        $request->validate(['action' => 'required|in:approved,rejected']);

        if ($request->action === 'approved') {
            // Credit comp-off leave balance
            $compOffType = LeaveType::where('company_id', $compOff->employee->company_id)
                ->where('category', 'comp_off')->first();

            if ($compOffType) {
                $balance = LeaveBalance::firstOrCreate(
                    ['employee_id' => $compOff->employee_id, 'leave_type_id' => $compOffType->id, 'year' => now()->year],
                    ['opening_balance' => 0, 'accrued' => 0]
                );
                $balance->increment('accrued', $compOff->credited_days);
            }
        }

        $compOff->update(['status' => $request->action, 'approved_by' => auth()->id()]);

        return response()->json(['message' => "Comp-off {$request->action}."]);
    }

    // ─────────────────────────────────────────────
    // CARRY FORWARD (year-end process)
    // ─────────────────────────────────────────────

    public function runCarryForward(Request $request): JsonResponse
    {
        $this->authorize('leave.manage');

        $request->validate(['company_id' => 'required', 'from_year' => 'required|integer']);

        $fromYear = $request->from_year;
        $toYear   = $fromYear + 1;

        $leaveTypes = LeaveType::where('company_id', $request->company_id)
            ->where('is_carry_forward', true)
            ->where('is_active', true)
            ->get();

        $processed = 0;
        foreach ($leaveTypes as $lt) {
            $balances = LeaveBalance::where('leave_type_id', $lt->id)
                ->where('year', $fromYear)
                ->whereHas('employee', fn($q) => $q->where('company_id', $request->company_id)->where('employee_status', 'active'))
                ->get();

            foreach ($balances as $balance) {
                $cfDays = min($balance->available, $lt->max_carry_forward);
                $lapsed = max(0, $balance->available - $cfDays);

                // Update current year as lapsed
                $balance->update(['lapsed' => $lapsed]);

                // Create next year balance with CF
                LeaveBalance::updateOrCreate(
                    ['employee_id' => $balance->employee_id, 'leave_type_id' => $lt->id, 'year' => $toYear],
                    ['carry_forwarded' => $cfDays, 'opening_balance' => $cfDays]
                );
                $processed++;
            }
        }

        return response()->json(['message' => "Carry-forward processed for {$processed} records."]);
    }

    // ─────────────────────────────────────────────
    // LEAVE CALENDAR (team view)
    // ─────────────────────────────────────────────

    public function leaveCalendar(Request $request): JsonResponse
    {
        $request->validate(['company_id' => 'required', 'from' => 'required|date', 'to' => 'required|date']);

        $leaves = LeaveApplication::with([
            'employee:id,employee_id,first_name,middle_name,last_name',
            'leaveType:id,name,code,color',
        ])
        ->whereHas('employee', fn($q) => $q->where('company_id', $request->company_id))
        ->where('status', 'approved')
        ->where('from_date', '<=', $request->to)
        ->where('to_date', '>=', $request->from)
        ->get()
        ->map(fn($l) => [
            'id'         => $l->id,
            'employee'   => $l->employee->full_name,
            'employee_id'=> $l->employee->employee_id,
            'leave_type' => $l->leaveType->name,
            'color'      => $l->leaveType->color ?? '#3b82f6',
            'start'      => $l->from_date->format('Y-m-d'),
            'end'        => $l->to_date->format('Y-m-d'),
            'days'       => $l->days,
        ]);

        return response()->json(['data' => $leaves]);
    }

    // ─────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────

    protected function calculateLeaveDays(string $from, string $to, bool $isHalfDay, Employee $employee): float
    {
        if ($isHalfDay) return 0.5;

        $period   = CarbonPeriod::create($from, $to);
        $holidays = $this->getHolidayDates($employee);
        $roster   = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
        $days     = 0;

        foreach ($period as $day) {
            if (in_array($day->format('D'), $roster) && !in_array($day->format('Y-m-d'), $holidays)) {
                $days++;
            }
        }
        return $days;
    }

    protected function getHolidayDates(Employee $employee): array
    {
        if (!$employee->holiday_calendar_id) return [];
        return \App\Models\Holiday::where('holiday_calendar_id', $employee->holiday_calendar_id)
            ->whereYear('date', now()->year)->pluck('date')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))->toArray();
    }

    /**
     * Comp-Off is meant to be spent down first: a CL application draws
     * against any available Comp-Off balance before touching the
     * employee's own CL pool, so approved Comp-Off doesn't just sit
     * alongside CL indefinitely.
     *
     * Matches strictly on code === 'CL', not category === 'casual' — the
     * "casual" category is shared by several unrelated auto-generated types
     * from the leave-tracker import (Birthday Leave, Anniversary Leave,
     * Punch Missing, Employee Left, Approval Pending, Exam Leave...), all
     * of them is_active and none of them a real Comp-Off-eligible CL
     * application. Only the canonical Casual Leave type should trigger this.
     */
    protected function deductLeaveBalance(LeaveApplication $application): void
    {
        $year          = Carbon::parse($application->from_date)->year;
        $leaveType     = $application->leaveType;
        $daysRemaining = $application->days;

        if ($leaveType && strtoupper($leaveType->code) === 'CL') {
            $compOffType = LeaveType::where('company_id', $leaveType->company_id)
                ->where('category', 'comp_off')->first();

            if ($compOffType) {
                $compOffBalance = LeaveBalance::where([
                    'employee_id'   => $application->employee_id,
                    'leave_type_id' => $compOffType->id,
                    'year'          => $year,
                ])->first();

                if ($compOffBalance && $compOffBalance->available > 0) {
                    $setOff = min($daysRemaining, $compOffBalance->available);
                    $compOffBalance->increment('availed', $setOff);
                    $compOffBalance->update(['closing_balance' => $compOffBalance->available]);
                    $daysRemaining -= $setOff;

                    // Recorded so a later cancellation can reverse the exact
                    // same split instead of crediting everything back to CL.
                    $application->update(['comp_off_setoff_days' => $setOff]);
                }
            }
        }

        if ($daysRemaining <= 0) {
            return;
        }

        $balance = LeaveBalance::where([
            'employee_id'   => $application->employee_id,
            'leave_type_id' => $application->leave_type_id,
            'year'          => $year,
        ])->first();

        if ($balance) {
            $balance->increment('availed', $daysRemaining);
            $balance->update(['closing_balance' => $balance->available]);
        }
    }

    /**
     * Mirrors deductLeaveBalance()'s split exactly — a CL application that
     * set off some days against Comp-Off must give those specific days back
     * to Comp-Off, not to CL, or cancelling repeatedly would inflate CL and
     * permanently drain Comp-Off.
     */
    protected function restoreLeaveBalance(LeaveApplication $application): void
    {
        $year          = Carbon::parse($application->from_date)->year;
        $setOffDays    = (float) $application->comp_off_setoff_days;
        $leaveType     = $application->leaveType;

        if ($setOffDays > 0 && $leaveType) {
            $compOffType = LeaveType::where('company_id', $leaveType->company_id)
                ->where('category', 'comp_off')->first();

            if ($compOffType) {
                $compOffBalance = LeaveBalance::where([
                    'employee_id'   => $application->employee_id,
                    'leave_type_id' => $compOffType->id,
                    'year'          => $year,
                ])->first();

                if ($compOffBalance) {
                    $compOffBalance->decrement('availed', $setOffDays);
                    $compOffBalance->update(['closing_balance' => $compOffBalance->available]);
                }
            }
        }

        $ownTypeDays = $application->days - $setOffDays;

        if ($ownTypeDays > 0) {
            $balance = LeaveBalance::where([
                'employee_id'   => $application->employee_id,
                'leave_type_id' => $application->leave_type_id,
                'year'          => $year,
            ])->first();

            if ($balance) {
                $balance->decrement('availed', $ownTypeDays);
                $balance->update(['closing_balance' => $balance->available]);
            }
        }
    }

    protected function updateAttendance(LeaveApplication $application): void
    {
        $period = CarbonPeriod::create($application->from_date, $application->to_date);
        foreach ($period as $day) {
            Attendance::updateOrCreate(
                ['employee_id' => $application->employee_id, 'attendance_date' => $day->format('Y-m-d')],
                ['status' => 'leave', 'source' => 'leave_system']
            );
        }
    }

    protected function removeAttendanceLeave(LeaveApplication $application): void
    {
        Attendance::where('employee_id', $application->employee_id)
            ->whereBetween('attendance_date', [$application->from_date, $application->to_date])
            ->where('status', 'leave')
            ->where('source', 'leave_system')
            ->delete();
    }

    protected function generateApplicationNumber(): string
    {
        $last = LeaveApplication::orderByDesc('id')->value('application_number');
        $num  = 1;
        if ($last) { preg_match('/(\d+)$/', $last, $m); $num = (int)($m[1] ?? 0) + 1; }
        return 'LA-' . now()->year . '-' . str_pad($num, 6, '0', STR_PAD_LEFT);
    }
}
