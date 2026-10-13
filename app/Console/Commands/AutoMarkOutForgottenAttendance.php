<?php

namespace App\Console\Commands;

use App\Models\AttendanceAppPunchEvent;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * End-of-day safety net for the mobile app's Mark In/Out: an employee who
 * marked in but never marked out gets an automatic 'out' event at 23:59:59
 * of the day they marked in — not 00:00:00 the next day, even though the
 * feature is described as "12 AM auto mark-out", because
 * AttendanceDayResolver attributes a punch event to whichever calendar date
 * its event_at timestamp falls on (see its groupBy on event_at->format('Y-m-d')).
 * A 00:00:00-next-day timestamp would attribute the mark-out to the WRONG
 * day — tomorrow, not the day they actually forgot to mark out — making
 * that day look like it still has no check-out at all. 23:59:59 same day
 * is the timestamp that actually achieves "auto-closed at midnight" from
 * the resolver's point of view.
 *
 * Scheduled daily just after midnight (see routes/console.php or
 * app/Console/Kernel.php's schedule()) so it processes "yesterday" once
 * that day has fully ended — matching the requirement that this only fires
 * for a day that's already over, never cutting off someone still working.
 *
 * Idempotent: only acts on employee+day combinations with an 'in' event
 * and zero 'out' events at all, so re-running (e.g. a cron misfire) never
 * creates a second auto-mark-out for the same day.
 */
class AutoMarkOutForgottenAttendance extends Command
{
    protected $signature = 'attendance:auto-mark-out {--date= : Process a specific date (Y-m-d) instead of yesterday — for manual backfill/testing}';
    protected $description = 'Auto mark-out (23:59:59) any employee who marked in via the app but never marked out that day';

    public function handle(): int
    {
        $date = $this->option('date') ?: now()->subDay()->toDateString();

        $employeeIdsWithIn = AttendanceAppPunchEvent::whereDate('event_at', $date)
            ->where('event_type', 'in')
            ->distinct()
            ->pluck('employee_id');

        if ($employeeIdsWithIn->isEmpty()) {
            $this->info("No app mark-ins found for {$date} — nothing to do.");
            return self::SUCCESS;
        }

        $employeeIdsWithOut = AttendanceAppPunchEvent::whereDate('event_at', $date)
            ->where('event_type', 'out')
            ->distinct()
            ->pluck('employee_id');

        $missingOut = $employeeIdsWithIn->diff($employeeIdsWithOut);

        if ($missingOut->isEmpty()) {
            $this->info("Every app mark-in for {$date} already has a mark-out — nothing to do.");
            return self::SUCCESS;
        }

        $eventAt = Carbon::parse($date . ' 23:59:59');

        foreach ($missingOut as $employeeId) {
            AttendanceAppPunchEvent::create([
                'employee_id' => $employeeId,
                'event_type'  => 'out',
                'method'      => 'auto',
                'event_at'    => $eventAt,
                'is_deviation' => false,
            ]);
        }

        $this->info("Auto mark-out created for {$missingOut->count()} employee(s) for {$date}.");

        return self::SUCCESS;
    }
}
