<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * WebAttendanceController::wfhImport() has always called
 * ExcelImportService::import() with module 'wfh_attendance' — a value that
 * was never actually added to excel_upload_logs.module's ENUM, so every WFH
 * / Client Location Attendance Upload has been failing outright with a
 * "Data truncated for column 'module'" error, never making it anywhere near
 * WfhAttendanceImporter's own logic. Discovered while adding the new
 * 'manual_attendance' module for ManualAttendanceImporter and testing it
 * end-to-end — add both now, following the enum-append pattern already
 * used by 2026_05_25_004_add_salary_enc_and_new_modules.php etc.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE excel_upload_logs MODIFY COLUMN module ENUM(
            'employee','bio_id','attendance','leave','leave_tracker',
            'salary','tds','loan','reimbursement','payroll',
            'holiday','helpdesk','master','department','standard_report',
            'wfh_attendance','manual_attendance'
        ) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE excel_upload_logs MODIFY COLUMN module ENUM(
            'employee','bio_id','attendance','leave','leave_tracker',
            'salary','tds','loan','reimbursement','payroll',
            'holiday','helpdesk','master','department','standard_report'
        ) NOT NULL");
    }
};
