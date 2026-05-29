<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add percent_deduction column to employee_loans
        if (!Schema::hasColumn('employee_loans', 'percent_deduction')) {
            Schema::table('employee_loans', function (Blueprint $table) {
                $table->decimal('percent_deduction', 5, 2)->nullable()->after('total_emis')
                    ->comment('Percentage of salary to deduct per month as EMI (optional)');
            });
        }

        // Seed SA (Salary Advance) loan type for every entity that has employees
        $now = now()->toDateTimeString();
        $entityIds = DB::table('employees')->distinct()->pluck('entity_id')->toArray();

        foreach ($entityIds as $entityId) {
            $exists = DB::table('loan_types')
                ->where('entity_id', $entityId)
                ->where('code', 'SA')
                ->exists();

            if (!$exists) {
                DB::table('loan_types')->insert([
                    'entity_id'     => $entityId,
                    'code'          => 'SA',
                    'name'          => 'Salary Advance',
                    'interest_rate' => 0,
                    'max_amount'    => 999999,
                    'max_emi'       => 12,
                    'is_active'     => 1,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('employee_loans', function (Blueprint $table) {
            $table->dropColumn('percent_deduction');
        });
    }
};
