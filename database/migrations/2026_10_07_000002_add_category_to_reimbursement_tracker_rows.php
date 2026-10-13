<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item 63: employees pick a Reimbursement Category when submitting a claim,
 * from a fixed 16-value list (see ReimbursementTrackerRow::CATEGORIES).
 * Nullable — HR's manual Tracker entry and bulk Excel import don't require
 * one, and existing rows predate this column entirely.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reimbursement_tracker_rows', function (Blueprint $table) {
            $table->string('category', 60)->nullable()->after('remarks');
        });
    }

    public function down(): void
    {
        Schema::table('reimbursement_tracker_rows', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
