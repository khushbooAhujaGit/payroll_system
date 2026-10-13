<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Day-level and week-level "below 9h" Change Requests are now two distinct
 * flows (see WebMyAttendanceController::requestChange()) — both still live
 * in the same attendance_regularizations table, distinguished by this new
 * reason value vs the existing 'weekly_hours_shortfall'.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE attendance_regularizations MODIFY reason ENUM(
            'forgot_punch', 'system_error', 'client_visit', 'wfh', 'travel', 'other',
            'weekly_hours_shortfall', 'daily_hours_shortfall'
        ) DEFAULT 'other'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE attendance_regularizations MODIFY reason ENUM(
            'forgot_punch', 'system_error', 'client_visit', 'wfh', 'travel', 'other',
            'weekly_hours_shortfall'
        ) DEFAULT 'other'");
    }
};
