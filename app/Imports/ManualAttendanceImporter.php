<?php

namespace App\Imports;

use App\Imports\Concerns\ParsesExcelDates;
use App\Models\AttendanceRawPunch;
use App\Models\Employee;

/**
 * General-purpose attendance correction/backfill sheet — any employee, no
 * biometric machine file needed. A plain "EMP ID + Date + Check In + Check
 * Out" sheet, as opposed to:
 *  - the raw machine-XLS Biometric Upload pipeline (scripts/attendance_import.py)
 *  - WfhAttendanceImporter, which is restricted to WFH/Client Location
 *    employees and stores a synthetic Status-derived time pair
 *
 * This exists for HR to bulk-correct attendance after reviewing raised
 * Change Requests (see WebMyAttendanceController::pendingRequests()) — e.g.
 * fixing a bad punch, backfilling a day the machine missed — for ANY
 * employee, office or otherwise.
 *
 * Upsert semantics mirror the existing single-row "Add Punch" manual entry
 * (WebAttendanceController::storePunch()): matched by employee_id +
 * attendance_date only, with NO machine_name filter, so it finds and
 * corrects whatever row is already there (biometric, WFH, or a prior manual
 * upload) rather than creating a duplicate parallel row for the same day.
 * A genuinely new day gets a fresh row tagged machine_name = 'manual'.
 * Leaving Check In or Check Out blank preserves that side of an existing
 * row instead of blanking it out.
 */
class ManualAttendanceImporter extends BaseImporter
{
    use ParsesExcelDates;

    public const MACHINE_NAME = 'manual';

    /** Cache: sheet_emp_id => Employee model (or null for unmatched) */
    protected array $empCache = [];

    // ─────────────────────────────────────────────
    // TEMPLATE DEFINITION
    // ─────────────────────────────────────────────

    public function getTemplateHeaders(): array
    {
        return ['EMP ID', 'Name', 'Date', 'Check In', 'Check Out', 'HR Remark'];
    }

    public function getSampleRows(): array
    {
        return [
            ['202118007', 'Vadher Neha Dipakbhai', now()->startOfMonth()->format('Y-m-d'), '10:00', '19:00', 'Backfilled — machine was down this day'],
            ['202118007', 'Vadher Neha Dipakbhai', now()->startOfMonth()->addDay()->format('Y-m-d'), '10:15', '', 'Corrected check-in per employee change request'],
        ];
    }

    public function getColumnValidations(): array
    {
        return [
            'EMP ID'    => ['required' => true,  'format' => 'Company employee ID', 'example' => '202118007'],
            'Name'      => ['required' => false, 'format' => 'Employee full name (display only)', 'example' => 'Vadher Neha Dipakbhai'],
            'Date'      => ['required' => true,  'format' => 'YYYY-MM-DD or any parseable date', 'example' => '2026-10-01'],
            'Check In'  => ['required' => false, 'format' => 'HH:MM (24-hour) — at least one of Check In / Check Out is required', 'example' => '10:00'],
            'Check Out' => ['required' => false, 'format' => 'HH:MM (24-hour) — at least one of Check In / Check Out is required', 'example' => '19:00'],
            'HR Remark' => ['required' => true,  'format' => 'Why this punch is being added/corrected', 'example' => 'Corrected check-in per employee change request'],
        ];
    }

    protected function getRequiredHeaders(): array
    {
        return ['EMP ID', 'Date', 'HR Remark'];
    }

    // ─────────────────────────────────────────────
    // ROW MAPPING
    // ─────────────────────────────────────────────

    public function mapRow(array $row, array $headers): array
    {
        $fieldMap = [
            'emp id'    => 'sheet_emp_id',
            'name'      => 'employee_name',
            'date'      => 'date_raw',
            'check in'  => 'check_in_raw',
            'check out' => 'check_out_raw',
            'hr remark' => 'hr_remark',
        ];

        $mapped = [];
        foreach ($headers as $col => $header) {
            $key   = strtolower(trim((string) $header));
            $field = $fieldMap[$key] ?? null;
            if ($field) {
                $v = $row[$col] ?? null;
                $mapped[$field] = is_string($v) ? trim($v) : $v;
            }
        }

        return $mapped;
    }

    public function shouldSkipRow(array $data): bool|string
    {
        if ($this->isRowEmpty($data)) {
            return true; // blank row — silent skip
        }

        $sheetEmpId = trim((string) ($data['sheet_emp_id'] ?? ''));
        if ($sheetEmpId === '') {
            return 'EMP ID is blank — cannot identify employee.';
        }

        return false;
    }

