<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRawPunch;
use App\Models\AttendanceRegularization;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\AttendanceChangeRequestResolved;
use App\Notifications\AttendanceChangeRequestSubmitted;
use App\Services\AttendanceWeeklyAverageService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Items #21-23 of the Master Details spec:
 *  - #21: weekly-average-under-9h employees get an email (see
 *    CheckWeeklyAverageAttendance + AttendanceWeeklyAverageService).
 *  - #22: employees see their own attendance first, and can raise a Change
 *    Request (a remark) against a flagged week, which HR approves/rejects.
 *    Reuses the existing AttendanceRegularization model, which already had
 *    the right columns but no web UI at all before this.
 *  - #23: role-scoped attendance analytics (Admin=all, Manager=team,
 *    Employee=self) showing average weekly/monthly attendance.
 */
class WebMyAttendanceController extends Controller
{
    /**
     * The employee's own daily attendance for a month, with any week that
     * failed the 9h weekly-average check flagged for a Change Request.
     */
    public function index(Request $request, AttendanceWeeklyAverageService $service)
    {
        $this->authorize('attendance_self_view');

        $employee = auth()->user()->employee;
        abort_unless($employee, 404, 'No employee profile linked to your account.');

        $month = $request->input('month') ?? now()->format('Y-m');
        try {
            $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Exception) {
            $monthStart = now()->startOfMonth();
            $month = $monthStart->format('Y-m');
        }
        $monthEnd = $monthStart->copy()->endOfMonth();

        // ── Daily punch detail ──────────────────────────────────────────
        $punchesByDate = AttendanceRawPunch::where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->get()
            ->groupBy(fn($p) => $p->attendance_date->format('Y-m-d'));

        $days = [];
        for ($cursor = $monthStart->copy(); $cursor <= $monthEnd; $cursor->addDay()) {
            $dateStr     = $cursor->toDateString();
            $dayPunches  = $punchesByDate->get($dateStr, collect());
            $checkIn     = $dayPunches->filter(fn($p) => $p->check_in  !== null)->min('check_in');
            $checkOut    = $dayPunches->filter(fn($p) => $p->check_out !== null)->max('check_out');

            $hours = null;
            if ($checkIn && $checkOut) {
                $inTs  = strtotime($dateStr . ' ' . $checkIn);
                $outTs = strtotime($dateStr . ' ' . $checkOut);
                if ($outTs > $inTs) {
                    $hours = round(($outTs - $inTs) / 3600, 2);
                }
            }

            $days[] = [
                'date'      => $dateStr,
                'day'       => $cursor->day,
                'check_in'  => $checkIn,
                'check_out' => $checkOut,
                'hours'     => $hours,
            ];
        }

        // ── Weekly averages + shortfall flags for every week touching this month ──
        $weekStarts = [];
        for ($c = $monthStart->copy()->startOfWeek(Carbon::MONDAY); $c <= $monthEnd; $c->addWeek()) {
            $weekStarts[] = $c->toDateString();
        }
        $existingRequests = AttendanceRegularization::where('employee_id', $employee->id)
            ->where('reason', 'weekly_hours_shortfall')
            ->whereIn('attendance_date', $weekStarts)
            ->get()
            ->keyBy(fn($r) => $r->attendance_date->format('Y-m-d'));

        $weeks = [];
        $weekCursor = $monthStart->copy()->startOfWeek(Carbon::MONDAY);
        while ($weekCursor <= $monthEnd) {
            $weekStart = $weekCursor->copy();
            $weekEnd   = $weekCursor->copy()->endOfWeek(Carbon::SUNDAY);

            $result = $service->forEmployeeDateRange($employee->id, $weekStart, $weekEnd);

            $weeks[] = [
                'week_start'        => $weekStart->toDateString(),
                'week_end'          => $weekEnd->toDateString(),
                'label'             => $weekStart->format('d M') . ' - ' . $weekEnd->format('d M'),
                'average_hours'     => $result['average_hours'] ?? null,
                'day_count'         => $result['day_count'] ?? 0,
                'is_shortfall'      => $service->isShortfall($result),
                'existing_request'  => $existingRequests->get($weekStart->toDateString()),
            ];

            $weekCursor->addWeek();
        }

        // ── This employee's past Change Requests, so they can see status ──
        $myRequests = AttendanceRegularization::where('employee_id', $employee->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('attendance.my-attendance', compact('days', 'weeks', 'month', 'monthStart', 'myRequests'));
    }

    /**
     * Submit a Change Request (remark only, per spec) for a flagged week.
     */
    public function requestChange(Request $request)
    {
        $this->authorize('attendance_self_view');

        $employee = auth()->user()->employee;
        abort_unless($employee, 404, 'No employee profile linked to your account.');

        $validated = $request->validate([
            'week_start' => 'required|date',
            'remarks'    => 'required|string|max:500',
        ]);

        $alreadyRaised = AttendanceRegularization::where('employee_id', $employee->id)
            ->where('attendance_date', $validated['week_start'])
            ->where('reason', 'weekly_hours_shortfall')
            ->exists();
        abort_if($alreadyRaised, 422, 'A change request has already been raised for this week.');

        $regularization = AttendanceRegularization::create([
            'employee_id'      => $employee->id,
            'attendance_date'  => $validated['week_start'],
            'reason'           => 'weekly_hours_shortfall',
            'remarks'          => $validated['remarks'],
            'status'           => 'pending',
        ]);

        $this->hrUsersForCompany($employee->company_id)
            ->each(fn(User $hr) => $hr->notify(new AttendanceChangeRequestSubmitted($regularization)));

        return redirect()->route('my-attendance', ['month' => Carbon::parse($validated['week_start'])->format('Y-m')])
            ->with('success', 'Change request submitted for HR review.');
    }

    /**
     * HR's queue of pending Change Requests — "HR will approve for all",
     * so this is unscoped by manager/team within a company; HR sees every
     * pending request for the selected company. A company selector is
     * needed here (same pattern as the main Attendance page) because a
     * super-admin login has no employee record of its own to infer a
     * company from — without it, resolveCompanyId() silently falls back to
     * "first company with employees," which can differ from the company
     * whose employee actually raised the request, making it look like
     * nothing was submitted.
     */
    public function pendingRequests(Request $request)
    {
        $this->authorize('attendance_edit');

        $companies = Company::orderBy('name')->get(['id', 'name']);
        $companyId = $this->resolveCompanyId($request);

        $requests = AttendanceRegularization::with('employee:id,employee_id,first_name,middle_name,last_name,company_id')
            ->whereHas('employee', fn($q) => $q->where('company_id', $companyId))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('attendance.change-requests', compact('requests', 'companies', 'companyId'));
    }

    /**
     * Approve/reject only updates the Change Request itself — it never
     * touches the underlying Attendance/punch data, since this is an
     * acknowledged remark about a weekly shortfall, not a punch-time
     * correction (unlike the pre-existing single-day regularization flow).
     */
    public function resolveRequest(Request $request, AttendanceRegularization $regularization)
    {
        $this->authorize('attendance_edit');

        $validated = $request->validate([
            'action'           => 'required|in:approved,rejected',
            'approval_remarks' => 'nullable|string|max:500',
        ]);

        $regularization->update([
            'status'           => $validated['action'],
            'approved_by'      => auth()->id(),
            'approved_at'      => now(),
            'approval_remarks' => $validated['approval_remarks'] ?? null,
        ]);

        $regularization->employee?->user?->notify(new AttendanceChangeRequestResolved($regularization));

        return redirect()->route('attendance.change_requests')
            ->with('success', 'Change request ' . $validated['action'] . '.');
    }

    /**
     * Role-scoped attendance analytics: Admin/HR see the whole company,
     * a manager sees themselves + direct reports, a plain employee sees
     * only themselves.
     */
    public function analytics(Request $request, AttendanceWeeklyAverageService $service)
    {
        $this->authorize('attendance_analytics_view');

        $companyId = $this->resolveCompanyId($request);
        $user      = auth()->user();
        $viewerEmployee = $user->employee;
        $isLeaveAdmin   = $user->hasRole('Admin') || $user->hasRole('HR');
        $subordinateIds = $viewerEmployee ? $viewerEmployee->subordinates()->pluck('id') : collect();
        $isManager      = $subordinateIds->isNotEmpty();

        if ($isLeaveAdmin) {
            $employees = Employee::currentlyEmployed()->where('company_id', $companyId)->orderBy('first_name')->get();
        } elseif ($isManager) {
            $ids = $subordinateIds->push($viewerEmployee->id);
            $employees = Employee::currentlyEmployed()->whereIn('id', $ids)->orderBy('first_name')->get();
        } elseif ($viewerEmployee) {
            $employees = Employee::currentlyEmployed()->where('id', $viewerEmployee->id)->get();
        } else {
            $employees = collect();
        }

        $month = $request->input('month') ?? now()->format('Y-m');
        try {
            $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Exception) {
            $monthStart = now()->startOfMonth();
            $month = $monthStart->format('Y-m');
        }
        $monthEnd = $monthStart->copy()->endOfMonth();

        $rows = $employees->map(function ($employee) use ($service, $monthStart, $monthEnd) {
            $monthly = $service->forEmployeeDateRange($employee->id, $monthStart, $monthEnd);

            // Average of each week's average within the month.
            $weeklyAverages = [];
            $weekCursor = $monthStart->copy()->startOfWeek(Carbon::MONDAY);
            while ($weekCursor <= $monthEnd) {
                $weekResult = $service->forEmployeeDateRange(
                    $employee->id,
                    $weekCursor->copy(),
                    $weekCursor->copy()->endOfWeek(Carbon::SUNDAY)
                );
                if ($weekResult) {
                    $weeklyAverages[] = $weekResult['average_hours'];
                }
                $weekCursor->addWeek();
            }

            return [
                'employee'             => $employee,
                'monthly_avg_hours'    => $monthly['average_hours'] ?? null,
                'monthly_day_count'    => $monthly['day_count'] ?? 0,
                'weekly_avg_hours'     => count($weeklyAverages) ? round(array_sum($weeklyAverages) / count($weeklyAverages), 2) : null,
                'is_shortfall'         => $monthly && $service->isShortfall($monthly),
            ];
        })->values();

        // Same super-admin-has-no-company problem as pendingRequests() —
        // only Admin/HR need the selector, since Manager/Employee views are
        // already scoped by their own employee record, not $companyId.
        $companies = $isLeaveAdmin ? Company::orderBy('name')->get(['id', 'name']) : collect();

        return view('attendance.analytics', compact('rows', 'month', 'monthStart', 'isLeaveAdmin', 'isManager', 'companies', 'companyId'));
    }

    /**
     * Users who can act on Change Requests for this company — anyone holding
     * attendance_edit, scoped the same way hrEmailsForCompany() is in
     * WebLeaveController (null company_id = global HR).
     */
    protected function hrUsersForCompany(?int $companyId)
    {
        return User::permission('attendance_edit')
            ->where(fn($q) => $q->whereNull('company_id')->orWhere('company_id', $companyId))
            ->get();
    }
}
