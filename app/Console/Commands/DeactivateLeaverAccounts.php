<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Disables login for any User whose linked Employee has a date_of_leaving
 * in the past. This only touches the User.is_active flag (login gate) — it
 * does not touch Employee.employee_status, which is a separate HR concept
 * already managed by Employee::syncLeaverStatuses() (LFT-based, from the
 * leave tracker import).
 *
 * Also enforced lazily at login time in WebAuthController — this command is
 * the nightly sweep so the Users list reflects the correct state even for
 * accounts that never attempt to log in again.
 */
class DeactivateLeaverAccounts extends Command
{
    protected $signature = 'accounts:deactivate-leavers';
    protected $description = 'Deactivate login for users whose employee record has a date_of_leaving in the past';

    public function handle(): int
    {
        $count = User::whereHas('employee', fn($q) => $q
                ->whereNotNull('date_of_leaving')
                ->where('date_of_leaving', '<', now()->toDateString())
            )
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $this->info("Deactivated {$count} user account(s) past their date_of_leaving.");

        return self::SUCCESS;
    }
}
