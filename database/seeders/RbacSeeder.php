<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\TdsSlab;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RbacSeeder extends Seeder
{
    // All system permissions grouped by module
    const PERMISSIONS = [
        'employee' => [
            'employee.view',   'employee.create', 'employee.edit',
            'employee.delete', 'employee.export', 'employee.import',
        ],
        'attendance' => [
            'attendance.view', 'attendance.mark',  'attendance.approve',
            'attendance.export',
        ],
        'leave' => [
            'leave.view', 'leave.apply', 'leave.approve', 'leave.manage',
        ],
        'payroll' => [
            'payroll.view',   'payroll.run',    'payroll.edit',
            'payroll.approve','payroll.lock',   'payroll.export',
        ],
        'statutory' => [
            'statutory.view', 'statutory.manage',
        ],
        'salary_revision' => [
            'salary_revision.view', 'salary_revision.create', 'salary_revision.approve',
        ],
        'investment_declaration' => [
            'investment_declaration.view', 'investment_declaration.approve',
        ],
        'poi' => [
            'poi.view', 'poi.review',
        ],
        'loan' => [
            'loan.view', 'loan.create', 'loan.approve',
        ],
        'reimbursement' => [
            'reimbursement.view', 'reimbursement.create', 'reimbursement.approve', 'reimbursement.manage',
        ],
        'contractor' => [
            'contractor.view', 'contractor.create', 'contractor.edit', 'contractor.approve',
        ],
        'helpdesk' => [
            'helpdesk.view', 'helpdesk.manage',
        ],
        'hr_analytics' => [
            'hr_analytics.view',
        ],
        'audit' => [
            'audit.view',
        ],
        'rbac' => [
            'rbac.manage',
        ],
    ];

    // Which permissions each system role gets
    const ROLE_PERMISSIONS = [
        'super_admin' => '*',  // gets everything

        'hr_manager' => [
            'employee.*', 'attendance.*', 'leave.*', 'salary_revision.*',
            'investment_declaration.*', 'poi.*', 'loan.*', 'reimbursement.*',
            'contractor.*', 'helpdesk.*', 'hr_analytics.view', 'statutory.view',
        ],

        'payroll_admin' => [
            'employee.view', 'employee.export',
            'attendance.view', 'attendance.export',
            'leave.view',
            'payroll.*', 'statutory.*',
            'salary_revision.*', 'investment_declaration.*', 'poi.*',
            'loan.view', 'reimbursement.view',
            'contractor.view', 'contractor.approve',
        ],

        'manager' => [
            'employee.view',
            'attendance.view', 'attendance.approve',
            'leave.view', 'leave.approve',
            'reimbursement.view', 'reimbursement.approve',
            'loan.view', 'loan.approve',
            'helpdesk.view',
        ],

        'employee' => [
            'leave.apply',
        ],

        'finance' => [
            'payroll.view', 'payroll.export',
            'loan.view', 'loan.approve',
            'reimbursement.view', 'reimbursement.approve',
            'contractor.view', 'contractor.approve',
            'statutory.view',
        ],
    ];

    public function run(): void
    {
        // ── 1. Create all permissions ──────────────────────────
        $permissionMap = [];
        foreach (static::PERMISSIONS as $module => $slugs) {
            foreach ($slugs as $slug) {
                $name = ucwords(str_replace(['.', '_'], ' ', $slug));
                $perm = Permission::firstOrCreate(
                    ['slug' => $slug],
                    ['module' => $module, 'name' => $name]
                );
                $permissionMap[$slug] = $perm->id;
            }
        }

        $allPermissions = Permission::all();

        // ── 2. Create system roles ─────────────────────────────
        $roleDefinitions = [
            ['slug' => 'super_admin',   'name' => 'Super Admin',    'description' => 'Full system access'],
            ['slug' => 'hr_manager',    'name' => 'HR Manager',     'description' => 'Manages HR operations'],
            ['slug' => 'payroll_admin', 'name' => 'Payroll Admin',  'description' => 'Manages payroll'],
            ['slug' => 'manager',       'name' => 'Manager',        'description' => 'Team manager access'],
            ['slug' => 'employee',      'name' => 'Employee',       'description' => 'Self-service employee'],
            ['slug' => 'finance',       'name' => 'Finance',        'description' => 'Finance team access'],
        ];

        foreach ($roleDefinitions as $def) {
            $role = Role::firstOrCreate(
                ['slug' => $def['slug']],
                ['name' => $def['name'], 'description' => $def['description'], 'is_system_role' => true, 'is_active' => true]
            );

            $rolePerms = static::ROLE_PERMISSIONS[$def['slug']] ?? [];

            if ($rolePerms === '*') {
                // Super admin: grant all with full CRUD
                $syncData = [];
                foreach ($allPermissions as $perm) {
                    $syncData[$perm->id] = [
                        'can_view'   => true,
                        'can_create' => true,
                        'can_edit'   => true,
                        'can_delete' => true,
                        'can_export' => true,
                        'can_approve'=> true,
                    ];
                }
                $role->permissions()->sync($syncData);
            } else {
                // Map wildcard patterns to specific permissions
                $granted = [];
                foreach ($rolePerms as $pattern) {
                    $matchedPerms = $this->matchPermissions($pattern, $permissionMap);
                    foreach ($matchedPerms as $permId) {
                        $granted[$permId] = $this->buildAbilities($pattern);
                    }
                }
                $role->permissions()->sync($granted);
            }
        }

        // ── 3. Create default Super Admin user ────────────────
        $admin = User::firstOrCreate(
            ['email' => 'admin@hrms.local'],
            [
                'name'      => 'System Admin',
                'password'  => Hash::make('Admin@123'),
                'is_active' => true,
            ]
        );

        $superAdminRole = Role::where('slug', 'super_admin')->first();
        if ($superAdminRole && !$admin->roles()->where('role_id', $superAdminRole->id)->exists()) {
            $admin->roles()->attach($superAdminRole->id);
        }

        $this->command->info("✓ RBAC seeded: " . $allPermissions->count() . " permissions, " . count($roleDefinitions) . " roles.");
        $this->command->info("✓ Default admin: admin@hrms.local / Admin@123");
    }

    protected function matchPermissions(string $pattern, array $permissionMap): array
    {
        if (str_ends_with($pattern, '.*')) {
            $prefix  = str_replace('.*', '.', $pattern);
            $matched = [];
            foreach ($permissionMap as $slug => $id) {
                if (str_starts_with($slug, $prefix)) {
                    $matched[] = $id;
                }
            }
            return $matched;
        }

        return isset($permissionMap[$pattern]) ? [$permissionMap[$pattern]] : [];
    }

    protected function buildAbilities(string $pattern): array
    {
        // Patterns ending with .* get full abilities; specific slugs get targeted abilities
        $base = ['can_view' => false, 'can_create' => false, 'can_edit' => false, 'can_delete' => false, 'can_export' => false, 'can_approve' => false];

        if (str_ends_with($pattern, '.*')) {
            return array_fill_keys(array_keys($base), true);
        }

        return array_merge($base, ['can_view' => true]);
    }
}
