<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\PermissionGroup;
use Illuminate\Console\Command;

/**
 * One-time fix: several Web controllers call $this->authorize('some_slug')
 * for slugs that were never actually created as `permissions` rows via the
 * RBAC UI — an entire feature (Helpdesk, Contractor, Salary Revision,
 * Reports, Statutory, TDS Slab) had no permission rows at all, so no role
 * (other than Admin, which bypasses all checks) could ever be granted
 * access, regardless of what was ticked in the RBAC checkbox UI.
 *
 * This creates those missing `permissions` rows (grouped sensibly under the
 * existing HR/Payroll/Finance/Master groups, or new Reports/Compliance
 * groups where nothing existing fits) so they become grantable via /rbac.
 * It does NOT grant them to any role — that stays an explicit admin
 * decision made through the RBAC UI.
 *
 * Idempotent — uses firstOrCreate, safe to re-run.
 */
class AddMissingRbacPermissions extends Command
{
    protected $signature = 'rbac:add-missing-permissions';
    protected $description = 'Create permission rows for modules that authorize() checks but the RBAC UI never created';

    public function handle(): int
    {
        $hr       = PermissionGroup::firstOrCreate(['name' => 'HR']);
        $payroll  = PermissionGroup::firstOrCreate(['name' => 'Payroll']);
        $master   = PermissionGroup::firstOrCreate(['name' => 'Master']);
        $reports  = PermissionGroup::firstOrCreate(['name' => 'Reports']);
        $compliance = PermissionGroup::firstOrCreate(['name' => 'Compliance']);

        $permissions = [
            // Helpdesk — employee support tickets
            ['helpdesk_view',    $hr->id],
            ['helpdesk_raise',   $hr->id],
            ['helpdesk_resolve', $hr->id],

            // Contractor management
            ['contractor_view',   $hr->id],
            ['contractor_create', $hr->id],
            ['contractor_edit',   $hr->id],

            // Salary Revision
            ['salary_revision_view',    $payroll->id],
            ['salary_revision_create',  $payroll->id],
            ['salary_revision_approve', $payroll->id],

            // Reports
            ['reports_view', $reports->id],

            // Statutory / Compliance
            ['statutory_view',   $compliance->id],
            ['statutory_manage', $compliance->id],

            // TDS Slab
            ['tds_slab_view',   $payroll->id],
            ['tds_slab_manage', $payroll->id],

            // Designation (pairs with existing department_* permissions)
            ['designation_manage', $master->id],

            // Branch master
            ['branch_view', $master->id],
            ['branch_add',  $master->id],

            // Employee export
            ['employee_export', $hr->id],

            // Payroll run / lock / override / edit
            ['payroll_run',      $payroll->id],
            ['payroll_lock',     $payroll->id],
            ['payroll_override', $payroll->id],
            ['payroll_edit',     $payroll->id],
        ];

        $created = 0;
        foreach ($permissions as [$name, $groupId]) {
            $permission = Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['permission_group_id' => $groupId]
            );
            if ($permission->wasRecentlyCreated) {
                $created++;
            }
        }

        $this->info("Created {$created} new permission(s). " . (count($permissions) - $created) . ' already existed.');

        return self::SUCCESS;
    }
}
