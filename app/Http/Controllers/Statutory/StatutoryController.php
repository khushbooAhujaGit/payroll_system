<?php

namespace App\Http\Controllers\Statutory;

use App\Http\Controllers\Controller;
use App\Models\PfSetting;
use App\Models\EsicSetting;
use App\Models\PtSlab;
use App\Models\LwfSlab;
use App\Models\TdsSlab;
use App\Models\PfEcrRegister;
use App\Models\ComplianceChallan;
use App\Models\PayrollRun;
use App\Models\PayrollEmployeeRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StatutoryController extends Controller
{
    // ─────────────────────────────────────────────
    // PF SETTINGS
    // ─────────────────────────────────────────────

    public function pfSettings(Request $request): JsonResponse
    {
        $settings = PfSetting::where('company_id', $request->company_id)->first();
        return response()->json(['data' => $settings]);
    }

    public function savePfSettings(Request $request): JsonResponse
    {
        $this->authorize('statutory.manage');

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'employee_contribution_pct'  => 'required|numeric|between:0,100',
            'employer_contribution_pct'  => 'required|numeric|between:0,100',
            'eps_contribution_pct'       => 'required|numeric|between:0,100',
            'edli_contribution_pct'      => 'required|numeric|between:0,100',
            'admin_charges_pct'          => 'required|numeric|between:0,100',
            'cap_at_wage_limit'          => 'required|boolean',
            'wage_limit'                 => 'required|numeric|min:0',
            'allow_vpf'                  => 'boolean',
        ]);

        $settings = PfSetting::updateOrCreate(['company_id' => $validated['company_id']], $validated);

        return response()->json(['message' => 'PF settings saved.', 'data' => $settings]);
    }

    // ─────────────────────────────────────────────
    // ESIC SETTINGS
    // ─────────────────────────────────────────────

    public function esicSettings(Request $request): JsonResponse
    {
        $settings = EsicSetting::where('company_id', $request->company_id)->first();
        return response()->json(['data' => $settings]);
    }

    public function saveEsicSettings(Request $request): JsonResponse
    {
        $this->authorize('statutory.manage');

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'employee_contribution_pct' => 'required|numeric|between:0,100',
            'employer_contribution_pct' => 'required|numeric|between:0,100',
            'wage_limit'                => 'required|numeric|min:0',
        ]);

        $settings = EsicSetting::updateOrCreate(['company_id' => $validated['company_id']], $validated);

        return response()->json(['message' => 'ESIC settings saved.', 'data' => $settings]);
    }

    // ─────────────────────────────────────────────
    // PT SLABS (Professional Tax)
    // ─────────────────────────────────────────────

    public function ptSlabs(Request $request): JsonResponse
    {
        $slabs = PtSlab::where('company_id', $request->company_id)
            ->where('is_active', true)
            ->orderBy('salary_from')
            ->get();

        return response()->json(['data' => $slabs]);
    }

    public function savePtSlabs(Request $request): JsonResponse
    {
        $this->authorize('statutory.manage');

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'state'     => 'required|string',
            'slabs'     => 'required|array',
            'slabs.*.salary_from' => 'required|numeric|min:0',
            'slabs.*.salary_to'   => 'nullable|numeric',
            'slabs.*.pt_amount'   => 'required|numeric|min:0',
        ]);

        // Deactivate old slabs for this state
        PtSlab::where('company_id', $validated['company_id'])->where('state', $validated['state'])->update(['is_active' => false]);

        foreach ($validated['slabs'] as $slab) {
            PtSlab::create([
                'company_id' => $validated['company_id'],
                'state'       => $validated['state'],
                'salary_from' => $slab['salary_from'],
                'salary_to'   => $slab['salary_to'] ?? null,
                'pt_amount'   => $slab['pt_amount'],
                'is_active'   => true,
            ]);
        }

        return response()->json(['message' => 'PT slabs saved.']);
    }

    // ─────────────────────────────────────────────
    // TDS SLABS
    // ─────────────────────────────────────────────

    public function tdsSlabs(Request $request): JsonResponse
    {
        $slabs = TdsSlab::where('financial_year', $request->get('fy', $this->currentFy()))
            ->when($request->regime, fn($q) => $q->where('regime', $request->regime))
            ->orderBy('income_from')
            ->get();

        return response()->json(['data' => $slabs]);
    }

    // ─────────────────────────────────────────────
    // PF ECR GENERATION
    // ─────────────────────────────────────────────

    public function generateEcr(PayrollRun $payrollRun): JsonResponse
    {
        $this->authorize('statutory.manage');

        if ($payrollRun->status !== 'finalized') {
            return response()->json(['message' => 'Payroll must be finalized to generate ECR.'], 422);
        }

        $period  = $payrollRun->period;
        $records = PayrollEmployeeRecord::where('payroll_run_id', $payrollRun->id)
            ->where('pf_employee', '>', 0)
            ->with(['employee.statutoryDetails'])
            ->get();

        // ECR Format as per EPFO
        $lines = [];
        $lines[] = '#~#';  // Header marker

        $totalWages = 0;
        $totalEmpPf = 0;
        $totalErPf  = 0;
        $totalEps   = 0;
        $totalEdli  = 0;

        foreach ($records as $record) {
            $employee = $record->employee;
            $uan      = $employee->statutoryDetails?->uan_number ?? '';
            if (!$uan) continue;

            $pfWages  = round($record->gross_salary > 15000 ? 15000 : $record->gross_salary, 2);
            $empPf    = $record->pf_employee;
            $eps      = round($pfWages * 0.0833, 2);
            $erPf     = $record->pf_employer;
            $edli     = round($pfWages * 0.005, 2);
            $ncpDays  = 0; // Non-contributory periods
            $refundAdv= 0; // Refund of advances

            $lines[] = implode('#~#', [
                $uan,
                $employee->full_name,
                $pfWages,   // Gross wages
                $pfWages,   // EPF wages
                $pfWages,   // EPS wages
                $pfWages,   // EDLI wages
                $empPf,     // EPF contri (EE)
                $eps,       // EPS contri
                $erPf,      // EPF contri (ER)
                $record->lop_days, // NCP days
                $refundAdv, // Refund of advance
            ]);

            $totalWages += $pfWages;
            $totalEmpPf += $empPf;
            $totalErPf  += $erPf;
            $totalEps   += $eps;
            $totalEdli  += $edli;
        }

        $content  = implode("\n", $lines);
        $fileName = "ecr/{$payrollRun->company_id}/{$period->year}_{$period->month}_ecr.txt";
        Storage::put($fileName, $content);

        $ecr = PfEcrRegister::updateOrCreate(
            ['payroll_run_id' => $payrollRun->id],
            [
                'company_id' => $payrollRun->company_id,
                'year'                => $period->year,
                'month'               => $period->month,
                'total_employees'     => $records->count(),
                'total_pf_wages'      => $totalWages,
                'total_employee_pf'   => $totalEmpPf,
                'total_employer_pf'   => $totalErPf,
                'total_eps'           => $totalEps,
                'total_edli'          => $totalEdli,
                'ecr_file_path'       => $fileName,
                'status'              => 'generated',
            ]
        );

        return response()->json(['message' => 'ECR generated.', 'data' => $ecr]);
    }

    public function downloadEcr(PfEcrRegister $ecr): \Symfony\Component\HttpFoundation\Response
    {
        if (!$ecr->ecr_file_path || !Storage::exists($ecr->ecr_file_path)) {
            abort(404, 'ECR file not found.');
        }

        return response()->download(
            storage_path("app/{$ecr->ecr_file_path}"),
            "PF_ECR_{$ecr->year}_{$ecr->month}.txt"
        );
    }

    // ─────────────────────────────────────────────
    // CHALLAN TRACKING
    // ─────────────────────────────────────────────

    public function challans(Request $request): JsonResponse
    {
        $challans = ComplianceChallan::where('company_id', $request->company_id)
            ->when($request->challan_type, fn($q) => $q->where('challan_type', $request->challan_type))
            ->when($request->year, fn($q) => $q->where('year', $request->year))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderByDesc('year')->orderByDesc('month')
            ->paginate(50);

        return response()->json($challans);
    }

    public function storeChallan(Request $request): JsonResponse
    {
        $this->authorize('statutory.manage');

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'challan_type' => 'required|in:pf,esic,pt,tds,lwf',
            'year'         => 'required|integer',
            'month'        => 'required|integer|min:1|max:12',
            'amount'       => 'required|numeric|min:0',
            'due_date'     => 'required|date',
        ]);

        $challan = ComplianceChallan::create(array_merge($validated, ['status' => 'pending']));

        return response()->json(['message' => 'Challan created.', 'data' => $challan], 201);
    }

    public function markChallanPaid(Request $request, ComplianceChallan $challan): JsonResponse
    {
        $this->authorize('statutory.manage');

        $validated = $request->validate([
            'challan_number' => 'required|string',
            'payment_date'   => 'required|date',
            'receipt'        => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')->store("challans/{$challan->company_id}", 'local');
        }

        $challan->update([
            'challan_number' => $validated['challan_number'],
            'payment_date'   => $validated['payment_date'],
            'status'         => 'paid',
            'receipt_path'   => $receiptPath,
        ]);

        return response()->json(['message' => 'Challan marked as paid.', 'data' => $challan]);
    }

    // ─────────────────────────────────────────────
    // COMPLIANCE DASHBOARD
    // ─────────────────────────────────────────────

    public function complianceDashboard(Request $request): JsonResponse
    {
        $this->authorize('statutory.view');

        $companyId = $request->company_id;
        $year     = $request->get('year', now()->year);

        // Overdue challans
        $overdue = ComplianceChallan::where('company_id', $companyId)
            ->where('due_date', '<', now())
            ->where('status', 'pending')
            ->get();

        // Due this month
        $dueThisMonth = ComplianceChallan::where('company_id', $companyId)
            ->whereYear('due_date', now()->year)
            ->whereMonth('due_date', now()->month)
            ->where('status', 'pending')
            ->get();

        // Monthly summary for the year
        $monthlySummary = ComplianceChallan::where('company_id', $companyId)
            ->where('year', $year)
            ->selectRaw('month, challan_type, SUM(amount) as total, SUM(penalty) as penalty, status')
            ->groupBy('month', 'challan_type', 'status')
            ->orderBy('month')
            ->get();

        // Annual totals by type
        $annualTotals = ComplianceChallan::where('company_id', $companyId)
            ->where('year', $year)
            ->selectRaw('challan_type, SUM(amount) as total_amount, COUNT(*) as count, SUM(CASE WHEN status="paid" THEN 1 ELSE 0 END) as paid_count')
            ->groupBy('challan_type')
            ->get();

        return response()->json([
            'overdue'         => $overdue,
            'due_this_month'  => $dueThisMonth,
            'monthly_summary' => $monthlySummary,
            'annual_totals'   => $annualTotals,
        ]);
    }

    private function currentFy(): int
    {
        return now()->month >= 4 ? now()->year : now()->year - 1;
    }
}
