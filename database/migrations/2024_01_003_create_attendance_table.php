<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
                // ─────────────────────────────────────────────
        // SHIFTS
        // ─────────────────────────────────────────────
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('name');                           // Morning, Night, General
            $table->string('code', 20)->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_night_shift')->default(false);
            $table->integer('working_hours')->default(8);     // in hours
            $table->integer('grace_period_minutes')->default(0);
            $table->integer('late_mark_after_minutes')->default(0); // minutes after grace
            $table->integer('half_day_after_minutes')->default(0);  // late by x = half day
            $table->boolean('is_flexible')->default(false);
            $table->time('flexible_start')->nullable();
            $table->time('flexible_end')->nullable();
            $table->integer('min_working_hours_flexible')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ─────────────────────────────────────────────
        // EMPLOYEE SHIFT ASSIGNMENT
        // ─────────────────────────────────────────────
        Schema::create('employee_shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->index(['employee_id', 'is_current']);
        });

        // ─────────────────────────────────────────────
        // WEEKLY ROSTER  (who works which days)
        // ─────────────────────────────────────────────
        Schema::create('rosters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('name');
            $table->json('working_days');   // ["Mon","Tue","Wed","Thu","Fri"]
            $table->timestamps();
        });

        Schema::create('employee_rosters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->unsignedBigInteger('roster_id')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();
        });

        // ─────────────────────────────────────────────
        // ATTENDANCE
        // ─────────────────────────────────────────────
        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->date('attendance_date');
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->integer('working_minutes')->default(0);
            $table->integer('overtime_minutes')->default(0);
            $table->integer('late_by_minutes')->default(0);
            $table->integer('early_exit_minutes')->default(0);
            $table->enum('status', [
                'present', 'absent', 'half_day', 'late', 'leave', 'holiday',
                'weekly_off', 'work_from_home', 'on_duty', 'tour', 'lop'
            ])->default('absent');
            $table->enum('work_type', ['wfo', 'wfh', 'client_location', 'hybrid'])->nullable();
            $table->string('source', 30)->default('manual'); // manual/biometric/excel/api
            $table->boolean('is_regularized')->default(false);
            $table->boolean('is_locked')->default(false);     // after payroll lock
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'attendance_date']);
            $table->index(['attendance_date']);
            $table->index(['employee_id', 'status']);
        });

        // ─────────────────────────────────────────────
        // ATTENDANCE REGULARIZATION REQUESTS
        // ─────────────────────────────────────────────
        Schema::create('attendance_regularizations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->unsignedBigInteger('attendance_id')->nullable();
            $table->date('attendance_date');
            $table->time('requested_check_in')->nullable();
            $table->time('requested_check_out')->nullable();
            $table->enum('reason', [
                'forgot_punch', 'system_error', 'client_visit', 'wfh', 'travel', 'other'
            ])->default('other');
            $table->text('remarks')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_remarks')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });

        // ─────────────────────────────────────────────
        // OVERTIME
        // ─────────────────────────────────────────────
        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->date('overtime_date');
            $table->integer('overtime_minutes');
            $table->decimal('overtime_amount', 10, 2)->default(0);
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'paid'])->default('pending');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();
        });

        // ─────────────────────────────────────────────
        // ATTENDANCE UPLOAD LOGS  (for Excel upload tracking)
        // ─────────────────────────────────────────────
        Schema::create('attendance_upload_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('file_name');
            $table->string('file_path');
            $table->integer('total_rows')->default(0);
            $table->integer('success_rows')->default(0);
            $table->integer('error_rows')->default(0);
            $table->json('errors')->nullable();               // array of {row, error}
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_upload_logs');
        Schema::dropIfExists('overtime_requests');
        Schema::dropIfExists('attendance_regularizations');
        Schema::dropIfExists('attendance');
        Schema::dropIfExists('employee_rosters');
        Schema::dropIfExists('rosters');
        Schema::dropIfExists('employee_shifts');
        Schema::dropIfExists('shifts');
    }
};
