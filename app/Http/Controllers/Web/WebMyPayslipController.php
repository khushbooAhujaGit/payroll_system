<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SalaryReportSnapshot;
use App\Services\Form16PdfService;
use App\Services\PayslipPdfService;

/**
 * Items #24-25: employee self-service payslip + Form 16 access — "Employee
 * can download this for any month." Admin/HR's equivalent (any employee, any
 * month) lives on WebSalaryReportController::downloadPayslip() instead.
 */
class WebMyPayslipController extends Controller
{
    /** Every locked month this employee has a payslip for, plus the financial years available for Form 16. */
    public function index()
    {
        $this->authorize('salary_self_view');

        $employee = auth()->user()->employee;
        abort_unless($employee, 404, 'No employee profile linked to your account.');

        $snapshots = SalaryReportSnapshot::where('employee_id', $employee->id)
            ->orderByDesc('year_month')
            ->get(['year_month', 'locked_at', 'payslip_sent_at']);

        // Financial years (Apr-Mar) that have at least one locked month.
        $financialYears = $snapshots->map(function ($s) {
            [$year, $month] = explode('-', $s->year_month);
            $startYear = (int) $month >= 4 ? (int) $year : (int) $year - 1;
            return $startYear . '-' . substr((string) ($startYear + 1), 2);
        })->unique()->sortDesc()->values();

        return view('salary-report.my-payslips', compact('snapshots', 'financialYears'));
    }

    public function download(string $month, PayslipPdfService $payslipService)
    {
        $this->authorize('salary_self_view');

        $employee = auth()->user()->employee;
        abort_unless($employee, 404, 'No employee profile linked to your account.');

        $snapshot = SalaryReportSnapshot::where('employee_id', $employee->id)
            ->where('year_month', $month)
            ->first();

        abort_unless($snapshot, 404, 'No locked payslip found for this month.');

        $path = $payslipService->ensureGenerated($employee, $month, $snapshot->snapshot_data);

        // no-store: without this, browsers can silently re-serve a
        // previously-downloaded (and since-regenerated) PDF from cache
        // instead of re-fetching — bit us once with the payslip font fix.
        return response()->download($path, "Payslip_{$employee->employee_id}_{$month}.pdf", [
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function form16(string $fy, Form16PdfService $form16Service)
    {
        $this->authorize('salary_self_view');

        $employee = auth()->user()->employee;
        abort_unless($employee, 404, 'No employee profile linked to your account.');

        $path = $form16Service->ensureGenerated($employee, $fy);
        abort_unless($path, 404, 'No locked salary data found for this financial year.');

        return response()->download($path, "Form16_{$employee->employee_id}_{$fy}.pdf", [
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
