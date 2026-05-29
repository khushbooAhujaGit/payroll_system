<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;
use App\Models\RolePermission;

class PermissionSeeder extends Seeder
{
    /**
     * All system permissions, grouped by module.
     * Format: [module, name, slug]
     */
    protected array $permissions = [
        // ── Employee ─────────────────────────────────────────────────────────
        ['employee', 'View Employees',    'employee.view'],
        ['employee', 'Create Employee',   'employee.create'],
        ['employee', 'Edit Employee',     'employee.edit'],
        ['employee', 'Delete Employee',   'employee.delete'],
        ['employee', 'Export Employees',  'employee.export'],
        ['employee', 'Import Employees',  'employee.import'],
        ['employee', 'Confirm Employee',  'employee.confirm'],
        ['employee', 'Transfer Employee', 'employee.transfer'],

        // ── Attendance ────────────────────────────────────────────────────────
        ['attendance', 'View Attendance',         'attendance.view'],
        ['attendance', 'Edit Attendance',         'attendance.edit'],
        ['attendance', 'Import Attendance',       'attendance.import'],
        ['attendance', 'Approve Regularization',  'attendance.regularization.approve'],
        ['attendance', 'Approve Overtime',        'attendance.overtime.approve'],

        // ── Leave ─────────────────────────────────────────────────────────────
        ['leave', 'View Leave',           'leave.view'],
        ['leave', 'Apply Leave',          'leave.apply'],
        ['leave', 'Approve Leave',        'leave.approve'],
        ['leave', 'Reject Leave',         'leave.reject'],
        ['leave', 'Adjust Leave Balance', 'leave.balance.adjust'],
        ['leave', 'Manage Leave Types',   'leave.type.manage'],
        ['leave', 'Import Leave Balance', 'leave.import'],

        // ── Payroll ───────────────────────────────────────────────────────────
        ['payroll', 'View Payroll',            'payroll.view'],
        ['payroll', 'Run Payroll',             'payroll.run'],
        ['payroll', 'Edit Payroll',            'payroll.edit'],
        ['payroll', 'Approve Payroll',         'payroll.approve'],
        ['payroll', 'Lock/Unlock Period',      'payroll.lock'],
        ['payroll', 'Override Salary',         'payroll.override'],
        ['payroll', 'Generate Bank File',      'payroll.bank_file'],
        ['payroll', 'Generate Payslips',       'payroll.payslip'],
        ['payroll', 'Export Payroll Register', 'payroll.export'],

        // ── Salary Revision ───────────────────────────────────────────────────
        ['salary_revision', 'View Salary Revisions',    'salary_revision.view'],
        ['salary_revision', 'Create Salary Revision',   'salary_revision.create'],
        ['salary_revision', 'Approve Salary Revision',  'salary_revision.approve'],

        // ── Statutory ─────────────────────────────────────────────────────────
        ['statutory', 'View Statutory Reports',  'statutory.view'],
        ['statutory', 'Manage PF Settings',      'statutory.pf.manage'],
        ['statutory', 'Manage ESIC Settings',    'statutory.esic.manage'],
        ['statutory', 'Manage PT Slabs',         'statutory.pt.manage'],
        ['statutory', 'Generate ECR',            'statutory.ecr.generate'],
        ['statutory', 'View TDS Sheet',          'statutory.tds.view'],
        ['statutory', 'Generate Form 16',        'statutory.form16.generate'],

        // ── Investment Declaration ─────────────────────────────────────────────
        ['investment_declaration', 'View Declarations',    'investment_declaration.view'],
        ['investment_declaration', 'Submit Declaration',   'investment_declaration.submit'],
        ['investment_declaration', 'Approve Declaration',  'investment_declaration.approve'],

        // ── POI ───────────────────────────────────────────────────────────────
        ['poi', 'Upload POI',    'poi.upload'],
        ['poi', 'Review POI',    'poi.review'],

        // ── Loans ────────────────────────────────────────────────────────────
        ['loan', 'View Loans',    'loan.view'],
        ['loan', 'Create Loan',   'loan.create'],
        ['loan', 'Approve Loan',  'loan.approve'],

        // ── Reimbursement ─────────────────────────────────────────────────────
        ['reimbursement', 'View Reimbursements',    'reimbursement.view'],
        ['reimbursement', 'Create Reimbursement',   'reimbursement.create'],
        ['reimbursement', 'Approve Reimbursement',  'reimbursement.approve'],

        // ── Contractor ────────────────────────────────────────────────────────
        ['contractor', 'View Contractors',    'contractor.view'],
        ['contractor', 'Create Contractor',   'contractor.create'],
        ['contractor', 'Edit Contractor',     'contractor.edit'],
        ['contractor', 'Approve Invoice',     'contractor.approve'],

        // ── Helpdesk ──────────────────────────────────────────────────────────
        ['helpdesk', 'View Tickets',         'helpdesk.view'],
        ['helpdesk', 'Raise Ticket',         'helpdesk.raise'],
        ['helpdesk', 'Resolve Ticket',       'helpdesk.resolve'],
        ['helpdesk', 'Manage Helpdesk',      'helpdesk.manage'],

        // ── Dashboards ────────────────────────────────────────────────────────
        ['dashboard', 'Manager Dashboard',    'manager_dashboard.view'],
        ['dashboard', 'HR Analytics',         'hr_analytics.view'],

        // ── Setup / Admin ─────────────────────────────────────────────────────
        ['setup', 'Manage Company',       'company.manage'],
        ['setup', 'Manage Entities',      'entity.manage'],
        ['setup', 'Manage Departments',   'department.manage'],
        ['setup', 'Manage Designations',  'designation.manage'],
        ['setup', 'Manage Grades',        'grade.manage'],
        ['setup', 'Manage Branches',      'branch.manage'],
        ['setup', 'Manage Cost Centers',  'cost_center.manage'],
        ['setup', 'Manage Holidays',      'holiday.manage'],

        // ── RBAC ──────────────────────────────────────────────────────────────
        ['rbac', 'Manage Roles',        'rbac.roles.manage'],
        ['rbac', 'Assign Roles',        'rbac.roles.assign'],
        ['rbac', 'View Audit Logs',     'audit_log.view'],
    ];

