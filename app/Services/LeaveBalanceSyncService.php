<?php

namespace App\Services;

use App\Models\LeaveBalance;
use App\Models\LeaveType;
use Illuminate\Support\Facades\DB;

/**
 * Synchronizes leave_balances table from leave_tracker_records.
 *
 * Called by:
 *   1. LeaveTrackerImporter::afterImport() — after file upload
 *   2. Command: leave:sync-balances — manual sync anytime
 *   3. Scheduled job (daily) — automatic daily sync
 */
class LeaveBalanceSyncService
{
    /**
     * Sync leave_balances for given employees.
     * Aggregates availed leaves from leave_tracker_records by employee, leave_type, FY year, and month.
     *
     * @param array $empIds  Database employee IDs to sync
     */
    public function sync(array $empIds): int
    {
        if (empty($empIds)) {
            return 0;
        }

        // Build code → id map for all global leave types
        $leaveTypeMap = LeaveType::whereIn('code', [
            'CL', 'SL', 'EL', 'CP', 'LOP', 'WFH',
            'HCL', 'HSL', 'CO', 'HCO', 'MTL', 'PTL',
            'RL', 'JN', 'POM', 'PIM', 'PM', 'BDY',
            'WDA', 'AP', 'LFT', 'EL', 'LOP'
        ])
            ->orderByRaw('company_id IS NULL DESC')
            ->get()
            ->keyBy('code')
            ->map(fn($lt) => $lt->id);

        // FY calculation: Apr 1 – Mar 31
        // Apr-Dec year Y → fy_year = Y
        // Jan-Mar year Y → fy_year = Y-1
        $fyExpr = 'CASE WHEN MONTH(leave_date) >= 4 THEN YEAR(leave_date) ELSE YEAR(leave_date) - 1 END';

        // Aggregate by employee, leave_type code, FY year, and month
        $rows = DB::table('leave_tracker_records')
            ->select(
                'employee_id',
                'leave_type',
                DB::raw("{$fyExpr} as fy_year"),
                DB::raw('MONTH(leave_date) as lv_month'),
                DB::raw('COUNT(*) as availed')
            )
            ->whereIn('employee_id', $empIds)
            ->whereNotNull('employee_id')
            ->where('is_active', true)
            ->groupBy('employee_id', 'leave_type', DB::raw($fyExpr), DB::raw('MONTH(leave_date)'))
            ->get();

        $now = now()->toDateTimeString();
        $synced = 0;

        foreach ($rows as $row) {
            $leaveTypeId = $leaveTypeMap[$row->leave_type] ?? null;
            if ($leaveTypeId === null) {
                continue; // unknown leave type code — skip
            }

            $month = (int) $row->lv_month;

            // Read existing monthly balance so we don't overwrite opening/accrued/carry-forward
            $existing = LeaveBalance::where('employee_id', $row->employee_id)
                ->where('leave_type_id', $leaveTypeId)
                ->where('year', $row->fy_year)
                ->where('month', $month)
                ->first();

            $opening       = (float) ($existing?->opening_balance  ?? 0);
            $accrued       = (float) ($existing?->accrued          ?? 0);
            $carryFwd      = (float) ($existing?->carry_forwarded  ?? 0);
            $adjusted      = (float) ($existing?->adjusted         ?? 0);
            $encashed      = (float) ($existing?->encashed         ?? 0);
            $lapsed        = (float) ($existing?->lapsed           ?? 0);
            $availed       = (float) $row->availed;
            $closingBal    = $opening + $accrued + $carryFwd + $adjusted - $availed - $encashed - $lapsed;

            LeaveBalance::updateOrCreate(
                [
                    'employee_id'   => $row->employee_id,
                    'leave_type_id' => $leaveTypeId,
                    'year'          => $row->fy_year,
                    'month'         => $month,
                ],
                [
                    'availed'         => $availed,
                    'closing_balance' => $closingBal,
                    'updated_at'      => $now,
                ]
            );

            $synced++;
        }

        return $synced;
    }
}
