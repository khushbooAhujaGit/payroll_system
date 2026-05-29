<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restructure employee_bio_ids to store type / number / machine separately.
 *
 * Bio ID format: I-20, II-6, III-8
 *   bio_type    = I / II / III
 *   machine_name = 10 / 12 / K   (device label for each type)
 *   bio_number  = numeric part (20, 6, 8)
 *   bio_id_full = the full string stored as-is for quick lookup
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_bio_ids', function (Blueprint $table) {
            // Drop columns from the original migration
            $table->dropUnique(['bio_id']);
            $table->dropColumn(['bio_id', 'device_name', 'device_location']);
        });

        Schema::table('employee_bio_ids', function (Blueprint $table) {
            // Full Bio ID string — e.g. "I-20" — globally unique
            $table->string('bio_id_full', 20)->unique()->after('employee_id');

            // Type prefix (I / II / III)
            $table->string('bio_type', 5)->after('bio_id_full');

            // Numeric portion of the bio ID
            $table->unsignedSmallInteger('bio_number')->after('bio_type');

            // Device/machine label mapped from the type
            // I → 10,  II → 12,  III → K
            $table->string('machine_name', 20)->after('bio_number');

            $table->index('bio_id_full');
        });
    }

    public function down(): void
    {
        Schema::table('employee_bio_ids', function (Blueprint $table) {
            $table->dropIndex(['bio_id_full']);
            $table->dropUnique(['bio_id_full']);
            $table->dropColumn(['bio_id_full', 'bio_type', 'bio_number', 'machine_name']);
        });

        Schema::table('employee_bio_ids', function (Blueprint $table) {
            $table->string('bio_id', 50)->unique()->after('employee_id');
            $table->string('device_name', 100)->nullable()->after('bio_id');
            $table->string('device_location', 100)->nullable()->after('device_name');
        });
    }
};
