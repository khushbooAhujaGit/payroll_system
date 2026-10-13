@extends('layouts.app')
@section('title', 'Employees')

@section('page_actions')
  <a href="{{ route('employees.master_history') }}" class="btn">📋 Master History</a>
  <button class="btn acc" onclick="om('emp-modal')">+ Add Employee</button>
  <button class="btn" onclick="om('upload-modal')">📤 Bulk Upload</button>
  <button class="btn" onclick="om('bioid-upload-modal')">🔖 Upload Bio IDs</button>
  <a href="{{ route('employees.export') . '?' . http_build_query(request()->only('search','department_id','status')) }}" class="btn">⬇ Export</a>
@endsection
 
@section('content')

  {{-- Filter bar --}}
  <form method="GET" action="{{ route('employees') }}" class="card" data-ajax-filter
    style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
    <div class="fld" style="flex:1;min-width:160px">
      <label>Search</label>
      <input type="text" name="search" value="{{ request('search') }}" placeholder="Name, ID, email…">
    </div>
    <div class="fld" style="min-width:160px">
      <label>Department</label>
      <select name="department_id">
        <option value="">All Departments</option>
        @foreach($departments as $dept)
          <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="fld" style="min-width:130px">
      <label>Status</label>
      <select name="status">
        <option value="">All</option>
        <option value="active"     {{ request('status') === 'active'     ? 'selected' : '' }}>Active</option>
        <option value="inactive"   {{ request('status') === 'inactive'   ? 'selected' : '' }}>Inactive</option>
        <option value="on_notice"  {{ request('status') === 'on_notice'  ? 'selected' : '' }}>On Notice</option>
        <option value="absconding" {{ request('status') === 'absconding' ? 'selected' : '' }}>Absconding</option>
        <option value="suspended"  {{ request('status') === 'suspended'  ? 'selected' : '' }}>Suspended</option>
        <option value="on_leave"   {{ request('status') === 'on_leave'   ? 'selected' : '' }}>On Leave</option>
      </select>
    </div>
    <button type="submit" class="btn acc">Filter</button>
    <a href="{{ route('employees') }}" class="btn" data-ajax-reset>Reset</a>
  </form>

  <div id="ajax-results">
  {{-- Hidden bulk-action form — fields injected by JS before submit --}}
  <form id="bulk-form" method="POST" action="{{ route('employees.bulk_destroy') }}" style="display:none">
    @csrf
    <input type="hidden" name="action"     id="bulk-action"     value="archive">
    <input type="hidden" name="select_all" id="bulk-select-all" value="0">
    {{-- Current filter params forwarded for select_all resolution --}}
    <input type="hidden" name="search"        value="{{ request('search') }}">
    <input type="hidden" name="department_id" value="{{ request('department_id') }}">
    <input type="hidden" name="status"        value="{{ request('status') }}">
    <div id="bulk-id-inputs"></div>
  </form>

  {{-- ── Selection action bar (shown when ≥1 employee selected) ── --}}
  <div id="sel-bar" style="display:none;
       background:var(--s2);border:1px solid var(--b1);border-radius:8px;
       padding:12px 16px;margin-bottom:12px;
       display:none;align-items:center;gap:12px;flex-wrap:wrap">
    <span id="sel-summary" style="font-size:13px;font-weight:600;color:var(--t1)"></span>

    {{-- Cross-page "select all" banner --}}
    <span id="sel-all-banner" style="display:none;font-size:12px;color:var(--t3)">
      &nbsp;·&nbsp;
      <a href="#" onclick="selectAllPages(); return false"
         style="color:var(--acc);text-decoration:underline">
        Select all {{ $employees->total() }} employees matching this filter
      </a>
    </span>
    <span id="sel-all-active" style="display:none;font-size:12px;font-weight:600;color:var(--acc)">
      &nbsp;·&nbsp; All {{ $employees->total() }} employees selected
      <a href="#" onclick="clearAllPages(); return false"
         style="color:var(--red);margin-left:6px;text-decoration:underline">Clear</a>
    </span>

    <span style="flex:1"></span>
    <button class="btn" style="background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;font-size:12px"
      onclick="submitBulk('archive')">📦 Archive</button>
    <button class="btn" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca;font-size:12px"
      onclick="submitBulk('delete')">🗑 Permanently Delete</button>
  </div>

  <div class="card">
    @if($employees->isNotEmpty())
      <table id="emp-tbl">
        <thead>
          <tr>
            <th style="width:36px;text-align:center">
              <input type="checkbox" id="chk-all" title="Select all on this page"
                style="width:15px;height:15px;cursor:pointer" onchange="toggleAll(this)">
            </th>
            <th>Emp ID</th>
            <th>Name</th>
            <th>Company</th>
            <th>Department</th>
            <th>Designation</th>
            <th>Status</th>
            <th>Joined</th>
            <th>CL</th>
            <th>SL</th>
            <th>CO</th>
            <th>Pending</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach($employees as $emp)
            @php $leaveSummary = $leaveSummaries[$emp->id] ?? null; @endphp
            <tr>
              <td style="text-align:center">
                <input type="checkbox" class="emp-chk" value="{{ $emp->id }}"
                  style="width:15px;height:15px;cursor:pointer" onchange="updateBulkBar()">
              </td>
              <td class="mono">{{ $emp->employee_id }}</td>
              <td>{{ $emp->full_name }}</td>
              <td>{{ $emp->company->name }}</td>
              <td>{{ $emp->department?->name ?? '—' }}</td>
              <td>{{ $emp->designation?->name ?? '—' }}</td>
              <td><span class="bx {{ match($emp->employee_status) {
                'active'     => 'g',
                'on_notice'  => 'y',
                'suspended'  => 'y',
                default      => 'gr'
              } }}">{{ str_replace('_', ' ', $emp->employee_status) }}</span></td>
              <td>{{ $emp->date_of_joining?->format('d M Y') ?? '—' }}</td>
              <td class="mono">{{ $leaveSummary['cl'] ?? '—' }}</td>
              <td class="mono">{{ $leaveSummary['sl'] ?? '—' }}</td>
              <td class="mono">{{ $leaveSummary['co'] ?? '—' }}</td>
              <td class="mono">{{ $leaveSummary['pending'] ?? '—' }}</td>
              <td>
                <button class="btn sm" onclick="openEditEmp({{ $emp->id }})">Edit</button>
                @if($emp->employee_status !== 'inactive')
                <button class="btn sm" style="color:var(--yel)"
                  onclick="openMarkAsLeft({{ $emp->id }}, '{{ addslashes($emp->full_name) }}')">Mark as Left</button>
                @endif
                <form method="POST" action="{{ route('employees.destroy', $emp) }}" style="display:inline">
                  @csrf @method('DELETE')
                  <button type="button" class="btn sm" style="color:var(--red)"
                    onclick="swConfirm(this.closest('form'), 'Delete Employee?', 'This employee record will be soft-deleted.', 'warning', 'Yes, delete', '#dc2626')">Del</button>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
      <div style="margin-top:14px">{{ $employees->links() }}</div>
    @else
      <div class="empty">
        <div class="ico">👥</div>
        <p>No employees found. <a href="#" onclick="om('emp-modal')">Add one now.</a></p>
      </div>
    @endif
  </div>
  </div> {{-- #ajax-results --}}

  {{-- ════════════════════════════════════════════════════════════════
       ADD EMPLOYEE MODAL
  ════════════════════════════════════════════════════════════════ --}}
  <div class="modal" id="emp-modal">
    <div class="mb wide" style="max-height:90vh;overflow-y:auto">
      <div class="mh" style="position:sticky;top:0;z-index:10;background:var(--s1)">
        <span class="mt">Add Employee</span>
        <button class="mc" onclick="cm('emp-modal')">✕</button>
      </div>
      <form method="POST" action="{{ route('employees.store') }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="employee_status" value="active">

        {{-- ── Company / Firm ───────────────────────────── --}}
        <div style="margin:14px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
          Company / Firm
        </div>
        <div class="fg2">
          <div class="fld">
            <label class="req">Company</label>
            <select name="company_id" id="add-company" required onchange="onCompanyChange(this.value)" @error('company_id') class="inp-err" @enderror>
              <option value="">Select Company</option>
              @foreach($companies as $co)
                <option value="{{ $co->id }}" {{ old('company_id') == $co->id ? 'selected' : '' }}>{{ $co->name }}</option>
              @endforeach
            </select>
            @error('company_id') <div class="ferr">{{ $message }}</div> @enderror
          </div>
        </div>

        {{-- ── Personal ──────────────────────────────── --}}
        <div style="margin:14px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
          Personal Information
        </div>
        <div class="fg2">
          <div class="fld">
            <label class="req">First Name</label>
            <input type="text" name="first_name" required value="{{ old('first_name') }}"
              @error('first_name') class="inp-err" @enderror>
            @error('first_name') <div class="ferr">{{ $message }}</div> @enderror
          </div>
          <div class="fld">
            <label>Middle Name</label>
            <input type="text" name="middle_name" value="{{ old('middle_name') }}">
          </div>
          <div class="fld">
            <label class="req">Last Name</label>
            <input type="text" name="last_name" required value="{{ old('last_name') }}"
              @error('last_name') class="inp-err" @enderror>
            @error('last_name') <div class="ferr">{{ $message }}</div> @enderror
          </div>
          <div class="fld">
            <label class="req">Gender</label>
            <select name="gender" required @error('gender') class="inp-err" @enderror>
              <option value="Male"   {{ old('gender', 'Male') === 'Male'   ? 'selected' : '' }}>Male</option>
              <option value="Female" {{ old('gender') === 'Female'         ? 'selected' : '' }}>Female</option>
              <option value="Other"  {{ old('gender') === 'Other'          ? 'selected' : '' }}>Other</option>
            </select>
            @error('gender') <div class="ferr">{{ $message }}</div> @enderror
          </div>
          <div class="fld">
            <label>Date of Birth</label>
            <input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}">
          </div>
          <div class="fld">
            <label>Marital Status</label>
            <select name="marital_status">
              <option value="">Select</option>
              <option value="single"   {{ old('marital_status') === 'single'   ? 'selected' : '' }}>Single</option>
              <option value="married"  {{ old('marital_status') === 'married'  ? 'selected' : '' }}>Married</option>
              <option value="divorced" {{ old('marital_status') === 'divorced' ? 'selected' : '' }}>Divorced</option>
              <option value="widowed"  {{ old('marital_status') === 'widowed'  ? 'selected' : '' }}>Widowed</option>
            </select>
          </div>
          <div class="fld">
            <label>Nationality</label>
            <input type="text" name="nationality" value="{{ old('nationality', 'Indian') }}">
          </div>
        </div>

        {{-- ── Contact ───────────────────────────────── --}}
        <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
          Contact Details
        </div>
        <div class="fg2">
          <div class="fld">
            <label class="req">Official Email</label>
            <input type="email" name="official_email" required value="{{ old('official_email') }}"
              @error('official_email') class="inp-err" @enderror>
            @error('official_email') <div class="ferr">{{ $message }}</div> @enderror
          </div>
          <div class="fld">
            <label class="req">Mobile (10 digits)</label>
            <input type="text" name="mobile" required pattern="\d{10}" maxlength="10"
              value="{{ old('mobile') }}" placeholder="9876543210"
              @error('mobile') class="inp-err" @enderror>
            @error('mobile') <div class="ferr">{{ $message }}</div> @enderror
          </div>
          <div class="fld">
            <label>Personal Email</label>
            <input type="email" name="personal_email" value="{{ old('personal_email') }}">
          </div>
          <div class="fld">
            <label>Alternate Phone</label>
            <input type="text" name="alternate_phone" pattern="\d{10}" maxlength="10"
              value="{{ old('alternate_phone') }}" placeholder="10 digits">
          </div>
          <div class="fld full">
            <label>Current Address</label>
            <textarea name="current_address" rows="2" maxlength="500"
              @error('current_address') class="inp-err" @enderror>{{ old('current_address') }}</textarea>
            @error('current_address') <div class="ferr">{{ $message }}</div> @enderror
          </div>
        </div>

        {{-- ── Employment ────────────────────────────── --}}
        <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
          Employment Details
        </div>
        <div class="fg2">
          <div class="fld">
            <label class="req">Date of Joining</label>
            <input type="date" name="date_of_joining" required value="{{ old('date_of_joining') }}"
              @error('date_of_joining') class="inp-err" @enderror>
            @error('date_of_joining') <div class="ferr">{{ $message }}</div> @enderror
          </div>
          <div class="fld">
            <label class="req">Employment Type</label>
            <select name="employment_type" required @error('employment_type') class="inp-err" @enderror>
              <option value="full_time"   {{ old('employment_type', 'full_time') === 'full_time'   ? 'selected' : '' }}>Full Time</option>
              <option value="part_time"   {{ old('employment_type') === 'part_time'   ? 'selected' : '' }}>Part Time</option>
              <option value="contract"    {{ old('employment_type') === 'contract'    ? 'selected' : '' }}>Contract</option>
              <option value="intern"      {{ old('employment_type') === 'intern'      ? 'selected' : '' }}>Intern</option>
              <option value="consultant"  {{ old('employment_type') === 'consultant'  ? 'selected' : '' }}>Consultant</option>
              <option value="retainer"    {{ old('employment_type') === 'retainer'    ? 'selected' : '' }}>Retainer</option>
            </select>
          </div>
          <div class="fld">
            <label class="req">Work Type</label>
            <select name="work_type" id="add-work-type" required onchange="toggleClientName('add', this.value); toggleBranchLocation('add', this.value)"
              @error('work_type') class="inp-err" @enderror>
              <option value="wfo"            {{ old('work_type', 'wfo') === 'wfo'            ? 'selected' : '' }}>Work from Office</option>
              <option value="wfh"            {{ old('work_type') === 'wfh'            ? 'selected' : '' }}>Work from Home</option>
              <option value="hybrid"         {{ old('work_type') === 'hybrid'         ? 'selected' : '' }}>Hybrid</option>
              <option value="client_location"{{ old('work_type') === 'client_location'? 'selected' : '' }}>Client Location</option>
            </select>
            @error('work_type') <div class="ferr">{{ $message }}</div> @enderror
          </div>
          <div class="fld" id="add-client-name-wrap" style="display:{{ old('work_type') === 'client_location' ? 'flex' : 'none' }}">
            <label class="req">Client Name</label>
            <input type="text" name="client_name" value="{{ old('client_name') }}"
              @error('client_name') class="inp-err" @enderror>
            @error('client_name') <div class="ferr">{{ $message }}</div> @enderror
          </div>
          <div class="fld">
            <label class="req">Department</label>
            <select name="department_id" id="add-department" data-old-value="{{ old('department_id') }}" required @error('department_id') class="inp-err" @enderror>
              <option value="">Select Department</option>
              @foreach($departments as $dept)
                <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
              @endforeach
            </select>
            @error('department_id') <div class="ferr">{{ $message }}</div> @enderror
          </div>
          <div class="fld">
            <label class="req">Designation</label>
            <select name="designation_id" id="add-designation" data-old-value="{{ old('designation_id') }}" required @error('designation_id') class="inp-err" @enderror>
              <option value="">Select Designation</option>
              @foreach($designations as $d)
                <option value="{{ $d->id }}" {{ old('designation_id') == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
              @endforeach
            </select>
            @error('designation_id') <div class="ferr">{{ $message }}</div> @enderror
          </div>
          <div class="fld" id="add-branch-wrap" style="display:{{ old('work_type', 'wfo') !== 'wfh' ? 'flex' : 'none' }}">
            <label class="req" id="add-branch-label">Branch</label>
            <select name="branch_id" id="add-branch" data-old-value="{{ old('branch_id') }}" @error('branch_id') class="inp-err" @enderror>
              <option value="">Select Branch</option>
              @foreach($branches as $b)
                <option value="{{ $b->id }}" {{ old('branch_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
              @endforeach
            </select>
            @error('branch_id') <div class="ferr">{{ $message }}</div> @enderror
          </div>
          <div class="fld" id="add-location-wrap" style="display:{{ in_array(old('work_type'), ['wfh', 'hybrid', 'client_location'], true) ? 'flex' : 'none' }}">
            <label class="req" id="add-location-label">Location</label>
            <select name="location_id" id="add-location" data-old-value="{{ old('location_id') }}" @error('location_id') class="inp-err" @enderror>
              <option value="">Select Location</option>
              @foreach($locations as $l)
                <option value="{{ $l->id }}" {{ old('location_id') == $l->id ? 'selected' : '' }}>{{ $l->name }}</option>
              @endforeach
            </select>
            @error('location_id') <div class="ferr">{{ $message }}</div> @enderror
          </div>
          <div class="fld">
            <label class="req">Reporting Manager</label>
            <select name="reporting_manager_id" id="add-manager" required @error('reporting_manager_id') class="inp-err" @enderror>
              <option value="">Select Manager</option>
              @foreach($currentManagers as $mgr)
                <option value="{{ $mgr->id }}" {{ old('reporting_manager_id') == $mgr->id ? 'selected' : '' }}>
                  {{ $mgr->employee_id }} — {{ $mgr->full_name }}
                </option>
              @endforeach
            </select>
            @error('reporting_manager_id') <div class="ferr">{{ $message }}</div> @enderror
            <label style="display:flex;align-items:center;gap:6px;font-size:11px;color:var(--t3);font-weight:400;margin-top:5px;cursor:pointer">
              <input type="checkbox" id="add-manager-show-all" style="width:14px;height:14px"
                onchange="toggleAllManagers('add', this.checked)"
                {{ old('reporting_manager_id') && !in_array(old('reporting_manager_id'), $currentManagerIds->all()) ? 'checked' : '' }}>
              Not listed? Show all employees
            </label>
          </div>
          <div class="fld">
            <label class="req">Probation Period (months)</label>
            <input type="number" name="probation_period_months" min="0" max="24" required
              value="{{ old('probation_period_months', 6) }}" placeholder="6"
              @error('probation_period_months') class="inp-err" @enderror>
            @error('probation_period_months') <div class="ferr">{{ $message }}</div> @enderror
          </div>
          <div class="fld">
            <label>Employee ID <span style="font-size:10px;color:var(--t3);font-weight:400">(auto-generated if blank)</span></label>
            <input type="text" name="employee_id" value="{{ old('employee_id', $suggestedEmpId) }}"
              placeholder="{{ $suggestedEmpId ?: 'e.g. 202618053' }}" maxlength="20">
          </div>
        </div>

        {{-- ── Payroll Flags ─────────────────────────── --}}
        <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
          Payroll Applicability
        </div>
        <div class="fg2">
          <div class="fld">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
              <input type="checkbox" name="is_pf_applicable" id="add-pf-applicable" value="1"
                {{ old('is_pf_applicable', '1') ? 'checked' : '' }} style="width:16px;height:16px"
                onchange="toggleUanRequired('add', this.checked)">
              PF Applicable
            </label>
          </div>
        </div>
        {{-- PT, ESIC, TDS flags and TDS Regime are set via Edit modal after creation --}}

        {{-- ── Statutory ─────────────────────────────── --}}
        <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
          Statutory Details
        </div>
        <div class="fg2">
          <div class="fld">
            <label>PAN Number</label>
            <input type="text" name="pan_number" value="{{ old('pan_number') }}"
              placeholder="ABCDE1234F" maxlength="10" style="text-transform:uppercase"
              oninput="this.value=this.value.toUpperCase()"
              @error('pan_number') class="inp-err" @enderror>
            @error('pan_number') <div class="ferr">{{ $message }}</div> @enderror
          </div>
          <div class="fld">
            <label>Aadhaar Number</label>
            <input type="text" name="aadhaar_number" value="{{ old('aadhaar_number') }}"
              placeholder="12-digit number" maxlength="12" pattern="\d{12}"
              @error('aadhaar_number') class="inp-err" @enderror>
            @error('aadhaar_number') <div class="ferr">{{ $message }}</div> @enderror
          </div>
          <div class="fld">
            <label id="add-uan-label" class="{{ old('is_pf_applicable', '1') ? 'req' : '' }}">UAN Number</label>
            <input type="text" name="uan_number" id="add-uan" value="{{ old('uan_number') }}"
              placeholder="12-digit UAN" maxlength="12" {{ old('is_pf_applicable', '1') ? 'required' : '' }}
              @error('uan_number') class="inp-err" @enderror>
            @error('uan_number') <div class="ferr">{{ $message }}</div> @enderror
          </div>
        </div>

        {{-- ── Bank Details ──────────────────────────── --}}
        <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
          Bank Details
        </div>
        <div class="fg2">
          <div class="fld">
            <label>Bank Name</label>
            <input type="text" name="bank_name" value="{{ old('bank_name') }}"
              placeholder="e.g. HDFC Bank"
              @error('bank_name') class="inp-err" @enderror>
            @error('bank_name') <div class="ferr">{{ $message }}</div> @enderror
          </div>
          <div class="fld">
            <label>Account Number</label>
            <input type="text" name="account_number" value="{{ old('account_number') }}"
              @error('account_number') class="inp-err" @enderror>
            @error('account_number') <div class="ferr">{{ $message }}</div> @enderror
          </div>
          <div class="fld">
            <label>IFSC Code</label>
            <input type="text" name="ifsc_code" value="{{ old('ifsc_code') }}"
              placeholder="HDFC0001234" maxlength="11" style="text-transform:uppercase"
              oninput="this.value=this.value.toUpperCase()"
              @error('ifsc_code') class="inp-err" @enderror>
            @error('ifsc_code') <div class="ferr">{{ $message }}</div> @enderror
          </div>
          <div class="fld">
            <label>Account Holder Name</label>
            <input type="text" name="account_holder_name" value="{{ old('account_holder_name') }}"
              placeholder="As per bank records"
              @error('account_holder_name') class="inp-err" @enderror>
            @error('account_holder_name') <div class="ferr">{{ $message }}</div> @enderror
          </div>
        </div>

        {{-- ── Emergency Contact ─────────────────────── --}}
        <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
          Emergency Contact
        </div>
        <div class="fg2">
          <div class="fld">
            <label class="req">Contact Name</label>
            <input type="text" name="emergency_contact_name" required value="{{ old('emergency_contact_name') }}"
              @error('emergency_contact_name') class="inp-err" @enderror>
            @error('emergency_contact_name') <div class="ferr">{{ $message }}</div> @enderror
          </div>
          <div class="fld">
            <label class="req">Contact Phone (10 digits)</label>
            <input type="text" name="emergency_contact_phone" required value="{{ old('emergency_contact_phone') }}"
              pattern="\d{10}" maxlength="10" placeholder="9876543210"
              @error('emergency_contact_phone') class="inp-err" @enderror>
            @error('emergency_contact_phone') <div class="ferr">{{ $message }}</div> @enderror
          </div>
        </div>

        {{-- ── Documents ──────────────────────────────── --}}
        <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
          Documents
        </div>
        <div class="fg2">
          @php $requiredDocTypes = ['pan', 'aadhaar', 'marksheet', 'cancelled_cheque']; @endphp
          @foreach (\App\Models\EmployeeDocument::UPLOAD_TYPES as $docType => $docLabel)
          <div class="fld">
            <label class="{{ in_array($docType, $requiredDocTypes) ? 'req' : '' }}">{{ $docLabel }}</label>
            <input type="file" name="documents[{{ $docType }}]" accept=".pdf,.jpg,.jpeg,.png"
              {{ in_array($docType, $requiredDocTypes) ? 'required' : '' }}
              @error("documents.$docType") class="inp-err" @enderror>
            @error("documents.$docType") <div class="ferr">{{ $message }}</div> @enderror
          </div>
          @endforeach
        </div>

        <div class="mf" style="position:sticky;bottom:0;background:var(--s1);padding-top:12px;border-top:1px solid var(--b1)">
          <button type="button" class="btn" onclick="cm('emp-modal')">Cancel</button>
          <button type="submit" class="btn acc">Save Employee</button>
        </div>
      </form>
    </div>
  </div>

  {{-- ════════════════════════════════════════════════════════════════
       EDIT EMPLOYEE MODAL
  ════════════════════════════════════════════════════════════════ --}}
  <div class="modal" id="emp-edit-modal">
    <div class="mb wide" style="max-height:90vh;overflow-y:auto">
      <div class="mh" style="position:sticky;top:0;z-index:10;background:var(--s1)">
        <span class="mt">Edit Employee</span>
        <button class="mc" onclick="cm('emp-edit-modal')">✕</button>
      </div>
      <form method="POST" id="emp-edit-form" enctype="multipart/form-data">
        @csrf
        <div id="edit-loading" style="text-align:center;padding:40px;color:var(--t3);display:none">
          Loading employee data…
        </div>
        <div id="edit-body">

          {{-- ── Personal ──────────────────────────────── --}}
          <div style="margin:14px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
            Personal Information
          </div>
          <div class="fg2">
            <div class="fld">
              <label>First Name *</label>
              <input type="text" name="first_name" id="e-first-name" required>
            </div>
            <div class="fld">
              <label>Middle Name</label>
              <input type="text" name="middle_name" id="e-middle-name">
            </div>
            <div class="fld">
              <label>Last Name *</label>
              <input type="text" name="last_name" id="e-last-name" required>
            </div>
            <div class="fld">
              <label>Gender</label>
              <select name="gender" id="e-gender">
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div class="fld">
              <label>Date of Birth</label>
              <input type="date" name="date_of_birth" id="e-dob">
            </div>
            <div class="fld">
              <label>Marital Status</label>
              <select name="marital_status" id="e-marital">
                <option value="">Select</option>
                <option value="single">Single</option>
                <option value="married">Married</option>
                <option value="divorced">Divorced</option>
                <option value="widowed">Widowed</option>
              </select>
            </div>
          </div>

          {{-- ── Contact ───────────────────────────────── --}}
          <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
            Contact Details
          </div>
          <div class="fg2">
            <div class="fld">
              <label>Official Email</label>
              <input type="email" name="official_email" id="e-email">
            </div>
            <div class="fld">
              <label>Mobile</label>
              <input type="text" name="mobile" id="e-mobile" maxlength="15">
            </div>
            <div class="fld">
              <label>Personal Email</label>
              <input type="email" name="personal_email" id="e-personal-email">
            </div>
            <div class="fld">
              <label>Alternate Phone</label>
              <input type="text" name="alternate_phone" id="e-alt-phone" maxlength="10">
            </div>
          </div>

          {{-- ── Employment ────────────────────────────── --}}
          <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
            Employment Details
          </div>
          <div class="fg2">
            <div class="fld">
              <label>Status *</label>
              <select name="employee_status" id="e-status" required>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="on_notice">On Notice</option>
                <option value="terminated">Terminated</option>
              </select>
            </div>
            <div class="fld">
              <label>Employment Type</label>
              <select name="employment_type" id="e-emp-type">
                <option value="full_time">Full Time</option>
                <option value="part_time">Part Time</option>
                <option value="contract">Contract</option>
                <option value="intern">Intern</option>
                <option value="consultant">Consultant</option>
                <option value="retainer">Retainer</option>
              </select>
            </div>
            <div class="fld">
              <label>Work Type</label>
              <select name="work_type" id="e-work-type" onchange="toggleClientName('e', this.value); toggleBranchLocation('e', this.value)">
                <option value="wfo">Work from Office</option>
                <option value="wfh">Work from Home</option>
                <option value="hybrid">Hybrid</option>
                <option value="client_location">Client Location</option>
              </select>
            </div>
            <div class="fld" id="e-client-name-wrap" style="display:none">
              <label>Client Name</label>
              <input type="text" name="client_name" id="e-client-name">
            </div>
            <div class="fld">
              <label>Department</label>
              <select name="department_id" id="e-dept">
                <option value="">Select Department</option>
                @foreach($departments as $dept)
                  <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="fld">
              <label>Designation</label>
              <select name="designation_id" id="e-desig">
                <option value="">Select Designation</option>
                @foreach($designations as $d)
                  <option value="{{ $d->id }}">{{ $d->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="fld" id="e-branch-wrap">
              <label id="e-branch-label">Branch</label>
              <select name="branch_id" id="e-branch">
                <option value="">Select Branch</option>
                @foreach($branches as $b)
                  <option value="{{ $b->id }}">{{ $b->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="fld" id="e-location-wrap" style="display:none">
              <label id="e-location-label">Location</label>
              <select name="location_id" id="e-location">
                <option value="">Select Location</option>
                @foreach($locations as $l)
                  <option value="{{ $l->id }}">{{ $l->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="fld">
              <label>Reporting Manager</label>
              <select name="reporting_manager_id" id="e-manager">
                <option value="">Select Manager</option>
                @foreach($currentManagers as $mgr)
                  <option value="{{ $mgr->id }}">{{ $mgr->employee_id }} — {{ $mgr->full_name }}</option>
                @endforeach
              </select>
              <label style="display:flex;align-items:center;gap:6px;font-size:11px;color:var(--t3);font-weight:400;margin-top:5px;cursor:pointer">
                <input type="checkbox" id="e-manager-show-all" style="width:14px;height:14px"
                  onchange="toggleAllManagers('e', this.checked)">
                Not listed? Show all employees
              </label>
            </div>
            <div class="fld">
              <label>Date of Joining</label>
              <input type="date" name="date_of_joining" id="e-doj">
            </div>
            <div class="fld">
              <label>Date of Leaving</label>
              <input type="date" name="date_of_leaving" id="e-dol">
            </div>
          </div>

          {{-- ── Payroll Flags ─────────────────────────── --}}
          <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
            Payroll Applicability
          </div>
          <div class="fg2">
            <div class="fld">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="is_pf_applicable" value="1" id="e-pf" style="width:16px;height:16px"
                  onchange="toggleUanRequired('e', this.checked)">
                PF Applicable
              </label>
            </div>
            <div class="fld">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="is_esic_applicable" value="1" id="e-esic" style="width:16px;height:16px">
                ESIC Applicable
              </label>
            </div>
            <div class="fld">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="is_pt_applicable" value="1" id="e-pt" style="width:16px;height:16px">
                PT Applicable
              </label>
            </div>
            <div class="fld">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="is_tds_applicable" value="1" id="e-tds" style="width:16px;height:16px">
                TDS Applicable
              </label>
            </div>
            <div class="fld">
              <label>TDS Regime</label>
              <select name="tds_regime" id="e-tds-regime">
                <option value="new">New Regime</option>
                <option value="old">Old Regime</option>
              </select>
            </div>
          </div>

          {{-- ── Statutory ─────────────────────────────── --}}
          <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
            Statutory Details
          </div>
          <div class="fg2">
            <div class="fld">
              <label>PAN Number</label>
              <input type="text" name="pan_number" id="e-pan"
                placeholder="ABCDE1234F" maxlength="10" style="text-transform:uppercase"
                oninput="this.value=this.value.toUpperCase()">
            </div>
            <div class="fld">
              <label>Aadhaar Number</label>
              <input type="text" name="aadhaar_number" id="e-aadhaar"
                placeholder="12-digit number" maxlength="12">
            </div>
            <div class="fld">
              <label id="e-uan-label">UAN Number</label>
              <input type="text" name="uan_number" id="e-uan" maxlength="12">
            </div>
          </div>

          {{-- ── Bank Details ──────────────────────────── --}}
          <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
            Bank Details
          </div>
          <div class="fg2">
            <div class="fld">
              <label>Bank Name</label>
              <input type="text" name="bank_name" id="e-bank-name" placeholder="e.g. HDFC Bank">
            </div>
            <div class="fld">
              <label>Account Number</label>
              <input type="text" name="account_number" id="e-acc-no">
            </div>
            <div class="fld">
              <label>IFSC Code</label>
              <input type="text" name="ifsc_code" id="e-ifsc"
                placeholder="HDFC0001234" maxlength="11" style="text-transform:uppercase"
                oninput="this.value=this.value.toUpperCase()">
            </div>
            <div class="fld">
              <label>Account Holder Name</label>
              <input type="text" name="account_holder_name" id="e-acc-holder">
            </div>
          </div>

          {{-- ── Emergency Contact ─────────────────────── --}}
          <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
            Emergency Contact
          </div>
          <div class="fg2">
            <div class="fld">
              <label>Contact Name</label>
              <input type="text" name="emergency_contact_name" id="e-emg-name">
            </div>
            <div class="fld">
              <label>Contact Phone</label>
              <input type="text" name="emergency_contact_phone" id="e-emg-phone" maxlength="10">
            </div>
          </div>

          {{-- ── Documents ──────────────────────────────── --}}
          <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
            Documents
          </div>
          <div class="fg2">
            @foreach (\App\Models\EmployeeDocument::UPLOAD_TYPES as $docType => $docLabel)
            <div class="fld">
              <label>{{ $docLabel }} <a href="#" id="e-doc-{{ $docType }}-link" target="_blank" style="display:none;font-weight:400;font-size:11px;margin-left:6px">(view current)</a></label>
              <input type="file" name="documents[{{ $docType }}]" accept=".pdf,.jpg,.jpeg,.png">
            </div>
            @endforeach
          </div>

        </div>{{-- /#edit-body --}}

        <div class="mf" style="position:sticky;bottom:0;background:var(--s1);padding-top:12px;border-top:1px solid var(--b1)">
          <button type="button" class="btn" onclick="cm('emp-edit-modal')">Cancel</button>
          <button type="submit" class="btn acc">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  {{-- Mark as Left Modal --}}
  <div class="modal" id="mark-left-modal">
    <div class="mb">
      <div class="mh">
        <span class="mt" id="mark-left-title">Mark as Left</span>
        <button class="mc" onclick="cm('mark-left-modal')">✕</button>
      </div>
      <form method="POST" id="mark-left-form">
        @csrf
        <div class="fg2">
          <div class="fld full">
            <label class="req">Date of Leaving</label>
            <input type="date" name="date_of_leaving" id="mark-left-date" required value="{{ now()->toDateString() }}">
            <div style="font-size:11px;color:var(--t3);margin-top:4px">
              Their portal login is deactivated immediately, regardless of the date entered here.
            </div>
          </div>
        </div>
        <div class="mf">
          <button type="button" class="btn" onclick="cm('mark-left-modal')">Cancel</button>
          <button type="submit" class="btn" style="background:var(--yel);border-color:var(--yel);color:#fff">Confirm — Mark as Left</button>
        </div>
      </form>
    </div>
  </div>

  @include('partials.upload-modal', [
    'uploadId'      => 'upload-modal',
    'uploadTitle'   => 'Bulk Upload Employees',
    'uploadRoute'   => 'employees.import',
    'templateRoute' => 'employees.template',
    'uploadFields'  => [],
    'uploadHint'    => 'Download the template, fill employee data (one row per employee), then upload. Existing records matched by Employee ID will be updated — everything else is created new.',
  ])

  @include('partials.upload-modal', [
    'uploadId'      => 'bioid-upload-modal',
    'uploadTitle'   => 'Upload Bio ID Mappings',
    'uploadRoute'   => 'bio_ids.import',
    'templateRoute' => 'bio_ids.template',
    'uploadFields'  => [],
    'uploadHint'    => 'Columns: Employee Name, New Emp ID, BioID. BioID can be multiple values separated by commas (e.g. I-20,II-6,III-8). Types: I=Machine 10, II=Machine 12, III=Machine K.',
  ])

  @if($errors->any())
    <script>
    document.addEventListener('DOMContentLoaded', () => {
      om('emp-modal');
      @if(old('company_id'))
        // Branch options only ever get populated by onCompanyChange()'s fetch —
        // re-fire it so the Branch dropdown isn't stuck empty after the reload.
        onCompanyChange('{{ old('company_id') }}', true);
      @endif
      @if(old('reporting_manager_id') && !in_array(old('reporting_manager_id'), $currentManagerIds->all()))
        // The previously chosen manager isn't in the default "current
        // managers only" list — switch to "show all" so old() can find them.
        document.getElementById('add-manager-show-all').checked = true;
        toggleAllManagers('add', true);
        document.getElementById('add-manager').value = '{{ old('reporting_manager_id') }}';
        if (window._ssSync) window._ssSync(document.getElementById('add-manager'));
      @endif
    });
    </script>
  @endif

  @push('scripts')
  <script>
  async function openEditEmp(id) {
    // Show modal with loading state first
    document.getElementById('edit-loading').style.display = 'block';
    document.getElementById('edit-body').style.display    = 'none';
    document.getElementById('emp-edit-form').action       = '/employees/' + id;
    om('emp-edit-modal');

    try {
      const res  = await fetch('/employees/' + id + '/edit-data');
      const d    = await res.json();

      // Personal
      setVal('e-first-name',    d.first_name);
      setVal('e-middle-name',   d.middle_name);
      setVal('e-last-name',     d.last_name);
      setVal('e-gender',        d.gender);
      setVal('e-dob',           d.date_of_birth);
      setVal('e-marital',       d.marital_status);

      // Contact
      setVal('e-email',         d.official_email);
      setVal('e-mobile',        d.mobile);
      setVal('e-personal-email',d.personal_email);
      setVal('e-alt-phone',     d.alternate_phone);

      // Employment
      setVal('e-status',        d.employee_status);
      setVal('e-emp-type',      d.employment_type);
      setVal('e-work-type',     d.work_type);
      setVal('e-client-name',   d.client_name);
      toggleClientName('e', d.work_type);
      toggleBranchLocation('e', d.work_type);
      setVal('e-dept',          d.department_id);
      setVal('e-desig',         d.designation_id);
      setVal('e-branch',        d.branch_id);
      setVal('e-location',      d.location_id);
      setVal('e-manager',       d.reporting_manager_id);
      setVal('e-doj', d.date_of_joining);
      // Auto-fill date_of_leaving from tracker when not set on the employee record
      setVal('e-dol', d.date_of_leaving || d.suggested_leaving_date || '');

      // Payroll flags
      setCheck('e-pf',          d.is_pf_applicable);
      setCheck('e-esic',        d.is_esic_applicable);
      setCheck('e-pt',          d.is_pt_applicable);
      setCheck('e-tds',         d.is_tds_applicable);
      setVal('e-tds-regime',    d.tds_regime);
      toggleUanRequired('e', d.is_pf_applicable);

      // Statutory
      setVal('e-pan',           d.pan_number);
      setVal('e-aadhaar',       d.aadhaar_number);
      setVal('e-uan',           d.uan_number);

      // Bank
      setVal('e-bank-name',     d.bank_name);
      setVal('e-acc-no',        d.account_number);
      setVal('e-ifsc',          d.ifsc_code);
      setVal('e-acc-holder',    d.account_holder_name);

      // Emergency
      setVal('e-emg-name',      d.emergency_contact_name);
      setVal('e-emg-phone',     d.emergency_contact_phone);

      // Documents — show a "view current" link for any type already uploaded,
      // or a "link pending" note for a bulk-imported OneDrive link that
      // hasn't been downloaded into a real file yet (see EmployeeImporter's
      // *Document Link columns — no file_path means nothing to view yet).
      document.querySelectorAll('[id^="e-doc-"][id$="-link"]').forEach(a => a.style.display = 'none');
      Object.entries(d.documents || {}).forEach(([type, doc]) => {
        const link = document.getElementById('e-doc-' + type + '-link');
        if (!link) return;
        if (doc.download_url) {
          link.href = doc.download_url;
          link.textContent = '(view: ' + doc.file_name + ')';
          link.removeAttribute('data-pending');
        } else if (doc.pending_url) {
          link.removeAttribute('href');
          link.textContent = '(🔗 link saved, not yet downloaded)';
          link.setAttribute('data-pending', '1');
        }
        link.style.display = 'inline';
      });

    } catch(e) {
      console.error('Failed to load employee data', e);
    } finally {
      document.getElementById('edit-loading').style.display = 'none';
      document.getElementById('edit-body').style.display    = '';
    }
  }

  function setVal(id, val) {
    const el = document.getElementById(id);
    if (!el) return;
    el.value = val ?? '';
    window._ssSync?.(el); // sync searchable-select display if enhanced
  }
  function setCheck(id, val) {
    const el = document.getElementById(id);
    if (el) el.checked = !!val;
  }

  // Company-based employee ID suggestion map (company_id → suggested emp ID string)
  const _empIdByCompany = @json($suggestedEmpIdByCompany);

  // Reporting Manager option sets — default is "current managers only",
  // toggle swaps in the full employee list for picking someone new.
  const _allManagerOptions = @json($managers->map(fn($m) => ['id' => $m->id, 'label' => $m->employee_id . ' — ' . $m->full_name])->values());
  const _currentManagerOptions = @json($currentManagers->map(fn($m) => ['id' => $m->id, 'label' => $m->employee_id . ' — ' . $m->full_name])->values());

  function openMarkAsLeft(empId, name) {
    document.getElementById('mark-left-title').textContent = 'Mark as Left — ' + name;
    document.getElementById('mark-left-form').action = '/employees/' + empId + '/mark-as-left';
    document.getElementById('mark-left-date').value = new Date().toISOString().slice(0, 10);
    om('mark-left-modal');
  }

  function toggleAllManagers(prefix, showAll) {
    const select = document.getElementById(prefix + '-manager');
    if (!select) return;
    const keepValue = select.value;
    const options = showAll ? _allManagerOptions : _currentManagerOptions;
    select.innerHTML = '<option value="">Select Manager</option>';
    options.forEach(o => {
      const opt = document.createElement('option');
      opt.value = o.id;
      opt.textContent = o.label;
      select.appendChild(opt);
    });
    select.value = keepValue; // preserved if still present in the new option set
    // Force-enhance in case this select started under MIN_OPTS (the
    // "current managers" default list is often just 2-3 people) and never
    // got the search box in the first place — a no-op if already enhanced.
    if (window._ssInit) window._ssInit(select);
    if (window._ssSync) window._ssSync(select);
  }

  // Force-enhance both manager selects immediately, regardless of how many
  // options the default "current managers" list has — without this, a
  // company with under 5 current managers never gets the search box at
  // all, even after switching to "show all employees".
  document.addEventListener('DOMContentLoaded', () => {
    if (window._ssInit) {
      window._ssInit(document.getElementById('add-manager'));
      window._ssInit(document.getElementById('e-manager'));
    }
    // Branch/Location visibility+required needs to match the Add form's
    // default Work Type on load — otherwise Branch stays optional client-side
    // until the user actually touches the Work Type dropdown.
    toggleBranchLocation('add', document.getElementById('add-work-type')?.value || 'wfo');
  });

  function onCompanyChange(companyId, isRestoring = false) {
    // isRestoring=true means this is re-firing after a validation error to
    // rebuild the Branch list (which is only ever populated by this fetch,
    // never by the initial page load) — don't clobber whatever the user
    // already typed into Employee ID in that case.
    if (!isRestoring) {
      const empIdInput = document.querySelector('#emp-modal input[name="employee_id"]');
      if (empIdInput) {
        const suggested = _empIdByCompany[companyId] || '';
        empIdInput.value       = suggested;
        empIdInput.placeholder = suggested || 'e.g. 202618053';
      }
    }

    // Branches/Locations/Departments/Designations all belong to a company —
    // refresh the lists instead of showing whatever company the Employees
    // page happens to be filtered to.
    _fetchOptionsByCompany('add-branch',      '{{ route("masters.branches.by-company") }}',      companyId, 'Select Branch',      isRestoring);
    _fetchOptionsByCompany('add-location',    '{{ route("masters.locations.by-company") }}',     companyId, 'Select Location',    isRestoring);
    _fetchOptionsByCompany('add-department',  '{{ route("masters.departments.by-company") }}',   companyId, 'Select Department',  isRestoring);
    _fetchOptionsByCompany('add-designation', '{{ route("masters.designations.by-company") }}',  companyId, 'Select Designation', isRestoring);
  }

  function _fetchOptionsByCompany(selectId, url, companyId, placeholder, isRestoring) {
    const select = document.getElementById(selectId);
    if (!select) return;
    select.innerHTML = '<option value="">Loading…</option>';
    if (!companyId) {
      select.innerHTML = `<option value="">${placeholder}</option>`;
      return;
    }
    fetch(url + '?company_id=' + encodeURIComponent(companyId))
      .then(r => r.json())
      .then(rows => {
        select.innerHTML = `<option value="">${placeholder}</option>`;
        rows.forEach(row => {
          const opt = document.createElement('option');
          opt.value = row.id;
          opt.textContent = row.name;
          select.appendChild(opt);
        });
        if (isRestoring && select.dataset.oldValue) {
          select.value = select.dataset.oldValue;
        }
      })
      .catch(() => { select.innerHTML = `<option value="">${placeholder}</option>`; });
  }

  // Work Type = Client Location -> show/require the Client Name field.
  function toggleClientName(prefix, workType) {
    const wrap  = document.getElementById(prefix + '-client-name-wrap');
    const input = wrap?.querySelector('input[name="client_name"]');
    if (!wrap || !input) return;
    const show = workType === 'client_location';
    wrap.style.display = show ? 'flex' : 'none';
    input.required = show;
    if (!show) input.value = '';
  }

  // Work Type drives which of Branch / Location apply — Work from Office
  // needs Branch, Work from Home needs Location, Hybrid needs both, Client
  // Location needs Location too (client_name additionally identifies the
  // client — see toggleClientName).
  function toggleBranchLocation(prefix, workType) {
    const branchWrap    = document.getElementById(prefix + '-branch-wrap');
    const locationWrap  = document.getElementById(prefix + '-location-wrap');
    const branchSelect  = branchWrap?.querySelector('select[name="branch_id"]');
    const locationSelect= locationWrap?.querySelector('select[name="location_id"]');
    if (!branchWrap || !locationWrap || !branchSelect || !locationSelect) return;

    // Unset/unrecognized work type (legacy employees created before this
    // split existed) falls back to showing Branch only, matching the old
    // combined "Branch / Location" field's behavior — never hide a value
    // that's already saved just because work_type wasn't set.
    const known = ['wfo', 'wfh', 'hybrid', 'client_location'].includes(workType);
    const showBranch   = !known || workType === 'wfo' || workType === 'hybrid';
    const showLocation = known && (workType === 'wfh' || workType === 'hybrid' || workType === 'client_location');

    branchWrap.style.display   = showBranch ? 'flex' : 'none';
    locationWrap.style.display = showLocation ? 'flex' : 'none';
    branchSelect.required   = showBranch;
    locationSelect.required = showLocation;
    if (!showBranch)   branchSelect.value = '';
    if (!showLocation) locationSelect.value = '';
  }

  // PF Applicable checked -> UAN Number becomes required.
  function toggleUanRequired(prefix, isChecked) {
    const input = document.getElementById(prefix + '-uan');
    const label = document.getElementById(prefix + '-uan-label');
    if (!input) return;
    input.required = isChecked;
    label?.classList.toggle('req', isChecked);
  }

  // ── Cross-page bulk selection (sessionStorage) ───────────────────────────
  // Selections persist across paginated page-loads. IDs stored per filter scope.
  const _totalCount = {{ $employees->total() }};
  const _pageCount  = {{ $employees->count() }};
  const _empStoreKey = 'emp_bulk_{{ md5(request("search","")."|".request("department_id","")."|".request("status","")."|".request("company_id","0")) }}';
  let _selectAllPages = false;

  function _empGetStored()       { try { return new Set(JSON.parse(sessionStorage.getItem(_empStoreKey)||'[]')); } catch { return new Set(); } }
  function _empSaveStored(set)   { sessionStorage.setItem(_empStoreKey, JSON.stringify([...set])); }
  function _empClearStored()     { sessionStorage.removeItem(_empStoreKey); _selectAllPages = false; }

  function restoreEmpSelections() {
    const stored = _empGetStored();
    if (!stored.size) return;
    document.querySelectorAll('.emp-chk').forEach(cb => { if (stored.has(cb.value)) cb.checked = true; });
    updateBulkBar();
  }

  function updateBulkBar() {
    const stored = _empGetStored();
    const count  = _selectAllPages ? _totalCount : stored.size;
    const bar    = document.getElementById('sel-bar');
    const summary= document.getElementById('sel-summary');

    bar.style.display = count > 0 ? 'flex' : 'none';
    summary.textContent = _selectAllPages
      ? 'All ' + _totalCount + ' employees selected'
      : count + ' employee' + (count === 1 ? '' : 's') + ' selected (across all pages)';

    const allPageBanner = document.getElementById('sel-all-banner');
    const allPageActive = document.getElementById('sel-all-active');
    const pageChecked   = document.querySelectorAll('.emp-chk:checked').length;
    const allOnPage     = pageChecked === _pageCount && _pageCount > 0;

    if (_selectAllPages) {
      allPageBanner.style.display = 'none';
      allPageActive.style.display = '';
    } else if (allOnPage && _totalCount > _pageCount) {
      allPageBanner.style.display = '';
      allPageActive.style.display = 'none';
    } else {
      allPageBanner.style.display = 'none';
      allPageActive.style.display = 'none';
    }

    const all = document.querySelectorAll('.emp-chk');
    const hdr = document.getElementById('chk-all');
    if (hdr) {
      hdr.indeterminate = pageChecked > 0 && pageChecked < all.length;
      hdr.checked       = all.length > 0 && pageChecked === all.length;
    }
  }

  function toggleAll(hdr) {
    _selectAllPages = false;
    const stored = _empGetStored();
    document.querySelectorAll('.emp-chk').forEach(c => {
      c.checked = hdr.checked;
      hdr.checked ? stored.add(c.value) : stored.delete(c.value);
    });
    _empSaveStored(stored);
    updateBulkBar();
  }

  // Each individual checkbox change
  document.addEventListener('change', function(e) {
    if (!e.target.classList.contains('emp-chk')) return;
    _selectAllPages = false;
    const stored = _empGetStored();
    e.target.checked ? stored.add(e.target.value) : stored.delete(e.target.value);
    _empSaveStored(stored);
    updateBulkBar();
  });

  function selectAllPages() {
    _selectAllPages = true;
    const stored = _empGetStored();
    document.querySelectorAll('.emp-chk').forEach(c => { c.checked = true; stored.add(c.value); });
    _empSaveStored(stored);
    updateBulkBar();
  }

  function clearAllPages() {
    document.querySelectorAll('.emp-chk').forEach(c => c.checked = false);
    const hdr = document.getElementById('chk-all');
    if (hdr) { hdr.checked = false; hdr.indeterminate = false; }
    _empClearStored();
    updateBulkBar();
  }

  // Clear storage after successful delete redirect
  @if(session('success') && (str_contains(session('success'), 'deleted') || str_contains(session('success'), 'archived')))
  _empClearStored();
  @endif

  document.addEventListener('DOMContentLoaded', restoreEmpSelections);

  // Searching/filtering now swaps #ajax-results in place instead of a full
  // reload — _empStoreKey (and _totalCount/_pageCount) are baked in from
  // PHP at page load and can't follow a filter change made via AJAX, so
  // treat a refreshed region the same way the per-filter storage key
  // already implies: a changed filter starts a fresh selection.
  document.getElementById('ajax-results')?.addEventListener('ajax:loaded', clearAllPages);

  function submitBulk(action) {
    const stored = _empGetStored();
    const count  = _selectAllPages ? _totalCount : stored.size;
    if (count === 0) return;

    if (action === 'delete') {
      Swal.fire({
        icon:              'error',
        title:             '⛔ Permanent Delete',
        html:
          '<strong>' + count + ' employee' + (count === 1 ? '' : 's') + '</strong> and ALL their data will be <strong>permanently destroyed</strong>:<br><br>' +
          '• Bank &amp; statutory details<br>' +
          '• Salary records<br>' +
          '• Attendance &amp; leave history<br>' +
          '• Loans &amp; EMIs<br>' +
          '• Documents<br><br>' +
          '<span style="color:#dc2626;font-weight:700">This CANNOT be undone.</span>',
        showCancelButton:  true,
        confirmButtonText: 'Yes, permanently delete',
        confirmButtonColor:'#dc2626',
        cancelButtonText:  'Cancel',
        reverseButtons:    true,
      }).then(result => {
        if (!result.isConfirmed) return;
        _doSubmitBulk(action);
      });
    } else {
      Swal.fire({
        icon:              'warning',
        title:             'Archive ' + count + ' employee' + (count === 1 ? '' : 's') + '?',
        html:
          'Their data will be <strong>preserved</strong> in the database but hidden everywhere in the app.<br><br>' +
          'You can restore them later by re-importing or re-adding.',
        showCancelButton:  true,
        confirmButtonText: 'Yes, archive',
        confirmButtonColor:'#f59e0b',
        cancelButtonText:  'Cancel',
        reverseButtons:    true,
      }).then(result => {
        if (!result.isConfirmed) return;
        _doSubmitBulk(action);
      });
    }
  }

  function _doSubmitBulk(action) {
    document.getElementById('bulk-action').value     = action;
    document.getElementById('bulk-select-all').value = _selectAllPages ? '1' : '0';

    const container = document.getElementById('bulk-id-inputs');
    container.innerHTML = '';

    if (!_selectAllPages) {
      // Inject ALL stored IDs (across all pages visited, not just current DOM)
      const stored = _empGetStored();
      stored.forEach(id => {
        const inp = document.createElement('input');
        inp.type  = 'hidden';
        inp.name  = 'ids[]';
        inp.value = id;
        container.appendChild(inp);
      });
    }

    _empClearStored(); // clear storage before submit
    document.getElementById('bulk-form').submit();
  }  // end _doSubmitBulk
  </script>
  @endpush
@endsection
