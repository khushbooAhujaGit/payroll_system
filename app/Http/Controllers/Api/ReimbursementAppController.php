<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReimbursementTrackerRow;
use App\Models\User;
use App\Notifications\ReimbursementClaimSubmitted;
use App\Services\PublicFileStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile mirror of WebMyReimbursementController — same scoping, same
 * validation, same CATEGORIES list and PublicFileStore upload path. submit()
 * expects a real multipart/form-data upload (the 'document' field), same as
 * the web form — the app side needs an image/file picker to produce that,
 * which is a separate decision (see conversation).
 */
class ReimbursementAppController extends Controller
{
    public function __construct(protected PublicFileStore $publicFileStore) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('reimbursment_self_view');

        $employee = $request->user()->employee;
        abort_unless($employee, 404, 'No employee profile linked to this account.');

        $claims = ReimbursementTrackerRow::where('employee_id', $employee->id)
            ->when($request->get('status'), fn($q, $status) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate(20)
            ->through(fn($c) => [
                'id'                 => $c->id,
                'expense_date'       => $c->expense_date,
                'amount'             => (float) $c->amount,
                'category'           => $c->category,
                'other_category_text' => $c->other_category_text,
                'remarks'            => $c->remarks,
                'has_document'       => (bool) $c->document_path,
                'status'             => $c->status,
                'approval_remarks'   => $c->approval_remarks,
                'payment_status'     => $c->status === 'approved' ? $c->payment_status : null,
                'payment_date'       => $c->payment_date,
                'payment_reference'  => $c->payment_reference,
            ]);

        return response()->json([
            'data' => $claims->items(),
            'meta' => [
                'current_page' => $claims->currentPage(),
                'per_page'     => $claims->perPage(),
                'total'        => $claims->total(),
                'last_page'    => $claims->lastPage(),
            ],
        ]);
    }

    /** The categories the submit form offers, so the app doesn't hardcode them separately. */
    public function categories(): JsonResponse
    {
        return response()->json(['data' => ReimbursementTrackerRow::CATEGORIES]);
    }

    public function submit(Request $request): JsonResponse
    {
        $this->authorize('reimbursment_self_add');

        $employee = $request->user()->employee;
        abort_unless($employee, 404, 'No employee profile linked to this account.');

        $validated = $request->validate([
            'expense_date'         => 'required|date',
            'amount'               => 'required|numeric|min:0.01',
            'category'             => 'required|string|in:' . implode(',', ReimbursementTrackerRow::CATEGORIES),
            'other_category_text'  => 'required_if:category,Other|nullable|string|max:150',
            'remarks'              => 'nullable|string|max:500',
            'document'             => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $documentPath = $this->publicFileStore->store($request->file('document'), 'reimbursement_docs');

        $row = ReimbursementTrackerRow::create([
            'year_month'           => \Carbon\Carbon::parse($validated['expense_date'])->format('Y-m'),
            'expense_date'         => $validated['expense_date'],
            'emp_id'               => $employee->employee_id,
            'employee_id'          => $employee->id,
            'employee_name'        => $employee->full_name,
            'amount'               => $validated['amount'],
            'category'             => $validated['category'],
            'other_category_text'  => $validated['category'] === 'Other' ? ($validated['other_category_text'] ?? null) : null,
            'remarks'              => $validated['remarks'] ?? null,
            'document_path'        => $documentPath,
            'document_name'        => $request->file('document')->getClientOriginalName(),
            'status'                => 'pending',
            'company_id'            => $employee->company_id,
            'created_by'            => auth()->id(),
        ]);

        User::permission('reimbursment_approve')
            ->get()
            ->each(fn(User $approver) => $approver->notify(new ReimbursementClaimSubmitted($row)));

        return response()->json(['data' => ['id' => $row->id]], 201);
    }
}
