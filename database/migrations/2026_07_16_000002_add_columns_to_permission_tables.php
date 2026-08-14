<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Small additive columns on top of Spatie's default permissions/roles
 * schema: permission_group_id links a permission to a permission_groups
 * row (created via the Role & Permissions admin UI), description is just
 * free-text context shown in that same UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_group_id')->nullable()->after('id');
            $table->string('description', 255)->nullable()->after('guard_name');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->string('description', 255)->nullable()->after('guard_name');
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn(['permission_group_id', 'description']);
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
