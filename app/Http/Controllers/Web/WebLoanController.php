<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\ImportsExcel;
use App\Http\Controllers\Controller;
use App\Imports\LoanImporter;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\EmployeeSalary;
use App\Models\Entity;
use App\Models\LoanInstallment;
use App\Models\LoanType;
use App\Services\ExcelImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebLoanController extends Controller
{
    use ImportsExcel;
    public function __construct(protected ExcelImportService $excelImportService) {}

    public function index(Request $request)
    {
        $entityId = $this->resolveEntityId($request);

        $loans = EmployeeLoan::with([
                'employee:id,employee_id,first_name,last_name',
                'loanType',
                'installments',
            ])
            ->whereHas('employee', fn($q) => $q->where('entity_id', $entityId))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate(25)->withQueryString();

        $loanTypes = LoanType::where('entity_id', $entityId)->where('is_active', true)->get();
        $employees = Employee::where('entity_id', $entityId)->where('employee_status', 'active')
            ->orderBy('first_name')->get();

        $stats = [
            'total'   => EmployeeLoan::whereHas('employee', fn($q) => $q->where('entity_id', $entityId))->count(),
            'active'  => EmployeeLoan::whereHas('employee', fn($q) => $q->where('entity_id', $entityId))->where('status', 'active')->count(),
            'pending' => EmployeeLoan::whereHas('employee', fn($q) => $q->where('entity_id', $entityId))->where('status', 'pending')->count(),
            'outstanding' => EmployeeLoan::whereHas('employee', fn($q) => $q->where('entity_id', $entityId))->where('status', 'active')->sum('outstanding_amount'),
        ];

        // Load current salary for percent-based loans so we can derive estimated EMI count
        $percentEmpIds = $loans->filter(fn($l) => $l->percent_deduction > 0)
            ->pluck('employee_id')->unique()->values()->all();

        $salaryMap = collect();
        if (!empty($percentEmpIds)) {
            $salaryMap = EmployeeSalary::whereIn('employee_id', $percentEmpIds)
                ->where('is_current', true)
                ->get()
                ->keyBy('employee_id');
        }

        return view('loans.index', compact('loans', 'loanTypes', 'employees', 'stats', 'entityId', 'salaryMap'));
    }

    public function store(Request $request)
    {
        $entityId = $this->resolveEntityId($request);

        $validated = $request->validate([
            'employee_id'       => 'required|exists:employees,id',
            'loan_amount'       => 'required|numeric|min:1',
            'total_emis'        => 'nullable|integer|min:1|max:360',
            'percent_deduction' => 'nullable|numeric|min:0|max:100',
            'first_emi_month'   => 'required|date_format:Y-m',
            'purpose'           => 'nullable|string|max:500',
        ]);

        $pct       = (float) ($validated['percent_deduction'] ?? 0);
        $loanAmt   = (float) $validated['loan_amount'];
        $totalEmis = (int) ($validated['total_emis'] ?? 1);
        $emiAmount = 0.0;

        if ($pct > 0) {
            // Auto-derive total_emis from the employee's current gross salary
            $salary = EmployeeSalary::where('employee_id', $validated['employee_id'])
                ->where('is_current', true)->first();
            $gross  = $salary ? $salary->getGrossDecrypted() : 0.0;

            if ($gross > 0) {
                $monthlyDed = round($gross * $pct / 100, 2);
                $totalEmis  = (int) ceil($loanAmt / $monthlyDed);
                $emiAmount  = $monthlyDed;
            } else {
                $totalEmis = $validated['total_emis'] ?? 1;
                $emiAmount = $totalEmis > 0 ? round($loanAmt / $totalEmis, 2) : $loanAmt;
            }
        } else {
            // Fixed EMI — use the provided total_emis
            if (!$validated['total_emis']) {
                return redirect()->back()->withErrors(['total_emis' => 'Number of EMIs is required when no % deduction is set.']);
            }
            $emiAmount = $this->calcEmi($loanAmt, 0, $totalEmis);
        }

        // Auto-resolve Salary Advance loan type for this entity
        $loanType = LoanType::where('entity_id', $entityId)->where('code', 'SA')->first()
            ?? LoanType::where('entity_id', $entityId)->first();

        if (!$loanType) {
            return redirect()->back()->withErrors(['loan_amount' => 'No loan type configured for this entity. Please contact admin.']);
        }

        $loanNumber = 'LN-' . now()->year . '-' . str_pad(EmployeeLoan::count() + 1, 4, '0', STR_PAD_LEFT);

        EmployeeLoan::create([
            'loan_number'        => $loanNumber,
            'employee_id'        => $validated['employee_id'],
            'loan_type_id'       => $loanType->id,
            'loan_amount'        => $loanAmt,
            'total_emis'         => $totalEmis,
            'percent_deduction'  => $pct > 0 ? $pct : null,
            'emi_amount'         => $emiAmount,
            'interest_rate'      => 0,
            'first_emi_month'    => $validated['first_emi_month'] . '-01',
            'outstanding_amount' => $loanAmt,
            'status'             => 'pending',
            'purpose'            => $validated['purpose'] ?? null,
            'created_by'         => auth()->id(),
        ]);

        return redirect()->route('loans')->with('success', 'Advance application submitted.');
    }

    public function approve(Request $request, EmployeeLoan $loan)
    {
        $request->validate([
            'action'            => 'required|in:approved,rejected',
            'disbursement_date' => 'required_if:action,approved|date',
        ]);

        if ($request->action === 'rejected') {
            $loan->update(['status' => 'rejected']);
            return redirect()->route('loans')->with('success', 'Loan rejected.');
        }

        DB::beginTransaction();
        try {
            $updateData = [
                'status'            => 'active',
                'disbursement_date' => $request->disbursement_date,
                'approved_by'       => auth()->id(),
                'approved_at'       => now(),
            ];

            // For percent-based loans, recalculate total_emis from the employee's current salary
            if ($loan->percent_deduction > 0) {
                $salary = EmployeeSalary::where('employee_id', $loan->employee_id)
                    ->where('is_current', true)->first();
                $gross  = $salary ? $salary->getGrossDecrypted() : 0.0;

                if ($gross > 0) {
                    $monthlyDed            = round($gross * (float) $loan->percent_deduction / 100, 2);
                    $updateData['total_emis']  = (int) ceil((float) $loan->loan_amount / $monthlyDed);
                    $updateData['emi_amount']  = $monthlyDed;
                }
            }

            $loan->update($updateData);
            $loan->refresh();
            $this->generateSchedule($loan);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()->route('loans')->with('success', 'Loan approved.');
    }

    protected function calcEmi(float $principal, float $annualRate, int $months): float
    {
        if ($annualRate == 0) return round($principal / $months, 2);
        $r = $annualRate / 100 / 12;
        return round($principal * $r * pow(1 + $r, $months) / (pow(1 + $r, $months) - 1), 2);
    }

    protected function generateSchedule(EmployeeLoan $loan): void
    {
        $start = \Carbon\Carbon::parse($loan->first_emi_month);
        $outstanding = $loan->loan_amount;
        for ($i = 1; $i <= $loan->total_emis; $i++) {
            $interest   = round($outstanding * ($loan->interest_rate / 100 / 12), 2);
            $principal  = round($loan->emi_amount - $interest, 2);
            $outstanding = round($outstanding - $principal, 2);
            LoanInstallment::create([
                'employee_loan_id'   => $loan->id,
                'employee_id'        => $loan->employee_id,
                'installment_number' => $i,
                'emi_year'           => $start->year,
                'emi_month'          => $start->month,
                'principal_amount'   => $principal,
                'interest_amount'    => $interest,
                'total_emi_amount'   => $loan->emi_amount,
                'outstanding_before' => $outstanding + $principal,
                'outstanding_after'  => max(0, $outstanding),
                'status'             => 'pending',
            ]);
            $start->addMonth();
        }
    }

    public function update(Request $request, EmployeeLoan $loan)
    {
        $hasPaidEmis = $loan->installments()->where('status', 'paid')->exists();

        // Fields always editable
        $rules = [
            'percent_deduction' => 'nullable|numeric|min:0|max:100',
            'purpose'           => 'nullable|string|max:500',
        ];

        // Pending-only: loan amount, EMI count, start month
        if ($loan->status === 'pending') {
            $rules['loan_amount']     = 'required|numeric|min:1';
            $rules['total_emis']      = 'required|integer|min:1|max:360';
            $rules['first_emi_month'] = 'required|date_format:Y-m';
        }

        // Active + no paid EMIs: allow editing start month (will regenerate schedule)
        if ($loan->status === 'active' && !$hasPaidEmis) {
            $rules['first_emi_month'] = 'nullable|date_format:Y-m';
        }

        $validated = $request->validate($rules);

        $data = [
            'percent_deduction' => $validated['percent_deduction'] ?? null,
            'purpose'           => $validated['purpose'] ?? $loan->purpose,
        ];

        if ($loan->status === 'pending') {
            $data['loan_amount']        = $validated['loan_amount'];
            $data['total_emis']         = $validated['total_emis'];
            $data['outstanding_amount'] = $validated['loan_amount'];
            $data['emi_amount']         = round($validated['loan_amount'] / $validated['total_emis'], 2);
            $data['first_emi_month']    = $validated['first_emi_month'] . '-01';
        }

        $regenerate = false;

        // Active loan, no paid EMIs, start month changed → regenerate schedule
        if ($loan->status === 'active' && !$hasPaidEmis && !empty($validated['first_emi_month'])) {
            $newMonthDate = $validated['first_emi_month'] . '-01';
            if ($newMonthDate !== $loan->first_emi_month?->format('Y-m-d')) {
                $data['first_emi_month'] = $newMonthDate;
                $regenerate = true;
            }
        }

        DB::beginTransaction();
        try {
            $loan->update($data);
            if ($regenerate) {
                // Delete existing pending installments, then regenerate from new start month
                $loan->installments()->where('status', 'pending')->delete();
                $loan->refresh();
                $this->generateSchedule($loan);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $msg = $regenerate
            ? 'Start month updated — EMI schedule regenerated.'
            : 'Loan updated successfully.';

        return redirect()->route('loans')->with('success', $msg);
    }

    public function destroy(EmployeeLoan $loan)
    {
        // Allow deleting any loan — active loans will also reverse paid installments
        if ($loan->status === 'active') {
            // Reverse any paid installments before deletion
            foreach ($loan->installments()->where('status', 'paid')->get() as $inst) {
                // No need to restore outstanding since we're deleting the loan entirely
            }
        }
        $loan->installments()->delete();
        $loan->delete();
        return redirect()->route('loans')->with('success', 'Loan deleted.');
    }

    public function schedule(EmployeeLoan $loan)
    {
        $loan->load(['employee:id,employee_id,first_name,last_name', 'installments' => fn($q) => $q->orderBy('installment_number')]);
        return response()->json([
            'loan'         => [
                'id'                 => $loan->id,
                'loan_number'        => $loan->loan_number,
                'loan_amount'        => $loan->loan_amount,
                'outstanding_amount' => $loan->outstanding_amount,
                'status'             => $loan->status,
                'employee_name'      => $loan->employee?->first_name . ' ' . $loan->employee?->last_name,
            ],
            'installments' => $loan->installments->map(fn($i) => [
                'installment_number' => $i->installment_number,
                'emi_year'           => $i->emi_year,
                'emi_month'          => $i->emi_month,
                'total_emi_amount'   => $i->total_emi_amount,
                'outstanding_after'  => $i->outstanding_after,
                'status'             => $i->status,
            ]),
        ]);
    }

    public function template()
    {
        return $this->excelImportService->generateTemplate(LoanImporter::class);
    }

    public function import(Request $request)
    {
        $entityId = $this->resolveEntityId($request);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        $result = $this->excelImportService->import(
            $request->file('file'),
            'loan',
            LoanImporter::class,
            ['entity_id' => $entityId]
        );

        return $this->handleImportResult($result, 'loans', 'loan');
    }
}
