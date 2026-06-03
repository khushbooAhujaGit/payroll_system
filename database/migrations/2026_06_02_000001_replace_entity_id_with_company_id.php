<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replace the two-tier Company → Entity hierarchy with a flat Company model.
 *
 * Each entity was always 1:1 with a company (same name, same data).
 * This migration:
 *   1. Adds company_id to every table that had entity_id
 *   2. Copies the correct company_id from the entities table (entity.company_id)
 *   3. Drops entity_id columns
 *   4. Drops the entities table
 */
return new class extends Migration
{
    /** Tables where entity_id → company_id (no FK constraint on entity_id was enforced in most) */
    private array $tables = [
        'employees',
        'departments',
        'designations',
        'branches',
        'grades',
        'cost_centers',
        'holiday_calendars',
        'leave_types',
        'leave_policies',
        'loan_types',
        'reimbursement_types',
        'salary_components',
        'salary_structures',
        'system_settings',
        'shifts',
        'employee_salaries',
        'salary_revisions',
        'payroll_periods',
        'payroll_runs',
        'pf_ecr_registers',
        'helpdesk_categories',
        'helpdesk_tickets',
        'bank_transfer_files',
        'bonus_rules',
        'contractors',
        'excel_upload_logs',
        'approval_workflows',
        'communication_logs',
    ];

    public function up(): void
    {
        // ── 1. Add nullable company_id to every table ─────────────────────────
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'entity_id')
                && !Schema::hasColumn($table, 'company_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedBigInteger('company_id')->nullable()->after('id');
                });
            }
        }

        // ── 2. Copy company_id from entities (entity_id → entity.company_id) ──
        // Only run when BOTH entity_id AND company_id columns exist.
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table)) continue;
            if (!Schema::hasColumn($table, 'entity_id')) continue;
            if (!Schema::hasColumn($table, 'company_id')) continue;

            // Primary: copy via entities join
            DB::statement("
                UPDATE `{$table}` t
                INNER JOIN entities e ON e.id = t.entity_id
                SET t.company_id = COALESCE(e.company_id, e.id)
                WHERE t.entity_id IS NOT NULL
            ");
        }

        // ── 3. Drop unique indexes that reference entity_id before dropping column ─
        $indexesToDrop = [
            'leave_types'     => 'leave_types_entity_id_code_unique',
            'payroll_periods' => 'payroll_periods_entity_id_year_month_unique',
            'system_settings' => 'system_settings_entity_id_key_unique',
            'user_roles'      => 'user_roles_user_id_role_id_entity_id_unique',
        ];
        foreach ($indexesToDrop as $table => $indexName) {
            if (!Schema::hasTable($table)) continue;
            // Query information_schema to confirm index exists before dropping
            $exists = DB::selectOne("
                SELECT COUNT(*) as cnt
                FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND INDEX_NAME = ?
            ", [$table, $indexName])->cnt ?? 0;

            if ($exists) {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
            }
        }

        // Drop all other non-primary indexes on entity_id dynamically
        $remainingIndexes = DB::select("
            SELECT TABLE_NAME, INDEX_NAME
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND COLUMN_NAME = 'entity_id'
              AND INDEX_NAME != 'PRIMARY'
            GROUP BY TABLE_NAME, INDEX_NAME
        ");
        foreach ($remainingIndexes as $idx) {
            DB::statement("ALTER TABLE `{$idx->TABLE_NAME}` DROP INDEX `{$idx->INDEX_NAME}`");
        }

        // ── 4. Drop entity_id columns ─────────────────────────────────────────
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'entity_id')) continue;
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('entity_id');
            });
        }

        // Also handle pf_settings / esic_settings / pt_slabs / lwf_slabs
        foreach (['pf_settings','esic_settings','pt_slabs','lwf_slabs'] as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'entity_id')) continue;
            if (!Schema::hasColumn($table, 'company_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedBigInteger('company_id')->nullable()->after('id');
                });
                DB::statement("UPDATE `{$table}` t INNER JOIN entities e ON e.id = t.entity_id SET t.company_id = COALESCE(e.company_id, e.id) WHERE t.entity_id IS NOT NULL");
            }
            Schema::table($table, fn(Blueprint $t) => $t->dropColumn('entity_id'));
        }

        // ── 5. Drop entities table ────────────────────────────────────────────
        Schema::dropIfExists('entities');
    }

    public function down(): void
    {
        // Restore entities table (empty — data cannot be recovered automatically)
        Schema::create('entities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name');
            $table->string('short_name', 50)->nullable();
            $table->string('pan')->nullable();
            $table->string('tan')->nullable();
            $table->string('gstin')->nullable();
            $table->string('pf_number')->nullable();
            $table->string('esic_number')->nullable();
            $table->string('pt_number')->nullable();
            $table->string('lwf_number')->nullable();
            $table->string('payroll_cycle')->default('monthly');
            $table->integer('payroll_cutoff_day')->default(31);
            $table->string('state', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Re-add entity_id to all tables (data will be NULL after rollback)
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table)) continue;
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('entity_id')->nullable();
            });
        }
    }
};
