<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // "Left Date" from the bulk upload template.
            // Used in salary report to clip WeekOff & Holidays to [DOJ, left_date] when
            // an employee joined or left mid-month.
            $table->date('date_of_leaving')->nullable()->after('date_of_joining');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('date_of_leaving');
        });
    }
};
