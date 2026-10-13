<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\SalaryReportSnapshot;
use App\Services\Form16PdfService;
use App\Services\PayslipPdfService;
use Illuminate\Console\Command;

/**
 * Deletes cached payslip/Form16 PDFs under public_path() and regenerates
 * them from locked snapshot data — for rolling out a template/font fix
 * (e.g. the ₹ glyph fix) to files that were already generated and cached
 * under the old, wrong output.
 */
class RegeneratePayslips extends Command
{
    protected $signature = 'payslips:regenerate
        {--month= : Regenerate payslips only for this year-month (YYYY-MM)}
        {--employee-id= : Regenerate only for this employee\'s numeric ID}
        {--form16 : Also regenerate Form 16 PDFs for every financial year each employee has locked data in}';

    protected $description = 'Delete and regenerate cached payslip (and optionally Form 16) PDFs from locked salary snapshots.';

    public function handle(PayslipPdfService $payslipService, Form16PdfService $form16Service)
    {
        $query = SalaryReportSnapshot::query();

        if ($month = $this->option('month')) {
            $query->where('year_month', $month);
        }

        if ($employeeId = $this->option('employee-id')) {
            $query->where('employee_id', $employeeId);
        }

        $snapshots = $query->get(['employee_id', 'year_month', 'snapshot_data']);

        if ($snapshots->isEmpty()) {
            $this->error('No locked salary snapshots matched.');
            return 1;
        }

        $employees = Employee::whereIn('id', $snapshots->pluck('employee_id')->unique())->get()->keyBy('id');

        $count = 0;
        foreach ($snapshots as $snapshot) {
            $employee = $employees->get($snapshot->employee_id);
            if (!$employee) {
                continue;
            }

            $path = $payslipService->path($employee->id, $snapshot->year_month);
            if (file_exists($path)) {
                unlink($path);
            }

            $payslipService->ensureGenerated($employee, $snapshot->year_month, $snapshot->snapshot_data);
            $count++;
        }

        $this->info("Regenerated {$count} payslip PDF(s).");

        if ($this->option('form16')) {
            $fys = $snapshots->map(function ($s) {
                [$year, $month] = explode('-', $s->year_month);
                $startYear = (int) $month >= 4 ? (int) $year : (int) $year - 1;
                return $startYear . '-' . substr((string) ($startYear + 1), 2);
            })->unique();

            $form16Count = 0;
            foreach ($snapshots->groupBy('employee_id') as $empId => $rows) {
                $employee = $employees->get($empId);
                if (!$employee) {
                    continue;
                }

                foreach ($fys as $fy) {
                    $path = $form16Service->path($employee->id, $fy);
                    if (file_exists($path)) {
                        unlink($path);
                    }

                    if ($form16Service->ensureGenerated($employee, $fy)) {
                        $form16Count++;
                    }
                }
            }

            $this->info("Regenerated {$form16Count} Form 16 PDF(s).");
        }

        return 0;
    }
}
