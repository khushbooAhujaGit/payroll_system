<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\ImportsExcel;
use App\Http\Controllers\Controller;
use App\Imports\ContractorImporter;
use App\Models\Contractor;
use App\Services\ExcelImportService;
use Illuminate\Http\Request;

class WebContractorController extends Controller
{
    use ImportsExcel;

    public function __construct(protected ExcelImportService $excelImportService) {}

    public function index(Request $request)
    {
        $entityId = $this->resolveEntityId($request);

        $contractors = Contractor::where('entity_id', $entityId)
            ->when($request->search, fn($q) =>
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('pan', 'like', "%{$request->search}%")
                  ->orWhere('mobile', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
            )
            ->when($request->type, fn($q) => $q->where('contractor_type', $request->type))
            ->when($request->status !== null && $request->status !== '', fn($q) =>
                $q->where('is_active', (bool) $request->status)
            )
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $total      = Contractor::where('entity_id', $entityId)->count();
        $activeCount= Contractor::where('entity_id', $entityId)->where('is_active', true)->count();

        return view('contractors.index', compact('contractors', 'total', 'activeCount', 'entityId'));
    }

    public function store(Request $request)
    {
        $entityId = $this->resolveEntityId($request);

        $validated = $request->validate([
            'name'                => 'required|string|max:200',
            'contractor_type'     => 'required|in:individual,company',
            'pan'                 => ['nullable', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/'],
            'gstin'               => 'nullable|string|max:20',
            'mobile'              => 'nullable|regex:/^\d{10}$/',
            'email'               => 'nullable|email|max:150',
            'bank_account_number' => 'nullable|string|max:30',
            'bank_name'           => 'nullable|string|max:100',
            'ifsc_code'           => ['nullable', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
            'address'             => 'nullable|string|max:500',
        ]);

        // Auto-generate contractor_id
        $last = Contractor::where('entity_id', $entityId)->orderByDesc('id')->value('contractor_id');
        $num  = $last ? ((int)preg_replace('/\D/', '', $last) + 1) : 1;

        Contractor::create(array_merge($validated, [
            'entity_id'     => $entityId,
            'contractor_id' => 'CTR-' . str_pad($num, 4, '0', STR_PAD_LEFT),
            'is_active'     => true,
        ]));

        return redirect()->route('contractors')->with('success', 'Contractor added successfully.');
    }

    public function update(Request $request, Contractor $contractor)
    {
        $validated = $request->validate([
            'name'                => 'required|string|max:200',
            'contractor_type'     => 'required|in:individual,company',
            'pan'                 => ['nullable', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/'],
            'gstin'               => 'nullable|string|max:20',
            'mobile'              => 'nullable|regex:/^\d{10}$/',
            'email'               => 'nullable|email|max:150',
            'bank_account_number' => 'nullable|string|max:30',
            'bank_name'           => 'nullable|string|max:100',
            'ifsc_code'           => ['nullable', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
            'address'             => 'nullable|string|max:500',
            'is_active'           => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $contractor->update($validated);

        return redirect()->route('contractors')->with('success', 'Contractor updated.');
    }

    public function destroy(Contractor $contractor)
    {
        $contractor->delete();
        return redirect()->route('contractors')->with('success', 'Contractor deleted.');
    }

    public function template()
    {
        return $this->excelImportService->generateTemplate(ContractorImporter::class);
    }

    public function import(Request $request)
    {
        $entityId = $this->resolveEntityId($request);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        $result = $this->excelImportService->import(
            $request->file('file'),
            'contractor',
            ContractorImporter::class,
            ['entity_id' => $entityId]
        );

        return $this->handleImportResult($result, 'contractors', 'contractor');
    }
}
