<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
                // ─────────────────────────────────────────────
        // LEAVE TYPES
        // ─────────────────────────────────────────────
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('name');                          // Casual Leave, Sick Leave
            $table->string('code', 10);                      // CL, SL, EL, LOP
            $table->string('color', 10)->nullable();         // for calendar display
            $table->enum('category', [
                'casual', 'sick', 'earned', 'lop', 'maternity', 'paternity',
                'bereavement', 'marriage', 'sabbatical', 'comp_off', 'optional', 'other'
            ])->default('casual');
            $table->boolean('is_paid')->default(true);
            $table->boolean('is_carry_forward')->default(false);
            $table->integer('max_carry_forward')->default(0);
            $table->boolean('is_encashable')->default(false);
            $table->boolean('is_half_day_allowed')->default(true);
            $table->boolean('requires_document')->default(false); // sick leave > 3 days
            $table->integer('document_required_after_days')->default(0);
            $table->integer('min_days')->default(1);
            $table->decimal('max_days_per_application', 5, 1)->nullable();
            $table->integer('max_days_per_year')->nullable();
            $table->boolean('applicable_on_probation')->default(true);
            $table->integer('notice_days_required')->default(0); // advance notice
            $table->enum('gender_specific', ['all', 'male', 'female'])->default('all');
            $table->boolean('is_lop_type')->default(false);   // loss of pay
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['entity_id', 'code']);
        });

        // ─────────────────────────────────────────────
        // LEAVE POLICIES  (accrual, proration etc.)
        // ─────────────────────────────────────────────
        Schema::create('leave_policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->unsignedBigInteger('leave_type_id')->nullable();
            $table->string('name');
            $table->decimal('days_per_year', 5, 2)->default(0);
            $table->enum('accrual_type', ['annual', 'monthly', 'quarterly'])->default('annual');
            $table->decimal('accrual_days', 5, 2)->nullable();  // days per accrual period
            $table->integer('accrual_on_day')->default(1);       // day of month for accrual
            $table->enum('proration', ['none', 'joining_month', 'full_month'])->default('none');
            $table->boolean('sandwich_policy')->default(false);  // count weekends inside leave
            $table->integer('min_balance_to_apply')->default(0);
            $table->boolean('allow_negative_balance')->default(false);
            $table->integer('max_negative_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ─────────────────────────────────────────────
        // LEAVE BALANCES  (per employee, per year)
        // ─────────────────────────────────────────────
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->unsignedBigInteger('leave_type_id')->nullable();
            $table->integer('year');
            $table->decimal('opening_balance', 7, 2)->default(0);
            $table->decimal('accrued', 7, 2)->default(0);
            $table->decimal('carry_forwarded', 7, 2)->default(0);
            $table->decimal('availed', 7, 2)->default(0);
            $table->decimal('encashed', 7, 2)->default(0);
            $table->decimal('lapsed', 7, 2)->default(0);
            $table->decimal('adjusted', 7, 2)->default(0);    // manual adjustments
            $table->decimal('closing_balance', 7, 2)->default(0);  // computed
            $table->timestamps();

            $table->unique(['employee_id', 'leave_type_id', 'year']);
        });

        // ─────────────────────────────────────────────
        // LEAVE APPLICATIONS
        // ─────────────────────────────────────────────
        Schema::create('leave_applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_number', 20)->unique(); // LA-2024-001234
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->unsignedBigInteger('leave_type_id')->nullable();
            $table->date('from_date');
            $table->date('to_date');
            $table->decimal('days', 5, 2);                     // calculated business days
            $table->boolean('is_half_day')->default(false);
            $table->enum('half_day_type', ['first_half', 'second_half'])->nullable();
            $table->text('reason')->nullable();
            $table->string('document_path')->nullable();
            $table->enum('status', ['draft', 'pending', 'approved', 'rejected', 'cancelled', 'withdrawn'])->default('pending');
            $table->boolean('is_lop')->default(false);          // converted to LOP
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['from_date', 'to_date']);
        });

        // ─────────────────────────────────────────────
        // LEAVE APPROVAL WORKFLOW
        // ─────────────────────────────────────────────
        Schema::create('leave_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('leave_application_id')->nullable();
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->integer('level')->default(1);              // L1, L2, L3 approval
            $table->enum('action', ['pending', 'approved', 'rejected', 'forwarded'])->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamp('actioned_at')->nullable();
            $table->timestamps();
        });

        // ─────────────────────────────────────────────
        // COMP-OFF  (compensatory off earned)
        // ─────────────────────────────────────────────
        Schema::create('comp_offs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->date('worked_on_date');                    // holiday/off worked
            $table->decimal('credited_days', 3, 1)->default(1);
            $table->date('expiry_date');
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'utilized'])->default('pending');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('comp_offs');
        Schema::dropIfExists('leave_approvals');
        Schema::dropIfExists('leave_applications');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_policies');
        Schema::dropIfExists('leave_types');
    }
};
