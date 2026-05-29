<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Simplify attendance_raw_punches:
 * Replace six granular time columns with a single check_in + check_out pair.
 *
 * Each machine XLS row represents one working session per day.
 * Either the "First time zone" or the "Overtime" columns carry In/Out —
 * always one In and one Out, never meaningfully split across zones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_raw_punches', function (Blueprint $table) {
            $table->dropColumn([
                'first_in', 'first_out',
                'second_in', 'second_out',
                'overtime_in', 'overtime_out',
            ]);

            $table->time('check_in')->nullable()->after('attendance_date');
            $table->time('check_out')->nullable()->after('check_in');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_raw_punches', function (Blueprint $table) {
            $table->dropColumn(['check_in', 'check_out']);

            $table->time('first_in')->nullable()->after('attendance_date');
            $table->time('first_out')->nullable()->after('first_in');
            $table->time('second_in')->nullable()->after('first_out');
            $table->time('second_out')->nullable()->after('second_in');
            $table->time('overtime_in')->nullable()->after('second_out');
            $table->time('overtime_out')->nullable()->after('overtime_in');
        });
    }
};
