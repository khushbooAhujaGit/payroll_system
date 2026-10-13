<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds 'auto' as a valid `method` value for the new end-of-day auto
 * mark-out feature (AutoMarkOutForgottenAttendance command) — a punch
 * event the system creates itself when an employee marked in but never
 * marked out, distinguishing it from the three employee-initiated methods.
 * Raw SQL (not $table->enum(...)->change(), which needs doctrine/dbal) to
 * avoid adding that dependency just for one enum change.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE attendance_app_punch_events MODIFY method ENUM('gps','wifi','manual_app','auto') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE attendance_app_punch_events MODIFY method ENUM('gps','wifi','manual_app') NOT NULL");
    }
};
