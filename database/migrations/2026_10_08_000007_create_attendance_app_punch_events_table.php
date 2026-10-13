<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Full raw log of every app-originated mark in/out — never summarized away
 * (the "all logs maintained for analytics" requirement). AttendanceDayResolver
 * reduces this to a first-in/last-out pair per employee+day; this table
 * itself keeps every individual event. See ATTENDANCE_APP_PLAN.md §3.3.
 * No real FK constraint on employee_id — `employees` is MyISAM, which
 * InnoDB refuses to reference (error 1824); plain indexed column instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_app_punch_events', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('employee_id');
            $table->enum('event_type', ['in', 'out']);
            $table->enum('method', ['gps', 'wifi', 'manual_app']);
            $table->dateTime('event_at');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('distance_from_mapped_m', 8, 2)->nullable();
            $table->boolean('is_deviation')->default(false);
            $table->text('deviation_reason')->nullable();
            $table->string('wifi_ssid', 100)->nullable();
            $table->string('source_app_version', 20)->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'event_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_app_punch_events');
    }
};
