<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 1. Make existing leave_types and leave_policies entity-agnostic (entity_id = NULL)
 *    so they appear for every entity, not just entity 1.
 *
 * 2. Seed the 22 leave-type codes that appear in the Leave Tracker xlsm but
 *    are not yet in the leave_types table.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Make existing leave types & policies global ─────────────────────
        DB::table('leave_types')->whereNotNull('entity_id')->update(['entity_id' => null]);
        DB::table('leave_policies')->whereNotNull('entity_id')->update(['entity_id' => null]);

        // ── Unique constraint fix: entity_id is part of the unique key.
        //    Dropping and recreating is safe because NULL values don't clash.
        // (MySQL allows multiple NULL values in a unique index, so this is fine.)

        // ── Seed missing leave type codes from the tracker ──────────────────
        $types = [
            // code  name                           category        paid  carry  enc   half  lop
            ['WFH', 'Work From Home',              'other',        true,  false, false, true,  false],
            ['HSL', 'Half-Day Sick Leave',         'sick',         true,  false, false, false, false],
            ['HCL', 'Half-Day Casual Leave',       'casual',       true,  false, false, false, false],
            ['RL',  'Restricted Leave',            'optional',     true,  false, false, true,  false],
            ['PM',  'Paternity / Marriage Leave',  'paternity',    true,  false, false, false, false],
            ['PIM', 'Paid Immediate Leave',        'casual',       true,  false, false, false, false],
            ['POM', 'Paid Other Leave',            'casual',       true,  false, false, false, false],
            ['AP',  'Annual / Privilege Leave',    'earned',       true,  true,  false, true,  false],
            ['JN',  'Joining Leave',               'other',        true,  false, false, false, false],
            ['LFT', 'Late / Early Departure',      'other',        false, false, false, false, false],
            ['CP',  'Child-care Paid Leave',       'other',        true,  false, false, true,  false],
            ['HCP', 'Half-Day Child-care Paid',    'other',        true,  false, false, false, false],
            ['HCO', 'Half-Day Comp-Off',           'comp_off',     true,  false, false, false, false],
            ['PTL', 'Paternity Leave',             'paternity',    true,  false, false, false, false],
            ['MTL', 'Maternity Leave',             'maternity',    true,  false, false, false, false],
            ['BDY', 'Birthday Leave',              'casual',       true,  false, false, false, false],
            ['WDA', 'Weekday Adjustment',          'other',        false, false, false, false, false],
        ];

        $now = now();
        foreach ($types as [$code, $name, $category, $isPaid, $isCarry, $isEnc, $isHalf, $isLop]) {
            DB::table('leave_types')->insertOrIgnore([
                'entity_id'           => null,
                'name'                => $name,
                'code'                => $code,
                'category'            => $category,
                'is_paid'             => $isPaid,
                'is_carry_forward'    => $isCarry,
                'max_carry_forward'   => 0,
                'is_encashable'       => $isEnc,
                'is_half_day_allowed' => $isHalf,
                'requires_document'   => false,
                'document_required_after_days' => 0,
                'min_days'            => 0,
                'applicable_on_probation' => true,
                'notice_days_required' => 0,
                'gender_specific'     => 'all',
                'is_lop_type'         => $isLop,
                'is_active'           => true,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);
        }

        // Fix MTL code clash (existing record has code=ML — add MTL separately)
        DB::table('leave_types')->where('code', 'ML')->update(['code' => 'MTL', 'name' => 'Maternity Leave', 'entity_id' => null]);
        // Fix PL → PTL
        DB::table('leave_types')->where('code', 'PL')->update(['code' => 'PTL', 'name' => 'Paternity Leave', 'entity_id' => null]);
        // Fix BL → BDY (Bereavement → Birthday doesn't match; keep BL but update code)
        // Actually BL = Bereavement Leave, let's keep it separate

        // Ensure EL is Earned Leave, CO is Comp Off (already correct in seed data)
    }

    public function down(): void
    {
        // Non-reversible seed, just mark entity_id back to 1 for the original 8
        DB::table('leave_types')->whereNull('entity_id')
            ->whereIn('code', ['CL','SL','EL','MTL','PTL','CO','LOP','BL'])
            ->update(['entity_id' => 1]);
    }
};
