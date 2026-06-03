<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reimbursement_tracker_rows', function (Blueprint $table) {
            $table->date('expense_date')->nullable()->after('year_month');
            $table->string('emp_id')->nullable()->after('expense_date');
        });
    }

    public function down(): void
    {
        Schema::table('reimbursement_tracker_rows', function (Blueprint $table) {
            $table->dropColumn(['expense_date', 'emp_id']);
        });
    }
};
