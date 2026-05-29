<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_tracker_records', function (Blueprint $table) {
            $table->boolean('is_approved')->default(true)->after('is_active')
                  ->comment('false = leave not approved → counts as LOP in salary report');
        });
    }

    public function down(): void
    {
        Schema::table('leave_tracker_records', function (Blueprint $table) {
            $table->dropColumn('is_approved');
        });
    }
};
