<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
                // ─────────────────────────────────────────────
        // SALARY COMPONENTS  (master list)
        // ─────────────────────────────────────────────
        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('name');
            $table->string('code', 30)->unique();
            $table->enum('component_type', ['earning', 'deduction', 'employer_contribution', 'reimbursement'])->default('earning');
            $table->enum('calculation_type', ['fixed', 'percentage', 'formula', 'slab'])->default('fixed');
            $table->string('percentage_of')->nullable();        // code of component it's % of
            $table->decimal('percentage_value', 5, 2)->nullable();
            $table->text('formula')->nullable();                // custom formula expression
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_pf_applicable')->default(false);
            $table->boolean('is_esic_applicable')->default(false);
            $table->boolean('is_pt_applicable')->default(false);
            $table->boolean('is_lwf_applicable')->default(false);
            $table->boolean('is_in_ctc')->default(true);
            $table->boolean('is_in_gross')->default(true);
            $table->boolean('show_in_payslip')->default(true);
            $table->boolean('is_arrear_applicable')->default(true);
            $table->enum('frequency', ['monthly', 'quarterly', 'half_yearly', 'annual', 'on_demand'])->default('monthly');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ─────────────────────────────────────────────
        // SALARY STRUCTURES  (template assigned to grades/employees)
        // ─────────────────────────────────────────────
        Schema::create('salary_structures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('salary_structure_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('salary_structure_id')->nullable();
            $table->unsignedBigInteger('salary_component_id')->nullable();
            $table->enum('calculation_override', ['fixed', 'percentage', 'formula', 'inherit'])->default('inherit');
            $table->decimal('fixed_value', 12, 2)->nullable();
            $table->decimal('percentage_value', 5, 2)->nullable();
            $table->string('percentage_of')->nullable();
            $table->text('formula')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['salary_structure_id', 'salary_component_id'], 'ssc_unique');
        });

        // ─────────────────────────────────────────────
        // EMPLOYEE SALARY  (versioned - never overwrite)
        // ─────────────────────────────────────────────
        Schema::create('employee_salaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->unsignedBigInteger('salary_structure_id')->nullable();
            $table->decimal('ctc_annual', 15, 2)->default(0);
            $table->decimal('ctc_monthly', 15, 2)->default(0);
            $table->decimal('gross_monthly', 15, 2)->default(0);
            $table->decimal('net_monthly', 15, 2)->default(0);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_current')->default(true);
            $table->enum('revision_type', ['joining', 'increment', 'promotion', 'correction', 'restructure'])->default('joining');
            $table->text('remarks')->nullable();
            $table->string('increment_letter_path')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'is_current']);
            $table->index(['effective_from']);
        });

        // Component-wise breakdown of each salary version
        Schema::create('employee_salary_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_salary_id')->nullable();
            $table->unsignedBigInteger('salary_component_id')->nullable();
            $table->decimal('monthly_amount', 12, 2)->default(0);
            $table->decimal('annual_amount', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['employee_salary_id', 'salary_component_id'], 'esc_unique');
        });

        // ─────────────────────────────────────────────
        // SALARY REVISION WORKFLOW
        // ─────────────────────────────────────────────
        Schema::create('salary_revisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->decimal('old_ctc', 15, 2)->default(0);
            $table->decimal('new_ctc', 15, 2)->default(0);
            $table->decimal('increment_amount', 15, 2)->default(0);
            $table->decimal('increment_percentage', 5, 2)->default(0);
            $table->date('effective_date');
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'rejected', 'implemented'])->default('draft');
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('employee_salary_id')->nullable(); // linked after approval
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        // ─────────────────────────────────────────────
        // PAYROLL PERIODS / CYCLES
        // ─────────────────────────────────────────────
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->integer('year');
            $table->integer('month');                         // 1–12
            $table->date('period_from');
            $table->date('period_to');
            $table->date('attendance_lock_date')->nullable();
            $table->date('payroll_run_date')->nullable();
            $table->date('payment_date')->nullable();
            $table->enum('status', ['open', 'attendance_locked', 'processing', 'processed', 'finalized', 'paid'])->default('open');
            $table->boolean('is_locked')->default(false);     // full lock after finalization
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->timestamps();

            $table->unique(['entity_id', 'year', 'month']);
        });

        // ─────────────────────────────────────────────
        // PAYROLL RUNS
        // ─────────────────────────────────────────────
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payroll_period_id')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->enum('run_type', ['regular', 'off_cycle', 'supplementary', 'revision', 'contractor'])->default('regular');
            $table->string('run_name')->nullable();
            $table->integer('total_employees')->default(0);
            $table->decimal('total_gross', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('total_net', 15, 2)->default(0);
            $table->decimal('total_employer_contribution', 15, 2)->default(0);
            $table->enum('status', ['draft', 'processing', 'calculated', 'approved', 'finalized', 'paid', 'cancelled'])->default('draft');
            $table->boolean('is_editable')->default(true);    // set false after finalization
            $table->timestamp('calculated_at')->nullable();
            $table->unsignedBigInteger('calculated_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        // ─────────────────────────────────────────────
        // PAYROLL RUN - EMPLOYEE RECORDS
        // ─────────────────────────────────────────────
        Schema::create('payroll_employee_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payroll_run_id')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();

            // Attendance summary (pulled from attendance)
            $table->integer('working_days')->default(0);      // total working days in period
            $table->integer('present_days')->default(0);
            $table->integer('absent_days')->default(0);
            $table->decimal('leave_days', 5, 2)->default(0);
            $table->decimal('lop_days', 5, 2)->default(0);
            $table->integer('holiday_days')->default(0);
            $table->integer('weekly_off_days')->default(0);
            $table->integer('overtime_minutes')->default(0);

            // Payable days
            $table->decimal('payable_days', 5, 2)->default(0);

            // Salary amounts (computed)
            $table->decimal('gross_salary', 15, 2)->default(0);
            $table->decimal('total_earnings', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('total_employer_contribution', 15, 2)->default(0);
            $table->decimal('net_salary', 15, 2)->default(0);

            // Individual statutory deductions for quick reporting
            $table->decimal('pf_employee', 10, 2)->default(0);
            $table->decimal('pf_employer', 10, 2)->default(0);
            $table->decimal('esic_employee', 10, 2)->default(0);
            $table->decimal('esic_employer', 10, 2)->default(0);
            $table->decimal('pt_amount', 10, 2)->default(0);
            $table->decimal('lwf_employee', 10, 2)->default(0);
            $table->decimal('lwf_employer', 10, 2)->default(0);
            $table->decimal('tds_amount', 10, 2)->default(0);
            $table->decimal('loan_deduction', 10, 2)->default(0);

            // Arrears
            $table->decimal('arrear_amount', 10, 2)->default(0);

            // Admin override flag
            $table->boolean('is_manually_edited')->default(false);
            $table->decimal('manual_net_override')->nullable();  // admin edited final net
            $table->text('manual_edit_reason')->nullable();
            $table->unsignedBigInteger('manually_edited_by')->nullable();
            $table->timestamp('manually_edited_at')->nullable();

            // Status
            $table->enum('status', ['calculated', 'on_hold', 'blocked', 'paid'])->default('calculated');
            $table->boolean('is_blocked')->default(false);       // zero payroll / blocked
            $table->text('block_reason')->nullable();

            $table->string('payslip_path')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
            $table->index(['employee_id']);
        });

        // Component-wise breakdown per employee per payroll run
        Schema::create('payroll_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payroll_employee_record_id')->nullable();
            $table->unsignedBigInteger('salary_component_id')->nullable();
            $table->decimal('computed_amount', 12, 2)->default(0);
            $table->decimal('override_amount', 12, 2)->nullable();  // manual override
            $table->decimal('final_amount', 12, 2)->default(0);     // override ?? computed
            $table->boolean('is_overridden')->default(false);
            $table->string('override_reason')->nullable();
            $table->timestamps();

            $table->unique(['payroll_employee_record_id', 'salary_component_id'], 'prc_unique');
        });

        // ─────────────────────────────────────────────
        // PAYROLL RECONCILIATION  (month-over-month diff)
        // ─────────────────────────────────────────────
        Schema::create('payroll_reconciliation', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payroll_run_id')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->decimal('prev_net', 12, 2)->default(0);
            $table->decimal('curr_net', 12, 2)->default(0);
            $table->decimal('net_diff', 12, 2)->default(0);
            $table->json('component_wise_diff')->nullable();  // detailed component comparison
            $table->json('reasons')->nullable();              // auto-detected reasons for diff
            $table->timestamps();

            $table->index(['payroll_run_id']);
        });

        // ─────────────────────────────────────────────
        // RETRO / ARREARS
        // ─────────────────────────────────────────────
        Schema::create('payroll_arrears', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->unsignedBigInteger('payroll_run_id')->nullable();  // when paid
            $table->integer('from_year');
            $table->integer('from_month');
            $table->integer('to_year');
            $table->integer('to_month');
            $table->decimal('arrear_amount', 12, 2)->default(0);
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'included', 'paid'])->default('pending');
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_arrears');
        Schema::dropIfExists('payroll_reconciliation');
        Schema::dropIfExists('payroll_components');
        Schema::dropIfExists('payroll_employee_records');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('salary_revisions');
        Schema::dropIfExists('employee_salary_components');
        Schema::dropIfExists('employee_salaries');
        Schema::dropIfExists('salary_structure_components');
        Schema::dropIfExists('salary_structures');
        Schema::dropIfExists('salary_components');
    }
};
