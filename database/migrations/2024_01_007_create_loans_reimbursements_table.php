<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
                // ─────────────────────────────────────────────
        // LOANS & ADVANCES
        // ─────────────────────────────────────────────
        Schema::create('loan_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('name');                          // Salary Advance, Personal Loan, Festival Advance
            $table->string('code', 20);
            $table->decimal('max_amount', 12, 2)->nullable();
            $table->integer('max_emi')->nullable();          // maximum EMIs allowed
            $table->decimal('interest_rate', 5, 2)->default(0); // 0 = interest free
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_number', 30)->unique();     // LN-2024-0001
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->unsignedBigInteger('loan_type_id')->nullable();
            $table->decimal('loan_amount', 12, 2);
            $table->integer('total_emis');
            $table->decimal('emi_amount', 10, 2);
            $table->decimal('interest_rate', 5, 2)->default(0);
            $table->date('disbursement_date')->nullable();
            $table->date('first_emi_month');                 // YYYY-MM-01
            $table->decimal('outstanding_amount', 12, 2)->default(0);
            $table->enum('status', ['pending', 'approved', 'rejected', 'active', 'closed', 'cancelled'])->default('pending');
            $table->text('purpose')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('loan_installments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_loan_id')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->integer('installment_number');
            $table->integer('emi_year');
            $table->integer('emi_month');
            $table->decimal('principal_amount', 10, 2)->default(0);
            $table->decimal('interest_amount', 10, 2)->default(0);
            $table->decimal('total_emi_amount', 10, 2)->default(0);
            $table->decimal('outstanding_before', 12, 2)->default(0);
            $table->decimal('outstanding_after', 12, 2)->default(0);
            $table->enum('status', ['pending', 'deducted', 'waived'])->default('pending');
            $table->unsignedBigInteger('payroll_run_id')->nullable();
            $table->timestamps();

            $table->unique(['employee_loan_id', 'installment_number']);
        });

        // ─────────────────────────────────────────────
        // REIMBURSEMENTS & EXPENSES
        // ─────────────────────────────────────────────
        Schema::create('reimbursement_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('name');                          // Travel, Fuel, Mobile, Food
            $table->string('code', 20);
            $table->decimal('monthly_limit', 10, 2)->nullable();
            $table->decimal('annual_limit', 10, 2)->nullable();
            $table->boolean('is_taxable')->default(false);
            $table->boolean('requires_bill')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('reimbursement_claims', function (Blueprint $table) {
            $table->id();
            $table->string('claim_number', 30)->unique();    // RC-2024-0001
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->integer('year');
            $table->integer('month');
            $table->decimal('total_claimed', 12, 2)->default(0);
            $table->decimal('total_approved', 12, 2)->default(0);
            $table->enum('status', ['draft', 'submitted', 'pending', 'approved', 'rejected', 'paid'])->default('draft');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('payroll_run_id')->nullable(); // paid with payroll
            $table->timestamps();
        });

        Schema::create('reimbursement_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reimbursement_claim_id')->nullable();
            $table->unsignedBigInteger('reimbursement_type_id')->nullable();
            $table->date('expense_date');
            $table->text('description');
            $table->decimal('claimed_amount', 10, 2);
            $table->decimal('approved_amount', 10, 2)->nullable();
            $table->string('bill_path')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });

        // ─────────────────────────────────────────────
        // FULL & FINAL SETTLEMENT
        // ─────────────────────────────────────────────
        Schema::create('full_final_settlements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->unsignedBigInteger('exit_request_id')->nullable();
            $table->date('last_working_date');
            $table->integer('settlement_year');
            $table->integer('settlement_month');

            // Components
            $table->integer('payable_days')->default(0);
            $table->decimal('salary_payable', 12, 2)->default(0);       // for worked days
            $table->decimal('leave_encashment', 12, 2)->default(0);
            $table->decimal('gratuity_amount', 12, 2)->default(0);
            $table->decimal('notice_pay_recovery', 12, 2)->default(0);  // negative
            $table->decimal('notice_pay_payable', 12, 2)->default(0);   // if employer waives notice
            $table->decimal('bonus_payable', 12, 2)->default(0);
            $table->decimal('bonus_recovery', 12, 2)->default(0);
            $table->decimal('loan_recovery', 12, 2)->default(0);        // outstanding loan
            $table->decimal('advance_recovery', 12, 2)->default(0);
            $table->decimal('other_deductions', 12, 2)->default(0);
            $table->decimal('other_payments', 12, 2)->default(0);
            $table->decimal('tds_deduction', 12, 2)->default(0);

            $table->decimal('total_payable', 12, 2)->default(0);
            $table->decimal('total_recoverable', 12, 2)->default(0);
            $table->decimal('net_payable', 12, 2)->default(0);

            $table->text('remarks')->nullable();
            $table->enum('status', ['draft', 'calculated', 'approved', 'paid'])->default('draft');
            $table->date('payment_date')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();
        });

        // ─────────────────────────────────────────────
        // GRATUITY PROVISIONING
        // ─────────────────────────────────────────────
        Schema::create('gratuity_provisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->integer('year');
            $table->integer('month');
            $table->decimal('basic_salary', 12, 2)->default(0);
            $table->integer('service_years')->default(0);
            $table->decimal('gratuity_accrued_this_month', 10, 2)->default(0);
            $table->decimal('cumulative_provision', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'year', 'month']);
        });

        // ─────────────────────────────────────────────
        // BANK FILE GENERATION
        // ─────────────────────────────────────────────
        Schema::create('bank_transfer_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payroll_run_id')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('bank_name');                     // HDFC, ICICI, SBI, Axis
            $table->string('file_format');                   // NEFT, RTGS, NACH
            $table->integer('total_records')->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('file_path')->nullable();
            $table->string('reference_number')->nullable();
            $table->enum('status', ['generated', 'submitted', 'processed', 'failed'])->default('generated');
            $table->timestamps();
        });

        // ─────────────────────────────────────────────
        // CONTRACTOR / VENDOR PAYROLL
        // ─────────────────────────────────────────────
        Schema::create('contractors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('name');
            $table->string('contractor_id', 30)->unique();   // CTR-0001
            $table->string('pan', 10)->nullable();
            $table->string('gstin', 15)->nullable();
            $table->string('mobile', 15)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('ifsc_code', 11)->nullable();
            $table->enum('contractor_type', ['individual', 'company'])->default('individual');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('contractor_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contractor_id')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('invoice_number', 50);
            $table->date('invoice_date');
            $table->integer('year');
            $table->integer('month');
            $table->decimal('invoice_amount', 12, 2)->default(0);
            $table->decimal('tds_rate', 5, 2)->default(10.00); // 194C / 194J
            $table->decimal('tds_amount', 10, 2)->default(0);
            $table->decimal('net_payable', 12, 2)->default(0);
            $table->text('description')->nullable();
            $table->string('invoice_path')->nullable();
            $table->enum('status', ['pending', 'approved', 'paid', 'rejected'])->default('pending');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->date('payment_date')->nullable();
            $table->timestamps();
        });

        // ─────────────────────────────────────────────
        // BONUS & INCENTIVES
        // ─────────────────────────────────────────────
        Schema::create('bonus_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('name');
            $table->enum('bonus_type', ['statutory', 'performance', 'festival', 'sales', 'referral', 'custom'])->default('custom');
            $table->decimal('amount_or_pct', 10, 2)->default(0);
            $table->enum('calculation_basis', ['fixed', 'percentage_basic', 'percentage_gross', 'percentage_ctc'])->default('fixed');
            $table->enum('frequency', ['monthly', 'quarterly', 'half_yearly', 'annual', 'on_demand'])->default('annual');
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_in_payroll')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_bonus', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->unsignedBigInteger('bonus_rule_id')->nullable();
            $table->string('bonus_name');
            $table->decimal('amount', 12, 2)->default(0);
            $table->integer('year');
            $table->integer('month');
            $table->boolean('is_deferred')->default(false);
            $table->date('deferred_till')->nullable();
            $table->text('remarks')->nullable();
            $table->enum('status', ['pending', 'approved', 'paid', 'deferred'])->default('pending');
            $table->unsignedBigInteger('payroll_run_id')->nullable();
            $table->timestamps();
        });

        // ─────────────────────────────────────────────
        // EMPLOYEE HELPDESK / TICKETING
        // ─────────────────────────────────────────────
        Schema::create('helpdesk_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('name');                          // Payslip, Leave, IT Support, HR Query
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->enum('assigned_team', ['hr', 'payroll', 'it', 'admin', 'finance'])->default('hr');
            $table->integer('sla_hours')->default(48);       // SLA in hours
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('helpdesk_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 20)->unique();   // TKT-2024-0001
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('subject');
            $table->text('description');
            $table->string('attachment_path')->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', ['open', 'in_progress', 'pending_employee', 'resolved', 'closed', 'reopened'])->default('open');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->timestamp('due_by')->nullable();          // SLA deadline
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->integer('rating')->nullable();            // 1–5 satisfaction
            $table->text('closure_remarks')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['assigned_to', 'status']);
        });

        Schema::create('helpdesk_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('comment');
            $table->string('attachment_path')->nullable();
            $table->boolean('is_internal')->default(false);  // internal note / visible to employee
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_comments');
        Schema::dropIfExists('helpdesk_tickets');
        Schema::dropIfExists('helpdesk_categories');
        Schema::dropIfExists('employee_bonus');
        Schema::dropIfExists('bonus_rules');
        Schema::dropIfExists('contractor_invoices');
        Schema::dropIfExists('contractors');
        Schema::dropIfExists('bank_transfer_files');
        Schema::dropIfExists('gratuity_provisions');
        Schema::dropIfExists('full_final_settlements');
        Schema::dropIfExists('reimbursement_items');
        Schema::dropIfExists('reimbursement_claims');
        Schema::dropIfExists('reimbursement_types');
        Schema::dropIfExists('loan_installments');
        Schema::dropIfExists('employee_loans');
        Schema::dropIfExists('loan_types');
    }
};
