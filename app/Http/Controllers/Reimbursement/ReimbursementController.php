<?php

namespace App\Http\Controllers\Reimbursement;

use App\Http\Controllers\Controller;
use App\Models\ReimbursementClaim;
use App\Models\ReimbursementItem;
use App\Models\ReimbursementType;
use App\Services\ReimbursementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReimbursementController extends Controller
{
    public function __construct(protected ReimbursementService $reimbursementService) {}

    // ─────────────────────────────────────────────
    // REIMBURSEMENT TYPES
    // ─────────────────────────────────────────────

    public function types(Request $request): JsonResponse
    {
        $types = ReimbursementType::where('company_id', $request->company_id)->where('is_active', true)->get();
        return response()->json(['data' => $types]);
    }

    public function storeType(Request $request): JsonResponse
    {
        $this->authorize('reimbursement.manage');

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name'          => 'required|string|max:100',
            'code'          => 'required|string|max:20',
            'monthly_limit' => 'nullable|numeric|min:0',
            'annual_limit'  => 'nullable|numeric|min:0',
            'is_taxable'    => 'boolean',
            'requires_bill' => 'boolean',
        ]);

        $type = ReimbursementType::create($validated);
        return response()->json(['data' => $type], 201);
    }

    // ─────────────────────────────────────────────
    // MY CLAIMS (Employee)
    // ─────────────────────────────────────────────

    public function myClaims(Request $request): JsonResponse
    {
        $employee = auth()->user()->employee;

        $claims = ReimbursementClaim::where('employee_id', $employee->id)
            ->when($request->year, fn($q) => $q->where('year', $request->year))
            ->when($request->month, fn($q) => $q->where('month', $request->month))
            ->with(['items.reimbursementType'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($claims);
    }

    // ─────────────────────────────────────────────
    // CREATE CLAIM
    // ─────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year'           => 'required|integer',
            'month'          => 'required|integer|min:1|max:12',
            'items'          => 'required|array|min:1',
            'items.*.reimbursement_type_id' => 'required|exists:reimbursement_types,id',
            'items.*.expense_date'          => 'required|date',
            'items.*.description'           => 'required|string|max:500',
            'items.*.claimed_amount'        => 'required|numeric|min:0.01',
        ]);

        $employee = auth()->user()->employee;

        // Check if claim already exists for this month
        $existing = ReimbursementClaim::where('employee_id', $employee->id)
            ->where('year', $validated['year'])
            ->where('month', $validated['month'])
            ->whereNotIn('status', ['rejected'])
            ->first();

        if ($existing) {
            return response()->json(['message' => 'A claim for this month already exists. Please edit or cancel it.', 'data' => $existing], 422);
        }

        DB::beginTransaction();
        try {
            $totalClaimed = array_sum(array_column($validated['items'], 'claimed_amount'));

            $claim = ReimbursementClaim::create([
                'claim_number'  => $this->generateClaimNumber(),
                'employee_id'   => $employee->id,
                'year'          => $validated['year'],
                'month'         => $validated['month'],
                'total_claimed' => $totalClaimed,
                'status'        => 'submitted',
            ]);

            foreach ($validated['items'] as $idx => $item) {
                $billPath = null;
                if ($request->hasFile("bills.{$idx}")) {
                    $billPath = $request->file("bills.{$idx}")->store("reimbursements/{$employee->id}", 'local');
                }

                ReimbursementItem::create([
                    'reimbursement_claim_id'  => $claim->id,
                    'reimbursement_type_id'   => $item['reimbursement_type_id'],
                    'expense_date'            => $item['expense_date'],
                    'description'             => $item['description'],
                    'claimed_amount'          => $item['claimed_amount'],
                    'bill_path'               => $billPath,
                    'status'                  => 'pending',
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json(['message' => 'Reimbursement claim submitted.', 'data' => $claim->load('items.reimbursementType')], 201);
    }

    // ─────────────────────────────────────────────
    // ADD ITEM to existing draft
    // ─────────────────────────────────────────────

    public function addItem(Request $request, ReimbursementClaim $claim): JsonResponse
    {
        if (!in_array($claim->status, ['draft', 'submitted'])) {
            return response()->json(['message' => 'Cannot add items to an approved/paid claim.'], 422);
        }

        $validated = $request->validate([
            'reimbursement_type_id' => 'required|exists:reimbursement_types,id',
            'expense_date'          => 'required|date',
            'description'           => 'required|string|max:500',
            'claimed_amount'        => 'required|numeric|min:0.01',
            'bill'                  => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $billPath = null;
        if ($request->hasFile('bill')) {
            $billPath = $request->file('bill')->store("reimbursements/{$claim->employee_id}", 'local');
        }

        $item = $claim->items()->create(array_merge($validated, ['bill_path' => $billPath, 'status' => 'pending']));
        $claim->update(['total_claimed' => $claim->items()->sum('claimed_amount')]);

        return response()->json(['message' => 'Item added.', 'data' => $item], 201);
    }

    // ─────────────────────────────────────────────
    // HR: ALL CLAIMS
    // ─────────────────────────────────────────────

    public function allClaims(Request $request): JsonResponse
    {
        $this->authorize('reimbursement.view');

        $claims = ReimbursementClaim::with([
            'employee:id,employee_id,first_name,middle_name,last_name,department_id',
            'employee.department:id,name',
            'items.reimbursementType:id,name',
            'approvedBy:id,name',
        ])
        ->whereHas('employee', fn($q) => $q->where('company_id', $request->company_id))
        ->when($request->status, fn($q) => $q->where('status', $request->status))
        ->when($request->year,   fn($q) => $q->where('year', $request->year))
        ->when($request->month,  fn($q) => $q->where('month', $request->month))
        ->orderByDesc('created_at')
        ->paginate(50);

        return response()->json($claims);
    }

    // ─────────────────────────────────────────────
    // APPROVE / REJECT CLAIM
    // ─────────────────────────────────────────────

    public function approveClaim(Request $request, ReimbursementClaim $claim): JsonResponse
    {
        $this->authorize('reimbursement.approve');

        $request->validate([
            'items'              => 'required|array',
            'items.*.id'         => 'required|exists:reimbursement_items,id',
            'items.*.action'     => 'required|in:approved,rejected',
            'items.*.amount'     => 'nullable|numeric|min:0',
            'items.*.reason'     => 'nullable|string',
        ]);

        $itemApprovals = collect($request->items)->keyBy('id');
        $this->reimbursementService->approve($claim, $itemApprovals->toArray());

        return response()->json(['message' => 'Reimbursement claim reviewed.', 'data' => $claim->fresh('items')]);
    }

    // ─────────────────────────────────────────────
    // MARK AS PAID (after payroll or direct payment)
    // ─────────────────────────────────────────────

    public function markPaid(Request $request, ReimbursementClaim $claim): JsonResponse
    {
        $this->authorize('reimbursement.approve');

        if ($claim->status !== 'approved') {
            return response()->json(['message' => 'Only approved claims can be marked as paid.'], 422);
        }

        $claim->update(['status' => 'paid', 'payroll_run_id' => $request->payroll_run_id ?? null]);

        return response()->json(['message' => 'Claim marked as paid.']);
    }

    // ─────────────────────────────────────────────
    // SUMMARY REPORT
    // ─────────────────────────────────────────────

    public function report(Request $request): JsonResponse
    {
        $this->authorize('reimbursement.view');

        $summary = ReimbursementClaim::where('year', $request->get('year', now()->year))
            ->when($request->month, fn($q) => $q->where('month', $request->month))
            ->whereHas('employee', fn($q) => $q->where('company_id', $request->company_id))
            ->selectRaw('status, SUM(total_claimed) as total_claimed, SUM(total_approved) as total_approved, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        $byType = ReimbursementItem::whereHas('claim', fn($q) => $q->whereHas('employee', fn($q2) => $q2->where('company_id', $request->company_id))
            ->where('status', 'approved'))
            ->where('status', 'approved')
            ->selectRaw('reimbursement_type_id, SUM(approved_amount) as total_approved, COUNT(*) as count')
            ->with('reimbursementType:id,name')
            ->groupBy('reimbursement_type_id')
            ->get();

        return response()->json(['summary' => $summary, 'by_type' => $byType]);
    }

    protected function generateClaimNumber(): string
    {
        $last = ReimbursementClaim::orderByDesc('id')->value('claim_number');
        $num  = 1;
        if ($last) { preg_match('/(\d+)$/', $last, $m); $num = (int)($m[1] ?? 0) + 1; }
        return 'RC-' . now()->year . '-' . str_pad($num, 4, '0', STR_PAD_LEFT);
    }
}
