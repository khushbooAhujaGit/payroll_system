<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\EmployeeLoan;
use App\Models\LoanInstallment;
use App\Models\LoanType;
use App\Services\ExcelImportService;
use App\Imports\LoanImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoanController extends Controller
{
    public function __construct(protected ExcelImportService $excelImportService) {}

    // ─────────────────────────────────────────────
    // LOAN TYPES
    // ─────────────────────────────────────────────

    public function loanTypes(Request $request): JsonResponse
    {
        $types = LoanType::where('company_id', $request->company_id)
            ->where('is_active', true)
            ->get();

        return response()->json(['data' => $types]);
    }

    public function storeLoanType(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name'         => 'required|string|max:100',
            'code'         => 'required|string|max:20',
            'max_amount'   => 'nullable|numeric|min:0',
            'max_emi'      => 'nullable|integer|min:1',
            'interest_rate'=> 'nullable|numeric|min:0|max:100',
        ]);

        $type = LoanType::create($validated);
        return response()->json(['data' => $type], 201);
    }

    // ─────────────────────────────────────────────
    // EMPLOYEE LOANS
    // ─────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $loans = EmployeeLoan::with(['employee:id,employee_id,first_name,middle_name,last_name', 'loanType'])
            ->whereHas('employee', fn($q) => $q->where('company_id', $request->company_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->employee_id, fn($q) => $q->where('employee_id', $request->employee_id))
            ->orderByDesc('created_at')
            ->paginate(25);

        return response()->json($loans);
    }

    public function show(EmployeeLoan $loan): JsonResponse
    {
        $loan->load([
            'employee:id,employee_id,first_name,middle_name,last_name,mobile',
            'loanType',
            'installments' => fn($q) => $q->orderBy('installment_number'),
        ]);

        return response()->json(['data' => $loan]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id'     => 'required|exists:employees,id',
            'loan_type_id'    => 'required|exists:loan_types,id',
            'loan_amount'     => 'required|numeric|min:1',
            'total_emis'      => 'required|integer|min:1|max:120',
            'interest_rate'   => 'nullable|numeric|min:0|max:100',
            'first_emi_month' => 'required|date_format:Y-m',
            'purpose'         => 'nullable|string|max:500',
        ]);

        $loanType = LoanType::findOrFail($validated['loan_type_id']);

        // Validate against loan type limits
        if ($loanType->max_amount && $validated['loan_amount'] > $loanType->max_amount) {
            return response()->json(['message' => "Loan amount exceeds max allowed: ₹{$loanType->max_amount}"], 422);
        }
        if ($loanType->max_emi && $validated['total_emis'] > $loanType->max_emi) {
            return response()->json(['message' => "EMIs exceed max allowed: {$loanType->max_emi}"], 422);
        }

        DB::beginTransaction();
        try {
            $interestRate = $validated['interest_rate'] ?? $loanType->interest_rate;
            $emiAmount    = $this->calculateEmi($validated['loan_amount'], $interestRate, $validated['total_emis']);

            $loan = EmployeeLoan::create([
                'loan_number'        => $this->generateLoanNumber(),
                'employee_id'        => $validated['employee_id'],
                'loan_type_id'       => $validated['loan_type_id'],
                'loan_amount'        => $validated['loan_amount'],
                'total_emis'         => $validated['total_emis'],
                'emi_amount'         => $emiAmount,
                'interest_rate'      => $interestRate,
                'first_emi_month'    => $validated['first_emi_month'] . '-01',
                'outstanding_amount' => $validated['loan_amount'],
                'status'             => 'pending',
                'purpose'            => $validated['purpose'] ?? null,
                'created_by'         => auth()->id(),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json(['message' => 'Loan application created.', 'data' => $loan], 201);
    }

    // ─────────────────────────────────────────────
    // APPROVAL
    // ─────────────────────────────────────────────

    public function approve(Request $request, EmployeeLoan $loan): JsonResponse
    {
        $this->authorize('loan.approve');

        if (!in_array($loan->status, ['pending'])) {
            return response()->json(['message' => 'Only pending loans can be approved.'], 422);
        }

        $request->validate(['disbursement_date' => 'required|date']);

        DB::beginTransaction();
        try {
            $loan->update([
                'status'             => 'active',
                'disbursement_date'  => $request->disbursement_date,
                'approved_by'        => auth()->id(),
                'approved_at'        => now(),
            ]);

            // Generate EMI schedule
            $this->generateEmiSchedule($loan);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json(['message' => 'Loan approved and EMI schedule generated.', 'data' => $loan->fresh()]);
    }

    public function reject(Request $request, EmployeeLoan $loan): JsonResponse
    {
        $this->authorize('loan.approve');

        $request->validate(['reason' => 'required|string']);

        $loan->update(['status' => 'rejected']);

        return response()->json(['message' => 'Loan rejected.']);
    }

    // ─────────────────────────────────────────────
    // EMI SCHEDULE
    // ─────────────────────────────────────────────

    public function emiSchedule(EmployeeLoan $loan): JsonResponse
    {
        $installments = LoanInstallment::where('employee_loan_id', $loan->id)
            ->orderBy('installment_number')
            ->get();

        return response()->json(['data' => $installments]);
    }

    // ─────────────────────────────────────────────
    // WAIVE INSTALLMENT
    // ─────────────────────────────────────────────

    public function waiveInstallment(Request $request, LoanInstallment $installment): JsonResponse
    {
        $this->authorize('loan.approve');

        $request->validate(['reason' => 'required|string']);

        if ($installment->status === 'deducted') {
            return response()->json(['message' => 'Already deducted.'], 422);
        }

        $installment->update(['status' => 'waived']);

        // Update outstanding
        $installment->employeeLoan->decrement('outstanding_amount', $installment->total_emi_amount);

        return response()->json(['message' => 'Installment waived.']);
    }

    // ─────────────────────────────────────────────
    // FORECLOSURE
    // ─────────────────────────────────────────────

    public function foreclose(Request $request, EmployeeLoan $loan): JsonResponse
    {
        $this->authorize('loan.approve');

        $request->validate(['remarks' => 'nullable|string']);

        // Mark all pending installments as waived
        LoanInstallment::where('employee_loan_id', $loan->id)
            ->where('status', 'pending')
            ->update(['status' => 'waived']);

        $loan->update([
            'outstanding_amount' => 0,
            'status'             => 'closed',
        ]);

        return response()->json(['message' => 'Loan foreclosed successfully.']);
    }

    // ─────────────────────────────────────────────
    // EXCEL UPLOAD
    // ─────────────────────────────────────────────

    public function uploadTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $this->excelImportService->generateTemplate(LoanImporter::class);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file'      => 'required|file|mimes:xlsx,xls|max:10240',
            'company_id' => 'required|exists:companies,id',
        ]);

        $result = $this->excelImportService->import(
            $request->file('file'),
            'loan',
            LoanImporter::class,
            ['company_id' => $request->company_id]
        );

        return response()->json($result);
    }

    // ─────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────

    protected function generateEmiSchedule(EmployeeLoan $loan): void
    {
        $startDate   = \Carbon\Carbon::parse($loan->first_emi_month);
        $outstanding = $loan->loan_amount;

        for ($i = 1; $i <= $loan->total_emis; $i++) {
            $interestAmount  = round($outstanding * ($loan->interest_rate / 100 / 12), 2);
            $principalAmount = round($loan->emi_amount - $interestAmount, 2);
            $outstanding     = round($outstanding - $principalAmount, 2);

            LoanInstallment::create([
                'employee_loan_id'   => $loan->id,
                'employee_id'        => $loan->employee_id,
                'installment_number' => $i,
                'emi_year'           => $startDate->year,
                'emi_month'          => $startDate->month,
                'principal_amount'   => $principalAmount,
                'interest_amount'    => $interestAmount,
                'total_emi_amount'   => $loan->emi_amount,
                'outstanding_before' => $outstanding + $principalAmount,
                'outstanding_after'  => max(0, $outstanding),
                'status'             => 'pending',
            ]);

            $startDate->addMonth();
        }
    }

    protected function calculateEmi(float $principal, float $annualRate, int $months): float
    {
        if ($annualRate == 0) {
            return round($principal / $months, 2);
        }
        $monthlyRate = $annualRate / 100 / 12;
        $emi = $principal * $monthlyRate * pow(1 + $monthlyRate, $months)
               / (pow(1 + $monthlyRate, $months) - 1);
        return round($emi, 2);
    }

    protected function generateLoanNumber(): string
    {
        $last = EmployeeLoan::orderByDesc('id')->value('loan_number');
        $num  = 1;
        if ($last) {
            preg_match('/(\d+)$/', $last, $m);
            $num = (int)($m[1] ?? 0) + 1;
        }
        return 'LN-' . now()->year . '-' . str_pad($num, 4, '0', STR_PAD_LEFT);
    }
}
