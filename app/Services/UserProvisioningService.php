<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserProvisioningService
{
    /**
     * Default password given to every employee-linked login account created
     * automatically (manual "Add Employee" or bulk Excel import). They're
     * expected to change it after signing in.
     */
    public const DEFAULT_PASSWORD = 'rsc@2026';

    /**
     * Seeds a login account with the default password for a newly-created
     * employee. Requires an email on the record — skipped otherwise. Also
     * skipped if that email is already in use, so this never fails the
     * caller or clobbers an existing account/password.
     */
    public function createDefaultAccountFor(Employee $employee): void
    {
        $email = $employee->official_email ?: $employee->personal_email;
        if (!$email || User::where('email', $email)->exists()) {
            return;
        }

        $user = User::create([
            'company_id'  => $employee->company_id,
            'employee_id' => $employee->id,
            'name'        => trim("{$employee->first_name} {$employee->last_name}"),
            'email'       => $email,
            'password'    => Hash::make(self::DEFAULT_PASSWORD),
            'is_active'   => true,
        ]);

        // Baseline "Employee" role so the account is actually usable on
        // login — no role means no permissions means every page 403s.
        $employeeRole = Role::where('name', 'Employee')->first();
        if ($employeeRole) {
            $user->assignRole($employeeRole);
        }

        $this->sendWelcomeEmail($employee, $email);
    }

    /**
     * Notifies the newly-provisioned employee of their login details so they
     * can sign in, change the shared default password, and complete their
     * profile — the onboarding step this account creation exists for.
     */
    protected function sendWelcomeEmail(Employee $employee, string $email): void
    {
        $body = view('emails.employee-welcome', [
            'employee'     => $employee,
            'email'        => $email,
            'password'     => self::DEFAULT_PASSWORD,
            'loginUrl'     => route('login'),
        ])->render();

        $context = ['employee_id' => $employee->id, 'employee_code' => $employee->employee_id, 'email' => $email];

        try {
            $result = app(OutlookMailerService::class)->sendMail($email, 'Welcome to HRMS Payroll - Your Account Details (Testing Mail)', $body);

            if (str_starts_with($result, 'Email sent successfully')) {
                Log::info('Employee welcome email sent.', $context);
            } else {
                Log::error('Employee welcome email not sent: ' . $result, $context);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send employee welcome email via Outlook: ' . $e->getMessage(), $context);
        }
    }
}
