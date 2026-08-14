<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Widens reimbursement_tracker_rows.amount from decimal(12,2) to TEXT so it
 * can hold encrypted ciphertext — see ReimbursementTrackerRow's
 * EncryptedDecimal cast. Raw ALTER TABLE, matching the pattern already used
 * in 2026_07_15_000001_add_column_encryption_support.php (no doctrine/dbal
 * dependency in this project).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE reimbursement_tracker_rows MODIFY amount TEXT NOT NULL');
    }

    public function down(): void
    {
        // Not reverted: once rows contain ciphertext, shrinking back to
        // decimal(12,2) would corrupt the data. Decrypt first if a true
        // rollback is ever needed.
    }
};
