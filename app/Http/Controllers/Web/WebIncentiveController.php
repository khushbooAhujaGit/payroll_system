<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeIncentive;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class WebIncentiveController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $this->resolveCompanyId($request);
        $month    = $request->get('month', now()->format('Y-m'));

        // Only load records that have been entered (not all employees)
        $records = EmployeeIncentive::where('month', $month)
            ->whereHas('employee', fn($q) => $q)
            ->with(['employee:id,first_name,middle_name,last_name,employee_id'])
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $totalIncentive = EmployeeIncentive::where('month', $month)
            ->whereHas('employee', fn($q) => $q)
            ->sum('amount');

        // Slim employee list for the "Add" modal dropdown — active only
        $allEmployees = Employee::query()
            ->currentlyEmployed()
            ->orderBy('employee_id')
            ->get(['id', 'first_name', 'middle_name', 'last_name', 'employee_id']);

        return view('incentives.index', compact('records', 'allEmployees', 'month', 'totalIncentive', 'companyId'));
    }

    public function upsert(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'month'       => 'required|date_format:Y-m',
            'amount'      => 'required|numeric|min:0',
            'reason'      => 'nullable|string|max:200',
        ]);

        EmployeeIncentive::updateOrCreate(
            ['employee_id' => $validated['employee_id'], 'month' => $validated['month']],
            ['amount' => $validated['amount'], 'reason' => $validated['reason'] ?? null,
             'created_by' => auth()->id()]
        );

        return redirect()->route('incentives', ['month' => $validated['month']])
            ->with('success', 'Incentive saved.');
    }

    public function destroy(EmployeeIncentive $incentive)
    {
        $month = $incentive->month;
        $incentive->delete();
        return redirect()->route('incentives', ['month' => $month])
            ->with('success', 'Incentive removed.');
    }

    public function export(Request $request)
    {
        $companyId = $this->resolveCompanyId($request);
        $month    = $request->get('month', now()->format('Y-m'));

        $records = EmployeeIncentive::whereHas('employee', fn($q) => $q)
            ->with(['employee:id,first_name,middle_name,last_name,employee_id'])
            ->where('month', $month)
            ->orderByDesc('created_at')
            ->get();

        $headers = ['Employee ID', 'Employee Name', 'Month', 'Amount (₹)', 'Reason'];

        $rows = $records->map(fn($r) => [
            $r->employee?->employee_id,
            $r->employee ? ($r->employee->full_name) : null,
            $r->month,
            (float) $r->amount,
            $r->reason,
        ]);

        return $this->streamXlsx("incentives_{$month}", $headers, $rows);
    }

    public function template()
    {
        $spreadsheet = new Spreadsheet();
        $ws = $spreadsheet->getActiveSheet();
        $ws->setTitle('Incentives');
        foreach (['Emp ID', 'Amount', 'Reason'] as $i => $h) {
            $ws->setCellValue([$i + 1, 1], $h);
        }

        $writer = new Xlsx($spreadsheet);
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'incentives_template.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function import(Request $request)
    {
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
                [$empId, $amount, $reason] = array_pad($row, 3, null);
                if (!$empId || $amount === null) continue;

                $employee = Employee::where('employee_id', trim((string) $empId))->first();
                if (!$employee) continue;

                EmployeeIncentive::updateOrCreate(
                    ['employee_id' => $employee->id, 'month' => $month],
                    ['amount' => (float) $amount, 'reason' => $reason ?? null,
                     'created_by' => auth()->id()]
                );
                $count++;
            }

            return redirect()->route('incentives', ['month' => $month])
                ->with('success', "{$count} incentive(s) imported.");
        } catch (\Exception $e) {
            return redirect()->route('incentives')->with('error', 'Import failed: ' . $e->getMessage());
        }
    }
}
