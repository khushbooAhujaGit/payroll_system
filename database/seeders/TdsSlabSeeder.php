<?php

namespace Database\Seeders;

use App\Models\TdsSlab;
use Illuminate\Database\Seeder;

class TdsSlabSeeder extends Seeder
{
    public function run(): void
    {
        TdsSlab::query()->delete();

        $slabs = [];

        // ──────────────────────────────────────────────
        // FY 2024-25 (financial_year = 2024)
        // ──────────────────────────────────────────────

        // OLD REGIME FY 2024-25
        $fy = 2024;
        $oldRegime = [
            ['income_from' => 0,        'income_to' => 250000,  'tax_rate' => 0.00],
            ['income_from' => 250001,    'income_to' => 500000,  'tax_rate' => 5.00],
            ['income_from' => 500001,    'income_to' => 1000000, 'tax_rate' => 20.00],
            ['income_from' => 1000001,   'income_to' => null,    'tax_rate' => 30.00],
        ];
        foreach ($oldRegime as $slab) {
            $slabs[] = array_merge($slab, ['financial_year' => $fy, 'regime' => 'old']);
        }

        // NEW REGIME FY 2024-25 (post Budget 2023)
        $newRegime = [
            ['income_from' => 0,        'income_to' => 300000,  'tax_rate' => 0.00],
            ['income_from' => 300001,    'income_to' => 600000,  'tax_rate' => 5.00],
            ['income_from' => 600001,    'income_to' => 900000,  'tax_rate' => 10.00],
            ['income_from' => 900001,    'income_to' => 1200000, 'tax_rate' => 15.00],
            ['income_from' => 1200001,   'income_to' => 1500000, 'tax_rate' => 20.00],
            ['income_from' => 1500001,   'income_to' => null,    'tax_rate' => 30.00],
        ];
        foreach ($newRegime as $slab) {
            $slabs[] = array_merge($slab, ['financial_year' => $fy, 'regime' => 'new']);
        }

        // ──────────────────────────────────────────────
        // FY 2025-26 (financial_year = 2025)
        // ──────────────────────────────────────────────

        $fy = 2025;
        // OLD REGIME (unchanged)
        foreach ($oldRegime as $slab) {
            $slabs[] = array_merge($slab, ['financial_year' => $fy, 'regime' => 'old']);
        }

        // NEW REGIME FY 2025-26 (Budget 2025 changes - revised slabs)
        $newRegime2025 = [
            ['income_from' => 0,        'income_to' => 400000,  'tax_rate' => 0.00],
            ['income_from' => 400001,    'income_to' => 800000,  'tax_rate' => 5.00],
            ['income_from' => 800001,    'income_to' => 1200000, 'tax_rate' => 10.00],
            ['income_from' => 1200001,   'income_to' => 1600000, 'tax_rate' => 15.00],
            ['income_from' => 1600001,   'income_to' => 2000000, 'tax_rate' => 20.00],
            ['income_from' => 2000001,   'income_to' => 2400000, 'tax_rate' => 25.00],
            ['income_from' => 2400001,   'income_to' => null,    'tax_rate' => 30.00],
        ];
        foreach ($newRegime2025 as $slab) {
            $slabs[] = array_merge($slab, ['financial_year' => $fy, 'regime' => 'new']);
        }

        foreach ($slabs as $slab) {
            TdsSlab::create($slab);
        }

        $this->command->info("✓ TDS slabs seeded for FY2024-25 and FY2025-26 (old + new regime).");
    }
}
