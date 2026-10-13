<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\EmployeeBioId;

/**
 * Bio ID Mapping Excel Importer
 *
 * Expected columns: Employee Name | New Emp ID | BioID
 *
 * BioID cell may contain multiple comma-separated values: "I-20,II-6,III-8"
 * Each entry is split by type (I/II/III), number, and machine (10/12/K).
 *
 * Lookup order for employee:
 *   1. employee_id / external_emp_id  (New Emp ID column, scoped to entity)
 *   2. full name match                (Employee Name column, fallback)
 *
 * All-or-nothing guarantee:
 *   Employee existence is confirmed inside validate() and cached. If any row
 *   fails validation (employee not found, bad Bio ID format, missing field) the
 *   ExcelImportService aborts the entire upload and returns an error file.
 *   process() is only called once every single row has passed validation.
 */
class BioIdImporter extends BaseImporter
{
    /**
     * Populated by validate() — maps "new_emp_id" => employees.id.
     * process() reads from this cache to avoid redundant DB lookups.
     */
    protected array $resolvedEmployees = [];

    /**
     * Bio IDs that already exist in the DB for their correct employee.
     * These are exact matches — process() skips them (no-op update).
     * Key = bio_id_full string.
     */
    protected array $existingExactMatches = [];

    // ─────────────────────────────────────────────
    // TEMPLATE DEFINITION
    // ─────────────────────────────────────────────

    public function getTemplateHeaders(): array
    {
        return ['Employee Name', 'New Emp ID', 'BioID'];
    }

    public function getSampleRows(): array
    {
        return [
            ['Parth Baldevbhai Patel', '201820001', 'I-20,II-6,III-8'],
            ['Dhruv Bhayani',          '201818002', 'II-2,I-19'],
            ['Vadher Neha Dipakbhai',  '202118007', 'II-3,III-5'],
        ];
    }

    public function getColumnValidations(): array
    {
        return [
            'Employee Name' => ['required' => false, 'format' => 'Text',                          'example' => 'Parth Patel'],
            'New Emp ID'    => ['required' => true,  'format' => 'Numeric company employee ID',   'example' => '201820001'],
            'BioID'         => ['required' => false, 'format' => 'I-N, II-N, III-N, IV-N (comma sep; IV = Other/WFH)', 'example' => 'I-20,II-6,III-8'],
        ];
    }

    protected function getRequiredHeaders(): array
    {
        return ['New Emp ID'];
    }

    // ─────────────────────────────────────────────
    // ROW MAPPING
    // ─────────────────────────────────────────────

    public function mapRow(array $row, array $headers): array
    {
        $mapped   = [];
        $fieldMap = [
            'Employee Name' => 'employee_name',
            'New Emp ID'    => 'new_emp_id',
            'BioID'         => 'bio_id',
        ];
        foreach ($headers as $col => $header) {
            $field = $fieldMap[$header] ?? null;
            if ($field) {
                $v = $row[$col] ?? null;
                $mapped[$field] = is_string($v) ? trim($v) : $v;
            }
        }
        return $mapped;
    }

    // ─────────────────────────────────────────────
    // VALIDATION  (runs for every row before any insert)
    // ─────────────────────────────────────────────

    public function validate(array $data, int $rowNumber): array
    {
        $errors   = [];
        $companyId = (int) ($this->meta['company_id'] ?? 0);

        // ── New Emp ID is mandatory ──────────────────────────────────────
        $newEmpId = trim((string) ($data['new_emp_id'] ?? ''));

        if ($newEmpId === '') {
            $errors[] = 'New Emp ID is required.';
            return $errors; // nothing else we can check without it
        }

        // ── Confirm employee exists (cached so each ID is only queried once)
        if (!array_key_exists($newEmpId, $this->resolvedEmployees)) {
            $employee = $this->findEmployee(
                $newEmpId,
                $data['employee_name'] ?? null
            );
            // dd($employee);
            $this->resolvedEmployees[$newEmpId] = $employee?->id;
        }

        if (!$this->resolvedEmployees[$newEmpId]) {
            $nameHint = !empty($data['employee_name']) ? " (Name: {$data['employee_name']})" : '';
            $errors[] = "Employee not found for New Emp ID \"{$newEmpId}\".{$nameHint}";
        }

        // ── Bio ID format + conflict check ──────────────────────────────────
        $rawBioIds        = trim((string) ($data['bio_id'] ?? ''));
        $currentEmpDbId   = $this->resolvedEmployees[$newEmpId] ?? null;

        foreach (explode(',', $rawBioIds) as $rawPart) {
            $rawPart = trim($rawPart);
            if ($rawPart === '') continue;

            $parsed = EmployeeBioId::parse($rawPart);
            if ($parsed === null) {
                $errors[] = "Unrecognised Bio ID format: \"{$rawPart}\". Expected I-N, II-N, III-N, or IV-N.";
                continue;
            }

            // Check DB for an existing record with this bio_id_full
            $existing = EmployeeBioId::where('bio_id_full', $parsed['bio_id_full'])->first();

            if ($existing) {
                if ($currentEmpDbId && $existing->employee_id === $currentEmpDbId) {
                    // Exact same employee → mark as no-op; no error, no re-insert
                    $this->existingExactMatches[$parsed['bio_id_full']] = true;
                } else {
                    // Conflict — this Bio ID belongs to a DIFFERENT employee
                    $errors[] = "Bio ID \"{$parsed['bio_id_full']}\" is already assigned to employee DB-ID {$existing->employee_id}. "
                              . "Remove it from the other employee first, then re-upload.";
                }
            }
        }

        return $errors;
    }

