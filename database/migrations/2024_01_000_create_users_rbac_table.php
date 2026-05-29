<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─────────────────────────────────────────────
        // USERS
        // ─────────────────────────────────────────────
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable(); // FK added after companies table created
            $table->unsignedBigInteger('employee_id')->nullable(); // FK added after employees table created
            $table->string('name');
            // $table->string('email')->unique();
            $table->string('email', 150)->unique();
            $table->string('password');
            $table->string('mobile', 15)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('avatar')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->string('timezone')->default('Asia/Kolkata');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        // ─────────────────────────────────────────────
        // ROLES
        // ─────────────────────────────────────────────
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable(); // FK added after companies table created // null = system role
            // $table->string('name');                           // Super Admin, HR Manager, Payroll Admin, Manager, Employee
            // $table->string('slug')->unique();                 // super_admin, hr_manager
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_system_role')->default(false); // built-in, cannot delete
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ─────────────────────────────────────────────
        // PERMISSIONS  (feature-level)
        // ─────────────────────────────────────────────
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            // $table->string('module');                         // employee, payroll, attendance
            // $table->string('name');
            // $table->string('slug')->unique();                 // employee.create, payroll.run
            // $table->string('description')->nullable();
            $table->string('module', 100);
            $table->string('name', 150);
            $table->string('slug', 150)->unique();
            $table->string('description', 255)->nullable();
            $table->timestamps();

            $table->index(['module']);
        });

        // ─────────────────────────────────────────────
        // ROLE — PERMISSION MAP
        // ─────────────────────────────────────────────
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->unsignedBigInteger('permission_id')->nullable();
            $table->boolean('can_view')->default(false);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_edit')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->boolean('can_export')->default(false);
            $table->boolean('can_approve')->default(false);
            $table->timestamps();

            $table->unique(['role_id', 'permission_id']);
        });

        // ─────────────────────────────────────────────
        // USER — ROLE ASSIGNMENTS
        // ─────────────────────────────────────────────
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable(); // FK added after entities table created
            $table->unsignedBigInteger('department_id')->nullable(); // FK added after departments table created
            $table->timestamps();

            $table->unique(['user_id', 'role_id', 'entity_id']);
        });

        // ─────────────────────────────────────────────
        // PERSONAL ACCESS TOKENS (Sanctum)
        // ─────────────────────────────────────────────
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // ─────────────────────────────────────────────
        // AUDIT LOGS
        // ─────────────────────────────────────────────
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            // $table->string('action');                         // created, updated, deleted, viewed
            // $table->string('module');                         // employee, payroll, leave
            // $table->string('model_type')->nullable();         // App\Models\Employee
            $table->string('action', 50);
            $table->string('module', 100);
            $table->string('model_type', 150)->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            // $table->index(['module', 'model_type', 'model_id']);
            $table->index(['module']);
            $table->index(['model_type']);
            $table->index(['model_id']);
            $table->index(['user_id']);
            $table->index(['created_at']);
        });

        // ─────────────────────────────────────────────
        // NOTIFICATIONS
        // ─────────────────────────────────────────────
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // ─────────────────────────────────────────────
        // UNIVERSAL EXCEL UPLOAD LOG  (all modules)
        // ─────────────────────────────────────────────
        Schema::create('excel_upload_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->enum('module', [
                'employee',
                'attendance',
                'leave_balance',
                'salary_revision',
                'loan',
                'reimbursement',
                'bonus',
                'contractor',
                'holiday',
                'bio_id',
                'other'
            ]);
            $table->string('file_name');
            $table->string('file_path');
            $table->integer('total_rows')->default(0);
            $table->integer('success_rows')->default(0);
            $table->integer('error_rows')->default(0);
            $table->json('errors')->nullable();               // [{row: 3, message: "Invalid PAN"}]
            $table->json('warnings')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->json('meta')->nullable();                 // any extra info like period, year, month
            $table->timestamps();

            $table->index(['module', 'status']);
        });

        // ─────────────────────────────────────────────
        // APPROVAL WORKFLOWS
        // ─────────────────────────────────────────────
        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('module');                        // leave, reimbursement, loan, salary_revision
            $table->string('name');
            $table->integer('levels')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('approval_workflow_levels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('approval_workflow_id')->nullable();
            $table->integer('level');
            $table->enum('approver_type', ['reporting_manager', 'role', 'specific_user', 'department_head'])->default('reporting_manager');
            $table->unsignedBigInteger('approver_role_id')->nullable();
            $table->unsignedBigInteger('approver_user_id')->nullable();
            $table->boolean('is_mandatory')->default(true);
            $table->timestamps();
        });

        // ─────────────────────────────────────────────
        // SYSTEM SETTINGS
        // ─────────────────────────────────────────────
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id')->nullable(); // null = global
            // $table->string('key');
            // $table->text('value')->nullable();
            $table->string('key', 150);
            $table->string('group', 100)->default('general');
            $table->timestamps();

            $table->unique(['entity_id', 'key']);
        });

        // ─────────────────────────────────────────────
        // COMMUNICATION LOGS
        // ─────────────────────────────────────────────
        Schema::create('communication_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->enum('channel', ['email', 'sms', 'notification'])->default('email');
            $table->string('subject')->nullable();
            $table->text('message');
            $table->json('recipients');                       // array of user_ids or emails
            $table->enum('type', ['payslip', 'announcement', 'reminder', 'alert', 'otp', 'other'])->default('other');
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        // ─────────────────────────────────────────────
        // NPS CONTRIBUTIONS
        // ─────────────────────────────────────────────
        Schema::create('nps_contributions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->integer('year');
            $table->integer('month');
            $table->decimal('employee_contribution', 10, 2)->default(0);
            $table->decimal('employer_contribution', 10, 2)->default(0);
            $table->unsignedBigInteger('payroll_run_id')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nps_contributions');
        Schema::dropIfExists('communication_logs');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('approval_workflow_levels');
        Schema::dropIfExists('approval_workflows');
        Schema::dropIfExists('excel_upload_logs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');
    }
};
