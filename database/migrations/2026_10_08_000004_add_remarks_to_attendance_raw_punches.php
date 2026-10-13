<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manual Attendance Upload (ManualAttendanceImporter) requires an HR Remark
 * explaining why a punch is being manually created/corrected — there was no
 * column to hold it, since biometric-machine rows never needed one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_raw_punches', function (Blueprint $table) {
            $table->text('remarks')->nullable()->after('is_absent');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_raw_punches', function (Blueprint $table) {
            $table->dropColumn('remarks');
        });
    }
};
