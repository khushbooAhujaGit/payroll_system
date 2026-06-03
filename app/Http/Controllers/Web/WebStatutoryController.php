<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ComplianceChallan;
use App\Models\Entity;
use App\Models\EsicSetting;
use App\Models\PfSetting;
use App\Models\PtSlab;
use Illuminate\Http\Request;

class WebStatutoryController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $this->resolveCompanyId($request);

        $overdue = ComplianceChallan::where('company_id', $companyId)
            ->where('due_date', '<', now())
            ->where('status', 'pending')
            ->get();

        $dueThisMonth = ComplianceChallan::where('company_id', $companyId)
            ->whereYear('due_date', now()->year)
            ->whereMonth('due_date', now()->month)
            ->where('status', 'pending')
            ->get();

        $annualTotals = ComplianceChallan::where('company_id', $companyId)
            ->where('year', now()->year)
            ->selectRaw('challan_type, SUM(amount) as total_amount, COUNT(*) as count,
                SUM(CASE WHEN status="paid" THEN 1 ELSE 0 END) as paid_count')
            ->groupBy('challan_type')
            ->get();

        $challans = ComplianceChallan::where('company_id', $companyId)
            ->orderByDesc('year')->orderByDesc('month')
            ->paginate(25)->withQueryString();

        $pfSettings   = PfSetting::where('company_id', $companyId)->first();
        $esicSettings = EsicSetting::where('company_id', $companyId)->first();

        return view('statutory.index', compact(
            'overdue', 'dueThisMonth', 'annualTotals',
            'challans', 'pfSettings', 'esicSettings', 'companyId'
        ));
    }

    public function storeChallan(Request $request)
    {
        $companyId = $this->resolveCompanyId($request);

        $validated = $request->validate([
            'challan_type' => 'required|in:pf,esic,pt,tds,lwf',
            'year'         => 'required|integer',
            'month'        => 'required|integer|min:1|max:12',
            'amount'       => 'required|numeric|min:0',
            'due_date'     => 'required|date',
        ]);

        ComplianceChallan::create(array_merge($validated, [
            'company_id' => $companyId,
            'status'    => 'pending',
        ]));

        return redirect()->route('statutory')->with('success', 'Challan created.');
    }

    public function markPaid(Request $request, ComplianceChallan $challan)
    {
        $validated = $request->validate([
            'challan_number' => 'required|string',
            'payment_date'   => 'required|date',
        ]);

        $challan->update([
            'challan_number' => $validated['challan_number'],
            'payment_date'   => $validated['payment_date'],
            'status'         => 'paid',
        ]);

        return redirect()->route('statutory')->with('success', 'Challan marked as paid.');
    }
}
