<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveTrackerRecord;
use App\Models\LeaveType;
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

        $now = now()->toDateTimeString();

        // Accumulate into batch — flushed by afterImport()
        $this->pendingRows[] = [
            'leave_ref_id'     => $refId,
            'employee_id'      => $employee?->id,
            'sheet_emp_id'     => $sheetEmpId ?: null,
            'employee_name'    => trim((string) ($data['employee_name'] ?? '')) ?: null,
            'leave_date'       => $leaveDate,
            'leave_type'       => $leaveType,
            'team_leader_name' => trim((string) ($data['team_leader_name'] ?? '')) ?: null,
            'reason_type'      => trim((string) ($data['reason_type'] ?? '')) ?: null,
            'reason'           => trim((string) ($data['reason'] ?? '')) ?: null,
            'source_file'      => $this->meta['source_file'] ?? null,
            'uploaded_by'      => $this->meta['uploaded_by'] ?? null,
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

        // Collect distinct employee_ids before we clear pendingRows
        $affectedEmpIds = array_values(array_unique(
            array_filter(array_column($this->pendingRows, 'employee_id'))
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
        if (!empty($affectedEmpIds)) {
            $this->syncLeaveBalances($affectedEmpIds);
        }
    }

    /**
     * Aggregate leave_tracker_records for the given employees and upsert into
     * leave_balances (availed + closing_balance).
     *
     * leave_balances.closing_balance = opening_balance + accrued + carry_forwarded
     *                                  + adjusted - availed - encashed - lapsed
     * Since we only manage availed here, closing_balance = -availed when other
     * fields are 0. To keep it meaningful (days consumed), we store availed as
     * the closing_balance when no opening/accrual data is set.
     */
    protected function syncLeaveBalances(array $empIds): void
    {
        // Build code → id map for all global leave types (entity_id IS NULL)
        // and fall back to any entity-specific type with the same code.
        $leaveTypeMap = LeaveType::whereIn('code', ['CL', 'SL', 'EL', 'CP', 'LOP', 'WFH',
                                                     'HCL', 'HSL', 'CO', 'HCO', 'MTL', 'PTL',
                                                     'RL', 'JN', 'POM', 'PIM', 'PM', 'BDY',
                                                     'WDA', 'AP', 'LFT', 'EL', 'LOP'])
            ->orderByRaw('entity_id IS NULL DESC') // prefer global (NULL) entries
            ->get()
            ->keyBy('code')
            ->map(fn($lt) => $lt->id);

        // Aggregate: (employee_id, leave_type code, FY year) → count of days availed.
        // Financial year: April 1 – March 31.  FY year = start calendar year.
        //   Apr-Dec of year Y  → fy_year = Y
        //   Jan-Mar of year Y  → fy_year = Y-1
        $fyExpr = 'CASE WHEN MONTH(leave_date) >= 4 THEN YEAR(leave_date) ELSE YEAR(leave_date) - 1 END';

        $rows = DB::table('leave_tracker_records')
            ->select(
                'employee_id',
                'leave_type',
                DB::raw("{$fyExpr} as fy_year"),
                DB::raw('MONTH(leave_date) as lv_month'),
                DB::raw('COUNT(*) as availed')
            )
            ->whereIn('employee_id', $empIds)
            ->whereNotNull('employee_id')
            ->where('is_active', true)
            ->groupBy('employee_id', 'leave_type', DB::raw($fyExpr), DB::raw('MONTH(leave_date)'))
            ->get();

        $now = now()->toDateTimeString();

        foreach ($rows as $row) {
            $leaveTypeId = $leaveTypeMap[$row->leave_type] ?? null;
            if ($leaveTypeId === null) {
                continue; // unknown leave type code — skip
            }

            $month = (int) $row->lv_month;

            // Read existing monthly balance so we don't overwrite opening/accrued/carry-forward
            $existing = LeaveBalance::where('employee_id', $row->employee_id)
                ->where('leave_type_id', $leaveTypeId)
                ->where('year', $row->fy_year)
                ->where('month', $month)
                ->first();

            $opening       = (float) ($existing->opening_balance  ?? 0);
            $accrued       = (float) ($existing->accrued          ?? 0);
            $carryFwd      = (float) ($existing->carry_forwarded  ?? 0);
            $adjusted      = (float) ($existing->adjusted         ?? 0);
            $encashed      = (float) ($existing->encashed         ?? 0);
            $lapsed        = (float) ($existing->lapsed           ?? 0);
            $availed       = (float) $row->availed;
            $closingBal    = $opening + $accrued + $carryFwd + $adjusted - $availed - $encashed - $lapsed;

            LeaveBalance::updateOrCreate(
                [
                    'employee_id'   => $row->employee_id,
                    'leave_type_id' => $leaveTypeId,
                    'year'          => $row->fy_year,
                    'month'         => $month,
                ],
                [
                    'availed'         => $availed,
                    'closing_balance' => $closingBal,
                    'updated_at'      => $now,
                ]
            );
        }
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
     * Parse a date value that may arrive in several forms from PhpSpreadsheet:
     *
     *   • PHP DateTime / Carbon object
     *   • Formatted date string: "04-03-2024", "3/4/2024", "2024-03-04", "04-Mar-2024"
     *   • Excel date serial as float or int: 45354 → 2024-03-04
     *   • Numeric string that looks like an Excel serial: "45354"
     *
     * Returns "Y-m-d" string or null on failure.
     */
    protected function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // DateTime / Carbon
        if ($value instanceof \DateTime) {
            return Carbon::instance($value)->format('Y-m-d');
        }

        // Numeric — could be Excel date serial
        if (is_numeric($value)) {
            $val = (float) $value;
            // Excel date serials start at 1 (1900-01-01). Realistic range: 30000–60000
            if ($val >= 1 && $val < 100000) {
                try {
                    $dt = ExcelDate::excelToDateTimeObject($val);
                    return Carbon::instance($dt)->format('Y-m-d');
                } catch (\Throwable) {
                    // fall through to Carbon::parse
                }
            }
        }

        // String — try Carbon parsing (handles many formats)
        try {
            $str = trim((string) $value);
            if ($str === '') {
                return null;
            }
            return Carbon::parse($str)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

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
