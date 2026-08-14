<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Services\UserProvisioningService;
use Illuminate\Console\Command;

/**
 * One-off/backfill job: creates a login account (default password, Employee
 * role, welcome email) for every employee who doesn't already have one
 * linked — the same thing UserProvisioningService::createDefaultAccountFor()
 * does automatically for newly-created employees, just applied retroactively
 * to existing records that predate that flow (bulk imports, manual DB
 * changes, etc).
 *
 * Idempotent — re-running only touches employees still missing an account.
 */
class BackfillEmployeeUserAccounts extends Command
{
    protected $signature = 'employees:backfill-user-accounts {--dry-run : List who would get an account without creating it or sending mail}';
    protected $description = 'Create a login account for every employee who does not already have one';

    public function handle(UserProvisioningService $userProvisioningService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Dry run — no accounts will be created and no mail will be sent.');
        }

        $employees = Employee::whereDoesntHave('user')->get();

        $created = 0;
        $skippedNoEmail = 0;

        foreach ($employees as $employee) {
            $email = $employee->official_email ?: $employee->personal_email;

            if (!$email) {
                $skippedNoEmail++;
                $this->line("Skipped {$employee->employee_id} — {$employee->full_name} (no email on record)");
                continue;
            }

            if ($dryRun) {
                $this->line("Would create account for {$employee->employee_id} — {$employee->full_name} ({$email})");
                $created++;
                continue;
            }

            $userProvisioningService->createDefaultAccountFor($employee);
            $created++;
        }

        $this->info(
            ($dryRun ? '[DRY RUN] ' : '') .
            "{$created} account(s) " . ($dryRun ? 'would be created' : 'created') .
            ", {$skippedNoEmail} skipped (no email on record)."
        );

        return self::SUCCESS;
    }
}
