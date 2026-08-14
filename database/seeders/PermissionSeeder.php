<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Creates the single 'Admin' role and a default admin login only.
 *
 * Deliberately does NOT create any permissions, permission groups, or
 * role_permissions rows, and does NOT create any role other than Admin —
 * everything else (roles, permission groups, permissions, and assigning
 * permissions to roles) is managed entirely through the Role & Permissions
 * admin page (/rbac), starting from a clean slate. Do not re-add a
 * hardcoded permission/role list here; anything that re-runs this seeder
 * must not repopulate them, or manually truncated tables will keep
 * reappearing.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::firstOrCreate(
            ['name' => 'Admin', 'guard_name' => 'web'],
            ['description' => 'Full, unconditional access — bypasses all permission checks. See User::isSuperAdmin().']
        );

        $admin = User::firstOrCreate(
            ['email' => 'admin@hrms.local'],
            [
                'name'      => 'System Admin',
                'password'  => Hash::make('Admin@123'),
                'is_active' => true,
            ]
        );

        if (!$admin->hasRole('Admin')) {
            $admin->assignRole($adminRole);
        }

        $this->command->info('✅  Admin role and default admin user seeded (no permissions — set those up via /rbac).');
        $this->command->info('✓ Default admin: admin@hrms.local / Admin@123');
    }
}
