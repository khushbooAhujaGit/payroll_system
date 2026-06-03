<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Services\LeaveBalanceSyncService;
use Illuminate\Console\Command;

class SyncLeaveBalances extends Command
{
    protected $signature = 'leave:sync-balances {--employee-id= : Sync specific employee ID} {--all : Sync all employees}';
    protected $description = 'Sync leave_balances table from leave_tracker_records. Automatically aggregates availed leaves by employee, leave type, and FY month.';

    public function handle()
    {
        $employeeId = $this->option('employee-id');
        $syncAll     = $this->option('all');

        if ($employeeId) {
            $emp = Employee::find($employeeId);
            if (!$emp) {
                $this->error("Employee ID {$employeeId} not found.");
                return 1;
            }
            $empIds = [$employeeId];
            $this->info("Syncing leave balances for employee: {$emp->full_name} (ID: {$emp->employee_id})");
        } elseif ($syncAll) {
            $empIds = Employee::pluck('id')->toArray();
            $this->info("Syncing leave balances for all employees (".count($empIds)." total)");
        } else {
            $this->error('Specify --employee-id=ID or --all flag');
            return 1;
        }

        $synced = (new LeaveBalanceSyncService())->sync($empIds);

        $this->info("✓ Synced {$synced} leave balance entries");
        return 0;
    }
}
