<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\BonusRule;
use App\Models\EmployeeBonus;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeBonusController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $bonuses = EmployeeBonus::with(['employee:id,employee_id,first_name,last_name', 'bonusRule:id,name'])
            ->whereHas('employee', fn($q) => $q->where('entity_id', $request->entity_id))
            ->when($request->year,  fn($q) => $q->where('year', $request->year))
            ->when($request->month, fn($q) => $q->where('month', $request->month))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderByDesc('year')->orderByDesc('month')
            ->paginate(50);

        return response()->json($bonuses);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('payroll.edit');

        $validated = $request->validate([
            'employee_id'  => 'required|exists:employees,id',
            'bonus_rule_id'=> 'required|exists:bonus_rules,id',
            'bonus_name'   => 'required|string|max:200',
            'amount'       => 'required|numeric|min:0',
            'year'         => 'required|integer',
            'month'        => 'nullable|integer|min:1|max:12',
            'is_deferred'  => 'boolean',
            'deferred_till'=> 'required_if:is_deferred,true|nullable|date',
            'remarks'      => 'nullable|string',
        ]);

        $bonus = EmployeeBonus::create(array_merge($validated, ['status' => 'approved']));
        return response()->json(['data' => $bonus], 201);
    }

    // Bulk assign bonus (same bonus to all employees in an entity/dept)
    public function bulkStore(Request $request): JsonResponse
    {
        $this->authorize('payroll.edit');

        $validated = $request->validate([
            'entity_id'     => 'required|exists:entities,id',
            'department_id' => 'nullable|exists:departments,id',
            'bonus_rule_id' => 'required|exists:bonus_rules,id',
            'bonus_name'    => 'required|string|max:200',
            'year'          => 'required|integer',
            'month'         => 'nullable|integer|min:1|max:12',
            'basis'         => 'required|in:fixed,percentage_basic,percentage_gross',
            'value'         => 'required|numeric|min:0',
            'remarks'       => 'nullable|string',
        ]);

        $employees = Employee::where('entity_id', $validated['entity_id'])
            ->where('employee_status', 'active')
            ->when($validated['department_id'] ?? null, fn($q) => $q->where('department_id', $validated['department_id']))
            ->with('currentSalary')
            ->get();

        $created = 0;
        DB::transaction(function () use ($employees, $validated, &$created) {
            foreach ($employees as $emp) {
                $amount = match($validated['basis']) {
                    'fixed'              => $validated['value'],
                    'percentage_basic'   => round(($emp->currentSalary?->gross_monthly * 0.4 * $validated['value']) / 100, 2),
                    'percentage_gross'   => round(($emp->currentSalary?->gross_monthly * $validated['value']) / 100, 2),
                    default              => $validated['value'],
                };

                EmployeeBonus::create([
                    'employee_id'   => $emp->id,
                    'bonus_rule_id' => $validated['bonus_rule_id'],
                    'bonus_name'    => $validated['bonus_name'],
                    'amount'        => $amount,
                    'year'          => $validated['year'],
                    'month'         => $validated['month'] ?? null,
                    'remarks'       => $validated['remarks'] ?? null,
                    'status'        => 'approved',
                ]);
                $created++;
            }
        });

        return response()->json(['message' => "{$created} bonus records created."]);
    }

    public function show(EmployeeBonus $employeeBonus): JsonResponse
    {
        return response()->json(['data' => $employeeBonus->load('employee', 'bonusRule')]);
    }

    public function destroy(EmployeeBonus $employeeBonus): JsonResponse
    {
        if ($employeeBonus->payroll_run_id) {
            return response()->json(['message' => 'Cannot delete: already included in a payroll run.'], 422);
        }
        $employeeBonus->delete();
        return response()->json(['message' => 'Bonus deleted.']);
    }
}
