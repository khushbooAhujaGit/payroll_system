<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_report_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->char('year_month', 7)->comment('e.g. 2025-04');
            $table->json('snapshot_data')->comment('Full computed row as JSON — frozen at lock time');
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'year_month'], 'srs_emp_month_unique');
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->index('year_month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_report_snapshots');
    }
};
