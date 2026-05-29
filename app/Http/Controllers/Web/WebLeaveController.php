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
use App\Services\ExcelImportService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebLeaveController extends Controller
{
    use ImportsExcel;

    public function __construct(protected ExcelImportService $excelImportService) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Leave Management  (applications + tracker records)
    // ─────────────────────────────────────────────────────────────────────────
 
    public function index(Request $request)
    {
        $entityId = $this->resolveEntityId($request);

        // Leave applications (workflow-based)
        $applications = LeaveApplication::with([
            'employee:id,employee_id,first_name,last_name,department_id',
            'employee.department:id,name',
            'leaveType:id,name,code',
        ])
        ->whereHas('employee', fn($q) => $q->where('entity_id', $entityId))
        ->when($request->status, fn($q) => $q->where('status', $request->status))
        ->orderByDesc('applied_at')
        ->paginate(25)->withQueryString();

        $pending = LeaveApplication::whereHas('employee', fn($q) => $q->where('entity_id', $entityId))
            ->where('status', 'pending')->count();

        // Tracker entity employee IDs (for subquery)
        $entityEmployeeIds = Employee::where('entity_id', $entityId)->pluck('id');

        // Base tracker filter closure (reused for count + grouped query)
        $trackerFilter = function ($q) use ($entityEmployeeIds, $request) {
            $q->where('is_active', true)
              ->where(function ($sub) use ($entityEmployeeIds) {
                $sub->whereIn('employee_id', $entityEmployeeIds)
                    ->orWhereNull('employee_id');
            })
            ->when($request->tracker_type, fn($s) => $s->where('leave_type', $request->tracker_type))
            ->when($request->tracker_emp,  fn($s) => $s->where(function ($sq) use ($request) {
                $sq->where('sheet_emp_id', $request->tracker_emp)
                   ->orWhere('employee_name', 'like', "%{$request->tracker_emp}%");
            }))
            ->when($request->tracker_month, fn($s) => $s->whereRaw('DATE_FORMAT(leave_date,"%Y-%m") = ?', [$request->tracker_month]));
        };

        // Total individual leave days for the stat card
        $trackerTotal = LeaveTrackerRecord::where($trackerFilter)->count();

        // Grouped summary: one row per (employee × leave_type), ordered by most recent
        $trackerGroups = DB::table('leave_tracker_records')
            ->select(
                'sheet_emp_id', 'employee_name', 'employee_id', 'leave_type',
                DB::raw('COUNT(*) as total_days'),
                DB::raw('MIN(leave_date) as first_date'),
                DB::raw('MAX(leave_date) as last_date')
            )
            ->where($trackerFilter)
            ->groupBy('sheet_emp_id', 'employee_name', 'employee_id', 'leave_type')
            ->orderByRaw('MAX(leave_date) DESC')
            ->paginate(30, ['*'], 'tracker_page')
            ->withQueryString();

        $leaveTypes = LeaveType::whereNull('entity_id')->orWhere('entity_id', $entityId)
            ->where('is_active', true)->orderBy('code')->get();

        $employees  = Employee::where('entity_id', $entityId)->where('employee_status', 'active')
            ->orderBy('first_name')->get();

        $trackerLeaveTypes = LeaveTrackerRecord::distinct()->pluck('leave_type')->sort()->values();

        return view('leave.index', compact(
            'applications', 'pending', 'leaveTypes', 'employees', 'entityId',
            'trackerGroups', 'trackerTotal', 'trackerLeaveTypes'
        ));
    }

    
    public function apply(Request $request)
    {
        $validated = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'from_date'     => 'required|date',
            'to_date'       => 'required|date|after_or_equal:from_date',
            'reason'        => 'nullable|string|max:500',
            'employee_id'   => 'nullable|exists:employees,id',
        ]);

        $employee = $validated['employee_id']
            ? Employee::findOrFail($validated['employee_id'])
            : auth()->user()->employee;

        if (!$employee) {
            return redirect()->back()->withErrors(['employee_id' => 'No employee profile found.']);
        }

        $days = $this->calculateDays($validated['from_date'], $validated['to_date']);

        DB::beginTransaction();
        try {
            $num  = 'LA-' . now()->year . '-' . str_pad(LeaveApplication::count() + 1, 6, '0', STR_PAD_LEFT);
            LeaveApplication::create([
                'application_number' => $num,
                'employee_id'        => $employee->id,
                'leave_type_id'      => $validated['leave_type_id'],
                'from_date'          => $validated['from_date'],
                'to_date'            => $validated['to_date'],
                'days'               => $days,
                'is_half_day'        => false,
                'reason'             => $validated['reason'] ?? null,
                'status'             => 'pending',
                'applied_at'         => now(),
            ]);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()->route('leave')->with('success', 'Leave application submitted.');
    }

    public function approve(Request $request, LeaveApplication $application)
    {
        $request->validate(['action' => 'required|in:approved,rejected']);

        $application->update(['status' => $request->action]);

        if ($request->action === 'approved') {
            $period = CarbonPeriod::create($application->from_date, $application->to_date);
            foreach ($period as $day) {
                \App\Models\Attendance::updateOrCreate(
                    ['employee_id' => $application->employee_id, 'attendance_date' => $day->format('Y-m-d')],
                    ['status' => 'leave', 'source' => 'leave_system']
                );
            }
        }

        return redirect()->route('leave')->with('success', 'Leave ' . $request->action . '.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Leave Tracker Import  (Mark Leave sheet from xlsm)
    // ─────────────────────────────────────────────────────────────────────────

    public function trackerImport(Request $request)
    {
        $entityId = $this->resolveEntityId($request);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,xlsm|max:20480',
        ]);

        $importMonth = $request->get('import_month'); // YYYY-MM — for tagging/logging
        $result = $this->excelImportService->import(
            $request->file('file'),
            'leave_tracker',
            LeaveTrackerImporter::class,
            [
                'entity_id'    => $entityId,
                'sheet_name'   => 'Mark Leave',
                'uploaded_by'  => auth()->id(),
                'source_file'  => $request->file('file')->getClientOriginalName(),
                'import_month' => $importMonth,
            ]
        );

        return $this->handleImportResult($result, 'leave', 'leave record');
    }

    public function trackerTemplate()
    {
        return $this->excelImportService->generateTemplate(LeaveTrackerImporter::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Leave Tracker records listing (dedicated page)
    // ─────────────────────────────────────────────────────────────────────────

    public function trackerStore(Request $request)
    {
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
            'employee_name'    => $validated['employee_name'] ?? ($employee ? $employee->first_name . ' ' . $employee->last_name : null),
            'leave_date'       => $validated['leave_date'],
            'leave_type'       => strtoupper($validated['leave_type']),
            'team_leader_name' => $validated['team_leader_name'] ?? null,
            'reason_type'      => $validated['reason_type'] ?? null,
            'reason'           => $validated['reason'] ?? null,
            'source_file'      => 'manual',
            'uploaded_by'      => auth()->id(),
        ]);

        return redirect()->route('leave.tracker')->with('success', 'Leave record added.');
    }

    public function trackerUpdate(Request $request, LeaveTrackerRecord $record)
    {
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
        $record->delete();
        return redirect()->route('leave.tracker', ['view' => 'daily'])->with('success', 'Leave record deleted.');
    }

    public function trackerRecords(Request $request)
    {
        $view = $request->get('view', 'spells'); // 'spells' | 'daily'

        $leaveTypes   = LeaveTrackerRecord::distinct()->pluck('leave_type')->sort()->values();
        $totalRecords = LeaveTrackerRecord::count();

        // Shared filter applied to both views — only active (non-superseded) records
        $baseQuery = LeaveTrackerRecord::with('employee:id,employee_id,first_name,last_name')
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
                && $current['leave_type']   === $rec->leave_type;

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
        $entityId = $this->resolveEntityId($request);

        $policies = LeavePolicy::with('leaveType:id,code,name')
            ->where(fn($q) => $q->whereNull('entity_id')->orWhere('entity_id', $entityId))
            ->orderBy('name')
            ->get();

        $leaveTypes = LeaveType::where('is_active', true)
            ->whereNull('entity_id')
            ->orWhere('entity_id', $entityId)
            ->orderBy('code')
            ->get();

        $accrualTypes = ['annual' => 'Annual', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly'];
        $prorationsTypes = ['none' => 'None', 'joining_month' => 'Joining Month', 'full_month' => 'Full Month'];

        return view('leave.policies', compact('policies', 'leaveTypes', 'accrualTypes', 'prorationsTypes', 'entityId'));
    }

    public function storePolicy(Request $request)
    {
        $entityId = $this->resolveEntityId($request);
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
            'entity_id'       => $entityId,
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
        $policy->delete();
        return redirect()->route('leave.policies')->with('success', 'Leave policy deleted.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Shared helpers
    // ─────────────────────────────────────────────────────────────────────────

    protected function calculateDays(string $from, string $to): float
    {
        $period = CarbonPeriod::create($from, $to);
        $days   = 0;
        foreach ($period as $day) {
            if (!in_array($day->format('D'), ['Sat', 'Sun'])) $days++;
        }
        return $days;
    }
}
