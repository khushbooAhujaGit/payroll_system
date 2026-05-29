<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * leave_tracker_records
 * ─────────────────────
 * Stores leave data imported from the "Mark Leave" sheet of the Leave Tracker xlsm.
 * Separate from leave_applications (which are workflow-based leave requests).
 *
 * One row per leave occurrence as recorded in the tracker file.
 * leave_ref_id is the ID from column A of the sheet — used as the natural key
 * for idempotent re-imports (ON DUPLICATE KEY UPDATE).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_tracker_records', function (Blueprint $table) {
            $table->id();

            // Original ID from the Leave Tracker sheet (e.g. "20211800745355")
            $table->string('leave_ref_id', 60)->unique();

            // Resolved employee — nullable because some EMP IDs in the sheet
            // use non-standard formats (e.g. "EMP23032O") and can't be matched.
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();

            // Employee data exactly as it appears in the tracker sheet
            $table->string('sheet_emp_id', 30)->nullable();   // raw EMP ID column value
            $table->string('employee_name', 120)->nullable();

            $table->date('leave_date');
            $table->string('leave_type', 15);                 // CL, SL, WFH, HSL …
            $table->string('team_leader_name', 120)->nullable();
            $table->string('reason_type', 120)->nullable();
            $table->text('reason')->nullable();

            // Audit
            $table->string('source_file', 255)->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();

            // Composite index for common queries
            $table->index(['employee_id', 'leave_date']);
            $table->index(['leave_type', 'leave_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_tracker_records');
    }
};
