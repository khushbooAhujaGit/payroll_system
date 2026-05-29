<?php

namespace App\Http\Controllers\Statutory;

use App\Http\Controllers\Controller;
use App\Models\InvestmentDeclaration;
use App\Models\DeclarationHra;
use App\Models\DeclarationInvestment;
use App\Models\PoiSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvestmentDeclarationController extends Controller
{
    // ─────────────────────────────────────────────
    // EMPLOYEE: VIEW MY DECLARATION
    // ─────────────────────────────────────────────

    public function myDeclaration(Request $request): JsonResponse
    {
        $employee = auth()->user()->employee;
        $fy       = $request->get('fy', $this->currentFy());

        $declaration = InvestmentDeclaration::where('employee_id', $employee->id)
            ->where('financial_year', $fy)
            ->with(['investments', 'hra'])
            ->first();

        return response()->json(['data' => $declaration]);
    }

    // ─────────────────────────────────────────────
    // EMPLOYEE: SAVE DECLARATION (Form 12BB)
    // ─────────────────────────────────────────────

    public function saveDeclaration(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'financial_year'            => 'required|integer',
            'regime'                    => 'required|in:old,new',
            // HRA
            'hra'                       => 'nullable|array',
            'hra.landlord_name'         => 'required_with:hra|string',
            'hra.landlord_pan'          => 'nullable|string|size:10',
            'hra.rental_address'        => 'required_with:hra|string',
            'hra.monthly_rent'          => 'required_with:hra|numeric|min:0',
            'hra.from_date'             => 'required_with:hra|date',
            'hra.to_date'               => 'required_with:hra|date|after:hra.from_date',
            // Investments (80C, 80D, etc.)
            'investments'               => 'nullable|array',
            'investments.*.section'     => 'required|string',
            'investments.*.investment_type' => 'required|string',
            'investments.*.declared_amount' => 'required|numeric|min:0',
        ]);

        $employee = auth()->user()->employee;
        $fy       = $validated['financial_year'];

        DB::beginTransaction();
        try {
            $declaration = InvestmentDeclaration::updateOrCreate(
                ['employee_id' => $employee->id, 'financial_year' => $fy],
                [
                    'regime' => $validated['regime'],
                    'status' => 'draft',
                ]
            );

            // HRA
            if ($request->has('hra') && $validated['regime'] === 'old') {
                DeclarationHra::updateOrCreate(
                    ['investment_declaration_id' => $declaration->id],
                    $validated['hra']
                );
            }

            // Chapter VI-A Investments
            if ($request->has('investments')) {
                DeclarationInvestment::where('investment_declaration_id', $declaration->id)->delete();
                foreach ($validated['investments'] as $inv) {
                    DeclarationInvestment::create([
                        'investment_declaration_id' => $declaration->id,
                        'section'                   => $inv['section'],
                        'investment_type'            => $inv['investment_type'],
                        'declared_amount'            => $inv['declared_amount'],
                        'proof_status'               => 'pending',
                    ]);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json([
            'message' => 'Declaration saved.',
            'data'    => $declaration->fresh(['investments', 'hra']),
        ]);
    }

    // ─────────────────────────────────────────────
    // EMPLOYEE: SUBMIT DECLARATION
    // ─────────────────────────────────────────────

    public function submitDeclaration(Request $request): JsonResponse
    {
        $employee    = auth()->user()->employee;
        $fy          = $request->get('fy', $this->currentFy());
        $declaration = InvestmentDeclaration::where('employee_id', $employee->id)
            ->where('financial_year', $fy)
            ->firstOrFail();

        if ($declaration->status === 'approved') {
            return response()->json(['message' => 'Declaration is already approved.'], 422);
        }

        $declaration->update(['status' => 'submitted', 'submitted_at' => now()]);

        return response()->json(['message' => 'Declaration submitted for HR review.']);
    }

    // ─────────────────────────────────────────────
    // HR: LIST ALL DECLARATIONS
    // ─────────────────────────────────────────────

    public function allDeclarations(Request $request): JsonResponse
    {
        $this->authorize('investment_declaration.view');

        $declarations = InvestmentDeclaration::with([
            'employee:id,employee_id,first_name,last_name,department_id',
            'employee.department:id,name',
        ])
        ->whereHas('employee', fn($q) => $q->where('entity_id', $request->entity_id))
        ->where('financial_year', $request->get('fy', $this->currentFy()))
        ->when($request->status, fn($q) => $q->where('status', $request->status))
        ->paginate(50);

        return response()->json($declarations);
    }

    // ─────────────────────────────────────────────
    // HR: APPROVE / REJECT DECLARATION
    // ─────────────────────────────────────────────

    public function approveDeclaration(Request $request, InvestmentDeclaration $declaration): JsonResponse
    {
        $this->authorize('investment_declaration.approve');

        $declaration->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return response()->json(['message' => 'Declaration approved.']);
    }

    public function rejectDeclaration(Request $request, InvestmentDeclaration $declaration): JsonResponse
    {
        $this->authorize('investment_declaration.approve');

        $request->validate(['reason' => 'required|string']);

        $declaration->update(['status' => 'rejected']);

        return response()->json(['message' => 'Declaration rejected.']);
    }

    // ─────────────────────────────────────────────
    // SECTION LIMITS  (helper for frontend)
    // ─────────────────────────────────────────────

    public function sectionLimits(): JsonResponse
    {
        $limits = [
            '80C'  => ['limit' => 150000, 'label' => 'Section 80C', 'types' => ['PPF', 'ELSS', 'NSC', 'LIC Premium', 'Home Loan Principal', 'Children Tuition Fees', 'Tax Saver FD', 'Sukanya Samridhi']],
            '80CCC'=> ['limit' => 150000, 'label' => 'Section 80CCC - Pension Plans', 'types' => ['Pension Fund', 'Annuity Plan']],
            '80CCD' => ['limit' => 50000, 'label' => 'Section 80CCD(1B) - NPS', 'types' => ['NPS Additional Contribution']],
            '80D'  => ['limit' => 25000, 'label' => 'Section 80D - Medical Insurance', 'types' => ['Health Insurance - Self', 'Health Insurance - Parents (Senior Citizen)']],
            '80E'  => ['limit' => null,  'label' => 'Section 80E - Education Loan Interest', 'types' => ['Education Loan Interest']],
            '80G'  => ['limit' => null,  'label' => 'Section 80G - Donations', 'types' => ['Donation to Charity', 'PM Relief Fund']],
            '24B'  => ['limit' => 200000, 'label' => 'Section 24B - Home Loan Interest', 'types' => ['Home Loan Interest']],
            'HRA'  => ['limit' => null,  'label' => 'HRA Exemption', 'types' => ['Rent Payment']],
            '80TTA'=> ['limit' => 10000, 'label' => 'Section 80TTA - Savings Interest', 'types' => ['Savings Account Interest']],
        ];

        return response()->json(['data' => $limits]);
    }

    private function currentFy(): int
    {
        return now()->month >= 4 ? now()->year : now()->year - 1;
    }
}
