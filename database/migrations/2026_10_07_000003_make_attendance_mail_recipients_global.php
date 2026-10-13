<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item 56 revised: one combined daily email covering every company (each as
 * its own section), sent to one shared recipient list — not a separate
 * email per company. Drops the per-company scoping, leaving a true
 * singleton settings row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_mail_recipients', function (Blueprint $table) {
            $table->dropUnique(['company_id']);
            $table->dropColumn('company_id');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_mail_recipients', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->unique()->nullable()->after('id');
        });
    }
};
