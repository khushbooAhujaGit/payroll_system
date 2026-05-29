<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Title (Mr / Mrs / Ms / Dr / Prof)
            $table->string('title', 10)->nullable()->after('id');

            // External reference ID — "New Emp ID" from Excel / old system ID
            $table->string('external_emp_id', 50)->nullable()->after('employee_id');

            // Primary biometric / attendance machine ID (rename of badge_number)
            // badge_number kept as-is; badge_id is the attendance-fetch key
            $table->string('badge_id', 50)->nullable()->after('badge_number');

            // Team leader (self-referencing FK)
            $table->foreignId('team_leader_id')
                  ->nullable()
                  ->constrained('employees')
                  ->nullOnDelete()
                  ->after('reporting_manager_id');

            // Attendance group / working mode label (e.g. "Regular", "Shift-A", "WFH")
            $table->string('attendance_group', 50)->nullable()->after('work_type');

            // Aadhaar card scan file path (from bulk upload)
            $table->string('aadhaar_document_path')->nullable()->after('photo');

            // Date of offer letter
            $table->date('offer_letter_date')->nullable()->after('date_of_joining');
        });

        // Index on external_emp_id for fast dedup lookups during import
        Schema::table('employees', function (Blueprint $table) {
            $table->index(['entity_id', 'external_emp_id'], 'emp_entity_external_idx');
            $table->index('badge_id', 'emp_badge_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex('emp_entity_external_idx');
            $table->dropIndex('emp_badge_id_idx');
            $table->dropForeign(['team_leader_id']);
            $table->dropColumn([
                'title', 'external_emp_id', 'badge_id', 'team_leader_id',
                'attendance_group', 'aadhaar_document_path', 'offer_letter_date',
            ]);
        });
    }
};
