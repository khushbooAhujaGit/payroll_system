<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserProvisioningService
{
    /** Fixed length of the auto-generated password. */
    protected const GENERATED_PASSWORD_LENGTH = 14;

    /**
     * Symbol pool for the generated password — deliberately excludes
     * arithmetic signs (+, -, *, /, =) since a password containing one can
     * read as a sum when skimmed, which is confusing to retype correctly
     * even though the employee is expected to change it right after login.
     */
    protected const PASSWORD_SYMBOLS = '!@#$%^&()_{}[]:;,.?';

    /**
     * Seeds a login account with a freshly-generated password for a
     * newly-created employee. Requires an email on the record — skipped
     * otherwise. Also skipped if that email is already in use, so this never
     * fails the caller or clobbers an existing account/password.
     */
    public function createDefaultAccountFor(Employee $employee): void
    {
        $email = $employee->official_email ?: $employee->personal_email;
        if (!$email || User::where('email', $email)->exists()) {
            return;
        }

        $password = $this->generatePassword();

        $user = User::create([
            'company_id'  => $employee->company_id,
            'employee_id' => $employee->id,
            'name'        => trim("{$employee->first_name} {$employee->last_name}"),
            'email'       => $email,
            'password'    => Hash::make($password),
            'is_active'   => true,
        ]);

        // Baseline "Employee" role so the account is actually usable on
        // login — no role means no permissions means every page 403s.
        $employeeRole = Role::where('name', 'Employee')->first();
        if ($employeeRole) {
            $user->assignRole($employeeRole);
        }

        $this->sendWelcomeEmail($employee, $email, $password);
    }

    /**
     * Fully random password — uppercase, lowercase, digits, and symbols,
     * drawn with random_int() (cryptographically secure). No arithmetic
     * signs (+, -, *, /, =) in the symbol pool, per request — everything
     * else is fair game. One character from each pool is guaranteed, then
     * the rest of the length is filled from the combined pool and shuffled,
     * so the guaranteed characters aren't always in the same position.
     */
    protected function generatePassword(): string
    {
        $pools = [
            'ABCDEFGHJKLMNPQRSTUVWXYZ',
            'abcdefghijkmnpqrstuvwxyz',
            '23456789',
            self::PASSWORD_SYMBOLS,
        ];

        $password = [];
        foreach ($pools as $pool) {
            $password[] = $pool[random_int(0, strlen($pool) - 1)];
        }

        $combined = implode('', $pools);
        while (count($password) < self::GENERATED_PASSWORD_LENGTH) {
            $password[] = $combined[random_int(0, strlen($combined) - 1)];
        }

        for ($i = count($password) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$password[$i], $password[$j]] = [$password[$j], $password[$i]];
        }

        return implode('', $password);
    }

    /**
     * Notifies the newly-provisioned employee of their login details so they
     * can sign in, change the generated password, and complete their
     * profile — the onboarding step this account creation exists for. The
     * plaintext password only ever exists here, in this one outbound email —
     * it is never logged or persisted anywhere else.
     */
    protected function sendWelcomeEmail(Employee $employee, string $email, string $password): void
    {
        $body = view('emails.employee-welcome', [
            'employee'     => $employee,
            'email'        => $email,
            'password'     => $password,
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
