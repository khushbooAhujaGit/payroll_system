<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Assigns a distinct color to every leave type an employee can actually
 * apply for (the $applyLeaveTypeNames set in WebLeaveController) that
 * doesn't already have one — CL/SL/EL/LOP/Maternity/Paternity/Comp Off
 * already carry colors from earlier seeding, this just fills the gaps so
 * the leave calendar has a color for every type it can show.
 */
return new class extends Migration
{
    public function up(): void
    {
        $colors = [
            'HCL' => '#93c5fd', // Half-Day Casual Leave — light blue
            'HSL' => '#fcd34d', // Half-Day Sick Leave — light amber
            'BDY' => '#f43f5e', // Birthday Leave — rose
            'ANV' => '#d946ef', // Anniversary Leave — fuchsia
            'WFH' => '#06b6d4', // Work From Home — cyan
            'EYL' => '#f97316', // Early Leave — orange
            'LTA' => '#eab308', // Late Arrival — yellow
            'PMR' => '#78716c', // Punch Miss Request — stone
        ];

        foreach ($colors as $code => $color) {
            DB::table('leave_types')->where('code', $code)->whereNull('color')->update(['color' => $color]);
        }
    }

    public function down(): void
    {
        DB::table('leave_types')->whereIn('code', ['HCL', 'HSL', 'BDY', 'ANV', 'WFH', 'EYL', 'LTA', 'PMR'])
            ->update(['color' => null]);
    }
};
