<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * compliance_challans was missed by 2026_06_02_000001_replace_entity_id_with_company_id
 * — every other statutory table (pf_settings, esic_settings, pt_slabs, lwf_slabs) got
 * renamed, this one didn't, so every company_id query against it (WebStatutoryController,
 * the Statutory\StatutoryController API) has been hitting a nonexistent column.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('compliance_challans', 'entity_id') && !Schema::hasColumn('compliance_challans', 'company_id')) {
            Schema::table('compliance_challans', function (Blueprint $table) {
                $table->renameColumn('entity_id', 'company_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('compliance_challans', 'company_id') && !Schema::hasColumn('compliance_challans', 'entity_id')) {
            Schema::table('compliance_challans', function (Blueprint $table) {
                $table->renameColumn('company_id', 'entity_id');
            });
        }
    }
};
