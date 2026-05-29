<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use Illuminate\Console\Command;

/**
 * Financial-year leave settlement for CL (and optionally SL).
 *
 * At the end of every financial year (March 31):
 *   remaining_CL = annual_allocation + carry_fwd_opening − availed
 *
 *   Half (floor) → encashed    → update the closing FY record's encashed field
 *                               → this amount is paid out in April/May salary
 *   Remainder   → carry-forward → seeded into the new FY record as carry_forwarded
 *                               → employee can use these days before September
 *
 * Usage:
 *   php artisan leave:year-close --fy=2024            # settle FY 2024-25 (default = last completed FY)
 *   php artisan leave:year-close --fy=2024 --dry-run  # preview without writing
 *   php artisan leave:year-close --fy=2024 --types=CL # only CL (default)
 *   php artisan leave:year-close --fy=2024 --types=CL,SL
 */
class LeaveYearClose extends Command
{
    protected $signature = 'leave:year-close
                            {--fy= : FY start year, e.g. 2024 for FY 2024-25 (default: previous FY)}
                            {--types=CL : Comma-separated leave type codes to settle (default: CL)}
                            {--dry-run : Preview changes without writing to DB}';

    protected $description = 'Run end-of-financial-year leave settlement (encashment + carry-forward)';

    public function handle(): int
    {
        // ── Resolve FY year ────────────────────────────────────────────────
        $currentMonth = (int) now()->format('n');
        $currentYear  = (int) now()->format('Y');
        // Default: the FY that just ended. If we are Apr-Dec, last FY = currentYear-1.
        // If we are Jan-Mar, last FY = currentYear-2 (current FY hasn't closed yet).
        $defaultFy = $currentMonth >= 4 ? $currentYear - 1 : $currentYear - 2;
        $fy        = (int) ($this->option('fy') ?? $defaultFy);
        $newFy     = $fy + 1;

        $dryRun = (bool) $this->option('dry-run');
        $types  = array_map('strtoupper', array_map('trim', explode(',', $this->option('types') ?? 'CL')));

        $this->info(sprintf(
            '%s  FY %d-%d → carry-forward to FY %d-%d  |  Types: %s',
            $dryRun ? '[DRY RUN]' : '[LIVE]',
            $fy, $fy + 1, $newFy, $newFy + 1,
            implode(', ', $types)
        ));

        // ── Load leave types ───────────────────────────────────────────────
        $leaveTypeMap = LeaveType::whereIn('code', $types)
            ->orderByRaw('entity_id IS NULL DESC')
            ->get()
            ->keyBy('code');

        if ($leaveTypeMap->isEmpty()) {
            $this->error('No matching leave types found in the database for: ' . implode(', ', $types));
            return self::FAILURE;
        }

        // ── Load leave policies (annual allocation) ────────────────────────
        // Prefer entity-specific policy; fall back to global (entity_id = null).
        $policyMap = LeavePolicy::whereIn('leave_type_id', $leaveTypeMap->map(fn($lt) => $lt->id)->values())
            ->where('is_active', true)
            ->orderByRaw('entity_id IS NULL ASC') // entity-specific first
            ->get()
            ->keyBy('leave_type_id');

        // ── Process each leave type ────────────────────────────────────────
        $totalEncashed    = 0;
        $totalCarryFwd    = 0;
        $employeesChanged = 0;

        foreach ($types as $code) {
            $leaveType = $leaveTypeMap[$code] ?? null;
            if (!$leaveType) {
                $this->warn("  Leave type '{$code}' not found — skipped.");
                continue;
            }

            $policy = $policyMap[$leaveType->id] ?? null;
            $allocation = $policy ? (float) $policy->days_per_year : 0;

            $this->info("  Processing {$code}: annual allocation = {$allocation} days");

            // All employees who have a balance record for this FY
            $balances = LeaveBalance::where('leave_type_id', $leaveType->id)
                ->where('year', $fy)
                ->get();

            if ($balances->isEmpty()) {
                $this->warn("    No leave_balances rows found for FY {$fy} — skipped.");
                continue;
            }

            $this->info("    Found {$balances->count()} employee balance records.");

            $rows = [];
            foreach ($balances as $bal) {
                $carryFwdOpening = (float) ($bal->carry_forwarded ?? 0);
                $availed         = (float) ($bal->availed         ?? 0);
                $encashedAlready = (float) ($bal->encashed        ?? 0);

                // Total available = this year's allocation + whatever was carried forward from last year
                $totalAvailable = $allocation + $carryFwdOpening;
                $remaining      = max(0, $totalAvailable - $availed - $encashedAlready);

                if ($remaining <= 0) {
                    continue; // nothing to settle for this employee
                }

                $halfEncash  = (float) floor($remaining / 2);
                $halfCarryFwd = $remaining - $halfEncash;

                $rows[] = [
                    'employee_id'  => $bal->employee_id,
                    'balance_id'   => $bal->id,
                    'availed'      => $availed,
                    'remaining'    => $remaining,
                    'encash'       => $halfEncash,
                    'carry_fwd'    => $halfCarryFwd,
                    'old_encashed' => $encashedAlready,
                ];
            }

            if (empty($rows)) {
                $this->info("    No employees have remaining balance — nothing to settle.");
                continue;
            }

            // ── Table preview ──────────────────────────────────────────────
            $this->table(
                ['Emp DB ID', 'Availed', 'Remaining', '→ Encashed', '→ Carry Fwd'],
                array_map(fn($r) => [
                    $r['employee_id'], $r['availed'], $r['remaining'],
                    $r['encash'], $r['carry_fwd'],
                ], $rows)
            );

            if (!$dryRun) {
                $now = now()->toDateTimeString();

                foreach ($rows as $r) {
                    // 1. Update closing FY record: mark encashment
                    $newEncashed    = $r['old_encashed'] + $r['encash'];
                    $bal            = LeaveBalance::find($r['balance_id']);
                    $newClosing     = ((float)($bal->opening_balance ?? 0))
                                   + ((float)($bal->accrued         ?? 0))
                                   + ((float)($bal->carry_forwarded ?? 0))
                                   + ((float)($bal->adjusted        ?? 0))
                                   - ((float)($bal->availed         ?? 0))
                                   - $newEncashed
                                   - ((float)($bal->lapsed          ?? 0));

                    $bal->update([
                        'encashed'        => $newEncashed,
                        'closing_balance' => $newClosing,
                        'updated_at'      => $now,
                    ]);

                    // 2. Seed carry-forward into new FY record
                    $newBal = LeaveBalance::where('employee_id', $r['employee_id'])
                        ->where('leave_type_id', $leaveType->id)
                        ->where('year', $newFy)
                        ->first();

                    $existingCarryFwd = (float) ($newBal->carry_forwarded ?? 0);
                    $mergedCarryFwd   = $existingCarryFwd + $r['carry_fwd'];

                    $newOpening  = (float) ($newBal->opening_balance ?? 0);
                    $newAccrued  = (float) ($newBal->accrued         ?? 0);
                    $newAvailed  = (float) ($newBal->availed         ?? 0);
                    $newEncash2  = (float) ($newBal->encashed        ?? 0);
                    $newAdjusted = (float) ($newBal->adjusted        ?? 0);
                    $newLapsed   = (float) ($newBal->lapsed          ?? 0);
                    $newClosing2 = $newOpening + $newAccrued + $mergedCarryFwd + $newAdjusted - $newAvailed - $newEncash2 - $newLapsed;

                    LeaveBalance::updateOrCreate(
                        [
                            'employee_id'   => $r['employee_id'],
                            'leave_type_id' => $leaveType->id,
                            'year'          => $newFy,
                        ],
                        [
                            'carry_forwarded' => $mergedCarryFwd,
                            'closing_balance' => $newClosing2,
                            'updated_at'      => $now,
                        ]
                    );

                    $totalEncashed  += $r['encash'];
                    $totalCarryFwd  += $r['carry_fwd'];
                    $employeesChanged++;
                }

                $this->info("    ✅  Settled {$leaveType->code}: encashed=" . array_sum(array_column($rows, 'encash'))
                    . "  carry_fwd=" . array_sum(array_column($rows, 'carry_fwd')));
            }
        }

        if ($dryRun) {
            $this->warn('DRY RUN complete — no changes written. Re-run without --dry-run to apply.');
        } else {
            $this->info("Settlement complete. Employees processed: {$employeesChanged}, "
                . "total encashed days: {$totalEncashed}, total carry-fwd days: {$totalCarryFwd}.");
        }

        return self::SUCCESS;
    }
}
