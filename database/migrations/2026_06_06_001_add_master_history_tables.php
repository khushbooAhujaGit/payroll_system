<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Track when PAN / statutory details change ─────────────────────
        // is_current = true  → the currently active record
        // is_current = false → historical / superseded record
        Schema::table('employee_statutory_details', function (Blueprint $table) {
            $table->boolean('is_current')->default(true)->after('nps_pran');
            $table->unsignedBigInteger('updated_by')->nullable()->after('is_current');
        });

        // Set all existing records as current
        \DB::table('employee_statutory_details')->update(['is_current' => true]);

        // ── Audit log for bank / statutory / email master changes ─────────
        Schema::create('employee_master_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->enum('field_type', ['bank_details', 'pan', 'email']);
            $table->json('old_value')->nullable();   // previous value (JSON object)
            $table->json('new_value')->nullable();   // new value    (JSON object)
            $table->string('changed_via', 50)->default('manual'); // 'salary_reupload' | 'manual' | 'import'
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'field_type']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_master_history');
        Schema::table('employee_statutory_details', function (Blueprint $table) {
            $table->dropColumn(['is_current', 'updated_by']);
        });
    }
};
