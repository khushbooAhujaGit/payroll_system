<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records exactly how many days of a CL application were set off against
 * Comp-Off (vs deducted from CL itself) at approval time — needed so
 * cancelling an approved leave later can reverse the SAME split, instead of
 * naively crediting the full amount back to CL and leaving the Comp-Off
 * portion permanently lost. See LeaveController::deductLeaveBalance() /
 * restoreLeaveBalance().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->decimal('comp_off_setoff_days', 5, 2)->default(0)->after('days');
        });
    }

    public function down(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->dropColumn('comp_off_setoff_days');
        });
    }
};
