<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeHistory;
use App\Models\EmployeeSalary;
use App\Models\EmployeeSalaryComponent;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\PayrollRun;
use App\Models\PayrollEmployeeRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EmployeeService
{
    public function create(array $data): Employee
    {
        return DB::transaction(function () use ($data) {
            // ── Check for a soft-deleted employee to restore ─────────────────
            // Priority: PAN match first (unique identifier), then full-name match.
            // Restoring avoids creating a duplicate record and preserves history.
            $deleted = $this->findSoftDeleted($data);
            if ($deleted) {
                return $this->restoreAndUpdate($deleted, $data);
            }

            // Auto-generate employee_id if not provided
            if (empty($data['employee_id'])) {
                $data['employee_id'] = $this->generateEmployeeId($data['company_id']);
            }

            // Auto-compute probation end date
            if (!empty($data['date_of_joining']) && !isset($data['probation_end_date'])) {
                $probationMonths = isset($data['probation_period_months']) && $data['probation_period_months'] !== ''
                    ? (int) $data['probation_period_months']
                    : 6;
                $data['probation_end_date'] = \Carbon\Carbon::parse($data['date_of_joining'])
                    ->addMonths($probationMonths)
                    ->format('Y-m-d');
            }

            $data['created_by'] = auth()->id();
            $employee = Employee::create($data);

            // Statutory details
            if (!empty($data['pan_number']) || !empty($data['aadhaar_number'])) {
                $employee->statutoryDetails()->create([
                    'pan_number'     => $data['pan_number'] ?? null,
                    'aadhaar_number' => $data['aadhaar_number'] ?? null,
                    'uan_number'     => $data['uan_number'] ?? null,
                    'esic_number'    => $data['esic_number'] ?? null,
                ]);
            }

            // Bank details
            if (!empty($data['account_number'])) {
                $employee->bankDetails()->create([
                    'bank_name'           => $data['bank_name'] ?? '',
                    'account_number'      => $data['account_number'],
                    'ifsc_code'           => $data['ifsc_code'] ?? '',
                    'account_holder_name' => $data['account_holder_name'] ?? $employee->full_name,
                    'account_type'        => 'savings',
                    'is_primary'          => true,
                ]);
            }

            // Joining history log
            EmployeeHistory::create([
                'employee_id'    => $employee->id,
                'event_type'     => 'joining',
                'effective_date' => $employee->date_of_joining,
                'new_values'     => ['company_id' => $employee->company_id, 'department_id' => $employee->department_id],
                'created_by'     => auth()->id(),
            ]);

            // Initial leave balance for this year
            $this->initLeaveBalances($employee);

            return $employee->fresh(['department', 'designation', 'grade', 'company']);
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SOFT-DELETE RESTORATION
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Find a soft-deleted employee who matches this incoming data.
     *
     * Match priority (most reliable first):
     *   1. PAN number  — unique statutory identifier
     *   2. First + Last name  — fallback when PAN is not provided
     */
    public function findSoftDeleted(array $data): ?Employee
    {
        // 1. PAN match
        $pan = !empty($data['pan_number']) ? strtoupper(trim((string) $data['pan_number'])) : null;
        if ($pan) {
            $byPan = Employee::onlyTrashed()
                ->whereHas('statutoryDetails', fn($q) => $q->where('pan_number', $pan))
                ->first();
            if ($byPan) return $byPan;
        }

        // 2. Full-name match (first + last, case-insensitive)
        $firstName = trim($data['first_name'] ?? '');
        $lastName  = trim($data['last_name']  ?? '');
        if ($firstName !== '' && $lastName !== '') {
            $byName = Employee::onlyTrashed()
                ->whereRaw('LOWER(first_name) = ?', [strtolower($firstName)])
                ->whereRaw('LOWER(last_name)  = ?', [strtolower($lastName)])
                ->first();
            if ($byName) return $byName;
        }

        return null;
    }

    /**
     * Restore a soft-deleted employee and update all their details.
     * Called from both manual create (EmployeeService) and bulk import (EmployeeImporter).
     */
    public function restoreAndUpdate(Employee $employee, array $data): Employee
    {
        // Restore the soft-delete (sets deleted_at = null)
        $employee->restore();

        // Only mark active if there is NO leaving date on the record (or on the incoming data).
        // If the employee still has a date_of_leaving, keep them inactive — they left; don't
        // silently re-activate them just because someone re-submitted their data.
        $hasLeavingDate = !empty($data['date_of_leaving'])
            || (!empty($employee->date_of_leaving) && empty($data['date_of_joining']));

        if ($hasLeavingDate) {
            $data['employee_status'] = 'inactive';
            // Preserve whatever leaving date is set; don't null it out
        } else {
            $data['employee_status'] = 'active';
            $data['date_of_leaving'] = null; // re-hire — clear the old leaving date
        }

        // Update the main employee record — only non-null incoming values
        $updateFields = array_filter($data, fn($v) => $v !== null && $v !== '');
        // These keys belong to related tables, not the employees table itself
        $relatedKeys = ['pan_number', 'aadhaar_number', 'uan_number', 'esic_number',
                        'bank_name', 'account_number', 'ifsc_code', 'account_holder_name',
                        'company_id'];
        foreach ($relatedKeys as $k) unset($updateFields[$k]);

        $employee->update($updateFields);

        // Statutory details — updateOrCreate so existing data is preserved when not provided
        $pan        = !empty($data['pan_number'])     ? strtoupper(trim($data['pan_number'])) : null;
        $aadhaar    = $data['aadhaar_number'] ?? null;
        $uan        = $data['uan_number']     ?? null;
        $esic       = $data['esic_number']    ?? null;
        if ($pan || $aadhaar || $uan || $esic) {
            $employee->statutoryDetails()->updateOrCreate(
                ['employee_id' => $employee->id],
                array_filter([
                    'pan_number'     => $pan,
                    'aadhaar_number' => $aadhaar,
                    'uan_number'     => $uan,
                    'esic_number'    => $esic,
                ], fn($v) => $v !== null)
            );
        }

        // Bank details — update primary bank if provided
        if (!empty($data['account_number'])) {
            $employee->bankDetails()->updateOrCreate(
                ['employee_id' => $employee->id, 'is_primary' => true],
                [
                    'bank_name'           => $data['bank_name']           ?? '',
                    'account_number'      => $data['account_number'],
                    'ifsc_code'           => $data['ifsc_code']           ?? '',
                    'account_holder_name' => $data['account_holder_name'] ?? trim($employee->full_name),
                    'account_type'        => 'savings',
                    'is_primary'          => true,
                ]
            );
        }

        // Log the re-hiring event
        EmployeeHistory::create([
            'employee_id'    => $employee->id,
            'event_type'     => 'rehire',
            'effective_date' => $data['date_of_joining'] ?? now()->toDateString(),
            'new_values'     => ['status' => 'active', 'restored_from' => 'soft_delete'],
            'created_by'     => auth()->id() ?? null,
        ]);

        return $employee->fresh(['department', 'designation', 'grade', 'company']);
    }

    public function update(Employee $employee, array $data): Employee
    {
        return DB::transaction(function () use ($employee, $data) {
            $trackableFields = ['department_id', 'designation_id', 'grade_id', 'branch_id', 'employee_status', 'reporting_manager_id'];
            $changed = array_intersect_key($data, array_flip($trackableFields));
            $old     = $employee->only(array_keys($changed));

            $employee->update($data);

            if (!empty($changed)) {
                EmployeeHistory::create([
                    'employee_id'    => $employee->id,
                    'event_type'     => 'role_change',
                    'effective_date' => now()->toDateString(),
                    'old_values'     => $old,
                    'new_values'     => $changed,
                    'created_by'     => auth()->id(),
                ]);
            }

            // Update statutory (any of PAN, Aadhaar, or UAN triggers an upsert)
            if (!empty($data['pan_number']) || !empty($data['aadhaar_number']) || !empty($data['uan_number'])) {
                $employee->statutoryDetails()->updateOrCreate(
                    ['employee_id' => $employee->id],
                    array_filter([
                        'pan_number'     => $data['pan_number'] ?? null,
                        'aadhaar_number' => $data['aadhaar_number'] ?? null,
                        'uan_number'     => $data['uan_number'] ?? null,
                    ])
                );
            }

            return $employee->fresh();
        });
    }

    protected function generateEmployeeId(int $companyId): string
    {
        $last = Employee::where('company_id', $companyId)
            ->whereNotNull('employee_id')
            ->orderByDesc('id')
            ->value('employee_id');
        $num = 1;
        if ($last) {
            preg_match('/(\d+)$/', $last, $m);
            $num = (int)($m[1] ?? 0) + 1;
        }
        return 'EMP' . str_pad($num, 4, '0', STR_PAD_LEFT);
    }

    protected function initLeaveBalances(Employee $employee): void
    {
        $year       = now()->year;
        // Include both global (entity_id = null) and entity-specific leave types
        $leaveTypes = LeaveType::where(function ($q) use ($employee) {
                $q->whereNull('company_id')
                  ->orWhere('company_id', $employee->company_id);
            })
            ->where('is_active', true)
            ->get();

        foreach ($leaveTypes as $lt) {
            LeaveBalance::firstOrCreate(
                ['employee_id' => $employee->id, 'leave_type_id' => $lt->id, 'year' => $year],
                [
                    'opening_balance' => 0,
                    'accrued'         => $lt->policies->first()?->days_per_year ?? 0,
                    'closing_balance' => $lt->policies->first()?->days_per_year ?? 0,
                ]
            );
        }
    }
}