    // ─────────────────────────────────────────────
    // VALIDATION
    // ─────────────────────────────────────────────

    public function validate(array $data, int $rowNumber): array
    {
        $errors = [];

        $sheetEmpId = trim((string) ($data['sheet_emp_id'] ?? ''));
        $employee   = $this->resolveEmployee($sheetEmpId);

        if (!$employee) {
            $name = trim((string) ($data['employee_name'] ?? ''));
            $errors[] = "EMP ID \"{$sheetEmpId}\"" . ($name ? " ({$name})" : '') . ' not found in the employees table.';
        }

        $parsedDate = $this->parseDate($data['date_raw'] ?? null);
        if ($parsedDate === null) {
            $rawVal = $data['date_raw'] ?? 'empty';
            $errors[] = "Date is required and must be a valid date. Got: \"{$rawVal}\".";
        }

        $checkIn  = $this->parseTime($data['check_in_raw']  ?? null);
        $checkOut = $this->parseTime($data['check_out_raw'] ?? null);
        if ($checkIn === null && $checkOut === null) {
            $errors[] = 'At least one of Check In or Check Out is required.';
        }

        if (trim((string) ($data['hr_remark'] ?? '')) === '') {
            $errors[] = 'HR Remark is required — say why this punch is being added or corrected.';
        }

        return $errors;
    }

    // ─────────────────────────────────────────────
    // PROCESSING  (only reached when ALL rows are valid)
    // ─────────────────────────────────────────────

    public function process(array $data, int $rowNumber): void
    {
        $sheetEmpId = trim((string) ($data['sheet_emp_id'] ?? ''));
        $employee   = $this->resolveEmployee($sheetEmpId);

        if (!$employee) {
            throw new \RuntimeException("Row {$rowNumber}: Employee not found for EMP ID \"{$sheetEmpId}\".");
        }

        $date     = $this->parseDate($data['date_raw'] ?? null);
        $checkIn  = $this->parseTime($data['check_in_raw']  ?? null);
        $checkOut = $this->parseTime($data['check_out_raw'] ?? null);
        $remarks  = trim((string) ($data['hr_remark'] ?? ''));

        $existing = AttendanceRawPunch::where('employee_id', $employee->id)
            ->where('attendance_date', $date)
            ->first();

        if ($existing) {
            $existing->update([
                'check_in'    => $checkIn  ?? $existing->check_in,
                'check_out'   => $checkOut ?? $existing->check_out,
                'is_absent'   => false,
                'remarks'     => $remarks,
                'uploaded_by' => $this->meta['uploaded_by'] ?? $existing->uploaded_by,
            ]);
            return;
        }

        // bio_id_full/bio_number are NOT NULL with no default (they normally
        // record which physical badge/device produced the punch) — there is
        // none here, so a sentinel tied to the virtual machine tag goes in
        // instead. Nothing downstream re-resolves a punch via these columns;
        // every consumer (reports, exception mail, payroll LOP) reads
        // attendance_raw_punches by employee_id + attendance_date.
        AttendanceRawPunch::create([
            'employee_id'     => $employee->id,
            'attendance_date' => $date,
            'machine_name'    => self::MACHINE_NAME,
            'bio_id_full'     => self::MACHINE_NAME,
            'bio_number'      => 0,
            'check_in'        => $checkIn,
            'check_out'       => $checkOut,
            'is_absent'       => false,
            'remarks'         => $remarks,
            'source_file'     => $this->meta['source_file'] ?? null,
            'uploaded_by'     => $this->meta['uploaded_by'] ?? null,
        ]);
    }

    // ─────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────

    protected function resolveEmployee(string $sheetEmpId): ?Employee
    {
        if ($sheetEmpId === '') {
            return null;
        }

        if (array_key_exists($sheetEmpId, $this->empCache)) {
            return $this->empCache[$sheetEmpId];
        }

        return $this->empCache[$sheetEmpId] = Employee::findByEmployeeIdLoose($sheetEmpId);
    }

    protected function isRowEmpty(array $data): bool
    {
        foreach (['sheet_emp_id', 'employee_name', 'date_raw', 'check_in_raw', 'check_out_raw', 'hr_remark'] as $field) {
            $value = $data[$field] ?? null;
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function parseTime(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($raw)->format('H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }
}
