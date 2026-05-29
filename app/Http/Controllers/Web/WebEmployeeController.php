<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\ImportsExcel;
use App\Http\Controllers\Controller;
use App\Http\Requests\HR\StoreEmployeeRequest;
use App\Imports\EmployeeImporter;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Entity;
use App\Services\EmployeeService;
use App\Services\ExcelImportService;
use Illuminate\Http\Request;

class WebEmployeeController extends Controller
{
    use ImportsExcel;
    public function __construct(
        protected EmployeeService $employeeService,
        protected ExcelImportService $excelImportService
    ) {}

    public function index(Request $request)
    {
        $entityId = $this->resolveEntityId($request);

        $query = Employee::with(['department', 'designation']);
            // ->where('entity_id', $entityId);
// dd($query->get(), $entityId);
        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(fn($sq) => $sq
                ->where('first_name', 'like', "%{$q}%")
                ->orWhere('last_name', 'like', "%{$q}%")
                ->orWhere('employee_id', 'like', "%{$q}%")
                ->orWhere('official_email', 'like', "%{$q}%")
            );
        }
        
        if ($request->filled('department_id')) $query->where('department_id', $request->department_id);
        if ($request->filled('status'))        $query->where('employee_status', $request->status);

        $employees    = $query->orderBy('employee_id')->paginate(25)->withQueryString();
        $departments  = Department::where('entity_id', $entityId)->orderBy('name')->get();
        $designations = Designation::where('entity_id', $entityId)->orderBy('name')->get();
        $branches     = Branch::where('entity_id', $entityId)->where('is_active', true)->orderBy('name')->get();
        $managers     = Employee::where('entity_id', $entityId)
                            ->where('employee_status', 'active')
                            ->orderBy('first_name')
                            ->get(['id', 'first_name', 'last_name', 'employee_id']);

