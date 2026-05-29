<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Change status from ENUM to varchar(20) so we can use any status string
        // without needing a migration every time a new status is added.
        // Existing values: pending, deducted, waived → preserved.
        // New values added by lock logic: paid, due.
        DB::statement("ALTER TABLE loan_installments MODIFY status VARCHAR(20) NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        // Revert to original ENUM (any non-enum values become 'pending')
        DB::statement("UPDATE loan_installments SET status = 'pending' WHERE status NOT IN ('pending','deducted','waived')");
        DB::statement("ALTER TABLE loan_installments MODIFY status ENUM('pending','deducted','waived') NOT NULL DEFAULT 'pending'");
    }
};
