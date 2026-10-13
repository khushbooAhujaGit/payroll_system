<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\ImportsExcel;
use App\Http\Controllers\Controller;
use App\Http\Requests\HR\StoreEmployeeRequest;
use App\Imports\EmployeeImporter;
use App\Models\Branch;
use App\Models\Location;
use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\Entity;
use App\Models\HolidayCalendar;
use App\Models\LeaveTrackerRecord;
use App\Services\EmployeeDocumentDownloadService;
use App\Services\EmployeeDocumentService;
use App\Services\EmployeeService;
use App\Services\ExcelImportService;
use App\Services\LeaveEntitlementService;
use App\Services\PublicFileStore;
use Illuminate\Http\Request;

class WebEmployeeController extends Controller
{
    use ImportsExcel;
    public function __construct(
        protected EmployeeService $employeeService,
        protected ExcelImportService $excelImportService,
        protected EmployeeDocumentService $employeeDocumentService,
        protected PublicFileStore $publicFileStore,
        protected EmployeeDocumentDownloadService $documentDownloadService,
        protected LeaveEntitlementService $leaveEntitlementService
    ) {}

    public function index(Request $request)
    {
        $this->authorize('employee_view');

        $companyId = $this->resolveCompanyId($request);

        $query = Employee::with(['department', 'designation', 'company']);

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

        // Companies only — entities are auto-resolved 1:1, never shown in UI
        $companies    = Company::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $departments  = Department::where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get();
        $designations = Designation::where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get();
        $branches     = Branch::where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get();
        $locations    = Location::where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get();
        $managers     = Employee::query()
                            ->where('employee_status', 'active')
                            ->orderBy('first_name')
                            ->get(['id', 'first_name', 'middle_name', 'last_name', 'employee_id']);

        // Reporting Manager defaults to only employees who already manage
        // someone — the common case — with a "show all" toggle in the UI for
        // picking someone new as a manager for the first time.
        $currentManagerIds = Employee::whereNotNull('reporting_manager_id')
            ->distinct()
            ->pluck('reporting_manager_id');
        $currentManagers = $managers->whereIn('id', $currentManagerIds)->values();

        // Suggest next employee_id per company — the highest numeric
        // sequence actually used, across EVERY employee_id ever assigned
        // for that company (withTrashed(): a soft-deleted employee still
        // occupies their old ID at the database level — see Employee's
        // SoftDeletes — so excluding them here previously re-suggested an
        // ID that was already taken and crashed on save). Also tolerates
        // an optional "EMP" prefix, since both "202618055" and
        // "EMP202618059"-style IDs exist side by side in real data; the
        // suggestion reuses whichever style the highest match used.
        $suggestedEmpIdByCompany = [];
        foreach ($companies as $co) {
            $maxSeq = null;
            $seqLen = 0;
            $prefix = '';

            Employee::withTrashed()
                ->where('company_id', $co->id)
                ->whereNotNull('employee_id')
                ->pluck('employee_id')
                ->each(function ($empId) use (&$maxSeq, &$seqLen, &$prefix) {
                    if (preg_match('/^(EMP)?(\d{4})(\d{1,6})$/i', $empId, $m)) {
                        $seq = (int) $m[3];
                        if ($maxSeq === null || $seq > $maxSeq) {
                            $maxSeq = $seq;
                            $seqLen = strlen($m[3]);
                            $prefix = $m[1];
                        }
                    }
                });

            $suggested = $maxSeq !== null
                ? $prefix . now()->year . str_pad((string) ($maxSeq + 1), $seqLen, '0', STR_PAD_LEFT)
                : '';
            $suggestedEmpIdByCompany[$co->id] = $suggested;
        }
        $suggestedEmpId = $suggestedEmpIdByCompany[$companyId] ?? '';

        // Per-employee CL/SL/CO + pending-count, for whoever's on this page
        // of results only — same summary shown on the Leave Management
        // page's reviewer tables, see WebLeaveController::buildLeaveSummaries().
        $leaveSummaries = [];
        foreach ($employees as $emp) {
            $clsl = $this->leaveEntitlementService->currentCLSL($emp);
            $leaveSummaries[$emp->id] = [
                'cl'      => $clsl['Casual Leave'] ?? 0,
                'sl'      => $clsl['Sick Leave'] ?? 0,
                'co'      => $this->leaveEntitlementService->currentCompOff($emp),
                'pending' => LeaveTrackerRecord::where('employee_id', $emp->id)
                    ->where('is_active', true)->where('is_approved', false)->count(),
            ];
        }

        return view('employees.index', compact(
            'companies', 'employees', 'departments', 'designations',
            'branches', 'locations', 'managers', 'currentManagers', 'currentManagerIds',
            'companyId', 'suggestedEmpId', 'suggestedEmpIdByCompany', 'leaveSummaries'
        ));
    }