        return view('employees.index', compact(
            'employees', 'departments', 'designations', 'branches', 'managers', 'entityId'
        ));
    }

    public function store(StoreEmployeeRequest $request)
    {
        $this->employeeService->create($request->validated());
        return redirect()->route('employees')->with('success', 'Employee created successfully.');
    }

    public function template()
    {
        return $this->excelImportService->generateTemplate(EmployeeImporter::class);
    }

    public function import(Request $request)
    {
        $entityId = $this->resolveEntityId($request);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        $result = $this->excelImportService->import(
            $request->file('file'),
            'employee',
            EmployeeImporter::class,
            ['entity_id' => $entityId]
        );

        return $this->handleImportResult($result, 'employees', 'employee');
    }

    /** Return employee data as JSON for the Edit modal (AJAX). */
    public function editData(Employee $employee)
    {
        $employee->load(['statutoryDetails', 'primaryBank']);
        return response()->json([
            'id'                      => $employee->id,
            'first_name'              => $employee->first_name,
            'middle_name'             => $employee->middle_name,
            'last_name'               => $employee->last_name,
            'gender'                  => $employee->gender,
            'date_of_birth'           => $employee->date_of_birth?->format('Y-m-d'),
            'blood_group'             => $employee->blood_group,
            'marital_status'          => $employee->marital_status,
            'official_email'          => $employee->official_email,
            'personal_email'          => $employee->personal_email,
            'mobile'                  => $employee->mobile,
            'alternate_phone'         => $employee->alternate_phone,
            'employee_status'         => $employee->employee_status,
            'department_id'           => $employee->department_id,
            'designation_id'          => $employee->designation_id,
            'branch_id'               => $employee->branch_id,
            'reporting_manager_id'    => $employee->reporting_manager_id,
            'work_type'               => $employee->work_type,
            'employment_type'         => $employee->employment_type,
            'date_of_leaving'         => $employee->date_of_leaving?->format('Y-m-d'),
            'is_pf_applicable'        => (bool) $employee->is_pf_applicable,
            'is_esic_applicable'      => (bool) $employee->is_esic_applicable,
            'is_pt_applicable'        => (bool) $employee->is_pt_applicable,
            'is_tds_applicable'       => (bool) $employee->is_tds_applicable,
            'tds_regime'              => $employee->tds_regime,
            'pan_number'              => $employee->statutoryDetails?->pan_number,
            'uan_number'              => $employee->statutoryDetails?->uan_number,
            'aadhaar_number'          => $employee->statutoryDetails?->aadhaar_number,
            'bank_name'               => $employee->primaryBank?->bank_name,
            'account_number'          => $employee->primaryBank?->account_number,
            'ifsc_code'               => $employee->primaryBank?->ifsc_code,
            'account_holder_name'     => $employee->primaryBank?->account_holder_name,
            'emergency_contact_name'  => $employee->emergency_contact_name,
            'emergency_contact_phone' => $employee->emergency_contact_phone,
        ]);
    }

    public function update(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'first_name'              => 'required|string|max:100',
            'middle_name'             => 'nullable|string|max:100',
            'last_name'               => 'required|string|max:100',
            'gender'                  => 'nullable|in:Male,Female,Other',
            'date_of_birth'           => 'nullable|date|before:today',
            'blood_group'             => 'nullable|string|max:5',
            'marital_status'          => 'nullable|string|max:20',
            'official_email'          => 'nullable|email',
            'personal_email'          => 'nullable|email',
            'mobile'                  => 'nullable|string|max:15',
            'alternate_phone'         => 'nullable|regex:/^\d{10}$/',
            'employee_status'         => 'required|in:active,inactive,on_notice,terminated',
            'department_id'           => 'nullable|exists:departments,id',
            'designation_id'          => 'nullable|exists:designations,id',
            'branch_id'               => 'nullable|exists:branches,id',
            'reporting_manager_id'    => 'nullable|exists:employees,id',
            'work_type'               => 'nullable|in:wfo,wfh,hybrid,client_location',
            'employment_type'         => 'nullable|in:full_time,part_time,contract,intern,consultant,retainer',
            'date_of_leaving'         => 'nullable|date',
            'is_pf_applicable'        => 'boolean',
            'is_esic_applicable'      => 'boolean',
            'is_pt_applicable'        => 'boolean',
            'is_tds_applicable'       => 'boolean',
            'tds_regime'              => 'nullable|in:old,new',
            'pan_number'              => ['nullable', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/'],
            'aadhaar_number'          => 'nullable|regex:/^\d{12}$/',
            'uan_number'              => 'nullable|string|max:12',
            'bank_name'               => 'nullable|string|max:100',
            'account_number'          => 'nullable|string|max:30',
            'ifsc_code'               => ['nullable', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
            'account_holder_name'     => 'nullable|string|max:200',
            'emergency_contact_name'  => 'nullable|string|max:100',
            'emergency_contact_phone' => 'nullable|regex:/^\d{10}$/',
        ]);

        // Checkboxes are absent when unchecked — coerce explicitly
        foreach (['is_pf_applicable','is_esic_applicable','is_pt_applicable','is_tds_applicable'] as $flag) {
            $validated[$flag] = $request->boolean($flag);
        }

        $this->employeeService->update($employee, $validated);

        // Bank details update (service only handles statutory)
        if (!empty($validated['account_number'])) {
            $employee->primaryBank()->updateOrCreate(
                ['employee_id' => $employee->id, 'is_primary' => true],
                [
                    'bank_name'           => $validated['bank_name'] ?? '',
                    'account_number'      => $validated['account_number'],
                    'ifsc_code'           => $validated['ifsc_code'] ?? '',
                    'account_holder_name' => $validated['account_holder_name'] ?? $employee->full_name,
                    'account_type'        => 'savings',
                ]
            );
        }

        return redirect()->route('employees')->with('success', 'Employee updated successfully.');
    }

    public function destroy(Employee $employee)
    {
        $employee->delete(); // soft delete
        return redirect()->route('employees')->with('success', 'Employee deleted.');
    }
}
