<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRegularization;
use App\Models\LeaveTrackerRecord;
use App\Services\AttendanceDayResolver;
use App\Services\AttendanceWeeklyAverageService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile mirror of WebMyAttendanceController's self-service surface
 * (index()/requestChange()/analytics()'s plain-employee branch) — daily
 * attendance + weekly averages + raising/viewing Change Requests, and the
 * self-only Attendance Analytics monthly summary. HR/Admin's review queue
 * (pendingRequests()/resolveRequest()) stays web-only — see
 * WebMyAttendanceController's own doc comments for why that's HR/Admin-only
 * now.
 */
class AttendanceHistoryAppController extends Controller
{
    private function resolveMonth(?string $month): Carbon
    {
        try {
            return $month ? Carbon::createFromFormat('Y-m', $month)->startOfMonth() : now()->startOfMonth();
        } catch (\Exception) {
            return now()->startOfMonth();
        }
    }

    /** Daily attendance + weekly averages + this employee's own past Change Requests, for one month. */
    public function history(Request $request, AttendanceWeeklyAverageService $service, AttendanceDayResolver $resolver): JsonResponse
    {
        $this->authorize('attendance_self_view');

        $employee = $request->user()->employee;
        abort_unless($employee, 404, 'No employee profile linked to this account.');

        $monthStart = $this->resolveMonth($request->get('month'));
        $monthEnd   = $monthStart->copy()->endOfMonth();

        $punchesByDate = $resolver->punchesForEmployeeRange($employee->id, $monthStart->toDateString(), $monthEnd->toDateString());

        $existingDayRequests = AttendanceRegularization::where('employee_id', $employee->id)
            ->where('reason', 'daily_hours_shortfall')
            ->whereBetween('attendance_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->get()
            ->keyBy(fn($r) => $r->attendance_date->format('Y-m-d'));

        $days = [];
        for ($cursor = $monthStart->copy(); $cursor <= $monthEnd; $cursor->addDay()) {
            $dateStr    = $cursor->toDateString();
            $dayPunches = $punchesByDate->get($dateStr, collect());
            $checkIn    = $dayPunches->filter(fn($p) => $p->check_in  !== null)->min('check_in');
            $checkOut   = $dayPunches->filter(fn($p) => $p->check_out !== null)->max('check_out');

            $hours = null;
            if ($checkIn && $checkOut) {
                $inTs  = strtotime($dateStr . ' ' . $checkIn);
                $outTs = strtotime($dateStr . ' ' . $checkOut);
                if ($outTs > $inTs) {
                    $hours = round(($outTs - $inTs) / 3600, 2);
                }
            }

            $existing = $existingDayRequests->get($dateStr);
            $days[] = [
                'date'             => $dateStr,
                'check_in'         => $checkIn,
                'check_out'        => $checkOut,
                'hours'            => $hours,
                'can_request_day'  => $hours !== null && $hours < 9 && !$existing,
                'existing_request' => $existing ? $this->formatRequest($existing) : null,
            ];
        }

        $weekStarts = [];
        $weekCursor = $monthStart->copy()->startOfWeek(Carbon::MONDAY);
        while ($weekCursor <= $monthEnd) {
            $weekStarts[] = $weekCursor->toDateString();
            $weekCursor->addWeek();
        }
        $existingWeekRequests = AttendanceRegularization::where('employee_id', $employee->id)
            ->where('reason', 'weekly_hours_shortfall')
            ->whereIn('attendance_date', $weekStarts)
            ->get()
            ->keyBy(fn($r) => $r->attendance_date->format('Y-m-d'));

        $weeks = [];
        foreach ($weekStarts as $weekStartStr) {
            $weekStart = Carbon::parse($weekStartStr);
            $weekEnd   = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
            $result    = $service->forEmployeeDateRange($employee->id, $weekStart, $weekEnd);
            $existing  = $existingWeekRequests->get($weekStartStr);

            $weeks[] = [
                'week_start'       => $weekStartStr,
                'week_end'         => $weekEnd->toDateString(),
                'average_hours'    => $result['average_hours'] ?? null,
                'day_count'        => $result['day_count'] ?? 0,
                'is_shortfall'     => $service->isShortfall($result) && !$existing,
                'existing_request' => $existing ? $this->formatRequest($existing) : null,
            ];
        }

        $myRequests = AttendanceRegularization::where('employee_id', $employee->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn($r) => $this->formatRequest($r));

        return response()->json([
            'data' => ['days' => $days, 'weeks' => $weeks, 'my_requests' => $myRequests],
        ]);
    }

    private function formatRequest(AttendanceRegularization $r): array
    {
        return [
            'id'                  => $r->id,
            'reason'              => $r->reason,
            'attendance_date'     => $r->attendance_date->format('Y-m-d'),
            'remarks'             => $r->remarks,
            'status'              => $r->status,
            'requested_check_in'  => $r->requested_check_in,
            'requested_check_out' => $r->requested_check_out,
            'approval_remarks'    => $r->approval_remarks,
        ];
    }

    /** Raise a Change Request — same two flows (day/week) and validation as WebMyAttendanceController::requestChange(). */
    public function requestChange(Request $request, AttendanceDayResolver $resolver): JsonResponse
    {
        $this->authorize('attendance_self_view');

        $employee = $request->user()->employee;
        abort_unless($employee, 404, 'No employee profile linked to this account.');

        $validated = $request->validate([
            'request_type'        => 'required|in:day,week',
            'attendance_date'     => 'required|date',
            'remarks'             => 'required|string|max:500',
            'request_check_in'    => 'nullable|boolean',
            'request_check_out'   => 'nullable|boolean',
            'requested_check_in'  => 'nullable|date_format:H:i|required_if:request_check_in,1',
            'requested_check_out' => 'nullable|date_format:H:i|required_if:request_check_out,1',
        ]);

        $date = Carbon::parse($validated['attendance_date']);
        $requestedCheckIn  = null;
        $requestedCheckOut = null;

        if ($validated['request_type'] === 'day') {
            $dayPunches = $resolver->punchesForEmployeeRange(
                $employee->id, $validated['attendance_date'], $validated['attendance_date']
            )->get($validated['attendance_date'], collect());
            $checkIn  = $dayPunches->filter(fn($p) => $p->check_in  !== null)->min('check_in');
            $checkOut = $dayPunches->filter(fn($p) => $p->check_out !== null)->max('check_out');

            if (!$checkIn || !$checkOut) {
                return $this->validationError('That day has no check-in/check-out recorded — nothing to raise a change request against.');
            }

            $inTs  = strtotime($validated['attendance_date'] . ' ' . $checkIn);
            $outTs = strtotime($validated['attendance_date'] . ' ' . $checkOut);
            $hours = $outTs > $inTs ? ($outTs - $inTs) / 3600 : null;
            if ($hours === null || $hours >= 9) {
                return $this->validationError('This day already has 9 or more hours recorded — nothing to raise a change request against.');
            }

            $requestCheckIn  = $request->boolean('request_check_in');
            $requestCheckOut = $request->boolean('request_check_out');
            if (!$requestCheckIn && !$requestCheckOut) {
                return $this->validationError('Select Check In, Check Out, or both to raise a request against.');
            }

            if ($requestCheckIn) {
                $requestedCheckIn = $validated['requested_check_in'] . ':00';
            }
            if ($requestCheckOut) {
                $requestedCheckOut = $validated['requested_check_out'] . ':00';
            }

            $reason    = 'daily_hours_shortfall';
            $storeDate = $validated['attendance_date'];
        } else {
            $weekStart = $date->copy()->startOfWeek(Carbon::MONDAY);
            $weekEnd   = $date->copy()->endOfWeek(Carbon::SUNDAY);

            $service = app(AttendanceWeeklyAverageService::class);
            $result  = $service->forEmployeeDateRange($employee->id, $weekStart, $weekEnd);
            if (!$service->isShortfall($result)) {
                return $this->validationError('That week is not currently flagged for a shortfall.');
            }

            $reason    = 'weekly_hours_shortfall';
            $storeDate = $weekStart->toDateString();
        }

        $alreadyRaised = AttendanceRegularization::where('employee_id', $employee->id)
            ->where('attendance_date', $storeDate)
            ->where('reason', $reason)
            ->exists();
        if ($alreadyRaised) {
            return $this->validationError("A change request has already been raised for this {$validated['request_type']}.");
        }

        $regularization = AttendanceRegularization::create([
            'employee_id'          => $employee->id,
            'attendance_date'      => $storeDate,
            'reason'               => $reason,
            'remarks'              => $validated['remarks'],
            'status'               => 'pending',
            'requested_check_in'   => $requestedCheckIn,
            'requested_check_out'  => $requestedCheckOut,
        ]);

        return response()->json(['data' => $this->formatRequest($regularization)], 201);
    }

    private function validationError(string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'errors' => ['request' => [$message]]], 422);
    }

    /** Self-only monthly Attendance Analytics — mirrors analytics()'s plain-employee branch. */
    public function analytics(Request $request, AttendanceWeeklyAverageService $service): JsonResponse
    {
        $this->authorize('attendance_analytics_view');

        $employee = $request->user()->employee;
        abort_unless($employee, 404, 'No employee profile linked to this account.');

        $monthStart = $this->resolveMonth($request->get('month'));
        $monthEnd   = $monthStart->copy()->endOfMonth();

        $monthly = $service->forEmployeeDateRange($employee->id, $monthStart, $monthEnd);

        $mainLeaveTypes = ['CL', 'SL', 'CO'];
        $leaveCounts    = ['CL' => 0, 'SL' => 0, 'CO' => 0];
        $otherBreakdown = [];
        $empLeaves = LeaveTrackerRecord::where('employee_id', $employee->id)
            ->where('is_active', true)->where('is_approved', true)
            ->whereBetween('leave_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->get(['leave_date', 'leave_type']);

        foreach ($empLeaves->groupBy(fn($r) => strtoupper($r->leave_type)) as $type => $recs) {
            $count = $recs->pluck('leave_date')->unique()->count();
            if (in_array($type, $mainLeaveTypes, true)) {
                $leaveCounts[$type] = $count;
            } else {
                $otherBreakdown[$type] = $count;
            }
        }

        return response()->json([
            'data' => [
                'month'               => $monthStart->format('Y-m'),
                'monthly_avg_hours'   => $monthly['average_hours'] ?? null,
                'monthly_day_count'   => $monthly['day_count'] ?? 0,
                'is_shortfall'        => $monthly && $service->isShortfall($monthly),
                'leave_cl'            => $leaveCounts['CL'],
                'leave_sl'            => $leaveCounts['SL'],
                'leave_co'            => $leaveCounts['CO'],
                'leave_other_breakdown' => $otherBreakdown,
                'leave_days_total'    => array_sum($leaveCounts) + array_sum($otherBreakdown),
            ],
        ]);
    }
}