    // ─────────────────────────────────────────────
    // DEFAULT ROLE → PERMISSION MATRIX
    // ─────────────────────────────────────────────

    protected array $rolePermissions = [
        'super_admin' => '*',   // all permissions

        'hr_manager' => [
            'employee.view', 'employee.create', 'employee.edit', 'employee.export', 'employee.import',
            'employee.confirm', 'employee.transfer',
            'attendance.view', 'attendance.edit', 'attendance.import', 'attendance.regularization.approve',
            'leave.view', 'leave.approve', 'leave.reject', 'leave.balance.adjust', 'leave.type.manage', 'leave.import',
            'payroll.view',
            'salary_revision.view', 'salary_revision.create', 'salary_revision.approve',
            'statutory.view', 'statutory.tds.view',
            'investment_declaration.view', 'investment_declaration.approve',
            'poi.review',
            'loan.view', 'loan.approve',
            'reimbursement.view', 'reimbursement.approve',
            'contractor.view', 'contractor.create', 'contractor.edit', 'contractor.approve',
            'helpdesk.view', 'helpdesk.resolve', 'helpdesk.manage',
            'manager_dashboard.view', 'hr_analytics.view',
            'department.manage', 'designation.manage', 'grade.manage', 'branch.manage', 'holiday.manage',
        ],

        'payroll_admin' => [
            'employee.view',
            'attendance.view',
            'leave.view',
            'payroll.view', 'payroll.run', 'payroll.edit', 'payroll.approve', 'payroll.lock',
            'payroll.override', 'payroll.bank_file', 'payroll.payslip', 'payroll.export',
            'salary_revision.view', 'salary_revision.create',
            'statutory.view', 'statutory.pf.manage', 'statutory.esic.manage', 'statutory.pt.manage',
            'statutory.ecr.generate', 'statutory.tds.view', 'statutory.form16.generate',
            'investment_declaration.view',
            'poi.review',
            'loan.view',
            'reimbursement.view', 'reimbursement.approve',
            'hr_analytics.view',
        ],

        'manager' => [
            'employee.view',
            'attendance.view', 'attendance.regularization.approve',
            'leave.view', 'leave.approve', 'leave.reject',
            'salary_revision.view',
            'helpdesk.view', 'helpdesk.resolve',
            'manager_dashboard.view',
        ],

        'employee' => [
            'leave.apply',
            'investment_declaration.submit',
            'poi.upload',
            'loan.create',
            'reimbursement.create',
            'helpdesk.raise',
        ],

        'finance_admin' => [
            'payroll.view', 'payroll.export',
            'loan.view', 'loan.approve',
            'reimbursement.view', 'reimbursement.approve',
            'contractor.view', 'contractor.approve',
            'statutory.view',
            'hr_analytics.view',
        ],
    ];

