<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ReimbursementTrackerRow;
use App\Models\User;
use App\Notifications\ReimbursementClaimResolved;
use App\Notifications\ReimbursementClaimSubmitted;
use App\Services\PublicFileStore;
use Illuminate\Http\Request;

/**
 * Items #26-29 of the Master Details spec:
 *  - #26: employees submit a claim with a supporting document, routed to
 *    whoever holds the reimbursment_approve permission — granted directly
 *    to one specific user (see WebUserController::toggleReimbursementApprover()),
 *    not to a role.
 *  - #27: a standard-remarks dropdown (+ "Other" free text) on the approval
 *    decision.
 *  - #28: Admin marks payment status on an approved claim.
 *  - #29: employee sees their own claims' approval + payment status.
 */
class WebMyReimbursementController extends Controller
{
    public function __construct(protected PublicFileStore $publicFileStore)
    {
    }

    /** Fixed vocabulary for the approval/rejection remark dropdown — "Other" reveals a free-text box in the UI. */
    public const STANDARD_REMARKS = [
        'Approved as per company policy',
        'Bill/receipt not clear or missing',
        'Claim exceeds policy limit',
        'Duplicate claim',
        'Invalid or non-reimbursable expense category',
        'Incomplete supporting document',
    ];

    /**
     * Employee's own claims across all months, with approval + payment
     * status visible (#29).
     */
    public function index(Request $request)
    {
        $this->authorize('reimbursment_self_view');

        $employee = auth()->user()->employee;
        abort_unless($employee, 404, 'No employee profile linked to your account.');

        $claims = ReimbursementTrackerRow::where('employee_id', $employee->id)
            ->orderByDesc('id')
            ->paginate(20);

        return view('reimbursements.my-claims', compact('claims'));
    }

    /**
     * Submit a new claim with one supporting document (#26).
     */
    public function submit(Request $request)
    {
        $this->authorize('reimbursment_self_add');

        $employee = auth()->user()->employee;
        abort_unless($employee, 404, 'No employee profile linked to your account.');

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
            'year_month'    => \Carbon\Carbon::parse($validated['expense_date'])->format('Y-m'),
            'expense_date'  => $validated['expense_date'],
            'emp_id'        => $employee->employee_id,
            'employee_id'   => $employee->id,
            'employee_name' => $employee->full_name,
            'amount'        => $validated['amount'],
            'category'              => $validated['category'],
            'other_category_text'   => $validated['category'] === 'Other' ? ($validated['other_category_text'] ?? null) : null,
            'remarks'       => $validated['remarks'] ?? null,
            'document_path' => $documentPath,
            'document_name' => $request->file('document')->getClientOriginalName(),
            'status'        => 'pending',
            'company_id'    => $employee->company_id,
            'created_by'    => auth()->id(),
        ]);

        User::permission('reimbursment_approve')
            ->get()
            ->each(fn(User $approver) => $approver->notify(new ReimbursementClaimSubmitted($row)));

        return redirect()->route('my-reimbursements')
            ->with('success', 'Claim submitted for approval.');
    }

    /**
     * Stream the employee's own supporting document back — same
     * path-traversal-safe local-disk pattern as trackerImageView(), but for
     * the private 'local' disk and gated to the claim's owner or someone who
     * can review/administer claims.
     */
    public function viewDocument(Request $request, ReimbursementTrackerRow $row)
    {
        $employee = auth()->user()->employee;
        $isOwner  = $employee && $row->employee_id === $employee->id;
        abort_unless($isOwner || auth()->user()->can('reimbursment_approve') || auth()->user()->can('reimbursment_tab'), 403);

        abort_unless($this->publicFileStore->exists($row->document_path), 404);

        return response()->file($this->publicFileStore->absolutePath($row->document_path));
    }

    /**
     * The designated approver's queue — gated by reimbursment_approve, held
     * by exactly one user (Admin can also reach this, since Admin bypasses
     * every permission check in this app).
     */
    public function approvals(Request $request)
    {
        $this->authorize('reimbursment_approve');

        $rows = ReimbursementTrackerRow::whereNotNull('employee_id')
            ->where('status', 'pending')
            ->orderBy('id')
            ->paginate(25);

        return view('reimbursements.approvals', [
            'rows' => $rows,
            'standardRemarks' => self::STANDARD_REMARKS,
        ]);
    }

    /**
     * Approve/reject a self-submitted claim (#27's standard-remarks dropdown
     * lands here as approval_remarks).
     */
    public function resolve(Request $request, ReimbursementTrackerRow $row)
    {
        $this->authorize('reimbursment_approve');

        $validated = $request->validate([
            'action'  => 'required|in:approved,rejected',
            'remark'  => 'required|string|max:500',
        ]);

        $row->update([
            'status'           => $validated['action'],
            'approved_by'      => auth()->id(),
            'approved_at'      => now(),
            'approval_remarks' => $validated['remark'],
        ]);

        $row->employee?->user?->notify(new ReimbursementClaimResolved($row));

        return redirect()->route('reimbursements.approvals')
            ->with('success', 'Claim ' . $validated['action'] . '.');
    }

    /**
     * Admin records payment status on an already-approved claim (#28) —
     * gated by the existing reimbursment_edit permission since the spec
     * says "by Admin" generically, not by the single designated approver.
     */
    public function updatePayment(Request $request, ReimbursementTrackerRow $row)
    {
        $this->authorize('reimbursment_edit');

        abort_unless($row->status === 'approved', 422, 'Only approved claims can have a payment status.');

        $validated = $request->validate([
            'payment_status'    => 'required|in:unpaid,paid,partial',
            'payment_date'      => 'nullable|date|required_if:payment_status,paid,partial',
            'payment_reference' => 'nullable|string|max:100',
            'paid_amount'       => 'nullable|numeric|min:0.01|required_if:payment_status,partial|lt:' . $row->amount,
        ], [
            'paid_amount.lt' => 'Paid amount must be less than the claim amount (₹' . number_format($row->amount, 2) . ') — use "Paid" instead if the full amount was paid.',
        ]);

        $row->applyPayment(
            $validated['payment_status'],
            $validated['payment_date'] ?? null,
            $validated['payment_reference'] ?? null,
            $validated['payment_status'] === 'partial' ? (float) $validated['paid_amount'] : null,
            auth()->id()
        );

        $msg = $validated['payment_status'] === 'partial'
            ? 'Payment recorded as partial — the remaining balance has been added to next month\'s tracker.'
            : 'Payment status updated.';

        return redirect()->route('reimbursements.tracker', ['month' => $row->year_month])
            ->with('success', $msg);
    }
}
