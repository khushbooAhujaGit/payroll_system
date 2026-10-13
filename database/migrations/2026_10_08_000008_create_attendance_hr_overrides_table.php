<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The "wins over everything" table (ATTENDANCE_APP_PLAN.md §3.4 / §4) — when
 * a row exists for an employee+date, AttendanceDayResolver uses it exclusively
 * and ignores attendance_raw_punches / attendance_app_punch_events for that
 * day entirely (system-recorded punches stay in the DB, just excluded from
 * every calculation for that day). One row per employee+date.
 * No real FK constraints — employees/users are MyISAM, which InnoDB
 * refuses to reference (error 1824); plain indexed columns instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_hr_overrides', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('employee_id');
            $table->date('attendance_date');
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->text('reason')->nullable();
            $table->enum('source', ['manual', 'excel_import']);
            $table->foreignId('created_by')->nullable();
            $table->string('source_file')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'attendance_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_hr_overrides');
    }
};
