<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\StoreEmployeeRequest;
use App\Http\Requests\HR\UpdateEmployeeRequest;
use App\Imports\EmployeeImporter;
use App\Models\Employee;
use App\Models\EmployeeHistory;
use App\Services\ExcelImportService;
use App\Services\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmployeeController extends Controller
{
    public function __construct(
        protected EmployeeService $employeeService,
        protected ExcelImportService $excelImportService
    ) {}

    // ─────────────────────────────────────────────
    // LIST / SEARCH
    // ─────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $this->authorize('employee.view');

        $query = Employee::with([
            'department',
            'designation',
            'grade',
            'branch',
            'reportingManager',
            'currentSalary',
        ])->byEntity(Auth::user()->company_id ?? $request->company_id);

        // Filters
        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($sq) use ($q) {
                $sq->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('employee_id', 'like', "%{$q}%")
                    ->orWhere('mobile', 'like', "%{$q}%")
                    ->orWhere('official_email', 'like', "%{$q}%");
            });
        }
        if ($request->filled('department_id'))    $query->where('department_id', $request->department_id);
        if ($request->filled('designation_id'))   $query->where('designation_id', $request->designation_id);
        if ($request->filled('grade_id'))         $query->where('grade_id', $request->grade_id);
        if ($request->filled('employment_type'))  $query->where('employment_type', $request->employment_type);
        if ($request->filled('employee_status'))  $query->where('employee_status', $request->employee_status);
        if ($request->filled('branch_id'))        $query->where('branch_id', $request->branch_id);
        if ($request->filled('on_probation'))     $query->onProbation();

        // Sorting
        $sortBy  = $request->get('sort_by', 'employee_id');
        $sortDir = $request->get('sort_dir', 'asc');
        $query->orderBy($sortBy, $sortDir);

        $employees = $query->paginate($request->get('per_page', 25));

        return response()->json($employees);
    }

    // ─────────────────────────────────────────────
    // SHOW
    // ─────────────────────────────────────────────

    public function show(Employee $employee): JsonResponse
    {
        $this->authorize('employee.view');

        $employee->load([
            'entity',
            'branch',
            'department',
            'designation',
            'grade',
            'costCenter',
            'reportingManager',
            'documents',
            'bankDetails',
            'statutoryDetails',
            'currentSalary.components.salaryComponent',
            'leaveBalances.leaveType',
            'onboarding.onboardingTask',
            'education',
            'experience',
        ]);

        return response()->json(['data' => $employee]);
    }

    // ─────────────────────────────────────────────
    // CREATE
    // ─────────────────────────────────────────────

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $this->authorize('employee.create');

        $employee = $this->employeeService->create($request->validated());

        return response()->json([
            'message' => 'Employee created successfully.',
            'data'    => $employee->load(['department', 'designation', 'grade']),
        ], 201);
    }

    // ─────────────────────────────────────────────
    // UPDATE
    // ─────────────────────────────────────────────

    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $this->authorize('employee.edit');

        $employee = $this->employeeService->update($employee, $request->validated());

        return response()->json([
            'message' => 'Employee updated successfully.',
            'data'    => $employee,
        ]);
    }

    // ─────────────────────────────────────────────
    // LIFECYCLE EVENTS
    // ─────────────────────────────────────────────

    public function confirm(Request $request, Employee $employee): JsonResponse
    {
        $this->authorize('employee.edit');

        $request->validate([
            'confirmation_date' => 'required|date',
            'remarks'           => 'nullable|string',
        ]);

        $employee->update(['date_of_confirmation' => $request->confirmation_date]);

        EmployeeHistory::create([
            'employee_id'    => $employee->id,
            'event_type'     => 'confirmation',
            'effective_date' => $request->confirmation_date,
            'new_values'     => ['date_of_confirmation' => $request->confirmation_date],
            'remarks'        => $request->remarks,
            'created_by'     => Auth::user()->id(),
        ]);

        return response()->json(['message' => 'Employee confirmed successfully.']);
    }


    public function transfer(Request $request, Employee $employee): JsonResponse
    {
        $this->authorize('employee.edit');

        $request->validate([
            'department_id'  => 'nullable|exists:departments,id',
            'branch_id'      => 'nullable|exists:branches,id',
            'designation_id' => 'nullable|exists:designations,id',
            'effective_date' => 'required|date',
            'remarks'        => 'nullable|string',
        ]);

        $old = $employee->only(['department_id', 'branch_id', 'designation_id']);
        $employee->update($request->only(['department_id', 'branch_id', 'designation_id']));

        EmployeeHistory::create([
            'employee_id'    => $employee->id,
            'event_type'     => 'transfer',
            'effective_date' => $request->effective_date,
            'old_values'     => $old,
            'new_values'     => $request->only(['department_id', 'branch_id', 'designation_id']),
            'remarks'        => $request->remarks,
            'created_by'     => auth()->id(),
        ]);

        return response()->json(['message' => 'Transfer recorded.']);
    }

    // ─────────────────────────────────────────────
    // EXCEL UPLOAD
    // ─────────────────────────────────────────────

    public function uploadTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $this->excelImportService->generateTemplate(EmployeeImporter::class);
    }

    public function import(Request $request): JsonResponse
    {
        $this->authorize('employee.create');

        $request->validate([
            'file'      => 'required|file|mimes:xlsx,xls|max:10240',
            'company_id' => 'required|exists:companies,id',
        ]);

        $result = $this->excelImportService->import(
            $request->file('file'),
            'employee',
            EmployeeImporter::class,
            ['company_id' => $request->company_id]
        );

        return response()->json($result);
    }

    // ─────────────────────────────────────────────
    // EXCEL EXPORT
    // ─────────────────────────────────────────────

    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('employee.export');

        $employees = Employee::with(['department', 'designation', 'grade', 'branch', 'statutoryDetails'])
            ->byEntity($request->company_id)
            ->get();

        $headers = [
            'Employee ID',
            'First Name',
            'Last Name',
            'Gender',
            'Date of Birth',
            'Mobile',
            'Official Email',
            'Date of Joining',
            'Employment Type',
            'Department',
            'Designation',
            'Grade',
            'Branch',
            'Status',
            'PAN',
            'UAN',
        ];

        $data = $employees->map(fn($e) => [
            $e->employee_id,
            $e->first_name,
            $e->last_name,
            $e->gender,
            $e->date_of_birth?->format('d-m-Y'),
            $e->mobile,
            $e->official_email,
            $e->date_of_joining->format('d-m-Y'),
            $e->employment_type,
            $e->department?->name,
            $e->designation?->name,
            $e->grade?->name,
            $e->branch?->name,
            $e->employee_status,
            $e->statutoryDetails?->pan_number,
            $e->statutoryDetails?->uan_number,
        ])->toArray();

        return $this->excelImportService->export($data, $headers, 'employee_master_' . now()->format('d_m_Y'));
    }

    // ─────────────────────────────────────────────
    // HISTORY
    // ─────────────────────────────────────────────

    public function history(Employee $employee): JsonResponse
    {
        $history = EmployeeHistory::where('employee_id', $employee->id)
            ->with('createdBy:id,name')
            ->orderByDesc('effective_date')
            ->get();

        return response()->json(['data' => $history]);
    }

    // ─────────────────────────────────────────────
    // DOCUMENT MANAGEMENT
    // ─────────────────────────────────────────────

    public function uploadDocument(Request $request, Employee $employee): JsonResponse
    {
        $request->validate([
            'document_type'   => 'required|string',
            'file'            => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'document_number' => 'nullable|string',
            'expiry_date'     => 'nullable|date',
        ]);

        // $path = $request->file('file')->store("documents/employee/{$employee->id}", 'local');
        $file      = $request->file('file');
        $folder    = public_path("uploads/documents/employee/{$employee->id}");
        $filename  = time() . '_' . $file->getClientOriginalName();

        // Create folder if it doesn't exist
        if (!file_exists($folder)) {
            mkdir($folder, 0755, true);
        }

        $file->move($folder, $filename);

        $path = "uploads/documents/employee/{$employee->id}/{$filename}";

        $doc = $employee->documents()->create([
            'document_type'   => $request->document_type,
            'document_number' => $request->document_number,
            'file_path'       => $path,
            'file_name'       => $file->getClientOriginalName(),
            'expiry_date'     => $request->expiry_date,
            'uploaded_by'     => auth()->id(),
        ]);

        return response()->json(['message' => 'Document uploaded.', 'data' => $doc], 201);
    }
}
