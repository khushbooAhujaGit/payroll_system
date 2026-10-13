<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The "Level (optional)" field on the Add/Edit Designation forms has always
 * referenced this column, but it was never actually created — every write
 * to it silently failed via mass-assignment guarding, which is why every
 * existing designation shows "—" for Level. See WebDepartmentController's
 * store/updateDesignation and Designation::$fillable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('designations', function (Blueprint $table) {
            $table->unsignedTinyInteger('level')->nullable()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('designations', function (Blueprint $table) {
            $table->dropColumn('level');
        });
    }
};
