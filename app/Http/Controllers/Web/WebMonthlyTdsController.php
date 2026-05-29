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
        $entityId = $this->resolveEntityId($request);
        $month    = $request->get('month', now()->format('Y-m'));

        $employees = Employee::where('entity_id', $entityId)
            ->where('employee_status', 'active')
            ->with(['designation:id,name', 'department:id,name'])
            ->orderBy('employee_id')
            ->get();

        $tdsRecords = EmployeeMonthlyTds::where('month', $month)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()
            ->keyBy('employee_id');

        $totalTds = $tdsRecords->sum('amount');

        return view('monthly-tds.index', compact('employees', 'tdsRecords', 'month', 'totalTds', 'entityId'));
    }

    public function upsert(Request $request)
    {
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
        $month = $tds->month;
        $tds->delete();
        return redirect()->route('tds.monthly', ['month' => $month])
            ->with('success', 'TDS record removed.');
    }

    public function template()
    {
        $spreadsheet = new Spreadsheet();
        $ws = $spreadsheet->getActiveSheet();
        $ws->setTitle('Monthly TDS');
        foreach (['Emp ID', 'TDS Amount', 'Remarks'] as $i => $h) {
            $ws->setCellValueByColumnAndRow($i + 1, 1, $h);
        }
        $ws->fromArray(['202118007', 2500, 'Computed TDS'], null, 'A2');

        $writer = new Xlsx($spreadsheet);
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'monthly_tds_template.xlsx',
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
