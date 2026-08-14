<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widens columns that will hold encrypted (ciphertext) values to TEXT,
 * and adds SHA-256 blind-index columns for the two fields that still need
 * exact-match lookups (PAN dedup on import, account_number upsert key)
 * once their plaintext columns are encrypted.
 *
 * Raw ALTER TABLE is used instead of Blueprint::change() to avoid a
 * doctrine/dbal dependency, which this project doesn't have installed.
 */
return new class extends Migration
{
    public function up(): void
    {
        // employees
        DB::statement('ALTER TABLE employees MODIFY date_of_birth TEXT NULL');
        DB::statement('ALTER TABLE employees MODIFY personal_email TEXT NULL');
        DB::statement('ALTER TABLE employees MODIFY mobile TEXT NULL');
        DB::statement('ALTER TABLE employees MODIFY alternate_phone TEXT NULL');
        DB::statement('ALTER TABLE employees MODIFY emergency_contact_name TEXT NULL');
        DB::statement('ALTER TABLE employees MODIFY emergency_contact_phone TEXT NULL');

        // employee_bank_details
        DB::statement('ALTER TABLE employee_bank_details MODIFY account_number TEXT NOT NULL');
        DB::statement('ALTER TABLE employee_bank_details MODIFY ifsc_code TEXT NOT NULL');
        DB::statement('ALTER TABLE employee_bank_details MODIFY micr_code TEXT NULL');
        DB::statement('ALTER TABLE employee_bank_details MODIFY account_holder_name TEXT NOT NULL');

        Schema::table('employee_bank_details', function (Blueprint $table) {
            $table->string('account_number_hash', 64)->nullable()->after('account_number')->index();
        });

        // employee_statutory_details
        DB::statement('ALTER TABLE employee_statutory_details MODIFY uan_number TEXT NULL');
        DB::statement('ALTER TABLE employee_statutory_details MODIFY pf_number TEXT NULL');
        DB::statement('ALTER TABLE employee_statutory_details MODIFY esic_number TEXT NULL');
        DB::statement('ALTER TABLE employee_statutory_details MODIFY pan_number TEXT NULL');
        DB::statement('ALTER TABLE employee_statutory_details MODIFY aadhaar_number TEXT NULL');
        DB::statement('ALTER TABLE employee_statutory_details MODIFY pt_number TEXT NULL');
        DB::statement('ALTER TABLE employee_statutory_details MODIFY nps_pran TEXT NULL');

        Schema::table('employee_statutory_details', function (Blueprint $table) {
            $table->string('pan_number_hash', 64)->nullable()->after('pan_number')->index();
        });

        // employee_documents
        DB::statement('ALTER TABLE employee_documents MODIFY document_number TEXT NULL');

        // contractors
        DB::statement('ALTER TABLE contractors MODIFY pan TEXT NULL');
        DB::statement('ALTER TABLE contractors MODIFY gstin TEXT NULL');
        DB::statement('ALTER TABLE contractors MODIFY mobile TEXT NULL');
        DB::statement('ALTER TABLE contractors MODIFY email TEXT NULL');
        DB::statement('ALTER TABLE contractors MODIFY bank_account_number TEXT NULL');
        DB::statement('ALTER TABLE contractors MODIFY ifsc_code TEXT NULL');

        // declaration_hra
        DB::statement('ALTER TABLE declaration_hra MODIFY landlord_pan TEXT NULL');

        // employee_master_history — old_value/new_value snapshot bank/PAN/email
        // changes verbatim, so they need to move off JSON (MySQL enforces JSON
        // columns be valid JSON, which ciphertext is not) onto TEXT so they can
        // be stored via the encrypted:array cast instead.
        DB::statement('ALTER TABLE employee_master_history MODIFY old_value TEXT NULL');
        DB::statement('ALTER TABLE employee_master_history MODIFY new_value TEXT NULL');
    }

    public function down(): void
    {
        // Column widening is intentionally not reverted: once rows contain
        // ciphertext, shrinking back to the original varchar/date types would
        // truncate or corrupt data. Decrypt data first if a true rollback is needed.
        Schema::table('employee_bank_details', function (Blueprint $table) {
            $table->dropColumn('account_number_hash');
        });

        Schema::table('employee_statutory_details', function (Blueprint $table) {
            $table->dropColumn('pan_number_hash');
        });
    }
};