    public function run(): void
    {
        // 1. Upsert all permissions
        foreach ($this->permissions as [$module, $name, $slug]) {
            Permission::firstOrCreate(
                ['slug' => $slug],
                ['module' => $module, 'name' => $name]
            );
        }

        // 2. Create default roles
        $defaultRoles = [
            ['name' => 'Super Admin',    'slug' => 'super_admin',    'is_system_role' => true],
            ['name' => 'HR Manager',     'slug' => 'hr_manager',     'is_system_role' => true],
            ['name' => 'Payroll Admin',  'slug' => 'payroll_admin',  'is_system_role' => true],
            ['name' => 'Manager',        'slug' => 'manager',        'is_system_role' => true],
            ['name' => 'Employee',       'slug' => 'employee',       'is_system_role' => true],
            ['name' => 'Finance Admin',  'slug' => 'finance_admin',  'is_system_role' => true],
        ];

        foreach ($defaultRoles as $roleData) {
            Role::firstOrCreate(['slug' => $roleData['slug']], $roleData);
        }

        // 3. Assign permissions to roles
        $allPermissions = Permission::all()->keyBy('slug');

        foreach ($this->rolePermissions as $roleSlug => $perms) {
            $role = Role::where('slug', $roleSlug)->first();
            if (!$role) continue;

            if ($perms === '*') {
                // Super admin: all permissions with all flags
                foreach ($allPermissions as $perm) {
                    RolePermission::updateOrCreate(
                        ['role_id' => $role->id, 'permission_id' => $perm->id],
                        [
                            'can_view'   => true,
                            'can_create' => true,
                            'can_edit'   => true,
                            'can_delete' => true,
                            'can_export' => true,
                            'can_approve'=> true,
                        ]
                    );
                }
            } else {
                foreach ($perms as $slug) {
                    $perm = $allPermissions->get($slug);
                    if (!$perm) continue;

                    // Infer flags from slug suffix
                    $isApprove = str_contains($slug, 'approve') || str_contains($slug, 'lock');
                    $isCreate  = str_contains($slug, 'create') || str_contains($slug, 'apply') || str_contains($slug, 'upload') || str_contains($slug, 'run') || str_contains($slug, 'submit') || str_contains($slug, 'raise') || str_contains($slug, 'import');
                    $isEdit    = str_contains($slug, 'edit') || str_contains($slug, 'manage') || str_contains($slug, 'override') || str_contains($slug, 'adjust');
                    $isExport  = str_contains($slug, 'export') || str_contains($slug, 'generate') || str_contains($slug, 'bank_file') || str_contains($slug, 'payslip');

                    RolePermission::updateOrCreate(
                        ['role_id' => $role->id, 'permission_id' => $perm->id],
                        [
                            'can_view'   => true,
                            'can_create' => $isCreate,
                            'can_edit'   => $isEdit,
                            'can_delete' => false,
                            'can_export' => $isExport,
                            'can_approve'=> $isApprove,
                        ]
                    );
                }
            }
        }

        $this->command->info('✅  Permissions and roles seeded successfully.');
        $this->command->table(
            ['Role', 'Permission Count'],
            Role::withCount('permissions')->get()->map(fn($r) => [$r->name, $r->permissions_count])->toArray()
        );
    }
}
