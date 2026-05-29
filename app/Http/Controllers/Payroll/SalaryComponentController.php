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

class SalaryComponentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $components = SalaryComponent::where('entity_id', $request->entity_id)
            ->when($request->component_type, fn($q) => $q->where('component_type', $request->component_type))
            ->when($request->is_active !== null, fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $components]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('payroll.edit');

        $validated = $request->validate([
            'entity_id'            => 'required|exists:entities,id',
            'name'                 => 'required|string|max:100',
            'code'                 => 'required|string|max:20',
            'component_type'       => 'required|in:earning,deduction,employer_contribution',
            'calculation_type'     => 'required|in:fixed,percentage,formula',
            'percentage_of'        => 'nullable|string',
            'percentage_value'     => 'nullable|numeric|min:0|max:100',
            'formula'              => 'nullable|string',
            'is_taxable'           => 'boolean',
            'is_pf_applicable'     => 'boolean',
            'is_esic_applicable'   => 'boolean',
            'is_pt_applicable'     => 'boolean',
            'is_lwf_applicable'    => 'boolean',
            'is_in_ctc'            => 'boolean',
            'is_in_gross'          => 'boolean',
            'show_in_payslip'      => 'boolean',
            'is_arrear_applicable' => 'boolean',
            'frequency'            => 'in:monthly,quarterly,annual,one_time',
            'sort_order'           => 'integer|min:0',
        ]);

        $component = SalaryComponent::create($validated);
        return response()->json(['data' => $component], 201);
    }

    public function show(SalaryComponent $salaryComponent): JsonResponse
    {
        return response()->json(['data' => $salaryComponent]);
    }

    public function update(Request $request, SalaryComponent $salaryComponent): JsonResponse
    {
        $this->authorize('payroll.edit');

        $validated = $request->validate([
            'name'             => 'sometimes|string|max:100',
            'calculation_type' => 'sometimes|in:fixed,percentage,formula',
            'percentage_value' => 'sometimes|nullable|numeric',
            'percentage_of'    => 'sometimes|nullable|string',
            'formula'          => 'sometimes|nullable|string',
            'is_taxable'       => 'sometimes|boolean',
            'is_active'        => 'sometimes|boolean',
            'sort_order'       => 'sometimes|integer',
        ]);

        $salaryComponent->update($validated);
        return response()->json(['data' => $salaryComponent]);
    }

    public function destroy(SalaryComponent $salaryComponent): JsonResponse
    {
        $this->authorize('payroll.edit');
        $salaryComponent->update(['is_active' => false]);
        return response()->json(['message' => 'Component deactivated.']);
    }
}

// ══════════════════════════════════════════════════════════════
// SALARY STRUCTURE CONTROLLER
// ══════════════════════════════════════════════════════════════
