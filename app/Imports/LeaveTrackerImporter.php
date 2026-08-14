<?php

namespace App\Imports;

use App\Imports\Concerns\ParsesExcelDates;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveTrackerRecord;
use App\Models\LeaveType;
use App\Services\LeaveBalanceSyncService;
use App\Services\LeaveTrackerStatusService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Leave Tracker — "Mark Leave" sheet importer
 *
 * Accepted file: the Leave Tracker .xlsm (or an xlsx export).
 * Required sheet: "Mark Leave"  (pass sheet_name in meta so ExcelImportService
 * loads the correct tab).
 *
 * Column mapping (headers trimmed, case-insensitive):
 *   ID             → leave_ref_id
 *   EMP ID         → sheet_emp_id   (used to resolve employee_id)
 *   Name           → employee_name
 *   Date           → leave_date
 *   Leave Type     → leave_type
 *   Team LeaderName → team_leader_name
 *   Reason Type    → reason_type
 *   Reason         → reason
 *
 * Validation:
 *   - Date is required and must be parseable
 *   - Leave Type is required
 *   - ID is required (used as natural key for idempotent re-imports)
 *
 * EMP ID lookup:
 *   Attempts to match sheet EMP ID against employees.employee_id.
 *   If no match is found the row is SILENTLY SKIPPED — it is not imported and
 *   does not appear in the error file.  This prevents importing ghost records
 *   for EMP IDs that have not been registered in the employees table yet.
 *
 * Idempotency:
 *   Re-uploading the same file updates existing rows rather than duplicating them.
 */
class LeaveTrackerImporter extends BaseImporter
{
    use ParsesExcelDates;
    /** Cache: sheet_emp_id => Employee model (or null for unmatched) */
    protected array $empCache = [];

    /** Accumulate rows for a single bulk upsert in afterImport() */
    protected array $pendingRows = [];

    // ─────────────────────────────────────────────
    // TEMPLATE DEFINITION
    // ─────────────────────────────────────────────

    public function getTemplateHeaders(): array
    {
        return ['ID', 'EMP ID', 'Name', 'Date', 'Leave Type', 'Team LeaderName', 'Reason Type', 'Reason'];
    }

    public function getSampleRows(): array
    {
        return []; // empty template — headers only
    }

    public function getColumnValidations(): array
    {
        return [
            'ID'         => ['required' => true,  'format' => 'Unique leave tracker reference ID', 'example' => '20211800745355'],
            'EMP ID'     => ['required' => true,  'format' => 'Company employee ID',               'example' => '202118007'],
            'Name'       => ['required' => false, 'format' => 'Employee full name',                'example' => 'Vadher Neha Dipakbhai'],
            'Date'       => ['required' => true,  'format' => 'YYYY-MM-DD or any parseable date',  'example' => '2024-03-04'],
            'Leave Type' => ['required' => true,  'format' => 'Leave type code (CL/SL/EL etc.)',   'example' => 'CL'],
        ];
    }

    protected function getRequiredHeaders(): array
    {
        return ['Date', 'Leave Type'];
    }

    // ─────────────────────────────────────────────
    // ROW MAPPING
    // ─────────────────────────────────────────────

