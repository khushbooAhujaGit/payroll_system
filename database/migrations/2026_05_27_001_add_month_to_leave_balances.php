<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a `month` column (1–12) to leave_balances so that records can be stored
 * at month-level granularity within a financial year, rather than as annual totals.
 *
 * The unique index is rebuilt to include month; existing annual rows get month = 0
 * (meaning "full-year / manual entry") so they don't conflict with the new monthly rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_balances', function (Blueprint $table) {
            // month = 0  → annual / manual row (pre-existing)
            // month = 1–12 → monthly row created by sync
            $table->tinyInteger('month')->unsigned()->default(0)->after('year');
        });

        // Drop old unique index and add one that includes month
        Schema::table('leave_balances', function (Blueprint $table) {
            $table->dropUnique(['employee_id', 'leave_type_id', 'year']);
            $table->unique(['employee_id', 'leave_type_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::table('leave_balances', function (Blueprint $table) {
            $table->dropUnique(['employee_id', 'leave_type_id', 'year', 'month']);
        });

        Schema::table('leave_balances', function (Blueprint $table) {
            $table->unique(['employee_id', 'leave_type_id', 'year']);
            $table->dropColumn('month');
        });
    }
};
