<?php

namespace App\Services;

use App\Models\CompOff;
use App\Models\Employee;
use App\Models\LeaveTrackerRecord;
use App\Models\LeaveType;
use App\Models\LeavePolicy;
use Carbon\Carbon;

/**
 * The real, live CL/SL balance computation — there is no persisted balance
 * ledger for these in this app (the leave_balances table is unused for CL/
 * SL; accrued is always 0 there). It's recomputed on every call: fixed
 * annual allocation minus a count of approved LeaveTrackerRecord rows for
 * the current financial year, with SL overflow beyond its own allocation
 * spilling into CL usage.
 *
 * Extracted out of WebLeaveController so the Dashboard can show the same
 * numbers as the Leave Management page instead of drifting out of sync.
 */
class LeaveEntitlementService
{
    public function currentCLSL(Employee $employee): array
    {
        $today   = now();
        $fyYear  = $today->month >= 4 ? $today->year : $today->year - 1;
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
     * Comp-Off has no fixed allocation (it's earned per extra day worked,
     * not granted annually) and no financial-year reset — it's a running
     * balance: total approved credits minus total days actually taken as
     * comp-off. Currently always 0 for everyone because nothing in the app
     * yet lets HR/an employee record a credit (the CompOff table this reads
     * from has no rows) — that's a real gap, not a display bug, but this
     * computation is ready to reflect real numbers the moment credits exist.
     */
    public function currentCompOff(Employee $employee): float
    {
        $earned = (float) CompOff::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->sum('credited_days');

        $coCodes = LeaveType::where('is_active', true)
            ->where('category', 'comp_off')
            ->pluck('code')
            ->toArray();

        $used = 0.0;
        if (!empty($coCodes)) {
            $records = LeaveTrackerRecord::where('employee_id', $employee->id)
                ->whereIn('leave_type', $coCodes)
                ->where('is_active', true)
                ->get(['leave_type']);

            foreach ($records as $r) {
                $used += str_starts_with($r->leave_type, 'H') ? 0.5 : 1.0;
            }
        }

        return round(max(0.0, $earned - $used), 1);
    }
}
