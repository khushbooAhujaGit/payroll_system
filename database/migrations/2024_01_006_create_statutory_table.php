<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
                // ─────────────────────────────────────────────
        // PF SETTINGS per entity
        // ─────────────────────────────────────────────
        Schema::create('pf_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->decimal('employee_contribution_pct', 5, 2)->default(12.00);
            $table->decimal('employer_contribution_pct', 5, 2)->default(12.00);
            $table->decimal('eps_contribution_pct', 5, 2)->default(8.33);  // employer EPS
            $table->decimal('edli_contribution_pct', 5, 2)->default(0.50); // EDLI
            $table->decimal('admin_charges_pct', 5, 2)->default(0.50);
            $table->boolean('cap_at_wage_limit')->default(true);
            $table->decimal('wage_limit', 10, 2)->default(15000.00); // PF wage cap
            $table->boolean('allow_vpf')->default(true);             // voluntary PF
            $table->timestamps();
        });

        // ─────────────────────────────────────────────
        // ESIC SETTINGS per entity
        // ─────────────────────────────────────────────
        Schema::create('esic_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->decimal('employee_contribution_pct', 5, 2)->default(0.75);
            $table->decimal('employer_contribution_pct', 5, 2)->default(3.25);
            $table->decimal('wage_limit', 10, 2)->default(21000.00); // ESIC eligibility cap
            $table->timestamps();
        });

        // ─────────────────────────────────────────────
        // PROFESSIONAL TAX SLABS  (state-wise)
        // ─────────────────────────────────────────────
        Schema::create('pt_slabs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('state', 100);
            $table->decimal('salary_from', 12, 2);
            $table->decimal('salary_to', 12, 2)->nullable();  // null = no upper limit
            $table->decimal('pt_amount', 10, 2);              // monthly PT
            $table->integer('effective_year')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ─────────────────────────────────────────────
        // LABOUR WELFARE FUND SLABS
        // ─────────────────────────────────────────────
        Schema::create('lwf_slabs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('state', 100);
            $table->decimal('employee_contribution', 10, 2)->default(0);
            $table->decimal('employer_contribution', 10, 2)->default(0);
            $table->enum('frequency', ['monthly', 'half_yearly', 'annual'])->default('annual');
            $table->enum('deduction_month', ['june_december', 'december', 'march', 'other'])->default('june_december');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ─────────────────────────────────────────────
        // TDS / INCOME TAX SLABS
        // ─────────────────────────────────────────────
        Schema::create('tds_slabs', function (Blueprint $table) {
            $table->id();
            $table->integer('financial_year');               // e.g., 2024 = FY 2024-25
            $table->enum('regime', ['old', 'new']);
            $table->decimal('income_from', 12, 2);
            $table->decimal('income_to', 12, 2)->nullable();
            $table->decimal('tax_rate', 5, 2);               // percentage
            $table->timestamps();
        });

        // ─────────────────────────────────────────────
        // INVESTMENT DECLARATION  (Form 12BB)
        // ─────────────────────────────────────────────
        Schema::create('investment_declarations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->integer('financial_year');
            $table->enum('regime', ['old', 'new'])->default('new');
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'financial_year']);
        });

        // HRA-specific declaration
        Schema::create('declaration_hra', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('investment_declaration_id')->nullable();
            $table->string('landlord_name');
            $table->string('landlord_pan')->nullable();
            $table->text('rental_address');
            $table->decimal('monthly_rent', 10, 2);
            $table->date('from_date');
            $table->date('to_date');
            $table->timestamps();
        });

        // Chapter VI A investments (80C, 80D, etc.)
        Schema::create('declaration_investments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('investment_declaration_id')->nullable();
            $table->string('section');                        // 80C, 80D, 80E, 80G, 24B
            $table->string('investment_type');                // PPF, ELSS, LIC, etc.
            $table->decimal('declared_amount', 12, 2)->default(0);
            $table->decimal('approved_amount', 12, 2)->default(0);
            $table->string('proof_path')->nullable();
            $table->enum('proof_status', ['pending', 'approved', 'rejected', 'not_required'])->default('pending');
            $table->timestamps();

            $table->index(['investment_declaration_id', 'section']);
        });

        // ─────────────────────────────────────────────
        // PROOF OF INVESTMENT (POI) WORKFLOW
        // ─────────────────────────────────────────────
        Schema::create('poi_submissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('investment_declaration_id')->nullable();
            $table->unsignedBigInteger('declaration_investment_id')->nullable();
            $table->integer('financial_year');
            $table->string('section');                        // 80C, HRA, etc.
            $table->string('investment_type');
            $table->decimal('claimed_amount', 12, 2)->default(0);
            $table->string('file_path');
            $table->string('file_name');
            $table->text('remarks')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'resubmit'])->default('pending');
            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->text('hr_remarks')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        // ─────────────────────────────────────────────
        // MONTHLY TDS COMPUTATION
        // ─────────────────────────────────────────────
        Schema::create('employee_tds_computation', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->integer('financial_year');
            $table->integer('month');                         // 1–12
            $table->decimal('gross_salary_ytd', 15, 2)->default(0);  // year-to-date
            $table->decimal('declared_investments', 12, 2)->default(0);
            $table->decimal('approved_investments', 12, 2)->default(0);
            $table->decimal('hra_exemption', 12, 2)->default(0);
            $table->decimal('standard_deduction', 10, 2)->default(50000);
            $table->decimal('taxable_income', 15, 2)->default(0);
            $table->decimal('tax_payable_annual', 12, 2)->default(0);
            $table->decimal('tax_paid_ytd', 12, 2)->default(0);
            $table->decimal('monthly_tds', 10, 2)->default(0);
            $table->enum('regime', ['old', 'new'])->default('new');
            $table->timestamps();

            $table->unique(['employee_id', 'financial_year', 'month']);
        });

        // ─────────────────────────────────────────────
        // COMPLIANCE CHALLAN TRACKING
        // ─────────────────────────────────────────────
        Schema::create('compliance_challans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->enum('challan_type', ['pf', 'esic', 'pt', 'tds', 'lwf'])->default('pf');
            $table->integer('year');
            $table->integer('month');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('challan_number')->nullable();
            $table->date('due_date')->nullable();
            $table->date('payment_date')->nullable();
            $table->enum('status', ['pending', 'paid', 'overdue'])->default('pending');
            $table->string('receipt_path')->nullable();
            $table->decimal('penalty', 10, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        // ─────────────────────────────────────────────
        // ECR (Electronic Challan cum Return for PF)
        // ─────────────────────────────────────────────
        Schema::create('pf_ecr_registers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->unsignedBigInteger('payroll_run_id')->nullable();
            $table->integer('year');
            $table->integer('month');
            $table->integer('total_employees')->default(0);
            $table->decimal('total_pf_wages', 15, 2)->default(0);
            $table->decimal('total_employee_pf', 12, 2)->default(0);
            $table->decimal('total_employer_pf', 12, 2)->default(0);
            $table->decimal('total_eps', 12, 2)->default(0);
            $table->decimal('total_edli', 12, 2)->default(0);
            $table->string('ecr_file_path')->nullable();      // generated txt file
            $table->enum('status', ['generated', 'filed', 'pending'])->default('generated');
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('pf_ecr_registers');
        Schema::dropIfExists('compliance_challans');
        Schema::dropIfExists('employee_tds_computation');
        Schema::dropIfExists('poi_submissions');
        Schema::dropIfExists('declaration_investments');
        Schema::dropIfExists('declaration_hra');
        Schema::dropIfExists('investment_declarations');
        Schema::dropIfExists('lwf_slabs');
        Schema::dropIfExists('pt_slabs');
        Schema::dropIfExists('esic_settings');
        Schema::dropIfExists('pf_settings');
        Schema::dropIfExists('tds_slabs');
    }
};
