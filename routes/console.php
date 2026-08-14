<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Scheduled Jobs ────────────────────────────────────────────

// Daily: Accrue leave balances (runs at midnight)
Schedule::command('leave:accrue-monthly')->monthlyOn(1, '00:05');

// Daily: Send payroll reminder 3 days before cutoff
Schedule::command('payroll:send-reminders')->dailyAt('09:00');

// Weekly: Check compliance due dates and alert
Schedule::command('compliance:check-due')->weeklyOn(1, '08:00');

// Daily: Auto-mark absent for employees without attendance
Schedule::command('attendance:auto-mark-absent')->dailyAt('23:30');

// Nightly: Expire unreviewed POI submissions reminder
Schedule::command('poi:send-pending-reminders')->dailyAt('09:30');

// Monthly: Run carry forward on April 1st
Schedule::command('leave:carry-forward --year=' . (date('Y') - 1))->yearlyOn(4, 1, '01:00');

// Hourly: Clean up expired Sanctum tokens
Schedule::command('sanctum:prune-expired --hours=24')->hourly();

// Hourly: Clean up stale import error-export files (safety net; the download
// route already deletes each file immediately after it's downloaded)
Schedule::command('storage:prune-error-exports --hours=24')->hourly();

// Nightly: Disable login for users whose employee record shows they've left
// (date_of_leaving in the past) — also enforced lazily at login time.
Schedule::command('accounts:deactivate-leavers')->dailyAt('00:15');

// Weekly (Monday morning): notify employees whose average clocked hours
// fell below 9/day for the week that just ended.
Schedule::command('attendance:check-weekly-average')->weeklyOn(1, '08:00');
