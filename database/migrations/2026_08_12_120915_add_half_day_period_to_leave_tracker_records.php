<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leave_tracker_records', function (Blueprint $table) {
            $table->enum('half_day_period', ['first_half', 'second_half'])->nullable()->after('leave_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_tracker_records', function (Blueprint $table) {
            $table->dropColumn('half_day_period');
        });
    }
};
