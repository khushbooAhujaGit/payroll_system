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
