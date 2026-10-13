<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Employee;
use App\Models\SalaryReportSnapshot;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Builds a best-effort Form 16 summary from locked SalaryReportSnapshot
 * rows across a financial year (April-March). This is NOT a validated
 * statutory computation — it reports the actual gross/PF/PT/TDS figures
 * already recorded in the system month by month, but does not independently
 * compute HRA exemption, Chapter VI-A deductions, surcharge, or final tax
 * liability (see the disclaimer baked into the PDF itself). Treat it as a
 * reference summary, not a TRACES-issued Form 16 substitute.
 *
 * Deliberately public, per explicit instruction: files here are reachable
 * by direct URL with no login/permission check at all. Flagged and
 * confirmed before implementing — see PayslipPdfService's docblock.
 */
class Form16PdfService
{
    /** Absolute filesystem path — public_path(), not a Storage-disk-relative path. */
    public function path(int $employeeId, string $financialYear): string
    {
        return public_path("form16/{$employeeId}/{$financialYear}.pdf");
    }

    /** e.g. "2025-26" -> ['2025-04', ..., '2026-03'] */
    public function monthsForFy(string $financialYear): array
    {
        [$startYear] = explode('-', $financialYear);
        $start = \Carbon\Carbon::createFromDate((int) $startYear, 4, 1);

        return collect(range(0, 11))
            ->map(fn($i) => $start->copy()->addMonths($i)->format('Y-m'))
            ->all();
    }

    public function ensureGenerated(Employee $employee, string $financialYear): ?string
    {
        $path = $this->path($employee->id, $financialYear);

        if (!file_exists($path)) {
            $generated = $this->generate($employee, $financialYear, $path);
            if (!$generated) {
                return null;
            }
        }

        return $path;
    }

    /** Returns false (generates nothing) if no locked months exist for this employee in the FY. */
    protected function generate(Employee $employee, string $financialYear, string $path): bool
    {
        $months = $this->monthsForFy($financialYear);

        $snapshots = SalaryReportSnapshot::where('employee_id', $employee->id)
            ->whereIn('year_month', $months)
            ->orderBy('year_month')
            ->get()
            ->keyBy('year_month');

        if ($snapshots->isEmpty()) {
            return false;
        }

        $monthlyRows = [];
        $totals = ['gross' => 0.0, 'pf' => 0.0, 'pt' => 0.0, 'tds' => 0.0];

        foreach ($months as $ym) {
            $snap = $snapshots->get($ym);
            $data = $snap?->snapshot_data;

            $gross = (float) ($data['payable_gross'] ?? 0);
            $pf    = (float) ($data['employee_pf'] ?? 0);
            $pt    = (float) ($data['professional_tax'] ?? 0);
            $tds   = (float) ($data['tds'] ?? 0);

            $monthlyRows[] = [
                'month' => $ym,
                'label' => \Carbon\Carbon::createFromFormat('Y-m', $ym)->format('M Y'),
                'gross' => $gross,
                'pf'    => $pf,
                'pt'    => $pt,
                'tds'   => $tds,
                'locked' => $snap !== null,
            ];

            $totals['gross'] += $gross;
            $totals['pf']    += $pf;
            $totals['pt']    += $pt;
            $totals['tds']   += $tds;
        }

        $lastSnapshot = $snapshots->last();
        $company = Company::find($lastSnapshot->snapshot_data['company_id'] ?? $employee->company_id);

        $pdf = Pdf::loadView('pdf.form16', [
            'employee'       => $employee,
            'company'        => $company,
            'financialYear'  => $financialYear,
            'monthlyRows'    => $monthlyRows,
            'totals'         => $totals,
            'panNumber'      => $lastSnapshot->snapshot_data['pan_number'] ?? null,
        ])->setPaper('a4');

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, $pdf->output());

        return true;
    }
}
