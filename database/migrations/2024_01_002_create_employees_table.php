<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
                // ─────────────────────────────────────────────
        // EMPLOYEES MASTER
        // ─────────────────────────────────────────────
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('designation_id')->nullable();
            $table->unsignedBigInteger('grade_id')->nullable();
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->unsignedBigInteger('reporting_manager_id')->nullable();
            $table->unsignedBigInteger('holiday_calendar_id')->nullable();

            // Unique Employee ID
            $table->string('employee_id', 30)->unique();  // auto-generated: ENT001-0001
            $table->string('badge_number', 30)->nullable()->unique();

            // Personal Details
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('gender', 20)->nullable();     // Male/Female/Other
            $table->date('date_of_birth')->nullable();
            $table->string('blood_group', 5)->nullable();
            $table->string('marital_status', 20)->nullable();
            $table->integer('number_of_dependents')->default(0);
            $table->string('nationality', 50)->default('Indian');
            $table->string('religion', 50)->nullable();
            $table->string('caste_category', 30)->nullable(); // General/OBC/SC/ST
            $table->boolean('is_physically_handicapped')->default(false);
            $table->string('photo')->nullable();

            // Contact
            $table->string('personal_email')->nullable();
            $table->string('official_email')->nullable();
            $table->string('mobile', 15)->nullable();
            $table->string('alternate_phone', 15)->nullable();

            // Address
            $table->text('current_address')->nullable();
            $table->string('current_city', 100)->nullable();
            $table->string('current_state', 100)->nullable();
            $table->string('current_pincode', 10)->nullable();
            $table->text('permanent_address')->nullable();
            $table->string('permanent_city', 100)->nullable();
            $table->string('permanent_state', 100)->nullable();
            $table->string('permanent_pincode', 10)->nullable();

            // Employment Details
            $table->date('date_of_joining');
            $table->date('date_of_confirmation')->nullable();
            $table->integer('probation_period_months')->default(6);
            $table->date('probation_end_date')->nullable();
            $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'intern', 'consultant', 'retainer'])->default('full_time');
            $table->enum('work_type', ['wfo', 'wfh', 'hybrid', 'client_location'])->default('wfo');
            $table->enum('employee_status', ['active', 'inactive', 'on_notice', 'absconding', 'suspended', 'on_leave'])->default('active');

            // Payroll-related
            $table->boolean('is_payroll_active')->default(true);
            $table->boolean('is_pf_applicable')->default(true);
            $table->boolean('is_esic_applicable')->default(true);
            $table->boolean('is_pt_applicable')->default(true);
            $table->boolean('is_lwf_applicable')->default(true);
            $table->boolean('is_tds_applicable')->default(true);
            $table->enum('tds_regime', ['old', 'new'])->default('new');

            // Emergency Contact
            $table->string('emergency_contact_name', 100)->nullable();
            $table->string('emergency_contact_phone', 15)->nullable();
            $table->string('emergency_contact_relation', 50)->nullable();

            // Meta
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['entity_id', 'employee_status']);
            $table->index(['entity_id', 'department_id']);
            $table->index(['reporting_manager_id']);
        });

        // ─────────────────────────────────────────────
        // EMPLOYEE DOCUMENTS
        // ─────────────────────────────────────────────
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->enum('document_type', [
                'pan', 'aadhaar', 'passport', 'driving_license', 'voter_id',
                'offer_letter', 'appointment_letter', 'increment_letter',
                'nda', 'agreement', 'educational_certificate', 'experience_letter',
                'bank_proof', 'address_proof', 'photo', 'other'
            ]);
            $table->string('document_number')->nullable();
            $table->string('document_name')->nullable();       // friendly name
            $table->string('file_path');                       // S3 / local path
            $table->string('file_name');
            $table->string('file_type', 20)->nullable();       // pdf, jpg, png
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();           // for alerts
            $table->boolean('is_verified')->default(false);
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'document_type']);
            $table->index(['expiry_date']);
        });

        // ─────────────────────────────────────────────
        // EMPLOYEE BANK DETAILS
        // ─────────────────────────────────────────────
        Schema::create('employee_bank_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('bank_name');
            $table->string('branch_name')->nullable();
            $table->string('account_number');
            $table->string('account_type', 30)->default('savings'); // savings/current
            $table->string('ifsc_code', 11);
            $table->string('micr_code', 9)->nullable();
            $table->string('account_holder_name');
            $table->boolean('is_primary')->default(true);       // primary for salary credit
            $table->boolean('is_verified')->default(false);
            $table->string('cancelled_cheque')->nullable();     // file path
            $table->timestamps();

            $table->index(['employee_id']);
        });

        // ─────────────────────────────────────────────
        // STATUTORY IDENTIFIERS  (PF, UAN, ESIC etc.)
        // ─────────────────────────────────────────────
        Schema::create('employee_statutory_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('uan_number', 12)->nullable();       // Universal Account Number
            $table->string('pf_number')->nullable();
            $table->string('esic_number', 17)->nullable();
            $table->string('pan_number', 10)->nullable();
            $table->string('aadhaar_number', 12)->nullable();
            $table->string('pt_number')->nullable();
            $table->string('nps_pran')->nullable();             // NPS Permanent Retirement Account Number
            $table->timestamps();
        });

        // ─────────────────────────────────────────────
        // EMPLOYEE LIFECYCLE / HISTORY
        // ─────────────────────────────────────────────
        Schema::create('employee_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->enum('event_type', [
                'joining', 'confirmation', 'promotion', 'demotion', 'transfer',
                'increment', 'role_change', 'department_change', 'grade_change',
                'status_change', 'exit', 'rehire'
            ]);
            $table->date('effective_date');
            $table->json('old_values')->nullable();             // snapshot before change
            $table->json('new_values')->nullable();             // snapshot after change
            $table->text('remarks')->nullable();
            $table->string('letter_path')->nullable();          // generated letter
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'event_type']);
            $table->index(['effective_date']);
        });

        // ─────────────────────────────────────────────
        // ONBOARDING CHECKLISTS
        // ─────────────────────────────────────────────
        Schema::create('onboarding_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('name');
            $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'intern', 'consultant', 'all'])->default('all');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('onboarding_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('onboarding_template_id')->nullable();
            $table->string('task_name');
            $table->text('description')->nullable();
            $table->enum('assigned_to', ['hr', 'it', 'finance', 'manager', 'employee'])->default('hr');
            $table->integer('due_days')->default(1);            // due in N days after joining
            $table->boolean('is_mandatory')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('employee_onboarding', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->unsignedBigInteger('onboarding_task_id')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'skipped'])->default('pending');
            $table->date('due_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });

        // ─────────────────────────────────────────────
        // EXIT MANAGEMENT
        // ─────────────────────────────────────────────
        Schema::create('exit_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->enum('exit_type', ['resignation', 'termination', 'retirement', 'end_of_contract', 'absconding', 'death'])->default('resignation');
            $table->date('notice_date');
            $table->date('last_working_date');
            $table->integer('notice_period_days')->default(0);
            $table->integer('actual_notice_days')->default(0);
            $table->text('exit_reason')->nullable();
            $table->enum('status', ['initiated', 'approved', 'rejected', 'withdrawn', 'completed'])->default('initiated');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        // ─────────────────────────────────────────────
        // EDUCATION & EXPERIENCE  (optional but useful)
        // ─────────────────────────────────────────────
        Schema::create('employee_education', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('degree');
            $table->string('specialization')->nullable();
            $table->string('institution');
            $table->integer('pass_year')->nullable();
            $table->string('percentage')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_experience', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('company_name');
            $table->string('designation')->nullable();
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->boolean('is_current')->default(false);
            $table->text('responsibilities')->nullable();
            $table->string('experience_letter')->nullable(); // file path
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('employee_experience');
        Schema::dropIfExists('employee_education');
        Schema::dropIfExists('exit_requests');
        Schema::dropIfExists('employee_onboarding');
        Schema::dropIfExists('onboarding_tasks');
        Schema::dropIfExists('onboarding_templates');
        Schema::dropIfExists('employee_history');
        Schema::dropIfExists('employee_statutory_details');
        Schema::dropIfExists('employee_bank_details');
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('employees');
    }
};