    public function store(StoreEmployeeRequest $request)
    {
        $this->authorize('employee_add');

        $data = $request->validated();

        // company_id is already set from the form — pass it straight through

        // Checkbox is absent from the request when unchecked — without this,
        // Employee::create() would silently fall back to the DB column
        // default (true), so unchecking "PF Applicable" on this form never
        // actually saved as unchecked.
        $data['is_pf_applicable'] = $request->boolean('is_pf_applicable');

        $employee = $this->employeeService->create($data);

        $this->employeeDocumentService->storeUploads($employee, $request->file('documents', []), auth()->id());

        return redirect()->route('employees')->with('success', 'Employee created successfully.');
    }

    public function template(Request $request)
    {
        $this->authorize('employee_export');

        // Master-data columns are scoped to whichever company the Employees
        // page currently has selected — same scoping the Department/
        // Designation/Branch/Location master pages themselves use. Without
        // this, these dropdowns previously showed every company's entries
        // pooled together, which looked like "extra" unexplained options to
        // whoever's only ever seen their own company's master lists.
        $companyId = $this->resolveCompanyId($request);

        // Build dropdown lists from live DB data for the template — matches
        // the manual Add Employee form's fields exactly (see
        // EmployeeImporter::getTemplateHeaders()). Fixed-choice columns
        // (Gender, Employment Type, etc.) get the same option text shown on
        // that form; master-data columns (Department/Designation/Branch)
        // are dynamic. All dropdowns here are WARNING-style, not enforced —
        // see ExcelImportService::applyDropdownValidation().
        $dropdowns = [
            'Company'            => Company::where('is_active', true)->orderBy('name')->pluck('name')->all(),
            'Gender'             => ['Male', 'Female', 'Other'],
            'Marital Status'     => ['Single', 'Married', 'Divorced', 'Widowed'],
            'Employment Type'    => ['Full Time', 'Part Time', 'Contract', 'Intern', 'Consultant', 'Retainer'],
            'Work Type'          => ['Work from Office', 'Work from Home', 'Hybrid', 'Client Location'],
            'PF Applicable'      => ['Yes', 'No'],
            'Department'         => Department::where('company_id', $companyId)->orderBy('name')->distinct()->pluck('name')->all(),
            'Designation'        => Designation::where('company_id', $companyId)->orderBy('name')->distinct()->pluck('name')->all(),
            'Branch'             => Branch::where('company_id', $companyId)->where('is_active', true)->orderBy('name')->distinct()->pluck('name')->all(),
            'Work Location'      => Location::where('company_id', $companyId)->where('is_active', true)->orderBy('name')->distinct()->pluck('name')->all(),
            // Plain Employee ID only (not "ID — Name") — matches how
            // EmployeeImporter::resolveEmployee() actually looks these up.
            'Reporting Manager'  => Employee::currentlyEmployed()->where('company_id', $companyId)->orderBy('employee_id')->pluck('employee_id')->filter()->all(),
        ];

        return $this->excelImportService->generateTemplate(EmployeeImporter::class, $dropdowns);
    }

