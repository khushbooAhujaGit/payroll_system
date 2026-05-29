<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_report_overrides', function (Blueprint $table) {
            $table->decimal('original_payable_gross', 12, 2)->nullable()->after('original_bonus');
            $table->decimal('payable_gross_override', 12, 2)->nullable()->after('bonus_override');
        });
    }

    public function down(): void
    {
        Schema::table('salary_report_overrides', function (Blueprint $table) {
            $table->dropColumn(['original_payable_gross', 'payable_gross_override']);
        });
    }
};
