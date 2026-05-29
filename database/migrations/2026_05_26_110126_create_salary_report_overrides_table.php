<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Salary Report Overrides
 * ───────────────────────
 * Stores manual overrides for non-auto-computed salary report fields.
 * The salary report's compute() reads these and substitutes them for
 * the auto-calculated values.
 *
 * Versioning: is_active = false means this record was superseded by an edit.
 * The newer active record points back via superseded_by_id for audit.
 *
 * Editable (non-auto-computed) fields:
 *   lop_override        — override LOP days (normally from leave tracker + approvals)
 *   incentive_override  — override incentive amount
 *   advance_override    — override advance/EMI deduction
 *   tds_override        — override TDS deduction
 *   bonus_override      — override diwali/other bonus
 *   notes               — reason for the override
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_report_overrides', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->char('year_month', 7);   // e.g. "2025-04"

            // Original computed values (snapshot at time of first override)
            $table->integer('original_lop')->nullable();
            $table->decimal('original_incentive', 12, 2)->nullable();
            $table->decimal('original_advance',   12, 2)->nullable();
            $table->decimal('original_tds',       12, 2)->nullable();
            $table->decimal('original_bonus',     12, 2)->nullable();

            // User-supplied overrides (null = use computed value)
            $table->integer('lop_override')->nullable();
            $table->decimal('incentive_override', 12, 2)->nullable();
            $table->decimal('advance_override',   12, 2)->nullable();
            $table->decimal('tds_override',       12, 2)->nullable();
            $table->decimal('bonus_override',     12, 2)->nullable();

            $table->text('notes')->nullable();

            // Versioning
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('superseded_by_id')->nullable();
            $table->foreign('superseded_by_id')
                  ->references('id')->on('salary_report_overrides')
                  ->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['employee_id', 'year_month', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_report_overrides');
    }
};
