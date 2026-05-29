<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add soft-versioning to leave_tracker_records.
 *
 * is_active = false  → original record superseded by an edit; kept for audit
 * superseded_by_id   → the ID of the newer active record that replaced this one
 *
 * All existing rows are backfilled to is_active = 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_tracker_records', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('uploaded_by');
            $table->unsignedBigInteger('superseded_by_id')->nullable()->after('is_active');
            $table->foreign('superseded_by_id')
                  ->references('id')->on('leave_tracker_records')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leave_tracker_records', function (Blueprint $table) {
            $table->dropForeign(['superseded_by_id']);
            $table->dropColumn(['is_active', 'superseded_by_id']);
        });
    }
};
