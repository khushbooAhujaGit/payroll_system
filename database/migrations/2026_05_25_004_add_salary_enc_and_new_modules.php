<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Encrypted gross salary for import feature
        Schema::table('employee_salaries', function (Blueprint $table) {
            $table->text('gross_monthly_enc')->nullable()->after('gross_monthly');
        });

        // Add 'standard_report' to excel_upload_logs module enum if not already present
        // Using raw query for MySQL enum alteration
        $columns = DB::select("SHOW COLUMNS FROM excel_upload_logs LIKE 'module'");
        if (!empty($columns)) {
            $type = $columns[0]->Type ?? '';
            if (strpos($type, 'standard_report') === false) {
                DB::statement("ALTER TABLE excel_upload_logs MODIFY COLUMN module ENUM(
                    'employee','bio_id','attendance','leave','leave_tracker',
                    'salary','tds','loan','reimbursement','payroll',
                    'holiday','helpdesk','master','department','standard_report'
                ) NOT NULL");
            }
        }
    }

    public function down(): void
    {
        Schema::table('employee_salaries', function (Blueprint $table) {
            $table->dropColumn('gross_monthly_enc');
        });
    }
};
