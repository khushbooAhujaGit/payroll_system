<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add 'import' and 'manual' to the revision_type enum so salary
        // imports (revision_type='import') and manual sets (revision_type='manual')
        // no longer cause MySQL truncation errors.
        DB::statement("ALTER TABLE employee_salaries MODIFY COLUMN revision_type
            ENUM('joining','increment','promotion','correction','restructure','import','manual')
            NOT NULL DEFAULT 'joining'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE employee_salaries MODIFY COLUMN revision_type
            ENUM('joining','increment','promotion','correction','restructure')
            NOT NULL DEFAULT 'joining'");
    }
};
