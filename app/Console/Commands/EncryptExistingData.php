<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * One-time migration: encrypts existing plaintext values in columns that were
 * just switched to Eloquent's 'encrypted' cast (see the Employee, EmployeeBankDetail,
 * EmployeeStatutoryDetail, EmployeeDocument, Contractor, DeclarationHra,
 * EmployeeMasterHistory models and database/migrations/2026_07_15_000001_add_column_encryption_support.php).
 *
 * Must run before those models are used normally — loading a row through
 * Eloquent applies the encrypted cast on read, which throws if the stored
 * value is still plaintext. This command talks to the tables directly via
 * the query builder (bypassing casts) so it can read existing plaintext and
 * write back ciphertext safely.
 *
 * Idempotent: any value that already decrypts successfully is left alone,
 * so this is safe to re-run (e.g. after adding more rows via a fresh import).
 */
class EncryptExistingData extends Command
{
    protected $signature = 'encrypt:existing-data {--dry-run : Report what would change without writing}';
    protected $description = 'Encrypt existing plaintext PII/financial columns in place';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Dry run — no data will be written.');
        }

        $this->migrateTable('employees', [
            'date_of_birth', 'personal_email', 'mobile', 'alternate_phone',
            'current_address', 'permanent_address', 'emergency_contact_name', 'emergency_contact_phone',
        ], $dryRun);

        $this->migrateTable('employee_bank_details', [
            'account_number', 'ifsc_code', 'micr_code', 'account_holder_name',
        ], $dryRun, function (int $id, array $plaintext) use ($dryRun) {
            if (array_key_exists('account_number', $plaintext)) {
                $hash = hash('sha256', $plaintext['account_number']);
                if (!$dryRun) {
                    DB::table('employee_bank_details')->where('id', $id)->update(['account_number_hash' => $hash]);
                }
            }
        });

        $this->migrateTable('employee_statutory_details', [
            'uan_number', 'pf_number', 'esic_number', 'pan_number', 'aadhaar_number', 'pt_number', 'nps_pran',
        ], $dryRun, function (int $id, array $plaintext) use ($dryRun) {
            if (array_key_exists('pan_number', $plaintext)) {
                $hash = hash('sha256', strtoupper($plaintext['pan_number']));
                if (!$dryRun) {
                    DB::table('employee_statutory_details')->where('id', $id)->update(['pan_number_hash' => $hash]);
                }
            }
        });

        $this->migrateTable('employee_documents', ['document_number'], $dryRun);

        $this->migrateTable('contractors', [
            'pan', 'gstin', 'mobile', 'email', 'address', 'bank_account_number', 'ifsc_code',
        ], $dryRun);

        $this->migrateTable('declaration_hra', ['landlord_pan', 'rental_address'], $dryRun);

        $this->migrateTable('employee_master_history', ['old_value', 'new_value'], $dryRun);

        $this->migrateTable('reimbursement_tracker_rows', ['amount'], $dryRun);

        $this->info($dryRun ? 'Dry run complete.' : 'Done.');

        return self::SUCCESS;
    }

    private function migrateTable(string $table, array $columns, bool $dryRun, ?callable $afterRow = null): void
    {
        $this->info("Processing {$table} (" . implode(', ', $columns) . ')...');

        $updated = 0;
        $skipped = 0;

        DB::table($table)->orderBy('id')->chunkById(200, function ($rows) use ($table, $columns, $dryRun, $afterRow, &$updated, &$skipped) {
            foreach ($rows as $row) {
                $update    = [];
                $plaintext = [];

                foreach ($columns as $column) {
                    $value = $row->{$column} ?? null;
                    if ($value === null || $value === '') {
                        continue;
                    }
                    $value = (string) $value;

                    if ($this->looksAlreadyEncrypted($value)) {
                        continue;
                    }

                    $plaintext[$column] = $value;
                    $update[$column]    = Crypt::encryptString($value);
                }

                if (!empty($update)) {
                    $updated++;
                    if (!$dryRun) {
                        DB::table($table)->where('id', $row->id)->update($update);
                    }
                } else {
                    $skipped++;
                }

                if ($afterRow) {
                    $afterRow($row->id, $plaintext);
                }
            }
        });

        $this->line("  {$table}: {$updated} row(s) encrypted, {$skipped} row(s) already encrypted or empty.");
    }

    private function looksAlreadyEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);
            return true;
        } catch (DecryptException) {
            return false;
        }
    }
}
