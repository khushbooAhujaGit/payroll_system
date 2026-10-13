<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Same reasoning as the file_path migration right before this one. */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE employee_documents MODIFY file_name VARCHAR(191) NULL');
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE employee_documents MODIFY file_name VARCHAR(191) NOT NULL DEFAULT ''");
    }
};
