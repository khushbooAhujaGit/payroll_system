<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;

class WebMasterController extends Controller
{
    public function index()
    {
        $companies = Company::orderBy('name')->get();
        return view('masters.index', compact('companies'));
    }

    public function storeCompany(Request $request)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:200',
            'short_name' => 'nullable|string|max:50',
            'cin'        => 'nullable|string|max:21',
            'pan'        => 'nullable|string|max:10',
            'tan'        => 'nullable|string|max:10',
            'gstin'      => 'nullable|string|max:15',
            'email'      => 'nullable|email|max:200',
            'phone'      => 'nullable|string|max:20',
            'address'    => 'nullable|string',
            'state'      => 'nullable|string|max:100',
            'city'       => 'nullable|string|max:100',
        ]);

        Company::create($validated);
        return redirect()->route('masters')->with('success', 'Company created.');
    }

    public function destroyCompany(Company $company)
    {
        $company->delete();
        return redirect()->route('masters')->with('success', 'Company deleted.');
    }
}
