<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\SalaryRevision;
use App\Models\EmployeeLoan;
use App\Models\LoanType;
use App\Models\Contractor;

class ContractorImporter extends BaseImporter
{
    protected function getRequiredHeaders(): array
    {
        return ['Name', 'Type', 'PAN'];
    }

    public static function getTemplateHeaders(): array
    {
        return ['Name', 'Type (individual/company)', 'PAN', 'GSTIN', 'Mobile', 'Email', 'Bank Account', 'Bank Name', 'IFSC Code', 'Address'];
    }

    public static function getSampleRows(): array
    {
        return [
            ['Ramesh Sharma', 'individual', 'ABCPS1234R', '',            '9876543210', 'ramesh@example.com', '12345678901234', 'HDFC Bank', 'HDFC0001234', 'Mumbai'],
            ['Tech Corp Ltd', 'company',    'AABCT1234T', '27AABCT1234T1Z5', '9988776655', 'info@techcorp.com', '98765432101234', 'SBI',       'SBIN0001234', 'Pune'],
        ];
    }

    public static function getColumnValidations(): array
    {
        return [
            'Name'  => ['required' => true,  'type' => 'string'],
            'Type'  => ['required' => true,  'type' => 'string'],
            'PAN'   => ['required' => false, 'type' => 'string'],
        ];
    }

    protected function validate(array $row, int $rowNum): array
    {
        $errors = [];
        $type = strtolower(trim($row['Type (individual/company)'] ?? $row['Type'] ?? ''));
        if (!in_array($type, ['individual', 'company'])) {
            $errors[] = "Row {$rowNum}: Type must be 'individual' or 'company'.";
        }
        if (!empty($row['PAN']) && !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', strtoupper(trim($row['PAN'])))) {
            $errors[] = "Row {$rowNum}: Invalid PAN format.";
        }
        return $errors;
    }

    protected function mapRow(array $row, array $context): array
    {
        return [
            'name'                => trim($row['Name']),
            'contractor_type'     => strtolower(trim($row['Type (individual/company)'] ?? $row['Type'])),
            'pan'                 => strtoupper(trim($row['PAN'] ?? '')),
            'gstin'               => trim($row['GSTIN'] ?? ''),
            'mobile'              => trim($row['Mobile'] ?? ''),
            'email'               => trim($row['Email'] ?? ''),
            'bank_account_number' => trim($row['Bank Account'] ?? ''),
            'bank_name'           => trim($row['Bank Name'] ?? ''),
            'ifsc_code'           => strtoupper(trim($row['IFSC Code'] ?? '')),
            'address'             => trim($row['Address'] ?? ''),
        ];
    }

    protected function process(array $rows, array $context): array
    {
        $success = 0;
        $errors  = [];
        $entityId = $context['entity_id'];

        $last = Contractor::where('entity_id', $entityId)->orderByDesc('id')->value('contractor_id');
        $num  = $last ? ((int)preg_replace('/\D/', '', $last) + 1) : 1;

        foreach ($rows as $idx => $row) {
            $rowNum = $idx + 2;
            try {
                Contractor::create(array_merge($row, [
                    'entity_id'     => $entityId,
                    'contractor_id' => 'CTR-' . str_pad($num++, 4, '0', STR_PAD_LEFT),
                    'is_active'     => true,
                ]));
                $success++;
            } catch (\Throwable $e) {
                $errors[] = "Row {$rowNum}: " . $e->getMessage();
            }
        }

        return ['success' => $success, 'errors' => $errors];
    }
}