    public function import(Request $request)
    {
        $this->authorize('employee_add');

        // dd($request->all());
        $companyId = $this->resolveCompanyId($request);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        $importStartedAt = now();

        $result = $this->excelImportService->import(
            $request->file('file'),
            'employee',
            EmployeeImporter::class,
            ['company_id' => $companyId]
        );

        // Try to fetch any OneDrive-linked documents this import just saved
        // as pending, right away — the row-level DB transaction inside
        // import() already committed by the time we get here, so a slow or
        // failing Graph call can't roll back the employee data itself. Any
        // document that fails here is left pending (source_url set,
        // file_path null) exactly as before, retryable later via
        // `php artisan documents:fetch-onedrive`.
        if ($result['success'] ?? false) {
            EmployeeDocument::whereNotNull('source_url')
                ->whereNull('file_path')
                ->where('updated_at', '>=', $importStartedAt)
                ->get()
                ->each(fn(EmployeeDocument $doc) => $this->documentDownloadService->download($doc));
        }

        return $this->handleImportResult($result, 'employees', 'employee');
    }

    /** Return employee data as JSON for the Edit modal (AJAX). */
    public function editData(Employee $employee)
    {
        $this->authorize('employee_view');

        $employee->load(['statutoryDetails', 'primaryBank', 'documents']);

        // Suggest a date_of_leaving from the leave tracker when none is set on the employee.
        //
        // Rules:
        //  1. Only look at leave dates on or before today (future leaves are not exits).
        //  2. Take the latest such date — if the employee's last tracker entry was in the
        //     past with no entries since, they may have left.
        //  3. If date_of_joining is AFTER that latest date the employee has rejoined —
        //     do not suggest (the old absence is irrelevant to current employment).
        //
        // Two-step lookup:
        //  a) Try the FK (employee_id) first — matches properly resolved imports.
        //  b) Fall back to sheet_emp_id text match — handles un-resolved/re-imported rows.
        $suggestedLeavingDate = null;
        if (!$employee->date_of_leaving) {
            $today = now()->toDateString();

            $lastTrackerDate = LeaveTrackerRecord::where('employee_id', $employee->id)
                ->where('leave_date', '<=', $today)
                ->orderByDesc('leave_date')
                ->value('leave_date');

            // Fallback: look up by the employee_id string stored on the sheet row
            if (!$lastTrackerDate && $employee->employee_id) {
                $lastTrackerDate = LeaveTrackerRecord::where('sheet_emp_id', (string) $employee->employee_id)
                    ->where('leave_date', '<=', $today)
                    ->orderByDesc('leave_date')
                    ->value('leave_date');
            }

            if ($lastTrackerDate) {
                $lastTrackerCarbon = \Carbon\Carbon::parse($lastTrackerDate);
                $doj               = $employee->date_of_joining;
                // Don't suggest if the employee rejoined after their last tracked leave
                if (!$doj || $doj->lte($lastTrackerCarbon)) {
                    $suggestedLeavingDate = $lastTrackerCarbon->format('Y-m-d');
                }
            }
        }

        return response()->json([
            'id'                      => $employee->id,
            'first_name'              => $employee->first_name,
            'middle_name'             => $employee->middle_name,
            'last_name'               => $employee->last_name,
            'gender'                  => $employee->gender,
            'date_of_birth'           => $employee->date_of_birth ? \Carbon\Carbon::parse($employee->date_of_birth)->format('Y-m-d') : null,
            'marital_status'          => $employee->marital_status,
            'official_email'          => $employee->official_email,
            'personal_email'          => $employee->personal_email,
            'mobile'                  => $employee->mobile,
            'alternate_phone'         => $employee->alternate_phone,
            'employee_status'         => $employee->employee_status,
            'department_id'           => $employee->department_id,
            'designation_id'          => $employee->designation_id,
            'branch_id'               => $employee->branch_id,
            'location_id'             => $employee->location_id,
            'reporting_manager_id'    => $employee->reporting_manager_id,
            'work_type'               => $employee->work_type,
            'client_name'             => $employee->client_name,
            'employment_type'         => $employee->employment_type,
            'date_of_joining'         => $employee->date_of_joining?->format('Y-m-d'),
            'date_of_leaving'         => $employee->date_of_leaving?->format('Y-m-d'),
            'suggested_leaving_date'  => $suggestedLeavingDate,
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
            // A bulk-imported OneDrive link with no file downloaded yet has
            // no file_path — surface it as pending rather than a broken
            // download link (see EmployeeImporter's *Document Link columns).
            'documents'               => $employee->documents
                ->whereIn('document_type', array_keys(EmployeeDocument::UPLOAD_TYPES))
                ->mapWithKeys(fn($doc) => [$doc->document_type => $doc->file_path ? [
                    'file_name'     => $doc->file_name,
                    'download_url'  => route('employees.documents.download', $doc),
                ] : [
                    'pending_url'   => $doc->source_url,
                ]]),
        ]);
    }

