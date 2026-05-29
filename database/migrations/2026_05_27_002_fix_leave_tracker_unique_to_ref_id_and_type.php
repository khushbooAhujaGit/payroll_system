<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Change the unique key on leave_tracker_records from leave_ref_id alone
 * to (leave_ref_id, leave_type).
 *
 * Reason: an employee can record WFH + SL on the same day. The tracker sheet
 * assigns the same leave_ref_id to both rows (ID is generated per date, not per
 * leave occurrence). The old single-column unique caused the second leave type to
 * be silently dropped on upsert. The composite key allows both to coexist while
 * still preventing true duplicates (same ref_id + same leave_type uploaded twice).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_tracker_records', function (Blueprint $table) {
            $table->dropUnique(['leave_ref_id']);
            $table->unique(['leave_ref_id', 'leave_type'], 'ltr_ref_type_unique');
        });
    }

    public function down(): void
    {
        Schema::table('leave_tracker_records', function (Blueprint $table) {
            $table->dropUnique('ltr_ref_type_unique');
            $table->unique(['leave_ref_id']);
        });
    }
};
