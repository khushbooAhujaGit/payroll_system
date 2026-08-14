<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The My Attendance "Raise Change Request" flow (item #22) writes
 * reason = 'weekly_hours_shortfall', but the enum only ever had the
 * original single-day regularization reasons — the insert failed with
 * "Data truncated for column 'reason'".
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE attendance_regularizations MODIFY reason ENUM(
            'forgot_punch', 'system_error', 'client_visit', 'wfh', 'travel', 'other',
            'weekly_hours_shortfall'
        ) DEFAULT 'other'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE attendance_regularizations MODIFY reason ENUM(
            'forgot_punch', 'system_error', 'client_visit', 'wfh', 'travel', 'other'
        ) DEFAULT 'other'");
    }
};
