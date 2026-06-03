<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\SalaryRevision;
use App\Models\EmployeeLoan;
use App\Models\LoanType;
use App\Models\Contractor;

class SalaryRevisionImporter extends BaseImporter
{
    protected function getRequiredHeaders(): array
    {
        return ['Employee ID', 'New CTC (Annual)', 'Effective Date', 'Reason'];
    }

    public static function getTemplateHeaders(): array
    {
        return ['Employee ID', 'New CTC Annual (₹)', 'Effective Date (DD-MM-YYYY)', 'Reason'];
    }

    public static function getSampleRows(): array
    {
        return [
            ['EMP0001', '1200000', '01-04-2025', 'Annual Increment FY25-26'],
            ['EMP0002', '900000',  '01-04-2025', 'Annual Increment FY25-26'],
        ];
    }

    public static function getColumnValidations(): array
    {
        return [
            'Employee ID'         => ['required' => true,  'type' => 'string'],
            'New CTC Annual (₹)'  => ['required' => true,  'type' => 'numeric'],
            'Effective Date'      => ['required' => true,  'type' => 'date'],
            'Reason'              => ['required' => true,  'type' => 'string'],
        ];
    }

    protected function validate(array $row, int $rowNum): array
    {
        $errors = [];
        $ctc = floatval($row['New CTC Annual (₹)'] ?? $row['New CTC (Annual)'] ?? 0);
        if ($ctc <= 0) {
            $errors[] = "Row {$rowNum}: New CTC must be greater than 0.";
        }
        return $errors;
    }

    protected function mapRow(array $row, array $context): array
    {
        $raw = $row['Effective Date (DD-MM-YYYY)'] ?? $row['Effective Date'] ?? '';
        return [
            'employee_code' => trim($row['Employee ID']),
            'new_ctc'       => floatval($row['New CTC Annual (₹)'] ?? $row['New CTC (Annual)']),
            'effective_date'=> $this->parseDate($raw),
            'reason'        => trim($row['Reason'] ?? ''),
        ];
    }

    protected function process(array $rows, array $context): array
    {
        $success = 0;
        $errors  = [];

        $employeeMap = Employee::where('company_id', $context['company_id'])
            ->pluck('id', 'employee_id');

        foreach ($rows as $idx => $row) {
            $rowNum = $idx + 2;
            $code   = $row['employee_code'];

            if (!isset($employeeMap[$code])) {
                $errors[] = "Row {$rowNum}: Employee '{$code}' not found.";
                continue;
            }

            $employee = Employee::with('currentSalary')->findOrFail($employeeMap[$code]);
            $oldCtc   = $employee->currentSalary?->ctc_annual ?? 0;
            $newCtc   = $row['new_ctc'];
            $increment = $newCtc - $oldCtc;
            $pct       = $oldCtc > 0 ? round(($increment / $oldCtc) * 100, 2) : 0;

            try {
                SalaryRevision::create([
                    'employee_id'          => $employeeMap[$code],
                    'old_ctc'              => $oldCtc,
                    'new_ctc'              => $newCtc,
                    'increment_amount'     => $increment,
                    'increment_percentage' => $pct,
                    'effective_date'       => $row['effective_date'],
                    'reason'               => $row['reason'],
                    'status'               => 'pending_approval',
                    'created_by'           => auth()->id(),
                ]);
                $success++;
            } catch (\Throwable $e) {
                $errors[] = "Row {$rowNum}: " . $e->getMessage();
            }
        }

        return ['success' => $success, 'errors' => $errors];
    }

    private function parseDate(string $raw): ?string
    {
        if (empty($raw)) return null;
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $raw, $m)) return "{$m[3]}-{$m[2]}-{$m[1]}";
        try { return \Carbon\Carbon::parse($raw)->format('Y-m-d'); } catch (\Throwable) { return null; }
    }
}