    public function update(Request $request, Employee $employee)
    {
        $this->authorize('employee_edit');

        $validated = $request->validate([
            'first_name'              => 'required|string|max:100',
            'middle_name'             => 'nullable|string|max:100',
            'last_name'               => 'required|string|max:100',
            'gender'                  => 'nullable|in:Male,Female,Other',
            'date_of_birth'           => 'nullable|date|before:today',
            'marital_status'          => 'nullable|string|max:20',
            'official_email'          => 'nullable|email',
            'personal_email'          => 'nullable|email',
            'mobile'                  => 'nullable|string|max:15',
            'alternate_phone'         => 'nullable|regex:/^\d{10}$/',
            'employee_status'         => 'required|in:active,inactive,on_notice,terminated',
            'department_id'           => 'nullable|exists:departments,id',
            'designation_id'          => 'nullable|exists:designations,id',
            'branch_id'               => 'nullable|exists:branches,id',
            'location_id'             => 'nullable|exists:locations,id',
            'reporting_manager_id'    => 'nullable|exists:employees,id',
            'work_type'               => 'nullable|in:wfo,wfh,hybrid,client_location',
            'client_name'             => 'nullable|required_if:work_type,client_location|string|max:150',
            'employment_type'         => 'nullable|in:full_time,part_time,contract,intern,consultant,retainer',
            'date_of_joining'         => 'nullable|date',
            'date_of_leaving'         => 'nullable|date',
            'is_pf_applicable'        => 'boolean',
            'is_esic_applicable'      => 'boolean',
            'is_pt_applicable'        => 'boolean',
            'is_tds_applicable'       => 'boolean',
            'tds_regime'              => 'nullable|in:old,new',
            'pan_number'              => ['nullable', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/'],
            'aadhaar_number'          => 'nullable|regex:/^\d{12}$/',
            'uan_number'              => 'nullable|required_if:is_pf_applicable,1|string|max:12',
            'bank_name'               => 'nullable|string|max:100',
            'account_number'          => 'nullable|string|max:30',
            'ifsc_code'               => ['nullable', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
            'account_holder_name'     => 'nullable|string|max:200',
            'emergency_contact_name'  => 'nullable|string|max:100',
            'emergency_contact_phone' => 'nullable|regex:/^\d{10}$/',
            'documents'               => 'nullable|array',
            'documents.*'             => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ], [
            'uan_number.required_if'  => 'UAN Number is required when PF Applicable is checked.',
            'client_name.required_if' => 'Client Name is required when Work Type is Client Location.',
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

        $this->employeeDocumentService->storeUploads($employee, $request->file('documents', []), auth()->id());

        return redirect()->route('employees')->with('success', 'Employee updated successfully.');
    }

    /**
     * Marks an employee as having left and cuts off their portal access
     * immediately — replaces the old bulk-import "Left Date" column, which
     * risked accidentally deactivating people via routine re-imports.
     * Reachable by HR (employee_edit) or that employee's own reporting
     * manager — not gated by a single blanket permission, since a manager
     * should only ever be able to do this for their own direct reports.
     */
    public function markAsLeft(Request $request, Employee $employee)
    {
        $user = auth()->user();
        $isHr = $user->can('employee_edit');
        $isTheirManager = $user->employee && $employee->reporting_manager_id === $user->employee->id;
        abort_unless($isHr || $isTheirManager, 403, 'You can only mark your own direct reports as left.');

        $validated = $request->validate([
            'date_of_leaving' => 'required|date',
        ]);

        $employee->update([
            'date_of_leaving' => $validated['date_of_leaving'],
            'employee_status' => 'inactive',
        ]);

        // Cut off portal access immediately, regardless of the date entered —
        // don't wait for the nightly sweep or their next login attempt.
        $employee->user?->update(['is_active' => false]);

        return redirect()->back()->with('success', $employee->full_name . ' marked as left. Portal access disabled.');
    }

    /**
     * Serves an employee's uploaded document — HR/Admin can view any
     * employee's, the employee themselves can only view their own.
     */
    public function downloadDocument(EmployeeDocument $document)
    {
        $isOwnDocument = auth()->user()->employee?->id === $document->employee_id;
        abort_unless($isOwnDocument || auth()->user()->can('employee_view'), 403);

        abort_unless($this->publicFileStore->exists($document->file_path), 404);

        return response()->download($this->publicFileStore->absolutePath($document->file_path), $document->file_name);
    }

    public function export(Request $request)
    {
        $this->authorize('employee_export');

        $query = Employee::with([
            'entity', 'branch', 'department', 'designation', 'grade', 'costCenter',
            'reportingManager', 'teamLeader',
            'statutoryDetails', 'primaryBank',
        ]);

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(fn($sq) => $sq
                ->where('first_name',  'like', "%{$q}%")
                ->orWhere('last_name', 'like', "%{$q}%")
                ->orWhere('employee_id', 'like', "%{$q}%")
                ->orWhere('official_email', 'like', "%{$q}%")
            );
        }
        if ($request->filled('department_id')) $query->where('department_id', $request->department_id);
        if ($request->filled('status'))        $query->where('employee_status', $request->status);

        $employees = $query->orderBy('employee_id')->get();

        // Also eager-load documents — PAN/Aadhaar for the import-template file-path
        // columns below, plus every upload type for the "Documents Uploaded" summary column.
        $employees->load(['documents' => fn($q) => $q->whereIn('document_type', array_keys(EmployeeDocument::UPLOAD_TYPES))]);

        // holiday_calendar_id has no defined relation on Employee — resolve via a single keyed lookup instead.
        $holidayCalendarNames = HolidayCalendar::whereIn('id', $employees->pluck('holiday_calendar_id')->filter())
            ->pluck('name', 'id');

        // The first block of headers/columns (through 'Left Type') matches the
        // import template exactly, unchanged, so the exported file can still be
        // re-imported — EmployeeImporter maps columns by this exact header text,
        // not by position, so don't rename or reorder any of these.
        $headers = [
            'New Emp ID', 'Count', 'Employee Name', 'CurrentStatus', 'Left Date',
            'Reporting Manager', 'Team Leader', 'Attendance', 'Office', 'Mobile Number',
            'PAN', 'Marital Status', 'Gender', 'Birth Date', 'Join Date',
            'Department', 'Designation Final', 'Location (City)',
            'PF Number (if applicable)', 'UAN No (if applicable)', 'ESI Number (if applicable)',
            'Bank Name', 'Account Number', 'IFSC Code', 'Email address',
            'Please attach a copy of your PAN card (size limit 10 MB)',
            'Please attach a copy of your AADHAAR card (size limit 10 MB)',
            'PF', 'RSC Mail ID', 'Firm', 'PF Applicability', 'UAN Number',
            'Offer Letter Date', 'Left Type',
            // ── Everything else on the Employee master, appended (not part of
            // the re-import template — new columns only, existing ones above
            // are untouched) so the export covers every field, not just the
            // import-template subset. Includes an unambiguously-labeled
            // "Official Email" / "Personal Email" pair alongside the
            // existing "Email address" / "RSC Mail ID" columns above, which
            // hold the same data under the import template's naming.
            'Title', 'External Employee ID', 'Badge Number', 'Badge ID',
            'Grade', 'Cost Center', 'Holiday Calendar',
            'Official Email', 'Personal Email', 'Alternate Phone',
            'Blood Group', 'Number of Dependents', 'Nationality', 'Religion', 'Caste Category',
            'Physically Handicapped',
            'Current Address', 'Current State', 'Current Pincode',
            'Permanent Address', 'Permanent City', 'Permanent State', 'Permanent Pincode',
            'Date of Confirmation', 'Probation Period (months)', 'Probation End Date',
            'Employment Type', 'Work Type',
            'Payroll Active', 'ESIC Applicable', 'PT Applicable', 'LWF Applicable', 'TDS Applicable', 'TDS Regime',
            'PT Number', 'NPS PRAN', 'Bank MICR Code', 'Bank Account Holder Name',
            'Emergency Contact Name', 'Emergency Contact Phone', 'Emergency Contact Relation',
            'Pending Reporting Manager Ref', 'Pending Team Leader Ref',
            'Documents Uploaded',
        ];

        $rows = $employees->values()->map(function ($e, $i) {
            // Several columns are stored encrypted (see Employee::$casts). A row
            // whose ciphertext predates encryption being enabled, or was
            // encrypted under a different APP_KEY, throws DecryptException on
            // access — which would otherwise crash the whole export over one
            // bad row instead of just leaving that cell blank.
            $mobile             = $this->safeDecrypted($e, 'mobile');
            $dateOfBirth        = $this->safeDecrypted($e, 'date_of_birth');
            $personalEmail      = $this->safeDecrypted($e, 'personal_email');
            $alternatePhone     = $this->safeDecrypted($e, 'alternate_phone');
            $currentAddress     = $this->safeDecrypted($e, 'current_address');
            $permanentAddress   = $this->safeDecrypted($e, 'permanent_address');
            $emergencyContactName  = $this->safeDecrypted($e, 'emergency_contact_name');
            $emergencyContactPhone = $this->safeDecrypted($e, 'emergency_contact_phone');

            $uploadedDocLabels = $e->documents
                ->pluck('document_type')
                ->unique()
                ->map(fn($type) => EmployeeDocument::UPLOAD_TYPES[$type] ?? $type)
                ->implode(', ');

            return [
                $e->employee_id,
                $i + 1,
                trim(implode(' ', array_filter([$e->first_name, $e->middle_name, $e->last_name]))),
                $e->employee_status,
                $e->date_of_leaving,        // Carbon → Excel date (null = empty cell)
                $e->reportingManager?->full_name,
                $e->teamLeader?->full_name,
                $e->attendance_group,
                $e->branch?->name,
                $mobile,
                $e->statutoryDetails?->pan_number,
                $e->marital_status,
                $e->gender,
                $dateOfBirth ? \Carbon\Carbon::parse($dateOfBirth) : null,   // Carbon → Excel date via streamXlsx
                $e->date_of_joining,        // Carbon → Excel date via streamXlsx
                $e->department?->name,
                $e->designation?->name,
                $e->current_city,
                $e->statutoryDetails?->pf_number,
                $e->statutoryDetails?->uan_number,
                $e->statutoryDetails?->esic_number,
                $e->primaryBank?->bank_name,
                $e->primaryBank?->account_number,
                $e->primaryBank?->ifsc_code,
                $e->official_email,
                $e->documents->firstWhere('document_type', 'pan')?->file_path,
                $e->documents->firstWhere('document_type', 'aadhaar')?->file_path ?? $e->aadhaar_document_path,
                null,   // PF (old/legacy column)
                $personalEmail,
                $e->entity?->name,
                $e->is_pf_applicable ? 'Yes' : 'No',
                $e->statutoryDetails?->uan_number,
                $e->offer_letter_date,      // Carbon → Excel date via streamXlsx
                null,   // Left Type — not stored

                // ── Appended columns ──
                $e->title,
                $e->external_emp_id,
                $e->badge_number,
                $e->badge_id,
                $e->grade?->name,
                $e->costCenter?->name,
                $e->holiday_calendar_id ? ($holidayCalendarNames[$e->holiday_calendar_id] ?? null) : null,
                $e->official_email,
                $personalEmail,
                $alternatePhone,
                $e->blood_group, // Form field removed, but existing data (if any) is still exported
                $e->number_of_dependents,
                $e->nationality,
                $e->religion,
                $e->caste_category,
                $e->is_physically_handicapped ? 'Yes' : 'No',
                $currentAddress,
                $e->current_state,
                $e->current_pincode,
                $permanentAddress,
                $e->permanent_city,
                $e->permanent_state,
                $e->permanent_pincode,
                $e->date_of_confirmation,   // Carbon → Excel date via streamXlsx
                $e->probation_period_months,
                $e->probation_end_date,     // Carbon → Excel date via streamXlsx
                $e->employment_type,
                $e->work_type,
                $e->is_payroll_active ? 'Yes' : 'No',
                $e->is_esic_applicable ? 'Yes' : 'No',
                $e->is_pt_applicable ? 'Yes' : 'No',
                $e->is_lwf_applicable ? 'Yes' : 'No',
                $e->is_tds_applicable ? 'Yes' : 'No',
                $e->tds_regime,
                $e->statutoryDetails?->pt_number,
                $e->statutoryDetails?->nps_pran,
                $e->primaryBank?->micr_code,
                $e->primaryBank?->account_holder_name,
                $emergencyContactName,
                $emergencyContactPhone,
                $e->emergency_contact_relation,
                $e->pending_reporting_manager_ref,
                $e->pending_team_leader_ref,
                $uploadedDocLabels ?: 'None',
            ];
        });

        return $this->streamXlsx('employees', $headers, $rows);
    }

    /**
     * Reads an encrypted-cast attribute, returning null instead of throwing
     * when the stored ciphertext can't be decrypted (pre-encryption legacy
     * data, or a row encrypted under a different APP_KEY) — used by export()
     * so one bad row doesn't 500 the whole download.
     */
    protected function safeDecrypted(Employee $employee, string $attribute)
    {
        try {
            return $employee->getAttribute($attribute);
        } catch (\Illuminate\Contracts\Encryption\DecryptException) {
            return null;
        }
    }

    public function masterHistory(\Illuminate\Http\Request $request)
    {
        $this->authorize('employee_view');

        $search     = trim((string) $request->get('search', ''));
        $fieldType  = $request->get('field_type', ''); // bank_details | pan | email
        $changedVia = $request->get('changed_via', '');

        $history = \App\Models\EmployeeMasterHistory::with([
                'employee:id,employee_id,first_name,middle_name,last_name',
                'changedBy:id,name',
            ])
            ->when($fieldType !== '', fn($q) => $q->where('field_type', $fieldType))
            ->when($changedVia !== '', fn($q) => $q->where('changed_via', $changedVia))
            ->when($search !== '', fn($q) => $q->whereHas('employee', fn($eq) =>
                $eq->where('employee_id', 'like', "%{$search}%")
                   ->orWhere('first_name',  'like', "%{$search}%")
                   ->orWhere('last_name',   'like', "%{$search}%")
            ))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('employees.master-history', compact('history', 'search', 'fieldType', 'changedVia'));
    }

    public function destroy(Employee $employee)
    {
        $this->authorize('employee_delete');

        $employee->delete(); // soft delete
        return redirect()->route('employees')->with('success', 'Employee deleted.');
    }

    /**
     * Bulk action: archive (soft-delete) or permanently delete selected employees.
     *
     * Accepts either:
     *   ids[]      — explicit employee primary keys (current-page selection)
     *   select_all = 1  — resolve all employees matching the same filter params
     *
     * action = 'archive'  → soft-delete employees (data stays in DB, hidden everywhere)
     * action = 'delete'   → hard-delete employees and ALL related records (irreversible)
     */
    public function bulkDestroy(Request $request)
    {
        $this->authorize('employee_delete');

        $action = $request->input('action', 'archive'); // 'archive' | 'delete'

        // ── Resolve employee IDs ─────────────────────────────────────────────────
        if ($request->boolean('select_all')) {
            // "Select all pages" — re-run the same filter query to get all matching IDs
            $q = Employee::withTrashed();
            if ($request->filled('search')) {
                $s = $request->input('search');
                $q->where(fn($sq) => $sq
                    ->where('first_name',  'like', "%{$s}%")
                    ->orWhere('last_name', 'like', "%{$s}%")
                    ->orWhere('employee_id', 'like', "%{$s}%")
                    ->orWhere('official_email', 'like', "%{$s}%")
                );
            }
            if ($request->filled('department_id')) {
                $q->where('department_id', $request->input('department_id'));
            }
            if ($request->filled('status')) {
                $q->where('employee_status', $request->input('status'));
            }
            $ids = $q->pluck('id')->map('intval')->all();
        } else {
            $ids = array_filter(
                array_map('intval', (array) $request->input('ids', [])),
                fn($id) => $id > 0
            );
        }

        if (empty($ids)) {
            return redirect()->route('employees')->with('error', 'No employees selected.');
        }

        $count = count($ids);

        if ($action === 'archive') {
            // ── Soft-delete — marks deleted_at on the employees row ──────────────
            // Eloquent SoftDeletes automatically excludes these from all model
            // queries so the employees and their data are hidden everywhere.
            Employee::whereIn('id', $ids)->delete();

            return redirect()->route('employees')
                ->with('success', "{$count} employee" . ($count === 1 ? '' : 's') . " archived. Data is preserved and can be restored if needed.");
        }

        // ── Permanent hard-delete ────────────────────────────────────────────────
        \Illuminate\Support\Facades\DB::transaction(function () use ($ids) {
            // Loan installments (child of employee_loans — no cascade)
            $loanIds = \DB::table('employee_loans')->whereIn('employee_id', $ids)->pluck('id')->all();
            if ($loanIds) {
                \DB::table('loan_installments')->whereIn('employee_loan_id', $loanIds)->delete();
            }

            // Reimbursement items (child of reimbursement_claims — no cascade)
            $claimIds = \DB::table('reimbursement_claims')->whereIn('employee_id', $ids)->pluck('id')->all();
            if ($claimIds) {
                \DB::table('reimbursement_items')->whereIn('claim_id', $claimIds)->delete();
            }

            // All other related tables (no cascade)
            foreach ([
                'employee_bank_details', 'employee_statutory_details', 'employee_documents',
                'employee_history', 'employee_onboarding', 'exit_requests',
                'employee_education', 'employee_experience',
                'employee_shifts', 'employee_rosters',
                'attendance', 'attendance_raw_punches', 'attendance_regularizations', 'overtime_requests',
                'leave_balances', 'leave_applications', 'comp_offs', 'leave_tracker_records',
                'employee_loans', 'reimbursement_claims',
                'employee_salaries', 'salary_revisions', 'salary_report_snapshots', 'salary_shadow_log',
                'payroll_arrears', 'investment_declarations', 'employee_tds_computation',
                'full_final_settlements', 'gratuity_provisions', 'employee_bonus', 'nps_contributions',
            ] as $table) {
                \DB::table($table)->whereIn('employee_id', $ids)->delete();
            }

            // Null self-referencing FKs to avoid constraint errors before deleting
            \DB::table('employees')->whereIn('reporting_manager_id', $ids)->update(['reporting_manager_id' => null]);
            \DB::table('employees')->whereIn('team_leader_id', $ids)->update(['team_leader_id' => null]);

            // Force-delete including any already soft-deleted records
            Employee::withTrashed()->whereIn('id', $ids)->forceDelete();
        });

        return redirect()->route('employees')
            ->with('success', "{$count} employee" . ($count === 1 ? '' : 's') . " permanently deleted. This cannot be undone.");
    }
}
