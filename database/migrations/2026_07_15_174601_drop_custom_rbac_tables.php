<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the hand-rolled RBAC tables (roles, permissions, role_permissions,
 * user_roles) in favor of spatie/laravel-permission's own schema, created
 * by the migration immediately after this one
 * (2026_07_15_174602_create_permission_tables.php). Must run before that
 * migration since it creates tables with the same names (`roles`,
 * `permissions`).
 *
 * permission_groups is NOT dropped — it's repurposed to reference Spatie's
 * permissions table via a new permission_group_id column added in
 * 2026_07_16_000002_add_columns_to_permission_tables.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Some environments have an FK pointing at `permissions` or `roles`
        // that isn't declared in any migration here (added by hand at some
        // point). Disable checks for this statement batch so the drop can't
        // fail on a constraint we can't see from the code.
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Intentionally not reversible — the old custom schema is retired.
        // Restore from a backup if the old tables are needed again.
    }
};
