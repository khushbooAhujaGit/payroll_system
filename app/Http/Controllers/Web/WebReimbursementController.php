<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Entity;
use App\Models\ReimbursementClaim;
use App\Models\ReimbursementItem;
use App\Models\ReimbursementType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class WebReimbursementController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('reimbursment_tab');

        $companyId = $this->resolveCompanyId($request);

        $claims = ReimbursementClaim::with([
            'employee:id,employee_id,first_name,middle_name,last_name,department_id',
            'employee.department:id,name',
            'items.reimbursementType:id,name',
        ])
        ->whereHas('employee', fn($q) => $q)
        ->when($request->status, fn($q) => $q->where('status', $request->status))
        ->orderByDesc('created_at')
        ->paginate(25)->withQueryString();

        $types     = ReimbursementType::where('is_active', true)
            ->where(fn($q) => $q->whereNull('company_id')->orWhere('company_id', $companyId))
            ->orderBy('name')->get();
        $employees = \App\Models\Employee::currentlyEmployed()
            ->orderBy('first_name')->get();

        return view('reimbursements.index', compact('claims', 'types', 'employees', 'companyId'));
    }

    public function store(Request $request)
    {
        $this->authorize('reimbursment_add');

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

    public function template()
    {
        $this->authorize('reimbursment_tab');

        $wb = new Spreadsheet();
        $ws = $wb->getActiveSheet();
        $ws->setTitle('Template');

        $headers = ['Date', 'Employee ID', 'Employee Name', 'Amount', 'Remarks', 'Image'];
        $ws->fromArray($headers, null, 'A1');

        $lastCol = count($headers);
        $range   = 'A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastCol) . '1';
        $ws->getStyle($range)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        foreach ([16, 8, 8, 20, 26, 40, 12] as $ci => $w) {
            $ws->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 1))->setWidth($w);
        }

        // Sample row
        $ws->fromArray(['EMP001', now()->year, now()->month, 'PETROL', now()->toDateString(), 'Petrol reimbursement', 1800], null, 'A2');

        $writer = new Xlsx($wb);
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'Reimbursement_Claims_Template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function approve(Request $request, ReimbursementClaim $claim)
    {
        $this->authorize('reimbursment_edit');

        $request->validate(['action' => 'required|in:approved,rejected']);
        $claim->update(['status' => $request->action, 'approved_by' => auth()->id(), 'approved_at' => now()]);
        return redirect()->route('reimbursements')->with('success', 'Claim ' . $request->action . '.');
    }
}
