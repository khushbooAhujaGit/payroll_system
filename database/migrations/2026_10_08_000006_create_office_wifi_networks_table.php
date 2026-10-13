<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-managed office wifi networks — connecting to one of these auto
 * marks an employee in, disconnecting marks them out (see
 * ATTENDANCE_APP_PLAN.md §3.2). Many rows per branch; branch_id is
 * nullable since some networks span a whole site rather than one branch.
 * No real FK constraints — companies/branches/users are MyISAM, which
 * InnoDB refuses to reference (error 1824); plain indexed columns instead,
 * application-level integrity only, matching the rest of this codebase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_wifi_networks', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('company_id');
            $table->foreignId('branch_id')->nullable();
            $table->string('ssid', 100);
            $table->string('bssid', 17)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_wifi_networks');
    }
};
