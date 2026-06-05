<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Extend the type enum to include 'weekly_off' for auto-generated Sunday/Saturday entries
        DB::statement("ALTER TABLE holidays MODIFY COLUMN type ENUM('national','regional','optional','company','weekly_off') DEFAULT 'national'");
    }

    public function down(): void
    {
        // Remove weekly_off entries first, then revert enum
        DB::table('holidays')->where('type', 'weekly_off')->delete();
        DB::statement("ALTER TABLE holidays MODIFY COLUMN type ENUM('national','regional','optional','company') DEFAULT 'national'");
    }
};
