<?php

namespace App\Console\Commands;

use App\Models\LeaveApplication;
use Illuminate\Console\Command;

/**
 * One-time data fix: any single-day leave application (from_date == to_date)
 * whose stored `days` is 0 gets corrected to 1.
 *
 * Root cause: apply() used to compute `days` via calculateDays(), which only
 * counts weekdays — so a single day picked on a Saturday/Sunday came out to
 * 0. apply() itself was fixed to always store 1 for a single-day pick
 * (calculateDays() is still used, correctly, for multi-day ranges), but that
 * fix only applies to applications submitted after the fix — rows already in
 * the database still have the old, wrong 0.
 *
 * Scoped tightly to days = 0 so it never touches a legitimate half-day entry
 * (days = 0.5) or a real multi-day range.
 *
 * Idempotent — re-running finds nothing left to fix once applied.
 */
class FixZeroDayLeaveApplications extends Command
{
    protected $signature = 'leave:fix-zero-day-applications';
    protected $description = 'Correct single-day leave applications stored with days = 0 to days = 1';

    public function handle(): int
    {
        $count = LeaveApplication::whereColumn('from_date', '=', 'to_date')
            ->where('days', 0)
            ->update(['days' => 1]);

        $this->info("Corrected {$count} single-day leave application(s) from days=0 to days=1.");

        return self::SUCCESS;
    }
}
