<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * user_roles was skipped by the main entity→company migration because it is a
 * pivot table (not in the $tables list).  Rename entity_id → company_id here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('user_roles')) return;
        if (!Schema::hasColumn('user_roles', 'entity_id')) return;

        // Add company_id if not already there
        if (!Schema::hasColumn('user_roles', 'company_id')) {
            Schema::table('user_roles', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->after('role_id');
            });
        }

        // Copy entity_id → company_id (they're 1:1 after the main migration)
        DB::statement('UPDATE user_roles SET company_id = entity_id WHERE entity_id IS NOT NULL');

        // Drop the old entity_id column
        Schema::table('user_roles', function (Blueprint $table) {
            $table->dropColumn('entity_id');
        });

        // Recreate the unique constraint with company_id
        Schema::table('user_roles', function (Blueprint $table) {
            $table->unique(['user_id', 'role_id', 'company_id'], 'user_roles_user_role_company_unique');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('user_roles')) return;

        Schema::table('user_roles', function (Blueprint $table) {
            $table->dropUnique('user_roles_user_role_company_unique');
            $table->unsignedBigInteger('entity_id')->nullable();
        });

        DB::statement('UPDATE user_roles SET entity_id = company_id WHERE company_id IS NOT NULL');

        Schema::table('user_roles', function (Blueprint $table) {
            $table->dropColumn('company_id');
        });
    }
};
