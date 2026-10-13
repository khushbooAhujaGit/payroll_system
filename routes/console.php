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
Schedule::command('payroll:send-reminders')->dailyAt('10:00');

// Weekly: Check compliance due dates and alert
Schedule::command('compliance:check-due')->weeklyOn(1, '08:00');

// Daily: Auto-mark absent for employees without attendance
Schedule::command('attendance:auto-mark-absent')->dailyAt('23:30');

// Just after midnight: auto mark-out (timestamped 23:59:59 the PREVIOUS
// day — see AutoMarkOutForgottenAttendance's own doc comment for why not
// 00:00:00) for anyone who marked in via the app but never marked out.
Schedule::command('attendance:auto-mark-out')->dailyAt('00:05');

// Nightly: Expire unreviewed POI submissions reminder
Schedule::command('poi:send-pending-reminders')->dailyAt('10:15');

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

// Daily 10:30 AM: per-company attendance exception report (item 56) — who
// hasn't arrived / is on leave, with a full attendance Excel attached.
// Skips any company with no recipients configured (AttendanceMailRecipient).
Schedule::command('attendance:daily-exception-mail')->dailyAt('10:30');

// Daily morning: remind each reporting manager and every HR user about
// leave applications still awaiting a decision. Own log file — see
// storage/logs/leave-reminders.log for whether/what it sent each day, on
// top of the Log::info/error calls inside the command itself (those also
// land in the regular storage/logs/laravel.log).
Schedule::command('leave:send-pending-reminders')
    ->dailyAt('09:00')
    ->appendOutputTo(storage_path('logs/leave-reminders.log'));
