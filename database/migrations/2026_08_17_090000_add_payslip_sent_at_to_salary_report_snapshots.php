<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks whether a payslip email has already gone out for this employee's
 * locked month, so "Send Payslips" can show accurate status and isn't
 * silently re-sendable with no visible trace.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_report_snapshots', function (Blueprint $table) {
            $table->timestamp('payslip_sent_at')->nullable()->after('locked_by');
        });
    }

    public function down(): void
    {
        Schema::table('salary_report_snapshots', function (Blueprint $table) {
            $table->dropColumn('payslip_sent_at');
        });
    }
};
