<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeMonthlyTds;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class WebMonthlyTdsController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('monthly_tds_view');

        $companyId = $this->resolveCompanyId($request);
        $month    = $request->get('month', now()->format('Y-m'));

        // Only load records that have been entered (not all employees)
        $records = EmployeeMonthlyTds::where('month', $month)
            ->whereHas('employee', fn($q) => $q)
            ->with(['employee:id,first_name,middle_name,last_name,employee_id'])
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $totalTds = EmployeeMonthlyTds::where('month', $month)
            ->whereHas('employee', fn($q) => $q)
            ->sum('amount');

        // Slim employee list for the "Add" modal dropdown — active only
        $allEmployees = Employee::query()
            ->currentlyEmployed()
            ->orderBy('employee_id')
            ->get(['id', 'first_name', 'middle_name', 'last_name', 'employee_id']);

        return view('monthly-tds.index', compact('records', 'allEmployees', 'month', 'totalTds', 'companyId'));
    }

    public function export(Request $request)
    {
        $this->authorize('monthly_tds_view');

        $companyId = $this->resolveCompanyId($request);
        $month    = $request->get('month', now()->format('Y-m'));

        $records = EmployeeMonthlyTds::whereHas('employee', fn($q) => $q)
            ->with(['employee:id,first_name,middle_name,last_name,employee_id'])
            ->where('month', $month)
            ->orderByDesc('created_at')
            ->get();

        $headers = ['Employee ID', 'Employee Name', 'Month', 'TDS Amount (₹)', 'Remarks'];

        $rows = $records->map(fn($r) => [
            $r->employee?->employee_id,
            $r->employee ? ($r->employee->full_name) : null,
            $r->month,
            (float) $r->amount,
            $r->remarks,
        ]);

        return $this->streamXlsx("monthly_tds_{$month}", $headers, $rows);
    }

    public function upsert(Request $request)
    {
        $this->authorize('monthly_tds_add');

        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'month'       => 'required|date_format:Y-m',
            'amount'      => 'required|numeric|min:0',
            'remarks'     => 'nullable|string|max:200',
        ]);

        EmployeeMonthlyTds::updateOrCreate(
            ['employee_id' => $validated['employee_id'], 'month' => $validated['month']],
            ['amount' => $validated['amount'], 'remarks' => $validated['remarks'] ?? null,
             'created_by' => auth()->id()]
        );

        return redirect()->route('tds.monthly', ['month' => $validated['month']])
            ->with('success', 'TDS record saved.');
    }

    public function destroy(EmployeeMonthlyTds $tds)
    {
        $this->authorize('monthly_tds_del');

        $month = $tds->month;
        $tds->delete();
        return redirect()->route('tds.monthly', ['month' => $month])
            ->with('success', 'TDS record removed.');
    }

    public function template()
    {
        $this->authorize('monthly_tds_add');

        $spreadsheet = new Spreadsheet();
        $ws = $spreadsheet->getActiveSheet();
        $ws->setTitle('Monthly TDS');
        foreach (['Emp ID', 'TDS Amount', 'Remarks'] as $i => $h) {
            $ws->setCellValue([$i + 1, 1], $h);
        }

        $writer = new Xlsx($spreadsheet);
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'monthly_tds_template.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function import(Request $request)
    {
        $this->authorize('monthly_tds_add');

        $request->validate([
            'file'         => 'required|file|mimes:xlsx,xls',
            'import_month' => 'required|date_format:Y-m',
        ]);

        $month = $request->import_month;

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($request->file('file')->getPathname());
            $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
            array_shift($rows); // remove header

            $count = 0;
            foreach ($rows as $row) {
                if (empty(array_filter($row))) continue;
                [$empId, $amount, $remarks] = array_pad($row, 3, null);
                if (!$empId || $amount === null) continue;

                $employee = Employee::where('employee_id', trim((string) $empId))->first();
                if (!$employee) continue;

                EmployeeMonthlyTds::updateOrCreate(
                    ['employee_id' => $employee->id, 'month' => $month],
                    ['amount' => (float) $amount, 'remarks' => $remarks ?? null,
                     'created_by' => auth()->id()]
                );
                $count++;
            }

            return redirect()->route('tds.monthly', ['month' => $month])
                ->with('success', "{$count} TDS record(s) imported.");
        } catch (\Exception $e) {
            return redirect()->route('tds.monthly')->with('error', 'Import failed: ' . $e->getMessage());
        }
    }
}
