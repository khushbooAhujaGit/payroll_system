<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Entity;
use App\Models\ReimbursementClaim;
use App\Models\ReimbursementItem;
use App\Models\ReimbursementType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebReimbursementController extends Controller
{
    public function index(Request $request)
    {
        $entityId = $this->resolveEntityId($request);

        $claims = ReimbursementClaim::with([
            'employee:id,employee_id,first_name,last_name,department_id',
            'employee.department:id,name',
            'items.reimbursementType:id,name',
        ])
        ->whereHas('employee', fn($q) => $q->where('entity_id', $entityId))
        ->when($request->status, fn($q) => $q->where('status', $request->status))
        ->orderByDesc('created_at')
        ->paginate(25)->withQueryString();

        $types     = ReimbursementType::where('is_active', true)
            ->where(fn($q) => $q->whereNull('entity_id')->orWhere('entity_id', $entityId))
            ->orderBy('name')->get();
        $employees = \App\Models\Employee::where('entity_id', $entityId)->where('employee_status', 'active')
            ->orderBy('first_name')->get();

        return view('reimbursements.index', compact('claims', 'types', 'employees', 'entityId'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'year'                          => 'required|integer',
            'month'                         => 'required|integer|min:1|max:12',
            'employee_id'                   => 'nullable|exists:employees,id',
            'items'                         => 'required|array|min:1',
            'items.*.reimbursement_type_id' => 'required|exists:reimbursement_types,id',
            'items.*.expense_date'          => 'required|date',
            'items.*.description'           => 'required|string|max:500',
            'items.*.claimed_amount'        => 'required|numeric|min:0.01',
        ]);

        $employee = $validated['employee_id']
            ? \App\Models\Employee::findOrFail($validated['employee_id'])
            : auth()->user()->employee;

        if (!$employee) {
            return redirect()->back()->withErrors(['employee_id' => 'No employee profile found.']);
        }

        $existing = ReimbursementClaim::where('employee_id', $employee->id)
            ->where('year', $validated['year'])->where('month', $validated['month'])
            ->whereNotIn('status', ['rejected'])->first();

        if ($existing) {
            return redirect()->back()->withErrors(['month' => 'A claim for this month already exists.']);
        }

        DB::beginTransaction();
        try {
            $total = array_sum(array_column($validated['items'], 'claimed_amount'));
            $num   = 'RC-' . now()->year . '-' . str_pad(ReimbursementClaim::count() + 1, 4, '0', STR_PAD_LEFT);

            $claim = ReimbursementClaim::create([
                'claim_number'  => $num,
                'employee_id'   => $employee->id,
                'year'          => $validated['year'],
                'month'         => $validated['month'],
                'total_claimed' => $total,
                'status'        => 'submitted',
            ]);

            foreach ($validated['items'] as $item) {
                ReimbursementItem::create([
                    'reimbursement_claim_id' => $claim->id,
                    'reimbursement_type_id'  => $item['reimbursement_type_id'],
                    'expense_date'           => $item['expense_date'],
                    'description'            => $item['description'],
                    'claimed_amount'         => $item['claimed_amount'],
                    'status'                 => 'pending',
                ]);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()->route('reimbursements')->with('success', 'Reimbursement claim submitted.');
    }

    public function approve(Request $request, ReimbursementClaim $claim)
    {
        $request->validate(['action' => 'required|in:approved,rejected']);
        $claim->update(['status' => $request->action, 'approved_by' => auth()->id(), 'approved_at' => now()]);
        return redirect()->route('reimbursements')->with('success', 'Claim ' . $request->action . '.');
    }
}
