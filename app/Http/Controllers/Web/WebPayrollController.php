<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Entity;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\SalaryReportSnapshot;
use App\Models\User;
use Illuminate\Http\Request;

class WebPayrollController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $this->resolveCompanyId($request);

        $periods = PayrollPeriod::where('company_id', $companyId)
            ->withCount('runs')
            ->orderByDesc('year')->orderByDesc('month')
            ->paginate(24);

        $lastRun = PayrollRun::where('company_id', $companyId)
            ->whereIn('status', ['finalized', 'paid'])
            ->orderByDesc('id')->first();

        // Saved salary report snapshots — grouped by month, most recent first
        $savedReports = SalaryReportSnapshot::orderByDesc('year_month')
            ->get()
            ->groupBy('year_month')
            ->map(function ($snaps, $ym) {
                $latest = $snaps->sortByDesc('locked_at')->first();
                return [
                    'year_month'     => $ym,
                    'employee_count' => $snaps->count(),
                    'locked_at'      => $latest->locked_at,
                    'locked_by'      => $latest->locked_by,
                    'total_net_pay'  => $snaps->sum(fn($s) => (float) ($s->snapshot_data['net_pay'] ?? 0)),
                    'total_gross'    => $snaps->sum(fn($s) => (float) ($s->snapshot_data['payable_gross'] ?? 0)),
                ];
            })
            ->values();

        $userIds = $savedReports->pluck('locked_by')->filter()->unique()->all();
        $lockers = User::whereIn('id', $userIds)->pluck('name', 'id');

        $companies = Company::orderBy('name')->get(['id', 'name', 'short_name']);

        return view('payroll.index', compact(
            'periods', 'lastRun', 'companyId',
            'savedReports', 'lockers', 'companies'
        ));
    }

    public function createPeriod(Request $request)
    {
        $companyId = $this->resolveCompanyId($request);

        $validated = $request->validate([
            'year'        => 'required|integer|min:2000|max:2100',
            'month'       => 'required|integer|min:1|max:12',
            'period_from' => 'required|date',
            'period_to'   => 'required|date|after:period_from',
            'payment_date'=> 'nullable|date',
        ]);

        $exists = PayrollPeriod::where('company_id', $companyId)
            ->where('year', $validated['year'])
            ->where('month', $validated['month'])->exists();

        if ($exists) {
            return redirect()->back()->withErrors(['month' => 'Period already exists for this month.']);
        }

        PayrollPeriod::create(array_merge($validated, [
            'company_id' => $companyId,
            'attendance_lock_date' => $validated['period_to'],
            'status'               => 'open',
        ]));

        return redirect()->route('payroll')->with('success', 'Payroll period created.');
    }

    public function initRun(Request $request)
    {
        $validated = $request->validate([
            'payroll_period_id' => 'required|exists:payroll_periods,id',
            'run_type'          => 'required|in:regular,supplementary,arrear,bonus',
        ]);

        $period = PayrollPeriod::findOrFail($validated['payroll_period_id']);

        if ($period->is_locked) {
            return redirect()->back()->withErrors(['payroll_period_id' => 'Period is locked.']);
        }

        PayrollRun::create([
            'payroll_period_id' => $period->id,
            'company_id' => $period->company_id,
            'run_type'          => $validated['run_type'],
            'run_name'          => ucfirst($validated['run_type']),
            'status'            => 'draft',
            'is_editable'       => true,
        ]);

        return redirect()->route('payroll')->with('success', 'Payroll run initiated.');
    }
}
