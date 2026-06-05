<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\ImportsExcel;
use App\Http\Controllers\Controller;
use App\Http\Requests\HR\StoreEmployeeRequest;
use App\Imports\EmployeeImporter;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Entity;
use App\Models\LeaveTrackerRecord;
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
        $departments  = Department::orderBy('name')->get();
        $designations = Designation::orderBy('name')->get();
        $branches     = Branch::where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get();
        $managers     = Employee::query()
                            ->where('employee_status', 'active')
                            ->orderBy('first_name')
                            ->get(['id', 'first_name', 'middle_name', 'last_name', 'employee_id']);

        // Suggest next employee_id per company
        $suggestedEmpIdByCompany = [];
        foreach ($companies as $co) {
            $lastId = Employee::where('company_id', $co->id)
                ->whereNotNull('employee_id')
                ->orderByDesc('id')
                ->value('employee_id');
            $suggested = '';
            if ($lastId && preg_match('/^\d{4}(\d+)$/', $lastId, $m)) {
                $suggested = now()->year . ((int)$m[1] + 1);
            } elseif ($lastId && preg_match('/(\d+)$/', $lastId, $m)) {
                $suggested = now()->year . ((int)$m[1] + 1);
            }
            $suggestedEmpIdByCompany[$co->id] = $suggested;
        }
        $suggestedEmpId = $suggestedEmpIdByCompany[$companyId] ?? '';

        return view('employees.index', compact(
            'companies', 'employees', 'departments', 'designations',
            'branches', 'managers', 'companyId', 'suggestedEmpId', 'suggestedEmpIdByCompany'
        ));
    }

    public function store(StoreEmployeeRequest $request)
    {
        $data = $request->validated();

        // company_id is already set from the form — pass it straight through

        $this->employeeService->create($data);
        return redirect()->route('employees')->with('success', 'Employee created successfully.');
    }

    public function template()
    {
        // Build dropdown lists from live DB data for the template
        $dropdowns = [
            'Firm'              => Company::where('is_active', true)->orderBy('name')->pluck('name')->all(),
            'Department'        => Department::orderBy('name')->distinct()->pluck('name')->all(),
            'Designation Final' => Designation::orderBy('name')->distinct()->pluck('name')->all(),
        ];

        return $this->excelImportService->generateTemplate(EmployeeImporter::class, $dropdowns);
    }

    public function import(Request $request)
    {
        // dd($request->all());
        $companyId = $this->resolveCompanyId($request);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        $result = $this->excelImportService->import(
            $request->file('file'),
            'employee',
            EmployeeImporter::class,
            ['company_id' => $companyId]
        );

        return $this->handleImportResult($result, 'employees', 'employee');
    }

    /** Return employee data as JSON for the Edit modal (AJAX). */
    public function editData(Employee $employee)
    {
        $employee->load(['statutoryDetails', 'primaryBank']);

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
            'date_of_joining'         => 'nullable|date',
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

    public function export(Request $request)
    {
        $query = Employee::with([
            'entity', 'branch', 'department', 'designation',
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

        // Also eager-load documents for PAN/Aadhaar doc columns
        $employees->load(['documents' => fn($q) => $q->whereIn('document_type', ['pan', 'aadhaar'])]);

        // Headers match the import template exactly so the exported file can be re-imported
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
        ];

        $rows = $employees->values()->map(fn($e, $i) => [
            $e->employee_id,
            $i + 1,
            trim(implode(' ', array_filter([$e->first_name, $e->middle_name, $e->last_name]))),
            $e->employee_status,
            $e->date_of_leaving,        // Carbon → Excel date (null = empty cell)
            $e->reportingManager?->full_name,
            $e->teamLeader?->full_name,
            $e->attendance_group,
            $e->branch?->name,
            $e->mobile,
            $e->statutoryDetails?->pan_number,
            $e->marital_status,
            $e->gender,
            $e->date_of_birth,          // Carbon → Excel date via streamXlsx
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
            $e->personal_email,
            $e->entity?->name,
            $e->is_pf_applicable ? 'Yes' : 'No',
            $e->statutoryDetails?->uan_number,
            $e->offer_letter_date,      // Carbon → Excel date via streamXlsx
            null,   // Left Type — not stored
        ]);

        return $this->streamXlsx('employees', $headers, $rows);
    }

    public function masterHistory(\Illuminate\Http\Request $request)
    {
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
