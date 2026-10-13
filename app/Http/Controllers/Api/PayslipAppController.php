<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalaryReportSnapshot;
use App\Services\Form16PdfService;
use App\Services\PayslipPdfService;
use Illuminate\Http\JsonResponse;

/**
 * Mobile mirror of WebMyPayslipController — same scoping (own locked
 * months only, authorize('salary_self_view')), same PDF services. The PDF
 * files themselves live under public_path() with no auth check by design
 * (see PayslipPdfService's own doc comment) — this controller's job is
 * just to ensure the file is generated and hand back its public URL; the
 * app then opens that URL directly (Linking.openURL) rather than the app
 * downloading/rendering the PDF itself, so no PDF-viewer native dependency
 * is needed.
 */
class PayslipAppController extends Controller
{
    /** Every locked month this employee has a payslip for, plus the financial years available for Form 16. */
    public function index(): JsonResponse
    {
        $this->authorize('salary_self_view');

        $employee = auth()->user()->employee;
        abort_unless($employee, 404, 'No employee profile linked to your account.');

        $snapshots = SalaryReportSnapshot::where('employee_id', $employee->id)
            ->orderByDesc('year_month')
            ->get(['year_month', 'locked_at']);

        $financialYears = $snapshots->map(function ($s) {
            [$year, $month] = explode('-', $s->year_month);
            $startYear = (int) $month >= 4 ? (int) $year : (int) $year - 1;
            return $startYear . '-' . substr((string) ($startYear + 1), 2);
        })->unique()->sortDesc()->values();

        return response()->json([
            'data' => [
                'payslips'        => $snapshots,
                'financial_years' => $financialYears,
            ],
        ]);
    }

    /** Ensures that month's payslip PDF is generated, then returns its public URL. */
    public function payslipUrl(string $month, PayslipPdfService $payslipService): JsonResponse
    {
        $this->authorize('salary_self_view');

        $employee = auth()->user()->employee;
        abort_unless($employee, 404, 'No employee profile linked to your account.');

        $snapshot = SalaryReportSnapshot::where('employee_id', $employee->id)
            ->where('year_month', $month)
            ->first();
        abort_unless($snapshot, 404, 'No locked payslip found for this month.');

        $payslipService->ensureGenerated($employee, $month, $snapshot->snapshot_data);

        return response()->json([
            'data' => ['url' => url("payslips/{$employee->id}/{$month}.pdf")],
        ]);
    }

    /** Ensures that financial year's Form 16 PDF is generated, then returns its public URL. */
    public function form16Url(string $fy, Form16PdfService $form16Service): JsonResponse
    {
        $this->authorize('salary_self_view');

        $employee = auth()->user()->employee;
        abort_unless($employee, 404, 'No employee profile linked to your account.');

        $path = $form16Service->ensureGenerated($employee, $fy);
        abort_unless($path, 404, 'No locked salary data found for this financial year.');

        return response()->json([
            'data' => ['url' => url("form16/{$employee->id}/{$fy}.pdf")],
        ]);
    }
}
