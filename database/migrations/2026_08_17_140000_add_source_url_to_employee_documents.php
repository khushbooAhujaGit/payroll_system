<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bulk-import document columns can carry a OneDrive share link instead of a
 * file. Until actual downloading is wired up (needs Files.Read.All on the
 * Graph app registration — not yet confirmed granted), the link is stored
 * here so it isn't lost, with file_path left null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_documents', function (Blueprint $table) {
            $table->string('source_url', 1000)->nullable()->after('file_path');
        });
    }

    public function down(): void
    {
        Schema::table('employee_documents', function (Blueprint $table) {
            $table->dropColumn('source_url');
        });
    }
};
