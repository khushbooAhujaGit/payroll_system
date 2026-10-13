<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\LoanType;

class LoanImporter extends BaseImporter
{
    protected function getRequiredHeaders(): array
    {
        return ['Emp ID', 'Start Month', 'Advance Amount'];
    }

    public function getTemplateHeaders(): array
    {
        return ['Emp ID', 'Start Month (YYYY-MM)', 'Advance Amount (₹)', 'No. of EMI', '% Deduction', 'Remark'];
    }

    public function getSampleRows(): array
    {
        return [
            ['202118007', '2025-06', '25000', '3',  '33.33', 'Medical advance'],
            ['202118008', '2025-06', '10000', '1',  '',      'Festival advance'],
        ];
    }

    public function getColumnValidations(): array
    {
        return [
            'Emp ID'                   => ['required' => true,  'format' => 'Employee ID from HRMS', 'example' => '202118007'],
            'Start Month (YYYY-MM)'    => ['required' => true,  'format' => 'YYYY-MM',               'example' => '2025-06'],
            'Advance Amount (₹)'       => ['required' => true,  'format' => 'Numeric > 0',           'example' => '25000'],
            'No. of EMI'               => ['required' => false, 'format' => 'Integer ≥ 1',           'example' => '3'],
            '% Deduction'              => ['required' => false, 'format' => '0–100',                 'example' => '50'],
            'Remark'                   => ['required' => false, 'format' => 'Free text',              'example' => 'Medical advance'],
        ];
    }

    public function mapRow(array $row, array $headers): array
    {
        // headers is an associative array: colLetter => header name
        // row is: colLetter => value
        $byName = [];
        foreach ($headers as $letter => $name) {
            $byName[trim($name)] = $row[$letter] ?? null;
        }

        return [
            'emp_id'            => trim((string) ($byName['Emp ID'] ?? '')),
            'start_month'       => trim((string) ($byName['Start Month (YYYY-MM)'] ?? $byName['Start Month'] ?? '')),
            'advance_amount'    => (float) ($byName['Advance Amount (₹)'] ?? $byName['Advance Amount'] ?? 0),
            'total_emis'        => max(1, (int) ($byName['No. of EMI'] ?? 1)),
            'percent_deduction' => strlen(trim((string) ($byName['% Deduction'] ?? ''))) > 0
                                    ? (float) $byName['% Deduction']
                                    : null,
            'remark'            => trim((string) ($byName['Remark'] ?? '')),
        ];
    }

    public function validate(array $data, int $rowNumber): array
    {
        $errors = [];
        if (empty($data['emp_id'])) {
            $errors[] = "Emp ID is required";
        }
        if (empty($data['start_month']) || !preg_match('/^\d{4}-\d{2}$/', $data['start_month'])) {
            $errors[] = "Start Month must be YYYY-MM format";
        }
        if ($data['advance_amount'] <= 0) {
            $errors[] = "Advance Amount must be greater than 0";
        }
        if ($data['percent_deduction'] !== null && ($data['percent_deduction'] < 0 || $data['percent_deduction'] > 100)) {
            $errors[] = "% Deduction must be between 0 and 100";
        }
        return $errors;
    }

    public function process(array $data, int $rowNumber): void
    {
        $companyId = $this->meta['company_id'];

        $employee = Employee::findByEmployeeIdLoose($data['emp_id'], $companyId);

        if (!$employee) {
            throw new \RuntimeException("Employee '{$data['emp_id']}' not found");
        }

        // Use Salary Advance loan type for this entity
        $loanType = LoanType::where('company_id', $companyId)->where('code', 'SA')->first()
            ?? LoanType::where('company_id', $companyId)->first();

        if (!$loanType) {
            throw new \RuntimeException("No loan type configured for entity {$companyId}");
        }

        $loanNumber = 'LN-' . now()->year . '-' . str_pad(EmployeeLoan::count() + 1, 4, '0', STR_PAD_LEFT);
        $emiAmount  = round($data['advance_amount'] / $data['total_emis'], 2);

        EmployeeLoan::create([
            'loan_number'        => $loanNumber,
            'employee_id'        => $employee->id,
            'loan_type_id'       => $loanType->id,
            'loan_amount'        => $data['advance_amount'],
            'total_emis'         => $data['total_emis'],
            'percent_deduction'  => $data['percent_deduction'],
            'emi_amount'         => $emiAmount,
            'interest_rate'      => 0,
            'first_emi_month'    => $data['start_month'] . '-01',
            'outstanding_amount' => $data['advance_amount'],
            'status'             => 'pending',
            'purpose'            => $data['remark'] ?: null,
            'created_by'         => auth()->id(),
        ]);
    }
}
