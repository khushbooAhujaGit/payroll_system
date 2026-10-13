<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveApplication;
use App\Models\LeaveTrackerRecord;
use App\Models\LeaveType;
use App\Services\LeaveEntitlementService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The mobile app's Leave Management API — self-service only (apply for
 * self, view own history/balances). Mirrors WebLeaveController's logic for
 * the plain-employee case exactly (same validation, same overlap check,
 * same LeaveTrackerRecord-per-day write), but with no manager/HR review
 * surface — approving a team's leave stays a web-only action for now.
 */
class LeaveAppController extends Controller
{
    /** Same curated list WebLeaveController::index() offers in the Apply Leave modal — see that method for why these specific types. */
    private const APPLY_LEAVE_TYPE_NAMES = [
        'Casual Leave', 'Half-Day Casual Leave', 'Sick Leave', 'Half-Day Sick Leave',
        'Exam Leave', 'Birthday Leave', 'Anniversary Leave', 'Work From Home', 'Compensation Off',
        'Maternity Leave', 'Paternity Leave',
    ];

    public function __construct(protected LeaveEntitlementService $leaveEntitlementService) {}

    /**
     * Balances + the Apply Leave type list + the earliest date leave can be
     * backdated to — everything the Apply Leave screen needs before the
     * employee even opens the form.
     */
    public function summary(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 404, 'No employee profile linked to this account.');

        $balances = collect($this->leaveEntitlementService->currentCLSL($employee))
            ->merge(['Compensation Off' => $this->leaveEntitlementService->currentCompOff($employee)]);

