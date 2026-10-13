<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs the "Other" option on the Reimbursement Category dropdown — the
 * category column itself still just stores the literal "Other" (so
 * reporting/grouping by category doesn't fragment into one-off values),
 * while the free text the employee/HR typed to say what it actually was
 * lives here instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reimbursement_tracker_rows', function (Blueprint $table) {
            $table->string('other_category_text', 150)->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('reimbursement_tracker_rows', function (Blueprint $table) {
            $table->dropColumn('other_category_text');
        });
    }
};