    // ─────────────────────────────────────────────
    // PROCESSING  (only reached when ALL rows are valid)
    // ─────────────────────────────────────────────

    public function process(array $data, int $rowNumber): void
    {
        $newEmpId   = trim((string) ($data['new_emp_id'] ?? ''));
        $employeeId = $this->resolvedEmployees[$newEmpId] ?? null;

        // Defensive guard — validate() should have already caught this.
        if (!$employeeId) {
            throw new \RuntimeException(
                "Row {$rowNumber}: Employee not found for New Emp ID \"{$newEmpId}\"."
            );
        }

        $rawBioIds = trim((string) ($data['bio_id'] ?? ''));

        if ($rawBioIds === '') {
            return; // row intentionally has no Bio IDs — skip silently
        }

        foreach (explode(',', $rawBioIds) as $rawPart) {
            $rawPart = trim($rawPart);
            if ($rawPart === '') continue;

            $parsed = EmployeeBioId::parse($rawPart);
            if (!$parsed) continue; // already caught in validate(); defensive guard only

            // Skip if this bio_id_full already exists for the same employee (no change needed)
            if (isset($this->existingExactMatches[$parsed['bio_id_full']])) {
                continue;
            }

            // Insert new record — validate() already confirmed no conflict with another employee
            EmployeeBioId::create([
                'employee_id'  => $employeeId,
                'bio_id_full'  => $parsed['bio_id_full'],
                'bio_type'     => $parsed['bio_type'],
                'bio_number'   => $parsed['bio_number'],
                'machine_name' => $parsed['machine_name'],
                'is_active'    => true,
            ]);
        }
    }

    // ─────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────

    /**
     * Look up an employee within the entity by New Emp ID, then by full name.
     *
     * Name matching handles NULL/empty middle names via CONCAT_WS so:
     *   first="Parth" middle="Baldevbhai" last="Patel"  matches "Parth Baldevbhai Patel"
     *   first="Dhruv" middle=NULL         last="Bhayani" matches "Dhruv Bhayani"
     */
    /**
     * Look up an employee by New Emp ID then by full name, always scoped to the
     * importer's entity (read from $this->meta so the signature stays at 2 params).
     *
     * Name matching handles NULL/empty middle names via CONCAT_WS:
     *   first="Parth" middle="Baldevbhai" last="Patel"  => matches "Parth Baldevbhai Patel"
     *   first="Dhruv" middle=NULL         last="Bhayani" => matches "Dhruv Bhayani"
     */
    protected function findEmployee(string $newEmpId, ?string $name): ?Employee
    {
        $companyId = (int) ($this->meta['company_id'] ?? 0);

        // 1. Match by employee_id, tolerant of an inconsistent "EMP" prefix
        // (see Employee::findByEmployeeIdLoose()).
        $employee = Employee::findByEmployeeIdLoose($newEmpId);

        if ($employee) {
            return $employee;
        }

        // 2. Fallback: exact full-name match within the entity (normalise
        // spaces). Deliberately does NOT also filter by employee_id here —
        // this branch only runs once step 1 has already failed to match
        // that id in any form, so repeating it would make this fallback
        // permanently unreachable.
        $name = preg_replace('/\s+/', ' ', trim((string) $name));
        if ($name === '') {
            return null;
        }

        return Employee::where('company_id', $companyId)
            ->whereRaw(
                "LOWER(TRIM(CONCAT_WS(' ', first_name, NULLIF(TRIM(COALESCE(middle_name, '')), ''), last_name))) = LOWER(?)",
                [$name]
            )
            ->first();
    }
}