        $leaveTypes = LeaveType::whereIn('name', self::APPLY_LEAVE_TYPE_NAMES)
            ->where(fn($q) => $q->whereNull('company_id')->orWhere('company_id', $employee->company_id))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn($lt) => [
                'id'            => $lt->id,
                'name'          => $lt->name,
                'code'          => $lt->code,
                'is_half_day'   => str_contains($lt->name, 'Half-Day'),
            ]);

        return response()->json([
            'data' => [
                'balances'       => $balances,
                'leave_types'    => $leaveTypes,
                'min_apply_date' => now()->subDays(30)->toDateString(),
            ],
        ]);
    }

    /**
     * Own leave history — legacy LeaveApplication rows plus
     * LeaveTrackerRecord rows (bulk import/manual entry/app-submitted),
     * combined and sorted, same normalization WebLeaveController uses for
     * the web "My Applications" table. Paginated, newest first.
     */
    public function applications(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 404, 'No employee profile linked to this account.');

        $applications = LeaveApplication::with('leaveType:id,name,code')
            ->where('employee_id', $employee->id)
            ->get()
            ->map(fn($app) => [
                'kind'       => 'application',
                'id'         => $app->id,
                'leave_type' => $app->leaveType?->name ?? '—',
                'from_date'  => $app->from_date,
                'to_date'    => $app->to_date,
                'days'       => $app->days,
                'reason'     => $app->reason,
                'status'     => $app->status,
                'sort_date'  => (string) ($app->applied_at ?? $app->from_date),
            ]);

        // Excludes legacy application-mirror rows (leave_ref_id LIKE 'APP-%')
        // same as WebLeaveController::trackerRecordsQuery() — those are
        // already represented via their real LeaveApplication row above.
        $trackerRecords = LeaveTrackerRecord::where('employee_id', $employee->id)
            ->where('is_active', true)
            ->where('leave_ref_id', 'not like', 'APP-%')
            ->get()
            ->map(fn($rec) => [
                'kind'       => 'tracker',
                'id'         => $rec->id,
                'leave_type' => $rec->leave_type . ($rec->half_day_period ? ' (' . ($rec->half_day_period === 'first_half' ? 'First Half' : 'Second Half') . ')' : ''),
                'from_date'  => $rec->leave_date,
                'to_date'    => $rec->leave_date,
                'days'       => 1,
                'reason'     => $rec->reason,
                'status'     => $rec->is_approved ? 'approved' : 'pending',
                'sort_date'  => (string) $rec->leave_date,
            ]);

        $status = $request->get('status');
        $sorted = $applications->concat($trackerRecords)
            ->when($status, fn($rows) => $rows->where('status', $status))
            ->sortByDesc(fn($r) => Carbon::parse($r['sort_date']))
            ->values();

        $perPage = 15;
        $page    = max(1, (int) $request->get('page', 1));
        $items   = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $sorted->count(),
                'last_page'    => max(1, (int) ceil($sorted->count() / $perPage)),
            ],
        ]);
    }

    /**
     * Submit a new leave request for self — same validation, overlap check,
     * and one-LeaveTrackerRecord-per-day write as
     * WebLeaveController::apply(), just with no employee_id override (the
     * app only ever applies for the logged-in employee, never on behalf of
     * someone else — that stays a manager/HR web-only action).
     */
    public function apply(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 404, 'No employee profile linked to this account.');

        $minApplyDate = now()->subDays(30)->toDateString();

        $validated = $request->validate([
            'duration_type'    => 'required|in:single,period',
            'single_date'      => "nullable|required_if:duration_type,single|date|after_or_equal:{$minApplyDate}",
            'from_date'        => "nullable|required_if:duration_type,period|date|after_or_equal:{$minApplyDate}",
            'to_date'          => 'nullable|required_if:duration_type,period|date|after_or_equal:from_date',
            'leave_type_id'    => 'required|exists:leave_types,id',
            'reason'           => 'nullable|string|max:500',
            'half_day_period'  => 'nullable|in:first_half,second_half',
        ], [
            'single_date.after_or_equal' => 'You cannot apply for leave more than 30 days in the past.',
            'from_date.after_or_equal'   => 'You cannot apply for leave more than 30 days in the past.',
        ]);

        $fromDate = $validated['duration_type'] === 'single' ? $validated['single_date'] : $validated['from_date'];
        $toDate   = $validated['duration_type'] === 'single' ? $validated['single_date'] : $validated['to_date'];

        $overlapping = LeaveTrackerRecord::where('employee_id', $employee->id)
            ->where('is_active', true)
            ->whereBetween('leave_date', [$fromDate, $toDate])
            ->exists();

        if ($overlapping) {
            return response()->json([
                'message' => 'A pending or approved leave record already exists covering these dates.',
                'errors'  => ['duplicate' => ['A pending or approved leave record already exists covering these dates.']],
            ], 422);
        }

        $leaveType = LeaveType::find($validated['leave_type_id']);
        $isHalfDayType = $leaveType && str_contains($leaveType->name, 'Half-Day');
        if ($isHalfDayType && empty($validated['half_day_period'])) {
            return response()->json([
                'message' => 'Please select which half of the day this leave covers.',
                'errors'  => ['half_day_period' => ['Please select which half of the day this leave covers.']],
            ], 422);
        }

        $requestRef = 'REQ-' . now()->format('YmdHis') . strtoupper(\Illuminate\Support\Str::random(4));
        $period     = CarbonPeriod::create($fromDate, $toDate);
        $createdIds = [];

        DB::transaction(function () use ($period, $requestRef, $employee, $leaveType, $validated, $isHalfDayType, &$createdIds) {
            foreach ($period as $day) {
                $record = LeaveTrackerRecord::create([
                    'leave_ref_id'    => $requestRef . '-' . $day->format('Ymd'),
                    'employee_id'     => $employee->id,
                    'sheet_emp_id'    => $employee->employee_id,
                    'employee_name'   => $employee->full_name,
                    'leave_date'      => $day->format('Y-m-d'),
                    'leave_type'      => strtoupper($leaveType?->code ?? $leaveType?->name ?? 'LEAVE'),
                    'half_day_period' => $isHalfDayType ? $validated['half_day_period'] : null,
                    'reason_type'     => 'Leave Application',
                    'reason'          => $validated['reason'] ?? null,
                    'source_file'     => 'leave_application',
                    'uploaded_by'     => auth()->id(),
                    'is_active'       => true,
                    'is_approved'     => false,
                ]);
                $createdIds[] = $record->id;
            }
        });

        return response()->json([
            'data' => [
                'created_record_ids' => $createdIds,
                'from_date'          => $fromDate,
                'to_date'            => $toDate,
            ],
        ], 201);
    }
}
