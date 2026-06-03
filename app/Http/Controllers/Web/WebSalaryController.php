<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\ImportsExcel;
use App\Http\Controllers\Controller;
use App\Imports\SalaryImporter;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\SalaryRawUpload;
use App\Services\LeaveTrackerStatusService;
use App\Services\ExcelImportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class WebSalaryController extends Controller
{
    use ImportsExcel;

    public function __construct(protected ExcelImportService $excelImportService) {}

    public function index(Request $request)
    {
        $companyId   = $this->resolveCompanyId($request);
        $isAdmin    = auth()->user()?->hasRole('admin') ?? true;
        $monthParam = $request->get('month'); // YYYY-MM or null
        $isHistoric = false;
        $monthLabel = null;

        // Validate month format
        if ($monthParam && preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
            $isHistoric = true;
            $monthLabel = Carbon::parse($monthParam . '-01')->format('F Y');
            $asOf       = Carbon::parse($monthParam . '-01')->endOfMonth()->toDateString();
        }

        $employees = Employee::with(['department', 'designation'])
            // entity_id filter intentionally removed — salary page shows all entities
            ->currentlyEmployed()
            // Only show employees who have at least one salary record set
            ->when(
                $isHistoric,
                fn($q) => $q->whereHas('salaries', fn($sq) => $sq->where('effective_from', '<=', $asOf)),
                fn($q) => $q->whereHas('salaries', fn($sq) => $sq->where('is_current', true))
            )
            ->when($request->search, fn($q) => $q->where(
                fn($sq) =>
                $sq->where('first_name',  'like', "%{$request->search}%")
                    ->orWhere('last_name', 'like', "%{$request->search}%")
                    ->orWhere('employee_id', 'like', "%{$request->search}%")
            ))
            ->orderBy('employee_id')
            ->paginate(25)->withQueryString();

        // Load the appropriate salary per employee and attach as 'salaryForMonth'
        $empIds = $employees->pluck('id');

        if ($isHistoric) {
            // For each employee, find the salary that was effective at end-of-month
            // = latest effective_from that is on or before $asOf
            $salaryMap = EmployeeSalary::whereIn('employee_id', $empIds)
                ->where('effective_from', '<=', $asOf)
                ->orderBy('effective_from', 'desc')
                ->get()
                ->groupBy('employee_id')
                ->map(fn($records) => $records->first());

            $employees->each(
                fn($emp) =>
                $emp->setRelation('salaryForMonth', $salaryMap->get($emp->id))
            );
        } else {
            // Current view — load is_current = true (same as currentSalary)
            $currentMap = EmployeeSalary::whereIn('employee_id', $empIds)
                ->where('is_current', true)
                ->get()
                ->keyBy('employee_id');

            $employees->each(
                fn($emp) =>
                $emp->setRelation('salaryForMonth', $currentMap->get($emp->id))
            );
        }

        return view('salary.index', compact(
            'employees',
            'companyId',
            'isAdmin',
            'monthParam',
            'isHistoric',
            'monthLabel'
        ));
    }

    public function setSalary(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'ctc_annual'    => 'required|numeric|min:1',
            'gross_monthly' => 'required|numeric|min:1',
            'effective_from' => 'required|date',
        ]);

        $gross      = (float) $validated['gross_monthly'];
        $ctcAnnual  = (float) $validated['ctc_annual'];
        $ctcMonthly = round($ctcAnnual / 12, 2);

        $grossEnc   = Crypt::encrypt((string) $gross);
        $ctcAnnEnc  = Crypt::encrypt((string) $ctcAnnual);
        $ctcMonEnc  = Crypt::encrypt((string) $ctcMonthly);

        \Illuminate\Support\Facades\DB::transaction(function () use (
            $employee,
            $grossEnc,
            $ctcAnnEnc,
            $ctcMonEnc,
            $validated
        ) {
            EmployeeSalary::where('employee_id', $employee->id)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            // Plaintext stored as 0 — real values only in encrypted columns
            EmployeeSalary::create([
                'employee_id'       => $employee->id,
                'ctc_annual'        => 0,
                'ctc_monthly'       => 0,
                'gross_monthly'     => 0,
                'gross_monthly_enc' => $grossEnc,
                'ctc_annual_enc'    => $ctcAnnEnc,
                'ctc_monthly_enc'   => $ctcMonEnc,
                'effective_from'    => $validated['effective_from'],
                'is_current'        => true,
                'revision_type'     => 'manual',
                'created_by'        => auth()->id(),
            ]);
        });

        // Encrypted audit record
        SalaryRawUpload::create([
            'employee_id'       => $employee->id,
            'emp_id_text'       => $employee->employee_id,
            'gross_monthly_enc' => $grossEnc,
            'ctc_annual_enc'    => $ctcAnnEnc,
            'ctc_monthly_enc'   => $ctcMonEnc,
            'effective_from'    => $validated['effective_from'],
            'import_month'      => null,
            'source'            => 'manual',
            'uploaded_by'       => auth()->id(),
        ]);

        return redirect()->route('salary')->with('success', 'Salary set for ' . $employee->full_name . '.');
    }

    public function destroySalary(EmployeeSalary $salary)
    {
        $salary->delete();
        return redirect()->route('salary')->with('success', 'Salary record deleted.');
    }

    public function template(Request $request)
    {
        $companyId = $this->resolveCompanyId($request);
        $today    = now()->toDateString();

        // ── Step 1: sync employee_status from LFT / JN tracker records ──────
        // Leavers (LFT with no subsequent JN) → inactive + date_of_leaving
        // Rejoiners (JN with no subsequent LFT) → active + date_of_leaving cleared
        (new LeaveTrackerStatusService())->sync(); // null = all employees

        // ── Step 2: fetch active employees for the template ───────────────────
        $employees = Employee::where('employee_status', 'active')
            ->whereNull('date_of_leaving')
            ->orderBy('employee_id')
            ->get(['first_name', 'middle_name', 'last_name', 'employee_id']);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $ws = $spreadsheet->getActiveSheet();
        $ws->setTitle('Salary');

        // Header row
        $headers = ['Employee Name', 'Emp ID', 'Gross Salary'];
        foreach ($headers as $i => $h) {
            $ws->setCellValue([$i + 1, 1], $h);
        }

        // Employee rows — Emp ID stored as explicit string to prevent scientific notation
        $row = 2;
        foreach ($employees as $emp) {
            $fullName = trim(implode(' ', array_filter([
                $emp->first_name,
                $emp->middle_name ?: null,
                $emp->last_name,
            ])));
            $ws->setCellValue([1, $row], $fullName);
            // Force text so "202618052" isn't displayed as "2.02E+08"
            $ws->getCell([2, $row])->setValueExplicit(
                (string) $emp->employee_id,
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );
            // Column 3 (Gross Salary) intentionally left blank
            $row++;
        }

        // Auto-size all three columns so nothing is truncated
        foreach (['A', 'B', 'C'] as $col) {
            $ws->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'salary_template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    
    public function import(Request $request)
    {
        $companyId = $this->resolveCompanyId($request);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        // import_month (YYYY-MM) → first day used as effective_from when row has none
        $importMonth = $request->get('import_month');
        $effectiveFrom = null;
        if ($importMonth && preg_match('/^\d{4}-\d{2}$/', $importMonth)) {
            $effectiveFrom = $importMonth . '-01';
        }

        // ── Archive the raw uploaded file to public/salary-archives/ ────────────
        // Stored as-is so the original data is always recoverable.
        // Path: public/salary-archives/YYYY-MM/YYYYMMDD_HHmmss_<originalname>.xlsx
        $archiveDir = public_path('salary-archives/' . ($importMonth ?: now()->format('Y-m')));
        if (!is_dir($archiveDir)) {
            mkdir($archiveDir, 0755, true);
        }
        $archiveName = now()->format('Ymd_His') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $request->file('file')->getClientOriginalName());
        $request->file('file')->move($archiveDir, $archiveName);

        // Re-open from the archived path for the import (file was moved above)
        $archivedFile = new \Illuminate\Http\UploadedFile(
            $archiveDir . '/' . $archiveName,
            $archiveName,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true   // test mode — treats existing file as valid upload
        );

        $result = $this->excelImportService->import(
            $archivedFile,
            'salary',
            SalaryImporter::class,
            [
                'company_id' => $companyId,
                'uploaded_by'    => auth()->id(),
                'effective_from' => $effectiveFrom,
                'import_month'   => $importMonth,
            ]
        );

        return $this->handleImportResult($result, 'salary', 'salary record');
    }
}
