<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reimbursement_report_snapshots', function (Blueprint $table) {
            $table->id();
            $table->char('year_month', 7)->unique()->comment('e.g. 2025-04');
            $table->json('snapshot_data')->comment('Array of all approved rows frozen at lock time');
            $table->integer('row_count')->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->timestamps();

            $table->index('year_month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reimbursement_report_snapshots');
    }
};
