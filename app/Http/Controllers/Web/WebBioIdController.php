<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\ImportsExcel;
use App\Http\Controllers\Controller;
use App\Imports\BioIdImporter;
use App\Models\Employee;
use App\Models\EmployeeBioId;
use App\Models\Entity;
use App\Services\ExcelImportService;
use Illuminate\Http\Request;

class WebBioIdController extends Controller
{
    use ImportsExcel;

    public function __construct(protected ExcelImportService $excelImportService) {}

    /**
     * Show all biometric ID mappings with optional search/filter.
     */
    public function index(Request $request)
    {
        $this->authorize('bioid_view');

        $companyId = $this->resolveCompanyId($request);

        $bioIds = EmployeeBioId::with('employee:id,employee_id,first_name,middle_name,last_name,company_id')
            ->whereHas('employee', fn($q) => $q)
            ->when($request->search, function ($q) use ($request) {
                $q->where(function ($sq) use ($request) {
                    $sq->where('bio_id_full', 'like', "%{$request->search}%")
                       ->orWhereHas('employee', fn($eq) =>
                           $eq->where('first_name', 'like', "%{$request->search}%")
                              ->orWhere('last_name', 'like', "%{$request->search}%")
                              ->orWhere('employee_id', 'like', "%{$request->search}%")
                       );
                });
            })
            ->when($request->machine, fn($q) => $q->where('machine_name', $request->machine))
            ->orderBy('machine_name')
            ->orderBy('bio_number')
            ->paginate(50)
            ->withQueryString();

        $machines = EmployeeBioId::distinct()->pluck('machine_name')->sort()->values();
        $totalBioIds = EmployeeBioId::whereHas('employee', fn($q) => $q)->count();
        $unmatchedCount = 0; // bio IDs exist in raw punches but not in employee_bio_ids

        $employees = Employee::query()
            ->currentlyEmployed()
            ->orderBy('employee_id')
            ->get(['id', 'employee_id', 'first_name', 'middle_name', 'last_name']);

        // Next expected Bio ID per machine — machine_name and bio_type are a
        // fixed 1:1 pairing (see EmployeeBioId::MACHINE_MAP), so once the
        // machine is picked on the Add form the correct type prefix and next
        // sequence number are both already known.
        $nextBioIdByMachine = [];
        foreach (EmployeeBioId::MACHINE_MAP as $type => $machine) {
            $maxNumber = EmployeeBioId::where('machine_name', $machine)->max('bio_number');
            $nextBioIdByMachine[$machine] = $type . '-' . (((int) $maxNumber) + 1);
        }

        // Existing Bio ID(s) per employee, so the Add form can flag "already
        // mapped" — an employee can legitimately have more than one (one per
        // machine), so this is a heads-up, not a block.
        $employeeBioIds = EmployeeBioId::orderBy('machine_name')
            ->get(['employee_id', 'bio_id_full', 'machine_name'])
            ->groupBy('employee_id')
            ->map(fn($rows) => $rows->map(fn($r) => "{$r->bio_id_full} (Machine {$r->machine_name})")->values());

        return view('bio-ids.index', compact('bioIds', 'machines', 'totalBioIds', 'employees', 'nextBioIdByMachine', 'employeeBioIds'));
    }

    public function template()
    {
        $this->authorize('bioid_create');

        return $this->excelImportService->generateTemplate(BioIdImporter::class);
    }

    public function import(Request $request)
    {
        $this->authorize('bioid_create');

        $companyId = $this->resolveCompanyId($request);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        $result = $this->excelImportService->import(
            $request->file('file'),
            'bio_id',
            BioIdImporter::class,
            ['company_id' => $companyId]
        );

        return $this->handleImportResult($result, 'bio_ids', 'Bio ID mapping');
    }

    public function store(Request $request)
    {
        $this->authorize('bioid_create');

        $companyId = $this->resolveCompanyId($request);

        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'bio_id_full' => 'required|string|max:20',
            'machine_name'=> 'required|string|max:10',
            'is_active'   => 'boolean',
        ]);

        $parsed = EmployeeBioId::parse($validated['bio_id_full']);
        if (!$parsed) {
            return redirect()->back()->withErrors(['bio_id_full' => 'Invalid Bio ID format. Use I-20, II-5, III-3, or IV-3 (Other/WFH).']);
        }

        // Prevent duplicates
        $exists = EmployeeBioId::where('bio_id_full', $parsed['bio_id_full'])
            ->where('machine_name', $validated['machine_name'])
            ->exists();
        if ($exists) {
            return redirect()->back()->withErrors(['bio_id_full' => 'Bio ID ' . $parsed['bio_id_full'] . ' already registered for Machine ' . $validated['machine_name']]);
        }

        EmployeeBioId::create([
            'employee_id'  => $validated['employee_id'],
            'bio_id_full'  => $parsed['bio_id_full'],
            'bio_type'     => $parsed['bio_type'],
            'bio_number'   => $parsed['bio_number'],
            'machine_name' => $validated['machine_name'],
            'is_active'    => $request->boolean('is_active', true),
        ]);

        return redirect()->route('bio_ids')->with('success', 'Bio ID ' . $parsed['bio_id_full'] . ' added successfully.');
    }

    public function update(Request $request, EmployeeBioId $bioId)
    {
        $this->authorize('bioid_edit');

        $validated = $request->validate([
            'bio_id_full' => 'required|string|max:20',
            'is_active'   => 'boolean',
        ]);

        $parsed = EmployeeBioId::parse($validated['bio_id_full']);
        if (!$parsed) {
            return redirect()->back()->withErrors(['bio_id_full' => 'Invalid Bio ID format (expected I-20, II-5, III-3, or IV-3).']);
        }

        $bioId->update(array_merge($parsed, [
            'is_active' => $request->boolean('is_active', true),
        ]));

        return redirect()->route('bio_ids')->with('success', 'Bio ID updated.');
    }

    public function destroy(EmployeeBioId $bioId)
    {
        $this->authorize('bioid_delete');

        $bioId->delete();
        return redirect()->route('bio_ids')->with('success', 'Bio ID deleted.');
    }
}
