<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds "Anniversary Leave" as a global leave type, same shape as the
 * existing Birthday Leave (BDY) seeded in
 * 2026_05_25_003_fix_leave_types_global_and_seed_tracker_types.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('leave_types')->insertOrIgnore([
            'company_id'          => null,
            'name'                => 'Anniversary Leave',
            'code'                => 'ANV',
            'category'            => 'casual',
            'is_paid'             => true,
            'is_carry_forward'    => false,
            'max_carry_forward'   => 0,
            'is_encashable'       => false,
            'is_half_day_allowed' => false,
            'requires_document'   => false,
            'document_required_after_days' => 0,
            'min_days'            => 0,
            'applicable_on_probation' => true,
            'notice_days_required' => 0,
            'gender_specific'     => 'all',
            'is_lop_type'         => false,
            'is_active'           => true,
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('leave_types')->where('code', 'ANV')->delete();
    }
};
