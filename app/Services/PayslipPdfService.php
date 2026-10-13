<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Employee;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Renders a payslip PDF from a locked SalaryReportSnapshot row (never from
 * live-computed data, so a downloaded/emailed payslip always matches what
 * was actually processed for that month) and caches it under public_path()
 * so repeat downloads don't re-render.
 *
 * Deliberately public, per explicit instruction: files here are reachable
 * by direct URL with no login/permission check at all — anyone with the
 * path can download any employee's payslip (bank account, PAN, salary).
 * This was flagged and confirmed before implementing; see conversation
 * history if this ever needs to be revisited.
 */
class PayslipPdfService
{
    /** Absolute filesystem path — public_path(), not a Storage-disk-relative path. */
    public function path(int $employeeId, string $yearMonth): string
    {
        return public_path("payslips/{$employeeId}/{$yearMonth}.pdf");
    }

    /**
     * Returns the absolute path, generating the PDF first if it doesn't
     * already exist for this employee/month.
     */
    public function ensureGenerated(Employee $employee, string $yearMonth, array $snapshotData): string
    {
        $path = $this->path($employee->id, $yearMonth);

        if (!file_exists($path)) {
            $this->generate($employee, $yearMonth, $snapshotData, $path);
        }

        return $path;
    }

    protected function generate(Employee $employee, string $yearMonth, array $row, string $path): void
    {
        $company = Company::find($row['company_id'] ?? $employee->company_id);

        $pdf = Pdf::loadView('pdf.payslip', [
            'row'      => $row,
            'employee' => $employee,
            'company'  => $company,
            'monthLabel' => \Carbon\Carbon::createFromFormat('Y-m', $yearMonth)->format('F Y'),
            'logoPath' => $this->logoPathFor($company),
        ])->setPaper('a4');

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, $pdf->output());
    }

    /**
     * Matches by keyword rather than exact name — this app's company names
     * have drifted between environments (e.g. live shows "TNBT Technology
     * Pvt. Ltd.", local shows "TNBT Tech"), so an exact match would silently
     * drop the logo on whichever environment doesn't match the hardcoded
     * string. Returns an absolute filesystem path (DomPDF needs that, not a
     * public URL) or null if the company doesn't match any known logo.
     */
    protected function logoPathFor(?Company $company): ?string
    {
        if (!$company) {
            return null;
        }

        $name = strtolower($company->name);
        $file = match (true) {
            str_contains($name, 'rutul')  => 'rsc_logo.png',
            str_contains($name, 'ashil')  => 'asc_logo.png',
            str_contains($name, 'tnbt')   => 'tnbt_logo.png',
            default => null,
        };

        if (!$file) {
            return null;
        }

        $path = public_path("logo/{$file}");
        return file_exists($path) ? $path : null;
    }
}
