<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\BonusRule;
use App\Models\EmployeeBonus;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BonusController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => BonusRule::where('company_id', $request->company_id)->where('is_active', true)->get()
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('payroll.edit');

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name'              => 'required|string|max:100',
            'bonus_type'        => 'required|in:performance,statutory,festival,gratuitous,retention',
            'amount_or_pct'     => 'required|in:amount,percentage',
            'calculation_basis' => 'required|in:basic,gross,ctc,fixed',
            'frequency'         => 'required|in:monthly,quarterly,annual,one_time',
            'is_taxable'        => 'boolean',
            'is_in_payroll'     => 'boolean',
        ]);

        return response()->json(['data' => BonusRule::create($validated)], 201);
    }

    public function show(BonusRule $bonusRule): JsonResponse
    {
        return response()->json(['data' => $bonusRule]);
    }

    public function update(Request $request, BonusRule $bonusRule): JsonResponse
    {
        $bonusRule->update($request->validate(['name' => 'sometimes|string', 'is_active' => 'sometimes|boolean']));
        return response()->json(['data' => $bonusRule]);
    }

    public function destroy(BonusRule $bonusRule): JsonResponse
    {
        $bonusRule->update(['is_active' => false]);
        return response()->json(['message' => 'Bonus rule deactivated.']);
    }
}
