<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\SalaryComponent;
use App\Models\SalaryStructure;
use App\Models\SalaryStructureComponent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// ══════════════════════════════════════════════════════════════
// SALARY COMPONENT CONTROLLER
// ══════════════════════════════════════════════════════════════

class SalaryStructureController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $structures = SalaryStructure::where('entity_id', $request->entity_id)
            ->when($request->boolean('with_components'), fn($q) => $q->with(['components.salaryComponent']))
            ->withCount('components')
            ->get();

        return response()->json(['data' => $structures]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('payroll.edit');

        $validated = $request->validate([
            'entity_id'   => 'required|exists:entities,id',
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string',
            'components'  => 'required|array|min:1',
            'components.*.salary_component_id' => 'required|exists:salary_components,id',
            'components.*.calculation_override'=> 'required|in:fixed,percentage,formula,inherit',
            'components.*.fixed_value'         => 'nullable|numeric|min:0',
            'components.*.percentage_value'    => 'nullable|numeric|min:0|max:200',
            'components.*.percentage_of'       => 'nullable|string',
            'components.*.sort_order'          => 'integer|min:0',
        ]);

        DB::beginTransaction();
        try {
            $structure = SalaryStructure::create([
                'entity_id'   => $validated['entity_id'],
                'name'        => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_active'   => true,
            ]);

            foreach ($validated['components'] as $idx => $comp) {
                SalaryStructureComponent::create([
                    'salary_structure_id'  => $structure->id,
                    'salary_component_id'  => $comp['salary_component_id'],
                    'calculation_override' => $comp['calculation_override'],
                    'fixed_value'          => $comp['fixed_value'] ?? null,
                    'percentage_value'     => $comp['percentage_value'] ?? null,
                    'percentage_of'        => $comp['percentage_of'] ?? null,
                    'sort_order'           => $comp['sort_order'] ?? $idx,
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json(['data' => $structure->load('components.salaryComponent')], 201);
    }

    public function show(SalaryStructure $salaryStructure): JsonResponse
    {
        return response()->json(['data' => $salaryStructure->load('components.salaryComponent')]);
    }

    public function update(Request $request, SalaryStructure $salaryStructure): JsonResponse
    {
        $this->authorize('payroll.edit');

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:100',
            'description' => 'sometimes|nullable|string',
            'is_active'   => 'sometimes|boolean',
            'components'  => 'sometimes|array',
            'components.*.salary_component_id' => 'required_with:components|exists:salary_components,id',
            'components.*.calculation_override'=> 'required_with:components|in:fixed,percentage,formula,inherit',
            'components.*.fixed_value'         => 'nullable|numeric',
            'components.*.percentage_value'    => 'nullable|numeric',
            'components.*.percentage_of'       => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $salaryStructure->update(array_filter($validated, fn($k) => $k !== 'components', ARRAY_FILTER_USE_KEY));

            if (isset($validated['components'])) {
                $salaryStructure->components()->delete();
                foreach ($validated['components'] as $idx => $comp) {
                    SalaryStructureComponent::create(array_merge($comp, [
                        'salary_structure_id' => $salaryStructure->id,
                        'sort_order'          => $comp['sort_order'] ?? $idx,
                    ]));
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json(['data' => $salaryStructure->fresh('components.salaryComponent')]);
    }

    public function destroy(SalaryStructure $salaryStructure): JsonResponse
    {
        $this->authorize('payroll.edit');

        // Check if any employee is on this structure
        $inUse = \App\Models\EmployeeSalary::where('salary_structure_id', $salaryStructure->id)
            ->where('is_current', true)
            ->exists();

        if ($inUse) {
            return response()->json(['message' => 'Cannot delete: structure is assigned to active employees.'], 422);
        }

        $salaryStructure->update(['is_active' => false]);
        return response()->json(['message' => 'Salary structure deactivated.']);
    }

    // Clone a structure as a new one
    public function clone(Request $request, SalaryStructure $salaryStructure): JsonResponse
    {
        $this->authorize('payroll.edit');

        $request->validate(['name' => 'required|string|max:100']);

        DB::beginTransaction();
        try {
            $clone = $salaryStructure->replicate();
            $clone->name      = $request->name;
            $clone->is_active = false;
            $clone->save();

            foreach ($salaryStructure->components as $comp) {
                $newComp = $comp->replicate();
                $newComp->salary_structure_id = $clone->id;
                $newComp->save();
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json(['data' => $clone->load('components.salaryComponent')], 201);
    }
}
