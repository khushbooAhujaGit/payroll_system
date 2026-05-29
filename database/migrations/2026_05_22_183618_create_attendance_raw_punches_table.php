<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * attendance_raw_punches
 * ─────────────────────────────────────────────────────────────────
 * Stores every raw biometric punch row exactly as it comes from the
 * machine's XLS export — one record per (employee, date, machine).
 *
 * Cross-office scenario:
 *   Employee punched IN at machine 10, OUT at machine 12 →
 *   machine-10 row: first_in=09:55, first_out=NULL
 *   machine-12 row: first_in=NULL,  first_out=19:05
 *   Payroll calculations merge these when needed.
 *
 * Second time zone (second_in / second_out):
 *   If the employee swiped again later on the same machine (e.g.
 *   returned after going out), those extra punches land here.
 * ─────────────────────────────────────────────────────────────────
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_raw_punches', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('employee_id');
            $table->string('bio_id_full', 20);           // e.g. I-35, II-15, III-42
            $table->unsignedSmallInteger('bio_number');   // numeric part  (35, 15, 42)
            $table->string('machine_name', 10);           // 10 | 12 | K

            $table->date('attendance_date');

            // First time-zone punches (primary office swipe)
            $table->time('first_in')->nullable();
            $table->time('first_out')->nullable();

            // Second time-zone punches (same machine, later re-entry)
            $table->time('second_in')->nullable();
            $table->time('second_out')->nullable();

            // Overtime punches
            $table->time('overtime_in')->nullable();
            $table->time('overtime_out')->nullable();

            $table->boolean('is_absent')->default(false);

            $table->string('source_file')->nullable();             // original filename
            $table->unsignedBigInteger('uploaded_by')->nullable(); // user who ran import

            $table->timestamps();

            // One record per employee per day per machine
            $table->unique(
                ['employee_id', 'attendance_date', 'machine_name'],
                'arp_emp_date_machine_unique'
            );

            $table->index(['employee_id', 'attendance_date'], 'arp_emp_date_idx');
            $table->index('attendance_date', 'arp_date_idx');
            $table->index('machine_name',    'arp_machine_idx');

            $table->foreign('employee_id')
                  ->references('id')->on('employees')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_raw_punches');
    }
};
