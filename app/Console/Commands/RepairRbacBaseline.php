<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * One-command repair for the recurring problem where this app's local
 * database gets reset to an earlier snapshot (something outside Laravel's
 * migrations is periodically restoring an old dump), which wipes every
 * `roles`/`permissions`/`model_has_roles`/`role_has_permissions` row —
 * including the Admin role itself and every employee's "Employee" role.
 *
 * Restores, idempotently:
 *  1. The Admin role + default admin@hrms.local account (same as PermissionSeeder).
 *  2. The full permission catalog — every slug actually referenced by
 *     $this->authorize()/@can() calls in the codebase (scanned, not guessed).
 *  3. HR and Employee roles, seeded with a reasonable default permission set
 *     ONLY if that role currently has zero permissions — so re-running this
 *     after someone has customized permissions via /rbac won't clobber that.
 *  4. Every employee-linked user who currently has no role at all gets the
 *     Employee role (matches what new employees get automatically via
 *     UserProvisioningService — this catches everyone created before this
 *     repair, or orphaned by a reset).
 *  5. Admin is re-synced to hold every permission that exists, every run.
 *
 * This does NOT know who should actually be HR vs plain Employee beyond
 * "linked to an employee record" — review role assignments via /users or
 * /rbac afterward if the default (everyone → Employee) isn't right for
 * some accounts.
 */
class RepairRbacBaseline extends Command
{
    protected $signature = 'rbac:repair-baseline';
    protected $description = 'Restore Admin/HR/Employee roles, the full permission catalog, and role assignments after an RBAC data wipe';

    /** Every permission slug referenced via $this->authorize() across app/Http/Controllers, plus the two blade-only ones (rbac_roles_manage, user_view) that gate sidebar links for Admin-only pages. */
    protected array $catalog = [
        'HR' => [
            'bioid_view', 'bioid_create', 'bioid_edit', 'bioid_delete',
            'employee_view', 'employee_add', 'employee_edit', 'employee_delete', 'employee_export',
            'helpdesk_view', 'helpdesk_raise', 'helpdesk_resolve',
            'contractor_view', 'contractor_create', 'contractor_edit',
        ],
        'Attendance' => [
            'attendance_view', 'attendance_edit',
            'attendance_self_view', 'attendance_analytics_view',
        ],
        'Leave' => [
            'leave_management_view', 'leave_management_add', 'leave_management_edit', 'leave_management_delete',
            'leave_policies_view', 'leave_policies_add', 'leave_policies_edit', 'leave_policies_del',
        ],
        'Master' => [
            'company_view', 'company_add', 'company_del',
            'department_view', 'department_add',
            'designation_manage',
            'branch_view', 'branch_add',
            'holidays_view', 'holiday_add', 'holiday_del',
        ],
        'Payroll' => [
            'payroll_view', 'payroll_run', 'payroll_lock', 'payroll_override', 'payroll_edit',
            'salary_details', 'salary_details_add', 'salary_detais_del',
            'salary_revision_view', 'salary_revision_create', 'salary_revision_approve',
            'incentive_view', 'incentive_add', 'incentive_delete',
            'monthly_tds_view', 'monthly_tds_add', 'monthly_tds_del',
            'tds_slab_view', 'tds_slab_manage',
        ],
        'Finance' => [
            'loan_tab_view', 'loan_tab_add', 'loan_tab_edit', 'loan_tab_del',
            'reimbursment_tab', 'reimbursment_add', 'reimbursment_edit', 'reimbursment_del', 'reimb_report',
            'reimbursment_self_add', 'reimbursment_self_view', 'reimbursment_approve',
        ],
        'Compliance' => [
            'statutory_view', 'statutory_manage',
        ],
        'Reports' => [
            'reports_view',
        ],
        'Admin' => [
            'rbac_roles_manage', 'user_view',
        ],
    ];

    /** Sensible default HR permission set — day-to-day operational access, excluding Admin-tier config/destructive actions (company_add/del, payroll_run/lock/override, rbac_roles_manage, user_view, *_del/delete, statutory_manage, tds_slab_manage). Review/adjust via /rbac. */
    protected array $hrDefaults = [
        'bioid_view', 'bioid_create', 'bioid_edit',
        'employee_view', 'employee_add', 'employee_edit', 'employee_export',
        'helpdesk_view', 'helpdesk_raise', 'helpdesk_resolve',
        'attendance_view', 'attendance_edit', 'attendance_self_view', 'attendance_analytics_view',
        'leave_management_view', 'leave_management_add', 'leave_management_edit',
        'leave_policies_view',
        'department_view', 'branch_view', 'designation_manage',
        'holidays_view', 'holiday_add',
        'payroll_view', 'salary_details',
        'incentive_view',
        'monthly_tds_view',
        'loan_tab_view', 'loan_tab_add', 'loan_tab_edit',
        'reimbursment_tab', 'reimbursment_add', 'reimbursment_edit', 'reimb_report',
        'reports_view',
        'statutory_view',
        'contractor_view',
    ];

    /** Employee role stays narrow, matching the existing isRestrictedToLeaveOnly() intent: leave + own profile only. */
    protected array $employeeDefaults = [
        'leave_management_view', 'leave_management_add', 'holidays_view',
        'attendance_self_view', 'attendance_analytics_view',
        'reimbursment_self_add', 'reimbursment_self_view',
    ];

    public function handle(): int
    {
        $adminRole = Role::firstOrCreate(
            ['name' => 'Admin', 'guard_name' => 'web'],
            ['description' => 'Full, unconditional access — bypasses all permission checks. See User::isSuperAdmin().']
        );

        $admin = User::firstOrCreate(
            ['email' => 'admin@hrms.local'],
            ['name' => 'System Admin', 'password' => Hash::make('Admin@123'), 'is_active' => true]
        );
        if (!$admin->hasRole('Admin')) {
            $admin->assignRole($adminRole);
        }

        $created = 0;
        foreach ($this->catalog as $groupName => $slugs) {
            $group = PermissionGroup::firstOrCreate(['name' => $groupName]);
            foreach ($slugs as $slug) {
                $permission = Permission::firstOrCreate(
                    ['name' => $slug, 'guard_name' => 'web'],
                    ['permission_group_id' => $group->id]
                );
                if ($permission->wasRecentlyCreated) {
                    $created++;
                }
            }
        }
        $this->info("Permission catalog: {$created} new permission(s) created.");

        $hrRole = Role::firstOrCreate(['name' => 'HR', 'guard_name' => 'web']);
        $employeeRole = Role::firstOrCreate(['name' => 'Employee', 'guard_name' => 'web']);

        if ($hrRole->permissions()->count() === 0) {
            $hrRole->syncPermissions($this->hrDefaults);
            $this->info('HR role had no permissions — seeded default set.');
        }
        if ($employeeRole->permissions()->count() === 0) {
            $employeeRole->syncPermissions($this->employeeDefaults);
            $this->info('Employee role had no permissions — seeded default set.');
        }

        $adminRole->syncPermissions(Permission::all());
        $this->info('Admin re-synced to hold every permission (' . Permission::count() . ' total).');

        $assigned = 0;
        User::whereHas('employee')->whereDoesntHave('roles')->get()->each(function (User $u) use ($employeeRole, &$assigned) {
            $u->assignRole($employeeRole);
            $assigned++;
        });
        $this->info("Assigned the Employee role to {$assigned} employee-linked user(s) who had no role at all.");

        return self::SUCCESS;
    }
}
