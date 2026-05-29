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
            // Auto-generate employee_id if not provided
            if (empty($data['employee_id'])) {
                $data['employee_id'] = $this->generateEmployeeId($data['entity_id']);
            }

            // Auto-compute probation end date
            if (!empty($data['date_of_joining']) && !isset($data['probation_end_date'])) {
                $probationMonths = $data['probation_period_months'] ?? 6;
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
                'new_values'     => ['entity_id' => $employee->entity_id, 'department_id' => $employee->department_id],
                'created_by'     => auth()->id(),
            ]);

            // Initial leave balance for this year
            $this->initLeaveBalances($employee);

            return $employee->fresh(['department', 'designation', 'grade', 'entity']);
        });
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

    protected function generateEmployeeId(int $entityId): string
    {
        $last = Employee::where('entity_id', $entityId)
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
                $q->whereNull('entity_id')
                  ->orWhere('entity_id', $employee->entity_id);
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
