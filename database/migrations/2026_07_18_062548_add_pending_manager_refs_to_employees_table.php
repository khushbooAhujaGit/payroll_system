<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a bulk-imported employee's "Reporting Manager" / "Team Leader" can't
 * be resolved yet (that person hasn't been imported at all so far — not even
 * in a later row of the same file), the raw reference text from the sheet
 * is stored here so a LATER, separate import of that manager can go back
 * and backfill it. Cleared to null as soon as it resolves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('pending_reporting_manager_ref', 150)->nullable()->after('reporting_manager_id');
            $table->string('pending_team_leader_ref', 150)->nullable()->after('team_leader_id');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['pending_reporting_manager_ref', 'pending_team_leader_ref']);
        });
    }
};
