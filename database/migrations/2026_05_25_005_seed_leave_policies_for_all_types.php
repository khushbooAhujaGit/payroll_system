<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1. Update existing CL and SL policy days
        DB::table('leave_policies')
            ->join('leave_types', 'leave_policies.leave_type_id', '=', 'leave_types.id')
            ->where('leave_types.code', 'CL')
            ->update(['leave_policies.days_per_year' => 18.00]);

        DB::table('leave_policies')
            ->join('leave_types', 'leave_policies.leave_type_id', '=', 'leave_types.id')
            ->where('leave_types.code', 'SL')
            ->update(['leave_policies.days_per_year' => 7.00]);

        // 2. Insert policies for every leave_type that has no policy yet
        // Using INSERT ... SELECT with NOT EXISTS to be idempotent
        DB::statement("
            INSERT INTO leave_policies
                (entity_id, leave_type_id, name, days_per_year, accrual_type, accrual_days,
                 accrual_on_day, proration, sandwich_policy, min_balance_to_apply,
                 allow_negative_balance, max_negative_days, is_active, created_at, updated_at)
            SELECT
                NULL,
                lt.id,
                lt.name,
                0.00,
                'annual',
                NULL,
                1,
                'none',
                0,
                0,
                0,
                0,
                1,
                NOW(),
                NOW()
            FROM leave_types lt
            WHERE lt.is_active = 1
              AND NOT EXISTS (
                  SELECT 1 FROM leave_policies lp
                  WHERE lp.leave_type_id = lt.id
                    AND lp.entity_id IS NULL
              )
        ");
    }

    public function down(): void
    {
        // Remove policies added by this migration (those with 0 days and no entity_id,
        // excluding the original 4 that were inserted before)
        // Not reversible cleanly — skip
    }
};