    public function mapRow(array $row, array $headers): array
    {
        $fieldMap = [
            'id'              => 'leave_ref_id',
            'emp id'          => 'sheet_emp_id',
            'name'            => 'employee_name',
            'date'            => 'leave_date_raw',
            'leave type'      => 'leave_type',
            'team leadername' => 'team_leader_name',
            'reason type'     => 'reason_type',
            'reason'          => 'reason',
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

    // ─────────────────────────────────────────────
    // VALIDATION
    // ─────────────────────────────────────────────

    /**
     * Decide whether a row should be skipped before validation/import.
     *
     * Returns:
     *   false  → import this row normally
     *   true   → skip silently (blank row — no entry in any report)
     *   string → skip AND add to the skipped-rows report with this reason
     *
     * Weekend dates are intentionally NOT skipped — the tracker legitimately
     * records leave on Saturdays (e.g. working Saturdays, half-day leaves).
     */
    public function shouldSkipRow(array $data): bool|string
    {
        // Truly blank rows → silent skip, no report entry
        if ($this->isRowEmpty($data)) {
            return true;
        }

        // EMP ID missing → reportable skip
        $sheetEmpId = trim((string) ($data['sheet_emp_id'] ?? ''));
        if ($sheetEmpId === '') {
            return 'EMP ID is blank — cannot identify employee.';
        }

        // EMP ID doesn't match any employee → reportable skip
        if ($this->resolveEmployee($sheetEmpId) === null) {
            $name = trim((string) ($data['employee_name'] ?? ''));
            return "EMP ID \"{$sheetEmpId}\"" . ($name ? " ({$name})" : '') . ' not found in the employees table.';
        }

        return false;
    }

    public function validate(array $data, int $rowNumber): array
    {
        $errors = [];

        // Leave Ref ID
        $refId = trim((string) ($data['leave_ref_id'] ?? ''));
        if ($refId === '') {
            $errors[] = 'ID (leave reference) is required.';
        }

        // Date — must be present and parseable (weekends already filtered by shouldSkipRow)
        $parsed = $this->parseDate($data['leave_date_raw'] ?? null);
        if ($parsed === null) {
            $rawVal = $data['leave_date_raw'] ?? 'empty';
            $errors[] = "Date is required and must be a valid date. Got: \"{$rawVal}\".";
        }

        // Leave Type
        $lt = trim((string) ($data['leave_type'] ?? ''));
        if ($lt === '') {
            $errors[] = 'Leave Type is required.';
        }

        return $errors;
    }

    // ─────────────────────────────────────────────
    // PROCESSING
    // ─────────────────────────────────────────────
    

    public function process(array $data, int $rowNumber): void
    {
        $refId      = trim((string) ($data['leave_ref_id'] ?? ''));
        $sheetEmpId = trim((string) ($data['sheet_emp_id'] ?? ''));
        $leaveDate  = $this->parseDate($data['leave_date_raw'] ?? null);
        $leaveType  = strtoupper(trim((string) ($data['leave_type'] ?? '')));

        // Employee is guaranteed to exist here — shouldSkipRow filtered unmatched rows
        $employee = $this->resolveEmployee($sheetEmpId);

        if (!$employee) {
            \Log::warning("LeaveTrackerImporter::process - Employee not found", [
                'sheet_emp_id' => $sheetEmpId,
                'row_number' => $rowNumber,
                'leave_date' => $leaveDate,
            ]);
            return; // Skip this row
        }

        $now = now()->toDateTimeString();

        // Accumulate into batch — flushed by afterImport()
        $this->pendingRows[] = [
            'leave_ref_id'     => $refId,
            'employee_id'      => $employee->id,
            'sheet_emp_id'     => $sheetEmpId ?: null,
            'employee_name'    => trim((string) ($data['employee_name'] ?? '')) ?: null,
            'leave_date'       => $leaveDate,
            'leave_type'       => $leaveType,
            'team_leader_name' => trim((string) ($data['team_leader_name'] ?? '')) ?: null,
            'reason_type'      => trim((string) ($data['reason_type'] ?? '')) ?: null,
            'reason'           => trim((string) ($data['reason'] ?? '')) ?: null,
            'source_file'      => $this->meta['source_file'] ?? null,
            'uploaded_by'      => $this->meta['uploaded_by'] ?? null,
            // Bulk-imported records are treated as already approved (they
            // reflect actual/historical leave, not a pending request) —
            // set explicitly rather than relying on the column default.
            'is_approved'      => true,
            'created_at'       => $now,
            'updated_at'       => $now,
        ];
    }

    /**
     * After all rows are processed:
     * 1. Flush pending tracker rows to the DB (bulk upsert).
     * 2. Sync leave_balances.availed for every affected employee so that
     *    ClosingCL / ClosingSL / CurrentCP columns on the salary report reflect real data.
     */
    public function afterImport(): void
    {
        if (empty($this->pendingRows)) {
            return;
        }

        // Collect sheet emp IDs from pendingRows (these are more reliable than employee_id)
        $affectedSheetIds = array_values(array_unique(
            array_filter(array_column($this->pendingRows, 'sheet_emp_id'))
        ));

        $updateColumns = [
            'employee_id', 'sheet_emp_id', 'employee_name', 'leave_date',
            'leave_type', 'team_leader_name', 'reason_type', 'reason',
            'source_file', 'uploaded_by', 'updated_at',
        ];

        foreach (array_chunk($this->pendingRows, 500) as $chunk) {
            LeaveTrackerRecord::upsert($chunk, ['leave_ref_id', 'leave_type'], $updateColumns);
        }

        $this->pendingRows = [];

        // ── Sync leave_balances from the tracker records ───────────────────────
        // Query database directly for all employees who now have leave records
        // This is more reliable than using employee_id from pendingRows (which might be null)
        if (!empty($affectedSheetIds)) {
            $affectedEmpIds = LeaveTrackerRecord::whereIn('sheet_emp_id', $affectedSheetIds)
                ->whereNotNull('employee_id')
                ->distinct()
                ->pluck('employee_id')
                ->all();

            if (!empty($affectedEmpIds)) {
                $synced = (new LeaveBalanceSyncService())->sync($affectedEmpIds);
                \Log::info('LeaveTrackerImporter: Leave balances synced successfully', [
                    'sheet_emp_ids' => $affectedSheetIds,
                    'employee_ids' => $affectedEmpIds,
                    'synced_entries' => $synced,
                    'timestamp' => now(),
                ]);
            } else {
                \Log::warning('LeaveTrackerImporter: No employee_id found in imported records', [
                    'sheet_emp_ids' => $affectedSheetIds,
                    'timestamp' => now(),
                ]);
            }
        }

        // ── Sync employee_status (active / inactive) from LFT / JN records ────
        // Re-evaluates only employees touched by THIS upload.
        // LFT with no subsequent JN  → inactive + date_of_leaving set
        // JN  with no subsequent LFT → active   + date_of_leaving cleared
        (new LeaveTrackerStatusService())->sync($affectedSheetIds ?: null);
    }


    // ─────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────

    /**
     * Resolve sheet EMP ID to an Employee model.
     * Returns null for empty or unmatched IDs — never throws.
     */
    protected function resolveEmployee(string $sheetEmpId): ?Employee
    {
        if ($sheetEmpId === '') {
            return null;
        }

        if (!array_key_exists($sheetEmpId, $this->empCache)) {
            $this->empCache[$sheetEmpId] = Employee::where('employee_id', $sheetEmpId)->first();
        }

        return $this->empCache[$sheetEmpId];
    }

    /**
    // parseDate() provided by ParsesExcelDates trait

    /**
     * Check if the entire row is effectively empty (all mapped fields are null/empty).
     */
    protected function isRowEmpty(array $data): bool
    {
        // Check all mapped fields
        $fieldsToCheck = [
            'leave_ref_id',
            'sheet_emp_id',
            'employee_name',
            'leave_date_raw',
            'leave_type',
            'team_leader_name',
            'reason_type',
            'reason',
        ];

        foreach ($fieldsToCheck as $field) {
            $value = $data[$field] ?? null;
            if ($value !== null && trim((string) $value) !== '') {
                return false; // Found non-empty field
            }
        }

        return true; // All fields are empty
    }
}
