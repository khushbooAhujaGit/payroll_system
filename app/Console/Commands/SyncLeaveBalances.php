<?php

namespace App\Console\Commands;

use App\Models\LeaveBalance;
use App\Models\LeaveType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rebuild leave_balances from leave_tracker_records using financial-year grouping.
 *
 * Financial year (India): April 1 – March 31.
 *   Apr-Dec of year Y  →  FY year = Y          (e.g. Oct 2024 → FY 2024)
 *   Jan-Mar of year Y  →  FY year = Y-1        (e.g. Feb 2025 → FY 2024)
 *
 * Usage:
 *   php artisan leave:sync-balances           # sync all employees
 *   php artisan leave:sync-balances --clean   # delete auto-generated records first, then sync
 */
class SyncLeaveBalances extends Command
{
    protected $signature   = 'leave:sync-balances {--clean : Delete auto-generated records before re-syncing}';
    protected $description = 'Rebuild leave_balances from tracker records using financial-year + monthly grouping';

    public function handle(): int
    {
        $this->info('Building leave type map…');

        $leaveTypeMap = LeaveType::orderByRaw('entity_id IS NULL DESC')
            ->get()
            ->keyBy('code')
            ->map(fn($lt) => $lt->id);

        if ($this->option('clean')) {
            $this->warn('--clean: removing auto-generated leave_balances rows (opening=accrued=carry_fwd=adjusted=encashed=lapsed=0)…');
            $deleted = LeaveBalance::where('opening_balance', 0)
                ->where('accrued', 0)
                ->where('carry_forwarded', 0)
                ->where('adjusted', 0)
                ->where('encashed', 0)
                ->where('lapsed', 0)
                ->delete();
            $this->info("  Deleted {$deleted} auto-generated rows.");
        }

        $this->info('Aggregating tracker records by FY year…');

        $fyExpr = 'CASE WHEN MONTH(leave_date) >= 4 THEN YEAR(leave_date) ELSE YEAR(leave_date) - 1 END';

        $rows = DB::table('leave_tracker_records')
            ->select(
                'employee_id',
                'leave_type',
                DB::raw("{$fyExpr} as fy_year"),
                DB::raw('MONTH(leave_date) as lv_month'),
                DB::raw('COUNT(*) as availed')
            )
            ->whereNotNull('employee_id')
            ->where('is_active', true)
            ->groupBy('employee_id', 'leave_type', DB::raw($fyExpr), DB::raw('MONTH(leave_date)'))
            ->get();

        $this->info("  Found {$rows->count()} (employee × leave_type × FY × month) groups.");

        $now     = now()->toDateTimeString();
        $upserted = 0;
        $skipped  = 0;

        foreach ($rows as $row) {
            $leaveTypeId = $leaveTypeMap[$row->leave_type] ?? null;
            if ($leaveTypeId === null) {
                $skipped++;
                continue;
            }

            $month = (int) $row->lv_month;

            $existing = LeaveBalance::where('employee_id', $row->employee_id)
                ->where('leave_type_id', $leaveTypeId)
                ->where('year', $row->fy_year)
                ->where('month', $month)
                ->first();

            $opening    = (float) ($existing->opening_balance  ?? 0);
            $accrued    = (float) ($existing->accrued          ?? 0);
            $carryFwd   = (float) ($existing->carry_forwarded  ?? 0);
            $adjusted   = (float) ($existing->adjusted         ?? 0);
            $encashed   = (float) ($existing->encashed         ?? 0);
            $lapsed     = (float) ($existing->lapsed           ?? 0);
            $availed    = (float) $row->availed;
            $closingBal = $opening + $accrued + $carryFwd + $adjusted - $availed - $encashed - $lapsed;

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

            $upserted++;
        }

        $this->info("Done. Upserted: {$upserted}, skipped (unknown leave type): {$skipped}.");

        if ($skipped > 0) {
            $this->warn("  {$skipped} rows skipped because their leave_type code was not in the leave_types table.");
        }

        return self::SUCCESS;
    }
}
