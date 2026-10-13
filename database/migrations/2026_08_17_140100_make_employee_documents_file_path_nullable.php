<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A bulk-import row can now create an EmployeeDocument that's just a pending
 * OneDrive link (source_url set, no file yet) — file_path was NOT NULL with
 * no default, which made that state impossible to save.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE employee_documents MODIFY file_path VARCHAR(191) NULL');
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE employee_documents MODIFY file_path VARCHAR(191) NOT NULL DEFAULT ''");
    }
};
