<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_shadow_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('emp_id_text', 50);
            $table->string('employee_name', 200)->nullable();

            // Plain-text salary values — this table is the owner's private record
            $table->decimal('gross_monthly', 12, 2)->default(0);
            $table->decimal('ctc_monthly',   12, 2)->default(0);
            $table->decimal('ctc_annual',    12, 2)->default(0);

            $table->date('effective_from');
            $table->string('import_month', 7)->nullable(); // YYYY-MM
            $table->enum('source', ['import', 'manual'])->default('import');
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'effective_from']);
            $table->index('import_month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_shadow_log');
    }
};
