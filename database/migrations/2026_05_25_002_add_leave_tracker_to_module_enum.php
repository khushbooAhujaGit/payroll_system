<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE excel_upload_logs
            MODIFY module ENUM(
                'employee',
                'attendance',
                'leave_balance',
                'leave_tracker',
                'salary_revision',
                'loan',
                'reimbursement',
                'bonus',
                'contractor',
                'holiday',
                'bio_id',
                'other'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE excel_upload_logs
            MODIFY module ENUM(
                'employee',
                'attendance',
                'leave_balance',
                'salary_revision',
                'loan',
                'reimbursement',
                'bonus',
                'contractor',
                'holiday',
                'bio_id',
                'other'
            ) NOT NULL
        ");
    }
};
