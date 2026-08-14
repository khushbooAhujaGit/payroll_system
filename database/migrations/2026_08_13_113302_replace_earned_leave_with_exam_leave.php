<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Item #14: "Remove Earned Leave and add Exam Leave, for this period salary
 * will not be computed, remove it from Net Working Days".
 *
 * "Exam Leave" already existed as a leave_types row (code 'EL') — but that
 * collided with "Earned Leave", which already used code 'EL'. Since
 * leave_type is stored as a plain string code on tracker records, two types
 * sharing one code would be indistinguishable in the data. This gives Exam
 * Leave its own code before anything else touches it.
 *
 * "Remove" Earned Leave means deactivated, not deleted — existing historical
 * leave_tracker_records/leave_balances referencing it are left untouched, it
 * just stops being offered for new applications (matches how "Remove Blood
 * Group" only hid that form field earlier, rather than deleting data).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('leave_types')->where('id', 3)->where('code', 'EL')->where('name', 'Earned Leave')
            ->update(['is_active' => false]);

        DB::table('leave_types')->where('id', 30)->where('name', 'Exam Leave')
            ->update(['code' => 'EXL', 'is_active' => true, 'is_lop_type' => true, 'color' => '#a3a3a3']);
    }

    public function down(): void
    {
        DB::table('leave_types')->where('id', 3)->where('code', 'EL')->where('name', 'Earned Leave')
            ->update(['is_active' => true]);

        DB::table('leave_types')->where('id', 30)->where('name', 'Exam Leave')
            ->update(['code' => 'EL', 'is_active' => false]);
    }
};
