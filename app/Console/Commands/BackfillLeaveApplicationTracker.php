<?php

namespace App\Console\Commands;

use App\Models\LeaveApplication;
use App\Services\LeaveApplicationTrackerSyncService;
use Illuminate\Console\Command;

/**
 * One-time backfill: mirrors every existing leave_applications row into
 * leave_tracker_records. The live apply()/approve()/reject() flow in
 * WebLeaveController only started doing this mirroring going forward — any
 * application submitted before that change has no leave_tracker_records
 * counterpart and won't show up on the /leave/tracker page.
 *
 * Idempotent — keyed by the same 'APP-{id}-{Ymd}' leave_ref_id the live flow
 * uses, so re-running this is safe (it just updates the same rows).
 */
class BackfillLeaveApplicationTracker extends Command
{
    protected $signature = 'leave:backfill-tracker-mirror';
    protected $description = 'Mirror existing leave_applications rows into leave_tracker_records';

    public function handle(LeaveApplicationTrackerSyncService $trackerSync): int
    {
        $applications = LeaveApplication::with('employee', 'leaveType')->get();

        $bar = $this->output->createProgressBar($applications->count());
        foreach ($applications as $application) {
            $trackerSync->syncFromStatus($application);
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        $this->info("Backfilled tracker mirror for {$applications->count()} leave application(s).");

        return self::SUCCESS;
    }
}
