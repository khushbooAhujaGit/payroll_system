<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SalaryReportSnapshot;
use App\Services\Form16PdfService;
use Illuminate\Http\Request;

/**
 * Admin/HR side of item #24's Form 16 generation — pick a financial year,
 * see every employee with at least one locked month in it, download per
 * employee. See Form16PdfService for what this is (and isn't) — a
 * best-effort summary of recorded gross/PF/PT/TDS figures, not a validated
 * statutory computation.
 */
class WebForm16Controller extends Controller
{
    public function index(Request $request, Form16PdfService $form16Service)
    {
        $this->authorize('payroll_view');

        $fy = $request->get('fy') ?: $this->currentFy();
        $companyId = $this->resolveCompanyId($request);

        $months = $form16Service->monthsForFy($fy);

        $employeeIds = SalaryReportSnapshot::whereIn('year_month', $months)
            ->distinct()
            ->pluck('employee_id');

        $employees = Employee::whereIn('id', $employeeIds)
            ->where('company_id', $companyId)
            ->orderBy('first_name')
            ->get();

        return view('salary-report.form16', compact('fy', 'employees', 'companyId'));
    }

    public function download(Employee $employee, string $fy, Form16PdfService $form16Service)
    {
        $this->authorize('payroll_view');

        $path = $form16Service->ensureGenerated($employee, $fy);
        abort_unless($path, 404, 'No locked salary data found for this employee in this financial year.');

        return response()->download($path, "Form16_{$employee->employee_id}_{$fy}.pdf", [
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    protected function currentFy(): string
    {
        $now = now();
        $startYear = $now->month >= 4 ? $now->year : $now->year - 1;
        return $startYear . '-' . substr((string) ($startYear + 1), 2);
    }
}
