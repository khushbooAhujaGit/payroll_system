<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRegularization;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveTrackerRecord;
use App\Models\User;
use App\Notifications\AttendanceChangeRequestResolved;
use App\Notifications\AttendanceChangeRequestSubmitted;
use App\Services\AttendanceDayResolver;
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
    public function index(Request $request, AttendanceWeeklyAverageService $service, AttendanceDayResolver $resolver)
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
        $punchesByDate = $resolver->punchesForEmployeeRange(
            $employee->id, $monthStart->toDateString(), $monthEnd->toDateString()
        );

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

        // ── Weekly averages + shortfall flags for every week touching this
        // month — this is its own, separate "Add Remark" trigger (reason =
        // weekly_hours_shortfall), independent of the per-day one below.
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

            $result = $service->forEmployeeDateRange($employee->id, $weekStart, $weekEnd);

            $weeks[] = [
                'week_start'       => $weekStartStr,
                'week_end'         => $weekEnd->toDateString(),
                'label'            => $weekStart->format('d M') . ' - ' . $weekEnd->format('d M'),
                'average_hours'    => $result['average_hours'] ?? null,
                'day_count'        => $result['day_count'] ?? 0,
                'is_shortfall'     => $service->isShortfall($result),
                'existing_request' => $existingWeekRequests->get($weekStartStr),
            ];
        }

        // ── Per-day existing requests (reason = daily_hours_shortfall,
        // distinct from the weekly ones above) — the day button's own
        // trigger is simply "this day's total is under 9h", independent of
        // whether its week average also fell short. ──
        $existingDayRequests = AttendanceRegularization::where('employee_id', $employee->id)
            ->where('reason', 'daily_hours_shortfall')
            ->whereBetween('attendance_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->get()
            ->keyBy(fn($r) => $r->attendance_date->format('Y-m-d'));

        // Day-level eligibility: only a day whose total is actually under 9h
        // — a late check-in with a correspondingly later check-out that
        // still clears 9h total has nothing wrong to report. (This is also
        // strictly the same thing as "check-in after 10:00 or check-out
        // before 19:00": across a clocked 10:00-19:00 window that's exactly
        // 9h, so falling under 9h necessarily means one side missed its
        // mark — checking hours directly is just the simpler, correct form
        // of that same rule.) Once eligible, the employee freely picks
        // Check In, Check Out, or both to correct. A day missing EITHER
        // punch isn't eligible at all — no check-in/out pair at all reads
        // as a leave day, not a time to correct, matching requestChange()'s
        // own "both punches required" guard below.
        foreach ($days as &$day) {
            $day['can_request_day'] = $day['hours'] !== null && $day['hours'] < 9;
            $day['existing_request'] = $existingDayRequests->get($day['date']);
        }
        unset($day);

        // ── This employee's past Change Requests, so they can see status ──
        $myRequests = AttendanceRegularization::where('employee_id', $employee->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('attendance.my-attendance', compact('days', 'weeks', 'month', 'monthStart', 'myRequests'));
    }

    /**
     * Submit a Change Request remark — two independent flows sharing this
     * one endpoint, distinguished by request_type and stored with a
     * different `reason` on the same attendance_regularizations table:
     *   - 'day':  the employee is contesting that day's Check In and/or
     *     Check Out time (picked independently), with the corrected time(s)
     *     they're requesting stored in requested_check_in/requested_check_out.
     *   - 'week': that week's average is under 9h (the original flow).
     * Both re-check their condition server-side rather than trusting the form.
     */
    public function requestChange(Request $request, AttendanceDayResolver $resolver)
    {
        $this->authorize('attendance_self_view');

        $employee = auth()->user()->employee;
        abort_unless($employee, 404, 'No employee profile linked to your account.');

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
            // Only a day with both a check-in and check-out actually has
            // times to contest — an absent/blank day isn't a time-correction
            // case at all. Resolved (override-aware) state, not a raw
            // AttendanceRawPunch query — an HR override already in place
            // for this date must be what's being compared against.
            $dayPunches = $resolver->punchesForEmployeeRange(
                $employee->id, $validated['attendance_date'], $validated['attendance_date']
            )->get($validated['attendance_date'], collect());
            $checkIn  = $dayPunches->filter(fn($p) => $p->check_in  !== null)->min('check_in');
            $checkOut = $dayPunches->filter(fn($p) => $p->check_out !== null)->max('check_out');
            abort_unless($checkIn && $checkOut, 422, 'That day has no check-in/check-out recorded — nothing to raise a change request against.');

            // Day-level eligibility re-check, matching index()'s own rule —
            // only a day whose total actually falls under 9h has anything
            // to raise (a late check-in a later check-out still clears
            // doesn't need explaining). Once eligible, either side can be
            // freely selected — not restricted to just whichever side
            // tripped the threshold.
            $inTs  = strtotime($validated['attendance_date'] . ' ' . $checkIn);
            $outTs = strtotime($validated['attendance_date'] . ' ' . $checkOut);
            $hours = $outTs > $inTs ? ($outTs - $inTs) / 3600 : null;
            abort_unless($hours !== null && $hours < 9, 422, 'This day already has 9 or more hours recorded — nothing to raise a change request against.');

            $requestCheckIn  = $request->boolean('request_check_in');
            $requestCheckOut = $request->boolean('request_check_out');
            abort_unless($requestCheckIn || $requestCheckOut, 422, 'Select Check In, Check Out, or both to raise a request against.');

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
            abort_unless($service->isShortfall($result), 422, 'That week is not currently flagged for a shortfall.');

            $reason    = 'weekly_hours_shortfall';
            $storeDate = $weekStart->toDateString();
        }

        $alreadyRaised = AttendanceRegularization::where('employee_id', $employee->id)
            ->where('attendance_date', $storeDate)
            ->where('reason', $reason)
            ->exists();
        abort_if($alreadyRaised, 422, "A change request has already been raised for this {$validated['request_type']}.");

        $regularization = AttendanceRegularization::create([
            'employee_id'          => $employee->id,
            'attendance_date'      => $storeDate,
            'reason'               => $reason,
            'remarks'              => $validated['remarks'],
            'status'               => 'pending',
            'requested_check_in'   => $requestedCheckIn,
            'requested_check_out'  => $requestedCheckOut,
        ]);

        $this->hrUsersForCompany($employee->company_id)
            ->each(fn(User $hr) => $hr->notify(new AttendanceChangeRequestSubmitted($regularization)));

        return redirect()->route('my-attendance', ['month' => $date->format('Y-m')])
            ->with('success', 'Change request submitted for HR review.');
    }

    /**
     * HR/Admin's queue of pending Change Requests — "HR will approve for
     * all", so this is unscoped by manager/team within a company; HR sees
     * every pending request for the selected company (or every company,
     * via the "All Companies" option — $companyId is either an int or the
     * literal string 'all'). A company selector is needed here (same
     * pattern as the main Attendance page) because a super-admin login has
     * no employee record of its own to infer a company from — without it,
     * resolveCompanyId() silently falls back to "first company with
     * employees," which can differ from the company whose employee
     * actually raised the request, making it look like nothing was
     * submitted.
     *
     * HR/Admin only — a plain employee or reporting manager never reaches
     * this page (no nav link, gated by attendance_edit here too so direct
     * URL access is blocked the same way). They see their own Change
     * Request history via the separate "My Change Requests" table on My
     * Attendance instead (see index() above).
     */
    public function pendingRequests(Request $request, AttendanceDayResolver $resolver)
    {
        $this->authorize('attendance_edit');

        $search = trim((string) $request->input('search', ''));
        $month  = trim((string) $request->input('month', ''));

        $companies = Company::orderBy('name')->get(['id', 'name']);
        $companyId = $this->resolveSwitchableCompanyId($request);

        $query = AttendanceRegularization::with('employee:id,employee_id,first_name,middle_name,last_name,company_id');

        if ($companyId !== 'all') {
            $query->whereHas('employee', fn($q) => $q->where('company_id', $companyId));
        }

        if ($search !== '') {
            $query->whereHas('employee', fn($q) => $q->where(fn($q2) => $q2
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('employee_id', 'like', "%{$search}%")
            ));
        }

        if ($month !== '') {
            try {
                $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
                $query->whereBetween('attendance_date', [$monthStart->toDateString(), $monthStart->copy()->endOfMonth()->toDateString()]);
            } catch (\Exception) {
                $month = '';
            }
        }

        $requests = $query->orderByDesc('created_at')->paginate(25)->withQueryString();

        $actualPunches = $this->actualPunchesFor($requests, $resolver);

        return view('attendance.change-requests', compact('requests', 'companies', 'companyId', 'actualPunches', 'search', 'month'));
    }

    /**
     * For every Day-type request in the given collection, resolve that
     * day's actual Check In/Check Out (override-aware — see
     * AttendanceDayResolver, not a raw attendance_raw_punches read) so HR
     * sees the real current state alongside the employee's requested
     * replacement. Keyed by "{employee_id}_{Y-m-d}" — Week-type requests
     * (attendance_date = the week's Monday, not a single punch day) simply
     * have no entry.
     */
    private function actualPunchesFor($requests, AttendanceDayResolver $resolver): \Illuminate\Support\Collection
    {
        // A paginator's own toArray() returns pagination metadata, not just
        // the items — collect() on it directly would iterate that metadata
        // instead of the actual request rows, so unwrap it first.
        $items = $requests instanceof \Illuminate\Contracts\Pagination\Paginator
            ? $requests->items()
            : $requests;

        $dayRequests = collect($items)->filter(fn($r) => $r->reason === 'daily_hours_shortfall');
        if ($dayRequests->isEmpty()) {
            return collect();
        }

        $empIds = $dayRequests->pluck('employee_id')->unique()->values()->all();
        $dates  = $dayRequests->map(fn($r) => $r->attendance_date->format('Y-m-d'))->unique();

        return $resolver->punchesForRange($empIds, $dates->min(), $dates->max())
            ->map(fn($group) => [
                'check_in'  => $group->filter(fn($p) => $p->check_in  !== null)->min('check_in'),
                'check_out' => $group->filter(fn($p) => $p->check_out !== null)->max('check_out'),
            ]);
    }

    /**
     * Excel export of the same scoped list pendingRequests() shows on
     * screen (HR/Admin only, selected company or All Companies) — no
     * pagination, every matching row.
     */
    public function exportChangeRequests(Request $request, AttendanceDayResolver $resolver)
    {
        $this->authorize('attendance_edit');

        $search = trim((string) $request->input('search', ''));
        $month  = trim((string) $request->input('month', ''));

        $companyId = $this->resolveSwitchableCompanyId($request);

        $query = AttendanceRegularization::with('employee:id,employee_id,first_name,middle_name,last_name,company_id');

        if ($companyId !== 'all') {
            $query->whereHas('employee', fn($q) => $q->where('company_id', $companyId));
        }

        if ($search !== '') {
            $query->whereHas('employee', fn($q) => $q->where(fn($q2) => $q2
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('employee_id', 'like', "%{$search}%")
            ));
        }

        if ($month !== '') {
            try {
                $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
                $query->whereBetween('attendance_date', [$monthStart->toDateString(), $monthStart->copy()->endOfMonth()->toDateString()]);
            } catch (\Exception) {
                // ignore invalid month, export unfiltered by date
            }
        }

        $requests = $query->orderByDesc('attendance_date')->get();
        $actualPunches = $this->actualPunchesFor($requests, $resolver);

        // "Check In"/"Check Out"/"HR Remark" here are deliberately the same
        // header names ManualAttendanceImporter's own template uses (see
        // Attendance page → Manual Upload, which requires HR Remark on every
        // row) — unknown extra headers (Old Check In/Out, Type, Request
        // Change Text) are simply ignored by that importer's header-name
        // mapping, so they're safe to carry along for HR's reference.
        // "Check In"/"Check Out" are pre-filled with the employee's
        // REQUESTED time (falling back to the actual time on whichever side
        // wasn't contested) so this sheet can be reviewed and re-uploaded
        // as-is; "Old Check In"/"Old Check Out" keep the actual recorded
        // time alongside for comparison before HR decides.
        $rows = $requests->map(function ($req) use ($actualPunches) {
            $isDay  = $req->reason === 'daily_hours_shortfall';
            $actual = $isDay ? $actualPunches->get($req->employee_id . '_' . $req->attendance_date->format('Y-m-d')) : null;

            $oldCheckIn  = $isDay ? substr($actual['check_in']  ?? '', 0, 5) : '';
            $oldCheckOut = $isDay ? substr($actual['check_out'] ?? '', 0, 5) : '';
            $newCheckIn  = $isDay && $req->requested_check_in  ? substr($req->requested_check_in,  0, 5) : $oldCheckIn;
            $newCheckOut = $isDay && $req->requested_check_out ? substr($req->requested_check_out, 0, 5) : $oldCheckOut;

            return [
                $req->employee?->employee_id,
                $req->employee?->full_name,
                $req->attendance_date,
                $oldCheckIn,
                $oldCheckOut,
                $newCheckIn,
                $newCheckOut,
                '',
                $isDay ? 'Day' : 'Week',
                $req->remarks,
            ];
        });

        return $this->streamXlsx(
            'attendance_change_requests',
            ['EMP ID', 'Name', 'Date', 'Old Check In', 'Old Check Out', 'Check In', 'Check Out', 'HR Remark', 'Type', 'Request Change Text'],
            $rows
        );
    }

    /**
     * Approve/reject only updates the Change Request itself — it never
     * touches the underlying Attendance/punch data, since this is an
     * acknowledged remark about a weekly shortfall, not a punch-time
     * correction (unlike the pre-existing single-day regularization flow).
     * HR/Admin only, matching pendingRequests() — a reporting manager no
     * longer has a page this is reachable from, so this no longer accepts
     * a manager-of-the-requester path either.
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

        $user      = auth()->user();
        $viewerEmployee = $user->employee;
        $isLeaveAdmin   = $user->hasRole('Admin') || $user->hasRole('HR');
        $subordinateIds = $viewerEmployee ? $viewerEmployee->subordinates()->pluck('id') : collect();
        $isManager      = $subordinateIds->isNotEmpty();

        // Only Admin/HR get the company selector (Manager/Employee views
        // are scoped by their own employee record instead), so only they
        // need the 'all'-aware resolver — everyone else gets a plain int
        // that's never actually used in a company_id where() below.
        $companyId = $isLeaveAdmin ? $this->resolveSwitchableCompanyId($request) : $this->resolveCompanyId($request);

        if ($isLeaveAdmin) {
            $employees = $companyId === 'all'
                ? Employee::currentlyEmployed()->orderBy('first_name')->get()
                : Employee::currentlyEmployed()->where('company_id', $companyId)->orderBy('first_name')->get();
        } elseif ($isManager) {
            $ids = $subordinateIds->push($viewerEmployee->id);
            $employees = Employee::currentlyEmployed()->whereIn('id', $ids)->orderBy('first_name')->get();
        } elseif ($viewerEmployee) {
            $employees = Employee::currentlyEmployed()->where('id', $viewerEmployee->id)->get();
        } else {
            $employees = collect();
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $term = mb_strtolower($search);
            $employees = $employees->filter(fn ($e) => str_contains(mb_strtolower($e->full_name), $term)
                || str_contains(mb_strtolower($e->employee_id ?? ''), $term))->values();
        }

        $month = $request->input('month') ?? now()->format('Y-m');
        try {
            $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Exception) {
            $monthStart = now()->startOfMonth();
            $month = $monthStart->format('Y-m');
        }
        $monthEnd = $monthStart->copy()->endOfMonth();

        // Leave days this month per employee, broken out by type (CL/SL/CO
        // get their own columns, matching the cl/sl/co breakdown already
        // used elsewhere in Leave Management; every other type taken —
        // WFH, EL, HCL, etc. — is listed by its own code + count rather than
        // collapsed into one blended "Other" number, so the table shows
        // exactly WHAT kind of leave explains a gap in attendance.
        $mainLeaveTypes = ['CL', 'SL', 'CO'];
        $leaveRecordsByEmployee = LeaveTrackerRecord::whereIn('employee_id', $employees->pluck('id'))
            ->where('is_active', true)
            ->where('is_approved', true)
            ->whereBetween('leave_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->get(['employee_id', 'leave_date', 'leave_type'])
            ->groupBy('employee_id');

        $rows = $employees->map(function ($employee) use ($service, $monthStart, $monthEnd, $leaveRecordsByEmployee, $mainLeaveTypes) {
            $monthly = $service->forEmployeeDateRange($employee->id, $monthStart, $monthEnd);

            $leaveCounts    = ['CL' => 0, 'SL' => 0, 'CO' => 0];
            $otherBreakdown = [];
            $empLeaves      = $leaveRecordsByEmployee->get($employee->id, collect());
            foreach ($empLeaves->groupBy(fn($r) => strtoupper($r->leave_type)) as $type => $recs) {
                $count = $recs->pluck('leave_date')->unique()->count();
                if (in_array($type, $mainLeaveTypes, true)) {
                    $leaveCounts[$type] = $count;
                } else {
                    $otherBreakdown[$type] = $count;
                }
            }

            return [
                'employee'            => $employee,
                'monthly_avg_hours'   => $monthly['average_hours'] ?? null,
                'monthly_day_count'   => $monthly['day_count'] ?? 0,
                'is_shortfall'        => $monthly && $service->isShortfall($monthly),
                'leave_cl'            => $leaveCounts['CL'],
                'leave_sl'            => $leaveCounts['SL'],
                'leave_co'            => $leaveCounts['CO'],
                'leave_other_breakdown' => $otherBreakdown,
                'leave_days_total'    => array_sum($leaveCounts) + array_sum($otherBreakdown),
            ];
        })->values();

        // Same super-admin-has-no-company problem as pendingRequests() —
        // only Admin/HR need the selector, since Manager/Employee views are
        // already scoped by their own employee record, not $companyId.
        $companies = $isLeaveAdmin ? Company::orderBy('name')->get(['id', 'name']) : collect();

        return view('attendance.analytics', compact('rows', 'month', 'monthStart', 'isLeaveAdmin', 'isManager', 'companies', 'companyId', 'search'));
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

    /**
     * resolveCompanyId() always prefers the viewer's OWN linked employee's
     * company over an explicit company_id param — correct for every page
     * that merely needs "my company" with no picker, but it means the
     * Entity/Company dropdown on pages like this one (which exists
     * specifically so an HR user can switch companies) silently does
     * nothing whenever that HR account also happens to have its own
     * employee record. An explicitly-submitted company_id — i.e. the
     * dropdown actually being used — should win outright here. Returns the
     * literal string 'all' when the "All Companies" option was picked —
     * callers must check for that before using the value in a where()
     * clause.
     */
    private function resolveSwitchableCompanyId(Request $request): int|string
    {
        if ($request->filled('company_id')) {
            $value = $request->get('company_id');
            return $value === 'all' ? 'all' : (int) $value;
        }

        return $this->resolveCompanyId($request);
    }
}
