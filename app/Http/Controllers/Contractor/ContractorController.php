<?php

namespace App\Http\Controllers\Contractor;

use App\Http\Controllers\Controller;
use App\Models\Contractor;
use App\Models\ContractorInvoice;
use App\Services\ExcelImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContractorController extends Controller
{
    public function __construct(protected ExcelImportService $excelImportService) {}

    // ─────────────────────────────────────────────
    // CONTRACTOR MASTER
    // ─────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $contractors = Contractor::where('entity_id', $request->entity_id)
            ->when($request->is_active !== null, fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%")
                                                  ->orWhere('contractor_id', 'like', "%{$request->search}%"))
            ->withCount('invoices')
            ->paginate(25);

        return response()->json($contractors);
    }

    public function show(Contractor $contractor): JsonResponse
    {
        $contractor->load(['invoices' => fn($q) => $q->orderByDesc('invoice_date')->limit(12)]);
        return response()->json(['data' => $contractor]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('contractor.create');

        $validated = $request->validate([
            'entity_id'          => 'required|exists:entities,id',
            'name'               => 'required|string|max:200',
            'pan'                => 'nullable|string|size:10|regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/',
            'gstin'              => 'nullable|string|max:15',
            'mobile'             => 'nullable|string|size:10',
            'email'              => 'nullable|email',
            'address'            => 'nullable|string',
            'bank_account_number'=> 'nullable|string',
            'bank_name'          => 'nullable|string',
            'ifsc_code'          => 'nullable|string|size:11',
            'contractor_type'    => 'required|in:individual,company',
        ]);

        $validated['contractor_id'] = $this->generateContractorId($validated['entity_id']);
        $contractor = Contractor::create($validated);

        return response()->json(['message' => 'Contractor created.', 'data' => $contractor], 201);
    }

    public function update(Request $request, Contractor $contractor): JsonResponse
    {
        $this->authorize('contractor.edit');

        $validated = $request->validate([
            'name'               => 'sometimes|string|max:200',
            'mobile'             => 'nullable|string',
            'email'              => 'nullable|email',
            'address'            => 'nullable|string',
            'bank_account_number'=> 'nullable|string',
            'bank_name'          => 'nullable|string',
            'ifsc_code'          => 'nullable|string',
            'is_active'          => 'sometimes|boolean',
        ]);

        $contractor->update($validated);
        return response()->json(['message' => 'Contractor updated.', 'data' => $contractor]);
    }

    // ─────────────────────────────────────────────
    // INVOICES
    // ─────────────────────────────────────────────

    public function invoices(Request $request, Contractor $contractor): JsonResponse
    {
        $invoices = ContractorInvoice::where('contractor_id', $contractor->id)
            ->when($request->year, fn($q) => $q->where('year', $request->year))
            ->when($request->month, fn($q) => $q->where('month', $request->month))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->with('approvedBy:id,name')
            ->orderByDesc('invoice_date')
            ->paginate(25);

        return response()->json($invoices);
    }

    public function storeInvoice(Request $request, Contractor $contractor): JsonResponse
    {
        $validated = $request->validate([
            'invoice_number'  => 'required|string|max:50',
            'invoice_date'    => 'required|date',
            'year'            => 'required|integer',
            'month'           => 'required|integer|min:1|max:12',
            'invoice_amount'  => 'required|numeric|min:0.01',
            'tds_rate'        => 'nullable|numeric|min:0|max:100',
            'description'     => 'nullable|string',
            'invoice_file'    => 'nullable|file|mimes:pdf,jpg,png|max:5120',
        ]);

        $tdsRate    = $validated['tds_rate'] ?? $this->defaultTdsRate($contractor);
        $tdsAmount  = round($validated['invoice_amount'] * $tdsRate / 100, 2);
        $netPayable = $validated['invoice_amount'] - $tdsAmount;

        $invoicePath = null;
        if ($request->hasFile('invoice_file')) {
            $invoicePath = $request->file('invoice_file')->store(
                "contractor_invoices/{$contractor->id}", 'local'
            );
        }

        $invoice = ContractorInvoice::create([
            'contractor_id'  => $contractor->id,
            'entity_id'      => $contractor->entity_id,
            'invoice_number' => $validated['invoice_number'],
            'invoice_date'   => $validated['invoice_date'],
            'year'           => $validated['year'],
            'month'          => $validated['month'],
            'invoice_amount' => $validated['invoice_amount'],
            'tds_rate'       => $tdsRate,
            'tds_amount'     => $tdsAmount,
            'net_payable'    => $netPayable,
            'description'    => $validated['description'] ?? null,
            'invoice_path'   => $invoicePath,
            'status'         => 'pending',
        ]);

        return response()->json(['message' => 'Invoice created.', 'data' => $invoice], 201);
    }

    public function approveInvoice(Request $request, ContractorInvoice $invoice): JsonResponse
    {
        $this->authorize('contractor.approve');

        if ($invoice->status !== 'pending') {
            return response()->json(['message' => 'Only pending invoices can be approved.'], 422);
        }

        $invoice->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
        ]);

        return response()->json(['message' => 'Invoice approved.']);
    }

    public function markPaid(Request $request, ContractorInvoice $invoice): JsonResponse
    {
        $this->authorize('contractor.approve');

        $request->validate(['payment_date' => 'required|date']);

        $invoice->update([
            'status'       => 'paid',
            'payment_date' => $request->payment_date,
        ]);

        return response()->json(['message' => 'Invoice marked as paid.']);
    }

    // ─────────────────────────────────────────────
    // TDS REPORT  (for 26Q filing)
    // ─────────────────────────────────────────────

    public function tdsReport(Request $request): JsonResponse
    {
        $this->authorize('contractor.view');

        $request->validate(['entity_id' => 'required', 'year' => 'required|integer', 'quarter' => 'required|in:Q1,Q2,Q3,Q4']);

        $monthRanges = ['Q1' => [4,6], 'Q2' => [7,9], 'Q3' => [10,12], 'Q4' => [1,3]];
        [$fromMonth, $toMonth] = $monthRanges[$request->quarter];

        $invoices = ContractorInvoice::with('contractor')
            ->where('entity_id', $request->entity_id)
            ->where('year', $request->year)
            ->whereBetween('month', [$fromMonth, $toMonth])
            ->where('status', 'paid')
            ->where('tds_amount', '>', 0)
            ->get();

        $summary = $invoices->groupBy('contractor_id')->map(fn($group) => [
            'contractor'     => $group->first()->contractor->name,
            'pan'            => $group->first()->contractor->pan,
            'total_invoice'  => $group->sum('invoice_amount'),
            'total_tds'      => $group->sum('tds_amount'),
        ]);

        return response()->json(['data' => $summary->values()]);
    }

    // ─────────────────────────────────────────────
    // EXCEL IMPORT  (bulk contractor onboarding)
    // ─────────────────────────────────────────────

    public function uploadTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $this->excelImportService->generateTemplate(\App\Imports\ContractorImporter::class);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file'      => 'required|file|mimes:xlsx,xls|max:10240',
            'entity_id' => 'required|exists:entities,id',
        ]);

        $result = $this->excelImportService->import(
            $request->file('file'),
            'contractor',
            \App\Imports\ContractorImporter::class,
            ['entity_id' => $request->entity_id]
        );

        return response()->json($result);
    }

    // ─────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────

    protected function generateContractorId(int $entityId): string
    {
        $last = Contractor::where('entity_id', $entityId)->orderByDesc('id')->value('contractor_id');
        $num  = 1;
        if ($last) {
            preg_match('/(\d+)$/', $last, $m);
            $num = (int)($m[1] ?? 0) + 1;
        }
        return 'CTR-' . str_pad($num, 4, '0', STR_PAD_LEFT);
    }

    protected function defaultTdsRate(Contractor $contractor): float
    {
        // 194C = 1% individual, 2% company; 194J = 10%
        return $contractor->contractor_type === 'individual' ? 1.0 : 2.0;
    }
}
