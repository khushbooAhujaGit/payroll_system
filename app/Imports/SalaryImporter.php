<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\SalaryRawUpload;
use Illuminate\Support\Facades\Crypt;

/**
 * Salary Import — template columns: Emp ID | Employee Full Name | Gross Salary
 *
 * - Looks up employee by employees.employee_id
 * - Skips rows where the EMP ID cannot be resolved
 * - Stores gross salary encrypted (gross_monthly_enc) in addition to plain decimal
 * - Creates a new current salary record (marks previous as non-current)
 */
class SalaryImporter extends BaseImporter
{
    protected array $empCache = []; 

    public function getTemplateHeaders(): array
    {
        // Matches the user's standard salary upload template exactly:
        //   Employee Name | Emp ID | Gross Salary (Monthly)
        return ['Employee Name', 'Emp ID', 'Gross Salary (Monthly)'];
    }

    public function getSampleRows(): array
    {
        return [
            ['Vadher Neha Dipakbhai', '202118007', '25000'],
            ['Parth Patel',           '202118012', '30000'],
        ];
    }

    public function getColumnValidations(): array
    {
        return [
            'Employee Name'          => ['required' => false, 'format' => 'For reference only',                          'example' => 'Vadher Neha'],
            'Emp ID'                 => ['required' => true,  'format' => 'Company employee_id',                         'example' => '202118007'],
            'Gross Salary (Monthly)' => ['required' => true,  'format' => 'Monthly gross salary in ₹ (numbers only)',   'example' => '25000'],
        ];
    }

    protected function getRequiredHeaders(): array
    {
        return ['Emp ID']; // Gross Salary header is flexible (old/new name both accepted)
    }

    public function mapRow(array $row, array $headers): array
    {
        $fieldMap = [
            'emp id'                  => 'emp_id',
            'employee name'           => 'full_name',
            'employee full name'      => 'full_name',
            'gross salary'            => 'gross_salary',   // old header — backward compat
            'gross salary (monthly)'  => 'gross_salary',   // new explicit header
            'monthly gross'           => 'gross_salary',   // alternate
            'effective from'          => 'effective_from',
        ];

        $mapped = [];
        foreach ($headers as $col => $header) {
            $key = strtolower(trim((string) $header));
            $field = $fieldMap[$key] ?? null;
            if ($field) {
                $v = $row[$col] ?? null;
                $mapped[$field] = is_string($v) ? trim($v) : $v;
            }
        }
        return $mapped;
    }

    public function shouldSkipRow(array $data): bool
    {
        return $this->isRowEmpty($data);
    }

    public function validate(array $data, int $rowNumber): array
    {
        $errors = [];

        $empId = trim((string) ($data['emp_id'] ?? ''));
        if ($empId === '') {
            $errors[] = 'Emp ID is required.';
        } elseif ($this->resolveEmployee($empId) === null) {
            $errors[] = "Emp ID \"{$empId}\" not found in employees table — row skipped.";
        }

        // Strip thousand-separator commas before numeric check (e.g. "75,000" → "75000")
        $gross = str_replace(',', '', (string) ($data['gross_salary'] ?? ''));
        if ($gross === '' || !is_numeric($gross) || (float) $gross <= 0) {
            $errors[] = 'Gross Salary must be a positive number. Got: "' . ($data['gross_salary'] ?? '') . '"';
        }

        // Effective From is optional — defaults to today when blank
        // (no validation error; process() uses date('Y-m-d') as fallback)

        return $errors;
    }

    public function process(array $data, int $rowNumber): void
    {
        $empId    = trim((string) ($data['emp_id'] ?? ''));
        $employee = $this->resolveEmployee($empId);

        // Strip commas so "75,000" → 75000.0
        $gross      = (float) str_replace(',', '', (string) ($data['gross_salary'] ?? '0'));
        $ctcAnnual  = round($gross * 12, 2);
        $ctcMonthly = $gross;

        $effectiveFrom = trim((string) ($data['effective_from'] ?? ''));
        if ($effectiveFrom === '') {
            $effectiveFrom = $this->meta['effective_from'] ?? date('Y-m-d');
        }

        $importMonth = $this->meta['import_month'] ?? null;
        $uploadedBy  = $this->meta['uploaded_by']  ?? null;

        // Encrypt all monetary values — plaintext columns stored as 0
        $grossEnc   = Crypt::encrypt((string) $gross);
        $ctcAnnEnc  = Crypt::encrypt((string) $ctcAnnual);
        $ctcMonEnc  = Crypt::encrypt((string) $ctcMonthly);

        // ── Mark previous salary as non-current, then insert new (atomic) ──────
        \Illuminate\Support\Facades\DB::transaction(function () use (
            $employee, $grossEnc, $ctcAnnEnc, $ctcMonEnc, $effectiveFrom, $uploadedBy
        ) {
            EmployeeSalary::where('employee_id', $employee->id)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            EmployeeSalary::create([
                'employee_id'       => $employee->id,
                'ctc_annual'        => 0,
                'ctc_monthly'       => 0,
                'gross_monthly'     => 0,
                'gross_monthly_enc' => $grossEnc,
                'ctc_annual_enc'    => $ctcAnnEnc,
                'ctc_monthly_enc'   => $ctcMonEnc,
                'effective_from'    => $effectiveFrom,
                'is_current'        => true,
                'revision_type'     => 'import',
                'created_by'        => $uploadedBy,
            ]);
        });

        // ── Encrypted audit record ────────────────────────────────────────────
        SalaryRawUpload::create([
            'employee_id'       => $employee->id,
            'emp_id_text'       => $empId,
            'gross_monthly_enc' => $grossEnc,
            'ctc_annual_enc'    => $ctcAnnEnc,
            'ctc_monthly_enc'   => $ctcMonEnc,
            'effective_from'    => $effectiveFrom,
            'import_month'      => $importMonth,
            'source'            => 'import',
            'uploaded_by'       => $uploadedBy,
        ]);

    }

    protected function resolveEmployee(string $empId): ?\App\Models\Employee
    {
        if ($empId === '') return null;

        if (!array_key_exists($empId, $this->empCache)) {
            $this->empCache[$empId] = Employee::where('employee_id', $empId)->first();
        }

        return $this->empCache[$empId];
    }

    protected function isRowEmpty(array $data): bool
    {
        foreach (['emp_id', 'full_name', 'gross_salary', 'effective_from'] as $field) {
            $v = $data[$field] ?? null;
            if ($v !== null && trim((string) $v) !== '') return false;
        }
        return true;
    }
}
