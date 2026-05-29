<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_installments', function (Blueprint $table) {
            // Allow safe updateOrCreate by (loan_id, emi_year, emi_month)
            $table->unique(['employee_loan_id', 'emi_year', 'emi_month'], 'loan_inst_loan_year_month_unique');
        });
    }

    public function down(): void
    {
        Schema::table('loan_installments', function (Blueprint $table) {
            $table->dropUnique('loan_inst_loan_year_month_unique');
        });
    }
};
