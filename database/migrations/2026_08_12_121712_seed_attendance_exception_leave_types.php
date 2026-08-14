<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        // 'LFT' is already used elsewhere in this app to mean "Employee Left"
        // (see Employee::syncLeaverStatuses()), so these use distinct codes
        // rather than anything resembling "late"/"left" to avoid a collision.
        $types = [
            ['EYL', 'Early Leave',         'other'],
            ['LTA', 'Late Arrival',        'other'],
            ['PMR', 'Punch Miss Request',  'other'],
        ];

        foreach ($types as [$code, $name, $category]) {
            DB::table('leave_types')->insertOrIgnore([
                'company_id'           => null,
                'name'                 => $name,
                'code'                 => $code,
                'category'             => $category,
                'is_paid'              => true,
                'is_carry_forward'     => false,
                'max_carry_forward'    => 0,
                'is_encashable'        => false,
                'is_half_day_allowed'  => false,
                'requires_document'    => false,
                'document_required_after_days' => 0,
                'min_days'             => 0,
                'applicable_on_probation' => true,
                'notice_days_required' => 0,
                'gender_specific'      => 'all',
                'is_lop_type'          => false,
                'is_active'            => true,
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('leave_types')->whereIn('code', ['EYL', 'LTA', 'PMR'])->delete();
    }
};
