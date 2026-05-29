<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendance_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('attendance_date');

            // Admin-set per day: null = not yet reviewed
            // approved = punch accepted for payroll
            // not_approved = punch rejected → treated as absent (LOP) for payroll
            $table->enum('punch_in_approval',  ['approved', 'not_approved'])->nullable()->default(null);
            $table->enum('punch_out_approval', ['approved', 'not_approved'])->nullable()->default(null);

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'attendance_date']);
            $table->index('attendance_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_approvals');
    }
};
