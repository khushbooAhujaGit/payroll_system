<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\ImportsExcel;
use App\Http\Controllers\Controller;
use App\Imports\LeaveTrackerImporter;
use App\Models\Employee;
use App\Models\Entity;
use App\Models\LeaveApplication;
use App\Models\LeavePolicy;
use App\Models\LeaveTrackerRecord;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\ExcelImportService;
use App\Services\LeaveApplicationTrackerSyncService;
use App\Services\LeaveBalanceSyncService;
use App\Services\OutlookMailerService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebLeaveController extends Controller
{
    use ImportsExcel;

    public function __construct(
        protected ExcelImportService $excelImportService,
        protected LeaveApplicationTrackerSyncService $trackerSync,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Leave Management  (applications + tracker records)
    // ─────────────────────────────────────────────────────────────────────────
 
    public function index(Request $request)
    {
        $this->authorize('leave_management_view');

        $companyId = $this->resolveCompanyId($request);
        $scope     = $this->leaveViewerScope();
        ['isLeaveAdmin' => $isLeaveAdmin, 'isManager' => $isManager, 'visibleIds' => $visibleIds, 'viewerEmployee' => $viewerEmployee] = $scope;

        $applications     = null;
        $selfApplications = null;
        $teamApplications = null;
        // Which of the Self/Team tabs is showing — only relevant for the
        // manager branch below, but always computed so the view can read it.
        $activeTab = in_array($request->input('applications_tab'), ['self', 'team']) ? $request->input('applications_tab') : 'self';

        // Date-range filter — applies to every viewer type.
        $dateFrom = $request->date_from;
        $dateTo   = $request->date_to;

        if ($isManager && $viewerEmployee && !$isLeaveAdmin) {
            // Manager (not HR/Admin): two separate buckets, each independently
            // searchable/paginated — their own applications vs. their team's.
            // Each bucket is a single combined list: formal applications plus
            // any tracker-only rows (bulk Excel imports / manual tracker
            // entries) — application-mirrored tracker rows are excluded since
            // the application itself is already the row shown for those.
            $teamIds = $visibleIds->reject(fn($id) => $id === $viewerEmployee->id)->values();

            $selfApplications = $this->combinedApplicationsAndTracker(
                collect([$viewerEmployee->id]), $request->status, $request->self_search, 'self_page', 10, dateFrom: $dateFrom, dateTo: $dateTo
            );
            $teamApplications = $this->combinedApplicationsAndTracker(
                $teamIds, $request->status, $request->team_search, 'team_page', 10, dateFrom: $dateFrom, dateTo: $dateTo
            );
        } elseif (!$isLeaveAdmin && $viewerEmployee) {
            // Plain employee (not a manager, not HR/Admin): same combined
            // treatment, just the one card — 10 per page, same as the
            // Self/Team buckets above.
            $applications = $this->combinedApplicationsAndTracker(
                $visibleIds, $request->status, $request->search, 'page', 10, dateFrom: $dateFrom, dateTo: $dateTo
            );
        } else {
            // HR/Admin: unified, entity-wide. Combined like the buckets
            // above, but scoped to leave-request rows only (source_file =
            // 'leave_application') — not every bulk-import/manual tracker
            // row company-wide, which would bury real pending requests in
            // hundreds of historical rows. HR/Admin already have the
            // dedicated, unrestricted /leave/tracker page for browsing all
            // of that.
            $applications = $this->combinedApplicationsAndTracker(
                null, $request->status, $request->search, 'page', 25, applicationsOnly: true, dateFrom: $dateFrom, dateTo: $dateTo
            );
        }

        // Apply-Leave modal only offers this curated set of types, not every
        // code in leave_types (which also holds internal/system markers like
        // "Approval Pending", "Punch Missing", etc.)
        $applyLeaveTypeNames = [
            'Casual Leave', 'Half-Day Casual Leave', 'Sick Leave', 'Half-Day Sick Leave',
            'Exam Leave', 'Birthday Leave', 'Anniversary Leave', 'Work From Home', 'Compensation Off',
            'Maternity Leave', 'Paternity Leave',
            'Early Leave', 'Late Arrival', 'Punch Miss Request',
        ];
        $leaveTypes = LeaveType::whereIn('name', $applyLeaveTypeNames)
            ->where(fn($q) => $q->whereNull('company_id')->orWhere('company_id', $companyId))
            ->where('is_active', true)->orderBy('name')->get();

        // Employee dropdown for "Apply Leave" / tracker employee filter —
        // scoped the same way: Employee sees only self, Manager sees self +
        // direct reports, HR/Admin sees everyone.
        $employees = $isLeaveAdmin
            ? Employee::currentlyEmployed()->orderBy('first_name')->get()
            : Employee::currentlyEmployed()->whereIn('id', $visibleIds)->orderBy('first_name')->get();

        $viewerEmployeeId = $scope['viewerEmployee']?->id;
        $minApplyDate     = now()->subDays(30)->toDateString();

        // "My Leave Balances" — the viewer's own CL/SL standing, computed the
        // same way WebSalaryReportController does (allocation minus FY-to-date
        // usage, SL overflow spilling into CL). The leave_balances table
        // (opening/accrued/availed rows) is not actually populated by
        // anything in this app — accrued is always 0 there — so it never
        // reflects a real number; this mirrors the one place that does.
        $myLeaveBalances = $scope['viewerEmployee']
            ? collect($this->currentCLSLEntitlement($scope['viewerEmployee']))
                ->map(fn($available, $name) => (object) ['leaveType' => (object) ['name' => $name], 'available' => $available])
                ->values()
            : collect();

        return view('leave.index', compact(
            'applications', 'selfApplications', 'teamApplications',
            'activeTab', 'leaveTypes', 'employees', 'companyId',
            'isLeaveAdmin', 'isManager', 'viewerEmployeeId', 'minApplyDate',
            'myLeaveBalances'
        ) + ['isAdmin' => $scope['isAdmin']]);
    }

    /**
     * Monthly leave calendar, scoped the same way as the rest of this
     * controller's leaveViewerScope(): plain employees see only their own
     * leave, managers see themselves + direct reports (with names), and
     * Admin/HR see everyone company-wide (with names). Name visibility is
     * gated the same way as scope — only Admin/HR/managers see who
     * specifically is out; a plain employee's own name isn't shown either,
     * just their own leave-type breakdown, since it's their own data anyway.
     */
    public function calendar(Request $request)
    {
        $this->authorize('leave_management_view');

        $companyId = $this->resolveCompanyId($request);
        $scope     = $this->leaveViewerScope();
        $canSeeNames = $scope['isLeaveAdmin'] || $scope['isManager'];
        $visibleIds  = $scope['visibleIds']; // null = everyone in the company

        $month = $request->input('month') ?? now()->format('Y-m');
        try {
            $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Exception) {
            $monthStart = now()->startOfMonth();
            $month = $monthStart->format('Y-m');
        }
        $monthEnd = $monthStart->copy()->endOfMonth();

        $records = LeaveTrackerRecord::with('employee:id,first_name,middle_name,last_name,employee_id,company_id')
            ->whereHas('employee', fn($q) => $q->where('company_id', $companyId))
            ->when($visibleIds !== null, fn($q) => $q->whereIn('employee_id', $visibleIds))
            ->where('is_active', true)
            ->where('is_approved', true)
            ->whereBetween('leave_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->get();

        $typeMeta = LeaveType::all(['code', 'name', 'color'])->keyBy('code');

        $recordsByDate = $records->groupBy(fn($r) => $r->leave_date->toDateString());

        $days = [];
        // Leading blanks so the grid's first real day lands under the right weekday (Sun=0).
        for ($i = 0; $i < $monthStart->dayOfWeek; $i++) {
            $days[] = null;
        }

        for ($cursor = $monthStart->copy(); $cursor <= $monthEnd; $cursor->addDay()) {
            $dateStr    = $cursor->toDateString();
            $dayRecords = $recordsByDate->get($dateStr, collect());

            $byType = $dayRecords->groupBy('leave_type')->map(function ($group, $code) use ($typeMeta) {
                $meta = $typeMeta->get($code);
                return [
                    'code'  => $code,
                    'label' => $meta?->name ?? $code,
                    'color' => $meta?->color ?? '#94a3b8',
                    'count' => $group->count(),
                ];
            })->values();

            $names = $dayRecords->map(function ($r) use ($typeMeta) {
                $meta = $typeMeta->get($r->leave_type);
                return [
                    'name'  => $r->employee?->full_name ?: $r->employee_name,
                    'label' => $meta?->name ?? $r->leave_type,
                    'color' => $meta?->color ?? '#94a3b8',
                ];
            })->values();

            $days[] = [
                'date'       => $dateStr,
                'day'        => $cursor->day,
                'count'      => $dayRecords->count(),
                'by_type'    => $byType,
                'names'      => $names,
                'is_today'   => $cursor->isToday(),
                'is_weekend' => $cursor->isWeekend(),
            ];
        }

        $legend = LeaveType::whereNotNull('color')
            ->whereIn('code', $records->pluck('leave_type')->unique())
            ->get(['code', 'name', 'color'])
            ->sortBy('name')
            ->values();

        return view('leave.calendar', compact('days', 'month', 'monthStart', 'canSeeNames', 'legend'));
    }

    /**
     * Current Casual/Sick Leave standing for one employee — full annual
     * allocation (from LeavePolicy, defaulting to 18/7 same as the salary
     * report) minus leave taken since the start of the financial year
     * (April 1), with SL overflow past its allocation spilling into CL
     * usage first. This is the same rule WebSalaryReportController uses to
     * compute "Closing CL"/"Closing SL" for payroll — duplicated here
     * (rather than extracted into a shared service) since the salary report
     * version is scoped to a specific payroll month/company batch and this
     * needs today's FY-to-date figure for a single employee instead.
     */
    protected function currentCLSLEntitlement(Employee $employee): array
    {
        $today  = now();
        $fyYear = $today->month >= 4 ? $today->year : $today->year - 1;
        $fyStart = Carbon::createFromDate($fyYear, 4, 1)->startOfDay();
        $fyEnd   = Carbon::createFromDate($fyYear + 1, 3, 31)->endOfDay();

        $clLtId = LeaveType::where('code', 'CL')->value('id');
        $slLtId = LeaveType::where('code', 'SL')->value('id');
        $clAllocation = (float) (LeavePolicy::where('leave_type_id', $clLtId)->where('is_active', true)->value('days_per_year') ?? 18);
        $slAllocation = (float) (LeavePolicy::where('leave_type_id', $slLtId)->where('is_active', true)->value('days_per_year') ?? 7);

        $ltByCategory = LeaveType::where('is_active', true)->get(['code', 'category'])->groupBy('category');
        $clCodes = $ltByCategory->get('casual', collect())->pluck('code')->toArray();
        $slCodes = $ltByCategory->get('sick', collect())->pluck('code')->toArray();

        $records = LeaveTrackerRecord::where('employee_id', $employee->id)
            ->whereIn('leave_type', array_merge($clCodes, $slCodes))
            ->whereBetween('leave_date', [$fyStart, $fyEnd])
            ->where('is_active', true)
            ->get(['leave_type']);

        $clAvailed = $slAvailed = 0.0;
        foreach ($records as $r) {
            $units = str_starts_with($r->leave_type, 'H') ? 0.5 : 1.0;
            if (in_array($r->leave_type, $clCodes)) {
                $clAvailed += $units;
            } elseif (in_array($r->leave_type, $slCodes)) {
                $slAvailed += $units;
            }
        }

        $slExcess        = max(0.0, $slAvailed - $slAllocation);
        $effectiveCLUsed = $clAvailed + $slExcess;

        return [
            'Casual Leave' => round(max(0.0, $clAllocation - $effectiveCLUsed), 1),
            'Sick Leave'   => round(max(0.0, $slAllocation - $slAvailed), 1),
        ];
    }

    /**
     * Base, scoped query for leave applications — shared by the unified
     * (HR/Admin/plain-employee) card and the manager's self/team buckets.
     */
    protected function applicationsQuery($employeeIds, ?string $status, ?string $dateFrom = null, ?string $dateTo = null)
    {
        return LeaveApplication::with([
            'employee:id,employee_id,first_name,middle_name,last_name,department_id,reporting_manager_id',
            'employee.department:id,name',
            'leaveType:id,name,code',
        ])
        ->whereHas('employee', fn($q) => $q)
        ->when($employeeIds !== null, fn($q) => $q->whereIn('employee_id', $employeeIds))
        ->when($status, fn($q) => $q->where('status', $status))
        // Overlap: the application's range touches the requested date window.
        ->when($dateFrom, fn($q) => $q->where('to_date', '>=', $dateFrom))
        ->when($dateTo, fn($q) => $q->where('from_date', '<=', $dateTo));
    }

    /**
     * Search by application #, leave type, or employee name/code.
     */
    protected function applySearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('application_number', 'like', "%{$term}%")
              ->orWhereHas('leaveType', fn($lq) => $lq->where('name', 'like', "%{$term}%"))
              ->orWhereHas('employee', fn($eq) => $eq
                  ->where('first_name', 'like', "%{$term}%")
                  ->orWhere('last_name', 'like', "%{$term}%")
                  ->orWhere('employee_id', 'like', "%{$term}%"));
        });
    }

    /**
     * Scoped query for leave_tracker_records rows to show in the combined
     * list — bulk Excel imports, manual tracker entries, and (since apply()
     * writes here directly) new leave requests. Deliberately excludes only
     * legacy application-mirrored rows (leave_ref_id LIKE 'APP-%'): those
     * still-pending pre-migration applications are already represented via
     * their real leave_applications row in combinedApplicationsAndTracker()
     * below, so including their mirror too would show them twice.
     */
    protected function trackerRecordsQuery($employeeIds, ?string $search, ?string $dateFrom = null, ?string $dateTo = null)
    {
        return LeaveTrackerRecord::with('employee:id,employee_id,first_name,middle_name,last_name,reporting_manager_id')
            ->where('is_active', true)
            ->where('leave_ref_id', 'not like', 'APP-%')
            ->when($employeeIds !== null, fn($q) => $q->whereIn('employee_id', $employeeIds))
            ->when($dateFrom, fn($q) => $q->where('leave_date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('leave_date', '<=', $dateTo))
            ->when($search, fn($q) => $q->where(function ($sq) use ($search) {
                $sq->where('leave_type', 'like', "%{$search}%")
                   ->orWhere('reason', 'like', "%{$search}%")
                   ->orWhere('employee_name', 'like', "%{$search}%")
                   ->orWhereHas('employee', fn($eq) => $eq
                       ->where('first_name', 'like', "%{$search}%")
                       ->orWhere('last_name', 'like', "%{$search}%")
                       ->orWhere('employee_id', 'like', "%{$search}%"));
            }));
    }

    /**
     * One combined, paginated list: legacy LeaveApplication rows plus
     * leave_tracker_records rows (see trackerRecordsQuery()), normalized to
     * a common shape and sorted by date. Manually paginated (like
     * trackerRecords()'s spell view) since it merges two different Eloquent
     * models rather than one query.
     *
     * $applicationsOnly restricts the tracker side to source_file =
     * 'leave_application' rows only (real leave requests, not bulk imports/
     * manual entries) — used for the HR/Admin entity-wide card, so it isn't
     * buried in every historical tracker row company-wide.
     */
    protected function combinedApplicationsAndTracker($employeeIds, ?string $status, ?string $search, string $pageName, int $perPage = 10, bool $applicationsOnly = false, ?string $dateFrom = null, ?string $dateTo = null)
    {
        $applications = $this->applicationsQuery($employeeIds, $status, $dateFrom, $dateTo)
            ->when($search, fn($q) => $this->applySearch($q, $search))
            ->get();

        // Only "pending"/"approved" are real tracker statuses (is_approved);
        // a rejected application's mirror is deactivated (see
        // LeaveApplicationTrackerSyncService), so there's never a tracker-only
        // "rejected" row to match against.
        $trackerRecords = $status === 'rejected'
            ? collect()
            : $this->trackerRecordsQuery($employeeIds, $search, $dateFrom, $dateTo)
                ->when($applicationsOnly, fn($q) => $q->where('source_file', 'leave_application'))
                ->get()
                ->filter(fn($rec) => !$status || ($rec->is_approved ? 'approved' : 'pending') === $status);

        $rows = $applications->map(fn($app) => $this->normalizeApplicationRow($app))
            ->concat($trackerRecords->map(fn($rec) => $this->normalizeTrackerRow($rec)));

        $sorted = $rows->sortByDesc(fn($r) => Carbon::parse($r->sort_date))->values();

        $page  = max(1, (int) request()->get($pageName, 1));
        $items = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items, $sorted->count(), $perPage, $page,
            ['path' => request()->url(), 'pageName' => $pageName, 'query' => request()->query()]
        );
    }

    /**
     * Normalize a legacy LeaveApplication into the common row shape shared
     * by combinedApplicationsAndTracker() and the HR/Admin unified card.
     */
    protected function normalizeApplicationRow(LeaveApplication $app): object
    {
        return (object) [
            'kind'          => 'application',
            'id'            => $app->id,
            'employee'      => $app->employee,
            'leave_type'    => $app->leaveType?->name ?? '—',
            'from_date'     => $app->from_date,
            'to_date'       => $app->to_date,
            'days'          => $app->days,
            'reason'        => $app->reason,
            'status'        => $app->status,
            'sort_date'     => $app->applied_at ?? $app->from_date,
            'application'   => $app,
            'trackerRecord' => null,
        ];
    }

    /**
     * Normalize a LeaveTrackerRecord — either a genuine tracker-only row
     * (bulk import / manual entry) or, now that apply() writes here
     * directly, a tracker-native leave request day — into the same common
     * row shape. Only rows where source_file = 'leave_application' and
     * still pending get approve/reject actions in the view; that's decided
     * there, this just exposes the raw record for URL building.
     */
    protected function normalizeTrackerRow(LeaveTrackerRecord $rec): object
    {
        return (object) [
            'kind'          => 'tracker',
            'id'            => $rec->id,
            'employee'      => $rec->employee,
            'leave_type'    => $rec->leave_type . ($rec->half_day_period ? ' (' . ($rec->half_day_period === 'first_half' ? 'First Half' : 'Second Half') . ')' : ''),
            'from_date'     => $rec->leave_date,
            'to_date'       => $rec->leave_date,
            'days'          => 1,
            'reason'        => $rec->reason,
            'status'        => $rec->is_approved ? 'approved' : 'pending',
            'sort_date'     => $rec->leave_date,
            'application'   => null,
            'trackerRecord' => $rec,
        ];
    }

    /**
     * Who can this viewer see/act on for leave purposes?
     * - Admin: everyone, sees all, can approve/reject anyone (full bypass).
     * - HR: everyone (visibleIds = null means "no restriction" for VIEWING),
     *   but can only approve/reject employees who actually report to them —
     *   see canApproveLeave(). HR is not automatically a "manager".
     * - Manager (has direct reports, not Admin/HR): self + direct reports.
     * - Plain employee: self only.
     * - No linked employee record: nobody.
     */
    protected function leaveViewerScope(): array
    {
        $user           = auth()->user();
        $viewerEmployee = $user->employee;
        $isAdmin        = $user->hasRole('Admin');
        $isHR           = $user->hasRole('HR');
        $isLeaveAdmin   = $isAdmin || $isHR;
        $subordinateIds = $viewerEmployee ? $viewerEmployee->subordinates()->pluck('id') : collect();
        $isManager      = $subordinateIds->isNotEmpty();

        if ($isLeaveAdmin) {
            $visibleIds = null;
        } elseif ($viewerEmployee) {
            $visibleIds = $subordinateIds->push($viewerEmployee->id);
        } else {
            $visibleIds = collect();
        }

        return [
            'isAdmin'        => $isAdmin,
            'isHR'           => $isHR,
            'isLeaveAdmin'   => $isLeaveAdmin,
            'isManager'      => $isManager,
            'viewerEmployee' => $viewerEmployee,
            'visibleIds'     => $visibleIds,
        ];
    }

    /**
     * Only Admin (unconditional) or the applicant's direct reporting manager
     * (which may be a plain manager or an HR user who happens to be someone's
     * reporting manager) may approve/reject a leave application. HR alone —
     * without being the reporting manager — can see every application but
     * cannot act on ones outside their own reports.
     */
    protected function canApproveLeave(LeaveApplication $application): bool
    {
        ['isAdmin' => $isAdmin, 'viewerEmployee' => $viewerEmployee] = $this->leaveViewerScope();

        if ($isAdmin) {
            return true;
        }

        return $viewerEmployee && $application->employee
            && $application->employee->reporting_manager_id === $viewerEmployee->id;
    }

    /**
     * Same rule as canApproveLeave(), for a tracker-native leave request
     * (the leave_applications table is no longer written to — new requests
     * from the modal live entirely in leave_tracker_records).
     */
    protected function canApproveTrackerRecord(LeaveTrackerRecord $record): bool
    {
        ['isAdmin' => $isAdmin, 'viewerEmployee' => $viewerEmployee] = $this->leaveViewerScope();

        if ($isAdmin) {
            return true;
        }

        return $viewerEmployee && $record->employee
            && $record->employee->reporting_manager_id === $viewerEmployee->id;
    }

    public function apply(Request $request)
    {
        $this->authorize('leave_management_add');

        // No leave can be applied for a date more than 30 days in the past.
        $minApplyDate = now()->subDays(30)->toDateString();

        $validated = $request->validate([
            'duration_type' => 'required|in:single,period',
            'single_date'   => "nullable|required_if:duration_type,single|date|after_or_equal:{$minApplyDate}",
            'from_date'     => "nullable|required_if:duration_type,period|date|after_or_equal:{$minApplyDate}",
            'to_date'       => 'nullable|required_if:duration_type,period|date|after_or_equal:from_date',
            'leave_type_id' => 'required|exists:leave_types,id',
            'reason'        => 'nullable|string|max:500',
            'employee_id'   => 'nullable|exists:employees,id',
            'half_day_period' => 'nullable|in:first_half,second_half',
        ], [
            'single_date.after_or_equal' => 'You cannot apply for leave more than 30 days in the past.',
            'from_date.after_or_equal'   => 'You cannot apply for leave more than 30 days in the past.',
        ]);

        $fromDate = $validated['duration_type'] === 'single' ? $validated['single_date'] : $validated['from_date'];
        $toDate   = $validated['duration_type'] === 'single' ? $validated['single_date'] : $validated['to_date'];

        $employee = $validated['employee_id']
            ? Employee::findOrFail($validated['employee_id'])
            : auth()->user()->employee;

        if (!$employee) {
            return redirect()->back()->withErrors(['employee_id' => 'No employee profile found.'])->withInput();
        }

        ['isLeaveAdmin' => $isLeaveAdmin, 'visibleIds' => $visibleIds] = $this->leaveViewerScope();
        if (!$isLeaveAdmin && !$visibleIds->contains($employee->id)) {
            return redirect()->back()->withErrors([
                'employee_id' => 'You can only apply leave for yourself or employees who report to you.',
            ])->withInput();
        }

        // Block a second application whose dates overlap an existing
        // pending/approved one for the same employee — this is what was
        // producing duplicate rows for the same date when the modal was
        // submitted more than once.
        $overlapping = LeaveTrackerRecord::where('employee_id', $employee->id)
            ->where('is_active', true)
            ->whereBetween('leave_date', [$fromDate, $toDate])
            ->exists();

        if ($overlapping) {
            return redirect()->back()->withErrors([
                'duplicate' => 'A pending or approved leave record already exists for ' . $employee->full_name . ' covering these dates.',
            ])->withInput();
        }

        // Leave applications no longer create a leave_applications row —
        // they go straight into leave_tracker_records, the same table bulk
        // Excel imports and manual tracker entries use, one row per day.
        // Unlike those, a fresh application starts unapproved
        // (is_approved = false) until a manager/HR/Admin acts on it.
        $leaveType   = LeaveType::find($validated['leave_type_id']);
        $requestRef  = 'REQ-' . now()->format('YmdHis') . strtoupper(\Illuminate\Support\Str::random(4));
        $period      = CarbonPeriod::create($fromDate, $toDate);

        // Half-day leave types ("Half-Day Casual Leave" etc.) need to record
        // which half of the day was taken.
        $isHalfDayType = $leaveType && str_contains($leaveType->name, 'Half-Day');
        if ($isHalfDayType && empty($validated['half_day_period'])) {
            return redirect()->back()->withErrors([
                'half_day_period' => 'Please select which half of the day this leave covers.',
            ])->withInput();
        }

        DB::transaction(function () use ($period, $requestRef, $employee, $leaveType, $validated, $isHalfDayType) {
            foreach ($period as $day) {
                LeaveTrackerRecord::create([
                    'leave_ref_id'     => $requestRef . '-' . $day->format('Ymd'),
                    'employee_id'      => $employee->id,
                    'sheet_emp_id'     => $employee->employee_id,
                    'employee_name'    => $employee->full_name,
                    'leave_date'       => $day->format('Y-m-d'),
                    'leave_type'       => strtoupper($leaveType?->code ?? $leaveType?->name ?? 'LEAVE'),
                    'half_day_period'  => $isHalfDayType ? $validated['half_day_period'] : null,
                    'reason_type'      => 'Leave Application',
                    'reason'           => $validated['reason'] ?? null,
                    'source_file'      => 'leave_application',
                    'uploaded_by'      => auth()->id(),
                    'is_active'        => true,
                    'is_approved'      => false,
                ]);
            }
        });

        $this->notifyLeaveApplied($employee, $leaveType, $fromDate, $toDate, $validated['reason'] ?? null);

        return redirect()->route('leave')->with('success', 'Leave application submitted.');
    }

    /**
     * Emails HR (to) with the applicant's reporting manager (cc) whenever a
     * leave request is submitted via the Apply Leave modal, so both see it
     * land in their own inbox rather than only inside the app.
     */
    protected function notifyLeaveApplied(Employee $employee, LeaveType $leaveType, string $fromDate, string $toDate, ?string $reason): void
    {
        // HR is always cc, never the primary recipient — the reporting
        // manager is the main recipient. If the employee has no manager on
        // record, there's nobody else to be "the" recipient, so HR takes
        // that slot instead (with nothing left to cc).
        $managerEmail = $this->resolveEmployeeEmail($employee->reportingManager);
        $hrEmails     = $this->hrEmailsForCompany($employee->company_id);

        $to = $managerEmail ? [$managerEmail] : $hrEmails;
        $cc = $managerEmail ? $hrEmails : [];

        if (empty($to)) {
            return;
        }

        $subject = "Leave Application - {$employee->full_name} ({$fromDate}" . ($fromDate !== $toDate ? " to {$toDate}" : '') . ')';
        $body = view('emails.leave-applied', compact('employee', 'leaveType', 'fromDate', 'toDate', 'reason'))->render();

        try {
            app(OutlookMailerService::class)->sendMail($to, $subject, $body, $cc);
        } catch (\Throwable $e) {
            Log::error('Failed to send leave application email via Outlook: ' . $e->getMessage());
        }
    }

    /**
     * Best available contact address for an employee — prefers their
     * official email, falling back to personal email, then their linked
     * login account's email.
     */
    protected function resolveEmployeeEmail(?Employee $employee): ?string
    {
        if (!$employee) {
            return null;
        }

        return $employee->official_email ?: $employee->personal_email ?: $employee->user?->email;
    }

    /**
     * All HR-role users who can see this company's leave requests — includes
     * HR accounts with a null company_id (treated as global, same convention
     * used for LeaveType/LeavePolicy elsewhere in this controller).
     */
    protected function hrEmailsForCompany(?int $companyId): array
    {
        return User::role('HR')
            ->where(fn($q) => $q->whereNull('company_id')->orWhere('company_id', $companyId))
            ->pluck('email')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Emails the employee (to) that their leave was approved/rejected, with
     * HR cc'd — fired from resolveLeaveApplication()/resolveTrackerRecord(),
     * so it covers the single-approve, single-reject, and bulk-approve paths
     * alike (all three funnel through those two methods).
     */
    protected function notifyLeaveResolved(?Employee $employee, string $leaveTypeLabel, string $fromDate, string $toDate, string $action): void
    {
        if (!$employee) {
            return;
        }

        $employeeEmail = $this->resolveEmployeeEmail($employee);
        if (!$employeeEmail) {
            return;
        }

        $hrEmails = $this->hrEmailsForCompany($employee->company_id);

        $subject = 'Leave ' . ucfirst($action) . " - {$leaveTypeLabel} ({$fromDate}" . ($fromDate !== $toDate ? " to {$toDate}" : '') . ')';
        $body = view('emails.leave-resolved', compact('employee', 'leaveTypeLabel', 'fromDate', 'toDate', 'action'))->render();

        try {
            app(OutlookMailerService::class)->sendMail($employeeEmail, $subject, $body, $hrEmails);
        } catch (\Throwable $e) {
            Log::error('Failed to send leave resolution email via Outlook: ' . $e->getMessage());
        }

    }

    /**
     * Legacy path — approves/rejects a pending leave_applications row from
     * before leave requests moved to leave_tracker_records. New requests
     * never create one of these; this stays only so any already-pending
     * applications from before the change remain actionable.
     */

    public function approve(Request $request, LeaveApplication $application)
    {
        $this->authorize('leave_management_edit');

        abort_unless($this->canApproveLeave($application), 403, "Only the employee's manager, HR (if their reporting manager), or Admin can approve this leave.");

        $request->validate(['action' => 'required|in:approved,rejected']);

        $this->resolveLeaveApplication($application, $request->action);

        return redirect()->route('leave', array_filter(['applications_tab' => $request->input('applications_tab')]))
            ->with('success', 'Leave ' . $request->action . '.');
    }


    /**
     * Approve/reject a single tracker-native leave request (one day — a
     * multi-day request is several rows, approved together via the bulk
     * action below or one at a time).
     */


    public function approveTrackerRecord(Request $request, LeaveTrackerRecord $record)
    {
        $this->authorize('leave_management_edit');

        abort_unless($this->canApproveTrackerRecord($record), 403, "Only the employee's manager, HR (if their reporting manager), or Admin can approve this leave.");

        $request->validate(['action' => 'required|in:approved,rejected']);

        $this->resolveTrackerRecord($record, $request->action);

        return redirect()->route('leave', array_filter(['applications_tab' => $request->input('applications_tab')]))
            ->with('success', 'Leave ' . $request->action . '.');
    }

    /**
     * Approve/reject several rows at once (checkbox + "select all" bulk
     * action) — legacy leave_applications rows (application_ids[]) and
     * tracker-native leave_tracker_records rows (tracker_ids[]) can both be
     * present in the same submission, since the combined list mixes them.
     * Rows the current viewer isn't allowed to act on, or that are no
     * longer pending, are silently skipped rather than failing the batch.
     */
    
    public function bulkApprove(Request $request)
    {
        $this->authorize('leave_management_edit');

        $validated = $request->validate([
            'action'           => 'required|in:approved,rejected',
            'application_ids'   => 'nullable|array',
            'application_ids.*' => 'integer|exists:leave_applications,id',
            'tracker_ids'       => 'nullable|array',
            'tracker_ids.*'     => 'integer|exists:leave_tracker_records,id',
        ]);

        if (empty($validated['application_ids']) && empty($validated['tracker_ids'])) {
            return redirect()->route('leave', array_filter(['applications_tab' => $request->input('applications_tab')]))
                ->with('warning', 'No rows selected.');
        }

        $processed = 0;
        $skipped   = 0;

        $applications = LeaveApplication::with('employee')
            ->whereIn('id', $validated['application_ids'] ?? [])
            ->where('status', 'pending')
            ->get();
        foreach ($applications as $application) {
            if ($this->canApproveLeave($application)) {
                $this->resolveLeaveApplication($application, $validated['action']);
                $processed++;
            } else {
                $skipped++;
            }
        }

        $trackerRecords = LeaveTrackerRecord::with('employee')
            ->whereIn('id', $validated['tracker_ids'] ?? [])
            ->where('is_active', true)
            ->where('is_approved', false)
            ->get();
        foreach ($trackerRecords as $record) {
            if ($this->canApproveTrackerRecord($record)) {
                $this->resolveTrackerRecord($record, $validated['action']);
                $processed++;
            } else {
                $skipped++;
            }
        }

        $message = "{$processed} row(s) " . $validated['action'] . '.';
        if ($skipped > 0) {
            $message .= " {$skipped} skipped (not your direct report).";
        }

        return redirect()->route('leave', array_filter(['applications_tab' => $request->input('applications_tab')]))
            ->with($processed > 0 ? 'success' : 'warning', $message);
    }

    /**
     * Shared approve/reject side effects for a single legacy application —
     * marks attendance for the period on approval, and keeps the
     * leave_tracker_records mirror (created at apply() time, back when
     * applications still wrote there) in sync: flips it to approved, or
     * deactivates it (soft-hides it, same convention as a superseded tracker
     * edit) on rejection.
     */
    protected function resolveLeaveApplication(LeaveApplication $application, string $action): void
    {
        $application->update(['status' => $action]);

        if ($action === 'approved') {
            $period = CarbonPeriod::create($application->from_date, $application->to_date);
            foreach ($period as $day) {
                \App\Models\Attendance::updateOrCreate(
                    ['employee_id' => $application->employee_id, 'attendance_date' => $day->format('Y-m-d')],
                    ['status' => 'leave', 'source' => 'leave_system']
                );
            }
        }

        $this->trackerSync->sync($application, isApproved: $action === 'approved', isActive: $action === 'approved', uploadedBy: auth()->id());

        $this->notifyLeaveResolved(
            $application->employee,
            $application->leaveType?->name ?? '—',
            $application->from_date->format('Y-m-d'),
            $application->to_date->format('Y-m-d'),
            $action
        );
    }

    /**
     * Approve/reject side effects for a single tracker-native day: flips
     * is_approved (and marks attendance) on approval, or deactivates the row
     * on rejection — same convention as resolveLeaveApplication(), just
     * operating directly on the row instead of through a mirror.
     */
    protected function resolveTrackerRecord(LeaveTrackerRecord $record, string $action): void
    {
        $record->update([
            'is_approved' => $action === 'approved',
            'is_active'   => $action === 'approved',
        ]);

        // Early Leave / Late Arrival / Punch Miss Request are attendance
        // *exceptions* on a day the employee actually worked — not an
        // absence — so approving them must not overwrite that day's
        // attendance as "leave" the way a real leave type does.
        $isAttendanceException = in_array(strtoupper($record->leave_type), ['EYL', 'LTA', 'PMR'], true);

        if ($action === 'approved' && !$isAttendanceException) {
            \App\Models\Attendance::updateOrCreate(
                ['employee_id' => $record->employee_id, 'attendance_date' => Carbon::parse($record->leave_date)->format('Y-m-d')],
                ['status' => 'leave', 'source' => 'leave_system']
            );
        }

        $this->notifyLeaveResolved(
            $record->employee,
            $record->leave_type,
            $record->leave_date->format('Y-m-d'),
            $record->leave_date->format('Y-m-d'),
            $action
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Leave Tracker Import  (Mark Leave sheet from xlsm)
    // ─────────────────────────────────────────────────────────────────────────

    public function trackerImport(Request $request)
    {
        $this->authorize('leave_management_add');
        abort_unless($this->leaveViewerScope()['isLeaveAdmin'], 403, 'Only HR or Admin can import the leave tracker.');

        $companyId = $this->resolveCompanyId($request);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,xlsm|max:20480',
        ]);

        $importMonth = $request->get('import_month'); // YYYY-MM — for tagging/logging
        $result = $this->excelImportService->import(
            $request->file('file'),
            'leave_tracker',
            LeaveTrackerImporter::class,
            [
                'company_id' => $companyId,
                'sheet_name'   => 'Mark Leave',
                'uploaded_by'  => auth()->id(),
                'source_file'  => $request->file('file')->getClientOriginalName(),
                'import_month' => $importMonth,
            ]
        );

        $redirect = $this->handleImportResult($result, 'leave', 'leave record');

        // Add confirmation that leave balances were auto-synced
        if ($result['success'] ?? false) {
            $redirect = $redirect->with('info', '✓ Leave balances automatically synced for all imported employees.');
        }

        return $redirect;
    }

    /**
     * Manually sync leave_balances for all employees
     * Useful when automatic sync didn't work or for batch updates
     */
    public function syncLeaveBalances(Request $request)
    {
        $this->authorize('leave_management_edit');

        try {
            // Get all employees with leave records
            $empIds = LeaveTrackerRecord::whereNotNull('employee_id')
                ->distinct()
                ->pluck('employee_id')
                ->all();

            if (empty($empIds)) {
                return redirect()->route('leave.tracker')->with('warning', 'No leave records found to sync.');
            }

            // Run the sync
            $synced = (new LeaveBalanceSyncService())->sync($empIds);

            return redirect()->route('leave.tracker')->with('success', "✓ Leave balances synced successfully! Updated {$synced} leave balance entries for " . count($empIds) . " employees.");
        } catch (\Exception $e) {
            return redirect()->route('leave.tracker')->with('error', 'Sync failed: ' . $e->getMessage());
        }
    }

    public function exportApplications(Request $request)
    {
        $this->authorize('leave_management_view');

        $companyId  = $this->resolveCompanyId($request);
        $visibleIds = $this->leaveViewerScope()['visibleIds'];

        $applications = LeaveApplication::with(['employee:id,first_name,middle_name,last_name,employee_id', 'leaveType:id,name,code'])
            ->whereHas('employee', fn($q) => $q)
            ->when($visibleIds !== null, fn($q) => $q->whereIn('employee_id', $visibleIds))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderByDesc('applied_at')
            ->get();

        $headers = [
            'Application #', 'Employee ID', 'Employee Name', 'Leave Type',
            'From Date', 'To Date', 'Days', 'Half Day', 'Status', 'Reason', 'Applied At',
        ];

        $rows = $applications->map(fn($a) => [
            $a->application_number,
            $a->employee?->employee_id,
            $a->employee?->full_name,
            $a->leaveType?->code ?? $a->leaveType?->name,
            $a->from_date,              // Carbon → Excel date
            $a->to_date,                // Carbon → Excel date
            (float) $a->days,
            $a->is_half_day ? 'Yes' : 'No',
            $a->status,
            $a->reason,
            $a->applied_at,             // Carbon → Excel date
        ]);

        return $this->streamXlsx('leave_applications', $headers, $rows);
    }


    public function exportTracker(Request $request)
    {
        $this->authorize('leave_management_view');

        ['isLeaveAdmin' => $isLeaveAdmin, 'visibleIds' => $visibleIds] = $this->leaveViewerScope();
        $entityEmployeeIds = $isLeaveAdmin ? Employee::pluck('id') : $visibleIds;

        $records = LeaveTrackerRecord::with('employee:id,first_name,middle_name,last_name,employee_id')
            ->where(function ($sub) use ($entityEmployeeIds, $isLeaveAdmin) {
                $sub->whereIn('employee_id', $entityEmployeeIds);
                if ($isLeaveAdmin) {
                    $sub->orWhereNull('employee_id');
                }
            })
            ->when($request->emp_id, fn($q) => $q->where(fn($sq) =>
                $sq->where('sheet_emp_id', 'like', "%{$request->emp_id}%")
                   ->orWhereHas('employee', fn($eq) =>
                       $eq->where('first_name', 'like', "%{$request->emp_id}%")
                          ->orWhere('last_name', 'like', "%{$request->emp_id}%")
                   )
            ))
            ->when($request->leave_type, fn($q) => $q->whereRaw('UPPER(leave_type) = ?', [strtoupper($request->leave_type)]))
            ->when($request->month, fn($q) => $q->whereRaw("DATE_FORMAT(leave_date, '%Y-%m') = ?", [$request->month]))
            ->orderByDesc('leave_date')
            ->get();

        $headers = [
            'Leave Ref ID', 'EMP Code', 'Employee Name', 'Leave Date',
            'Leave Type', 'Team Leader', 'Reason Type', 'Reason',
        ];

        $rows = $records->map(fn($r) => [
            $r->leave_ref_id,
            $r->sheet_emp_id,
            $r->employee ? ($r->employee->full_name) : $r->employee_name,
            $r->leave_date,             // Carbon → Excel date
            $r->leave_type,
            $r->team_leader_name,
            $r->reason_type,
            $r->reason,
        ]);

        return $this->streamXlsx('leave_tracker', $headers, $rows);
    }


    public function trackerTemplate()
    {
        $this->authorize('leave_management_add');

        return $this->excelImportService->generateTemplate(LeaveTrackerImporter::class);
    }

    
    // ─────────────────────────────────────────────────────────────────────────
    // Leave Tracker records listing (dedicated page)
    // ─────────────────────────────────────────────────────────────────────────

    public function trackerStore(Request $request)
    {
        $this->authorize('leave_management_add');

        $validated = $request->validate([
            'sheet_emp_id'     => 'required|string|max:50',
            'leave_date'       => 'required|date',
            'leave_type'       => 'required|string|max:20',
            'employee_name'    => 'nullable|string|max:150',
            'team_leader_name' => 'nullable|string|max:150',
            'reason_type'      => 'nullable|string|max:100',
            'reason'           => 'nullable|string|max:500',
        ]);

        $employee = Employee::where('employee_id', $validated['sheet_emp_id'])->first();

        // Check for LOP conflict: same employee, same date already has a record
        $existing = LeaveTrackerRecord::where('sheet_emp_id', $validated['sheet_emp_id'])
            ->whereDate('leave_date', $validated['leave_date'])
            ->first();

        if ($existing && strtoupper($existing->leave_type) !== 'LOP' && strtoupper($validated['leave_type']) === 'LOP') {
            // Replace existing non-LOP with LOP (unapproved leave → loss of pay)
            $existing->update([
                'leave_type'   => 'LOP',
                'reason'       => $validated['reason'] ?? 'Manual LOP — unapproved leave taken',
                'uploaded_by'  => auth()->id(),
            ]);
            return redirect()->route('leave.tracker')->with('success', 'Existing leave replaced with LOP for ' . $validated['leave_date'] . '.');
        }

        // Generate a unique ref ID for manual entries
        $refId = 'MAN-' . now()->format('YmdHis') . '-' . substr(uniqid(), -5);

        LeaveTrackerRecord::create([
            'leave_ref_id'     => $refId,
            'employee_id'      => $employee?->id,
            'sheet_emp_id'     => $validated['sheet_emp_id'],
            'employee_name'    => $validated['employee_name'] ?? ($employee ? $employee->full_name : null),
            'leave_date'       => $validated['leave_date'],
            'leave_type'       => strtoupper($validated['leave_type']),
            'team_leader_name' => $validated['team_leader_name'] ?? null,
            'reason_type'      => $validated['reason_type'] ?? null,
            'reason'           => $validated['reason'] ?? null,
            'source_file'      => 'manual',
            'uploaded_by'      => auth()->id(),
            // Manually-added records are pending approval, unlike bulk
            // Excel imports (LeaveTrackerImporter), which are always
            // treated as already approved.
            'is_approved'      => false,
        ]);

        return redirect()->route('leave.tracker')->with('success', 'Leave record added — pending approval.');
    }

    public function trackerUpdate(Request $request, LeaveTrackerRecord $record)
    {
        $this->authorize('leave_management_edit');

        $validated = $request->validate([
            'leave_date'       => 'required|date',
            'leave_type'       => 'required|string|max:20',
            'is_approved'      => 'nullable|in:0,1',
            'team_leader_name' => 'nullable|string|max:150',
            'reason_type'      => 'nullable|string|max:100',
            'reason'           => 'nullable|string|max:500',
        ]);

        // ── Versioned edit: preserve the original, create a new active record ──
        $newRecord = $record->replicate();
        $newRecord->leave_date       = $validated['leave_date'];
        $newRecord->leave_type       = strtoupper($validated['leave_type']);
        $newRecord->is_approved      = ($validated['is_approved'] ?? '1') === '1';
        $newRecord->team_leader_name = $validated['team_leader_name'] ?? null;
        $newRecord->reason_type      = $validated['reason_type'] ?? null;
        $newRecord->reason           = $validated['reason'] ?? null;
        $newRecord->leave_ref_id     = 'EDIT-' . $record->id . '-' . now()->format('YmdHis');
        $newRecord->is_active        = true;
        $newRecord->superseded_by_id = null;
        $newRecord->uploaded_by      = auth()->id();
        $newRecord->save();

        // Deactivate the original — point it at the new record for the audit trail
        $record->update([
            'is_active'        => false,
            'superseded_by_id' => $newRecord->id,
        ]);

        return redirect()->route('leave.tracker', ['view' => 'daily'])->with('success', 'Leave record updated. Original preserved as audit history.');
    }

    public function trackerDestroy(LeaveTrackerRecord $record)
    {
        $this->authorize('leave_management_delete');

        $record->delete();
        return redirect()->back()->with('success', 'Leave record deleted.');
    }

    /**
     * Bulk-delete all day records belonging to one leave spell
     * (same employee + leave_type + date range).
     */
    public function trackerBulkDestroy(Request $request)
    {
        $this->authorize('leave_management_delete');

        $v = $request->validate([
            'sheet_emp_id' => 'required|string',
            'leave_type'   => 'required|string|max:20',
            'date_from'    => 'required|date',
            'date_to'      => 'required|date|after_or_equal:date_from',
        ]);

        LeaveTrackerRecord::where('sheet_emp_id', $v['sheet_emp_id'])
            ->where('leave_type', $v['leave_type'])
            ->whereBetween('leave_date', [$v['date_from'], $v['date_to']])
            ->delete();

        return redirect()->back()->with('success', 'Leave spell deleted.');
    }

    public function trackerRecords(Request $request)
    {
        $this->authorize('leave_management_view');
        abort_unless($this->leaveViewerScope()['isLeaveAdmin'], 403, 'Only HR or Admin can view the full leave tracker.');

        $view = $request->get('view', 'spells'); // 'spells' | 'daily'

        $leaveTypes   = LeaveTrackerRecord::distinct()->pluck('leave_type')
            ->map(fn($t) => strtoupper(trim($t)))
            ->unique()
            ->sort()
            ->values();
        $totalRecords = LeaveTrackerRecord::count();

        // Shared filter applied to both views — only active (non-superseded) records
        $baseQuery = LeaveTrackerRecord::with('employee:id,employee_id,first_name,middle_name,last_name')
            ->where('is_active', true)
            ->when($request->emp_id, fn($q) => $q->where(function ($sq) use ($request) {
                $sq->where('sheet_emp_id', $request->emp_id)
                   ->orWhere('employee_name', 'like', "%{$request->emp_id}%");
            }))
            ->when($request->leave_type, fn($q) => $q->where('leave_type', $request->leave_type))
            ->when($request->month, fn($q) => $q->whereRaw('DATE_FORMAT(leave_date,"%Y-%m") = ?', [$request->month]))
            ->when($request->date_from, fn($q) => $q->where('leave_date', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->where('leave_date', '<=', $request->date_to));

        if ($view === 'daily') {
            // ── Per-day individual records ──────────────────────────────
            $records = (clone $baseQuery)
                ->orderByDesc('leave_date')
                ->paginate(50)
                ->withQueryString();

            return view('leave.tracker', compact('records', 'leaveTypes', 'totalRecords', 'view'));
        }

        // ── Spell grouping: consecutive days (bridging weekends) = one row ──
        // Fetch sorted by employee + leave_type + date so grouping is O(n)
        $allRecords = (clone $baseQuery)
            ->orderBy('sheet_emp_id')
            ->orderBy('leave_type')
            ->orderBy('leave_date')
            ->get();

        $spellList = $this->detectSpells($allRecords);

        // Sort spells by most recent end_date first
        usort($spellList, fn($a, $b) => $b['end_date']->timestamp <=> $a['end_date']->timestamp);

        // Manual pagination over the spell array
        $page    = max(1, (int) $request->get('page', 1));
        $perPage = 50;
        $spells  = new \Illuminate\Pagination\LengthAwarePaginator(
            array_slice($spellList, ($page - 1) * $perPage, $perPage),
            count($spellList),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('leave.tracker', compact('spells', 'leaveTypes', 'totalRecords', 'view'));
    }

    /**
     * Group a date-sorted collection of tracker records into leave spells.
     * Two records are in the same spell when they share the same employee + leave_type
     * and have no working day gap between them (weekends are bridged automatically).
     */
    private function detectSpells($records): array
    {
        $spells  = [];
        $current = null;

        foreach ($records as $rec) {
            $date = Carbon::parse($rec->leave_date);

            $sameGroup = $current
                && $current['sheet_emp_id'] === $rec->sheet_emp_id
                && $current['leave_type']   === $rec->leave_type
                && $current['is_approved']  === (bool) $rec->is_approved;

            if ($sameGroup && $date->eq($current['end_date'])) {
                // Duplicate date in same group (re-import / multiple records same day)
                // Silently skip — daily view will show the individual records
                continue;
            } elseif ($sameGroup && $this->isConsecutiveWorkday($current['end_date'], $date)) {
                // Extend the current spell
                $current['end_date']  = $date;
                $current['day_count']++;
            } else {
                if ($current) $spells[] = $current;
                $current = [
                    'sheet_emp_id'     => $rec->sheet_emp_id,
                    'employee_name'    => $rec->employee_name,
                    'employee_id'      => $rec->employee_id,
                    'employee'         => $rec->employee,
                    'leave_type'       => $rec->leave_type,
                    'start_date'       => $date,
                    'end_date'         => $date,
                    'day_count'        => 1,
                    'team_leader_name' => $rec->team_leader_name,
                    'reason_type'      => $rec->reason_type,
                    'reason'           => $rec->reason,
                    'is_approved'      => (bool) $rec->is_approved,
                ];
            }
        }

        if ($current) $spells[] = $current;

        return $spells;
    }

    /**
     * Returns true if $curr is the next working day after $prev,
     * allowing a weekend bridge (Fri → Mon, gap of 3 calendar days).
     * Any other gap means the two dates belong to separate spells.
     */
    private function isConsecutiveWorkday(Carbon $prev, Carbon $curr): bool
    {
        $gap = $prev->diffInDays($curr);
        if ($gap === 0 || $gap > 3) return false;
        // All calendar days strictly between prev and curr must be weekend
        for ($i = 1; $i < $gap; $i++) {
            if ($prev->copy()->addDays($i)->isWeekday()) return false;
        }
        return true;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Leave Policies  (leave_policies table — full CRUD)
    // ─────────────────────────────────────────────────────────────────────────

    public function policies(Request $request)
    {
        $this->authorize('leave_policies_view');

        $companyId = $this->resolveCompanyId($request);

        $policies = LeavePolicy::with('leaveType:id,code,name')
            ->where(fn($q) => $q->whereNull('company_id')->orWhere('company_id', $companyId))
            ->orderBy('name')
            ->get();

        $leaveTypes = LeaveType::where('is_active', true)
            ->whereNull('company_id')
            ->orWhere('company_id', $companyId)
            ->orderBy('code')
            ->get();

        $accrualTypes = ['annual' => 'Annual', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly'];
        $prorationsTypes = ['none' => 'None', 'joining_month' => 'Joining Month', 'full_month' => 'Full Month'];

        return view('leave.policies', compact('policies', 'leaveTypes', 'accrualTypes', 'prorationsTypes', 'companyId'));
    }

    public function storePolicy(Request $request)
    {
        $this->authorize('leave_policies_add');

        $companyId = $this->resolveCompanyId($request);
        $validated = $request->validate([
            'leave_type_id'   => 'required|exists:leave_types,id',
            'name'            => 'required|string|max:100',
            'days_per_year'   => 'required|numeric|min:0|max:365',
            'accrual_type'    => 'required|in:annual,monthly,quarterly',
            'accrual_days'    => 'nullable|numeric|min:0',
            'proration'       => 'required|in:none,joining_month,full_month',
            'sandwich_policy' => 'boolean',
            'is_active'       => 'boolean',
        ]);

        LeavePolicy::create(array_merge($validated, [
            'company_id' => $companyId,
            'accrual_on_day'  => 1,
            'min_balance_to_apply'    => 0,
            'allow_negative_balance'  => false,
            'max_negative_days'       => 0,
            'sandwich_policy' => $request->boolean('sandwich_policy'),
            'is_active'       => $request->boolean('is_active', true),
        ]));

        return redirect()->route('leave.policies')->with('success', 'Leave policy added.');
    }

    public function updatePolicy(Request $request, LeavePolicy $policy)
    {
        $this->authorize('leave_policies_edit');

        $validated = $request->validate([
            'leave_type_id'   => 'required|exists:leave_types,id',
            'name'            => 'required|string|max:100',
            'days_per_year'   => 'required|numeric|min:0|max:365',
            'accrual_type'    => 'required|in:annual,monthly,quarterly',
            'accrual_days'    => 'nullable|numeric|min:0',
            'proration'       => 'required|in:none,joining_month,full_month',
            'sandwich_policy' => 'boolean',
            'is_active'       => 'boolean',
        ]);

        $policy->update(array_merge($validated, [
            'sandwich_policy' => $request->boolean('sandwich_policy'),
            'is_active'       => $request->boolean('is_active', true),
        ]));

        return redirect()->route('leave.policies')->with('success', 'Leave policy updated.');
    }

    public function destroyPolicy(LeavePolicy $policy)
    {
        $this->authorize('leave_policies_del');

        $policy->delete();
        return redirect()->route('leave.policies')->with('success', 'Leave policy deleted.');
    }

}
