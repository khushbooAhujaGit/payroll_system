@extends('layouts.app')
@section('title', 'Employees')

@section('page_actions')
  <button class="btn acc" onclick="om('emp-modal')">+ Add Employee</button>
  <button class="btn" onclick="om('upload-modal')">📤 Bulk Upload</button>
  <button class="btn" onclick="om('bioid-upload-modal')">🔖 Upload Bio IDs</button>
  <a href="{{ route('employees.export') . '?' . http_build_query(request()->only('search','department_id','status')) }}" class="btn">⬇ Export</a>
@endsection
 
@section('content')

  {{-- Filter bar --}}
  <form method="GET" action="{{ route('employees') }}" class="card"
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
    <a href="{{ route('employees') }}" class="btn">Reset</a>
  </form>

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
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach($employees as $emp)
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
              <td>
                <button class="btn sm" onclick="openEditEmp({{ $emp->id }})">Edit</button>
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

  {{-- ════════════════════════════════════════════════════════════════
       ADD EMPLOYEE MODAL
  ════════════════════════════════════════════════════════════════ --}}
  <div class="modal" id="emp-modal">
    <div class="mb wide" style="max-height:90vh;overflow-y:auto">
      <div class="mh" style="position:sticky;top:0;z-index:10;background:var(--s1)">
        <span class="mt">Add Employee</span>
        <button class="mc" onclick="cm('emp-modal')">✕</button>
      </div>
      <form method="POST" action="{{ route('employees.store') }}">
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
                <option value="{{ $co->id }}">{{ $co->name }}</option>
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
            <label>Blood Group</label>
            <select name="blood_group">
              <option value="">Select</option>
              @foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg)
                <option value="{{ $bg }}" {{ old('blood_group') === $bg ? 'selected' : '' }}>{{ $bg }}</option>
              @endforeach
            </select>
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
            <label>Official Email</label>
            <input type="email" name="official_email" value="{{ old('official_email') }}">
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
            <label>Work Type *</label>
            <select name="work_type" required>
              <option value="wfo"            {{ old('work_type', 'wfo') === 'wfo'            ? 'selected' : '' }}>Work from Office</option>
              <option value="wfh"            {{ old('work_type') === 'wfh'            ? 'selected' : '' }}>Work from Home</option>
              <option value="hybrid"         {{ old('work_type') === 'hybrid'         ? 'selected' : '' }}>Hybrid</option>
              <option value="client_location"{{ old('work_type') === 'client_location'? 'selected' : '' }}>Client Location</option>
            </select>
          </div>
          <div class="fld">
            <label>Department</label>
            <select name="department_id">
              <option value="">Select Department</option>
              @foreach($departments as $dept)
                <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="fld">
            <label>Designation</label>
            <select name="designation_id">
              <option value="">Select Designation</option>
              @foreach($designations as $d)
                <option value="{{ $d->id }}" {{ old('designation_id') == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="fld">
            <label>Branch / Location</label>
            <select name="branch_id">
              <option value="">Select Branch</option>
              @foreach($branches as $b)
                <option value="{{ $b->id }}" {{ old('branch_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="fld">
            <label>Reporting Manager</label>
            <select name="reporting_manager_id">
              <option value="">Select Manager</option>
              @foreach($managers as $mgr)
                <option value="{{ $mgr->id }}" {{ old('reporting_manager_id') == $mgr->id ? 'selected' : '' }}>
                  {{ $mgr->employee_id }} — {{ $mgr->full_name }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="fld">
            <label>Probation Period (months)</label>
            <input type="number" name="probation_period_months" min="0" max="24"
              value="{{ old('probation_period_months', 6) }}" placeholder="6">
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
              <input type="checkbox" name="is_pf_applicable" value="1"
                {{ old('is_pf_applicable', '1') ? 'checked' : '' }} style="width:16px;height:16px">
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
            <label class="req">PAN Number</label>
            <input type="text" name="pan_number" value="{{ old('pan_number') }}" required
              placeholder="ABCDE1234F" maxlength="10" style="text-transform:uppercase"
              oninput="this.value=this.value.toUpperCase()"
              @error('pan_number') class="inp-err" @enderror>
            @error('pan_number') <div class="ferr">{{ $message }}</div> @enderror
          </div>
          <div class="fld">
            <label class="req">Aadhaar Number</label>
            <input type="text" name="aadhaar_number" value="{{ old('aadhaar_number') }}" required
              placeholder="12-digit number" maxlength="12" pattern="\d{12}"
              @error('aadhaar_number') class="inp-err" @enderror>
            @error('aadhaar_number') <div class="ferr">{{ $message }}</div> @enderror
          </div>
          <div class="fld">
            <label>UAN Number</label>
            <input type="text" name="uan_number" value="{{ old('uan_number') }}"
              placeholder="12-digit UAN" maxlength="12">
          </div>
        </div>

        {{-- ── Bank Details ──────────────────────────── --}}
        <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
          Bank Details
        </div>
        <div class="fg2">
          <div class="fld">
            <label class="req">Bank Name</label>
            <input type="text" name="bank_name" value="{{ old('bank_name') }}" required
              placeholder="e.g. HDFC Bank"
              @error('bank_name') class="inp-err" @enderror>
            @error('bank_name') <div class="ferr">{{ $message }}</div> @enderror
          </div>
          <div class="fld">
            <label class="req">Account Number</label>
            <input type="text" name="account_number" value="{{ old('account_number') }}" required
              @error('account_number') class="inp-err" @enderror>
            @error('account_number') <div class="ferr">{{ $message }}</div> @enderror
          </div>
          <div class="fld">
            <label class="req">IFSC Code</label>
            <input type="text" name="ifsc_code" value="{{ old('ifsc_code') }}" required
              placeholder="HDFC0001234" maxlength="11" style="text-transform:uppercase"
              oninput="this.value=this.value.toUpperCase()"
              @error('ifsc_code') class="inp-err" @enderror>
            @error('ifsc_code') <div class="ferr">{{ $message }}</div> @enderror
          </div>
          <div class="fld">
            <label class="req">Account Holder Name</label>
            <input type="text" name="account_holder_name" value="{{ old('account_holder_name') }}" required
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
            <label>Contact Name</label>
            <input type="text" name="emergency_contact_name" value="{{ old('emergency_contact_name') }}">
          </div>
          <div class="fld">
            <label>Contact Phone (10 digits)</label>
            <input type="text" name="emergency_contact_phone" value="{{ old('emergency_contact_phone') }}"
              pattern="\d{10}" maxlength="10" placeholder="9876543210">
          </div>
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
      <form method="POST" id="emp-edit-form">
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
              <label>Blood Group</label>
              <select name="blood_group" id="e-blood-group">
                <option value="">Select</option>
                @foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg)
                  <option value="{{ $bg }}">{{ $bg }}</option>
                @endforeach
              </select>
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
              <select name="work_type" id="e-work-type">
                <option value="wfo">Work from Office</option>
                <option value="wfh">Work from Home</option>
                <option value="hybrid">Hybrid</option>
                <option value="client_location">Client Location</option>
              </select>
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
            <div class="fld">
              <label>Branch / Location</label>
              <select name="branch_id" id="e-branch">
                <option value="">Select Branch</option>
                @foreach($branches as $b)
                  <option value="{{ $b->id }}">{{ $b->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="fld">
              <label>Reporting Manager</label>
              <select name="reporting_manager_id" id="e-manager">
                <option value="">Select Manager</option>
                @foreach($managers as $mgr)
                  <option value="{{ $mgr->id }}">{{ $mgr->employee_id }} — {{ $mgr->full_name }}</option>
                @endforeach
              </select>
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
                <input type="checkbox" name="is_pf_applicable" value="1" id="e-pf" style="width:16px;height:16px">
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
              <label>UAN Number</label>
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

        </div>{{-- /#edit-body --}}

        <div class="mf" style="position:sticky;bottom:0;background:var(--s1);padding-top:12px;border-top:1px solid var(--b1)">
          <button type="button" class="btn" onclick="cm('emp-edit-modal')">Cancel</button>
          <button type="submit" class="btn acc">Save Changes</button>
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
    'uploadHint'    => 'Download the template, fill employee data (one row per employee), then upload. Existing records matched by mobile number will be updated.',
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
    <script>document.addEventListener('DOMContentLoaded',()=>om('emp-modal'))</script>
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
      setVal('e-blood-group',   d.blood_group);
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
      setVal('e-dept',          d.department_id);
      setVal('e-desig',         d.designation_id);
      setVal('e-branch',        d.branch_id);
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

  function onCompanyChange(companyId) {
    const empIdInput = document.querySelector('#emp-modal input[name="employee_id"]');
    if (!empIdInput) return;
    const suggested = _empIdByCompany[companyId] || '';
    empIdInput.value       = suggested;
    empIdInput.placeholder = suggested || 'e.g. 202618053';
  }

  // ── Bulk selection ───────────────────────────────────────────────────────
  // _selectAllPages is ONLY set to true when the user explicitly clicks the
  // "Select all N employees" banner — the header checkbox NEVER triggers it.
  let _selectAllPages = false;
  const _totalCount   = {{ $employees->total() }};
  const _pageCount    = {{ $employees->count() }};

  function getChecked() {
    return [...document.querySelectorAll('.emp-chk:checked')].map(c => c.value);
  }

  function updateBulkBar() {
    const ids    = getChecked();
    const count  = _selectAllPages ? _totalCount : ids.length;
    const bar    = document.getElementById('sel-bar');
    const summary= document.getElementById('sel-summary');

    bar.style.display = count > 0 ? 'flex' : 'none';
    summary.textContent = (count === _totalCount && _selectAllPages)
      ? 'All ' + count + ' employees selected'
      : count + ' employee' + (count === 1 ? '' : 's') + ' selected (this page)';

    // Show "Select all pages" banner only when ALL current-page items are checked
    // AND there are more employees on other pages — AND user hasn't already done it
    const allPageBanner = document.getElementById('sel-all-banner');
    const allPageActive = document.getElementById('sel-all-active');
    const allOnPage     = ids.length === _pageCount && _pageCount > 0;

    if (_selectAllPages) {
      allPageBanner.style.display = 'none';
      allPageActive.style.display = '';
    } else if (allOnPage && _totalCount > _pageCount) {
      // Offer the option but don't activate it automatically
      allPageBanner.style.display = '';
      allPageActive.style.display = 'none';
    } else {
      allPageBanner.style.display = 'none';
      allPageActive.style.display = 'none';
    }

    // Sync header checkbox — reflects only current page selection, never "all pages"
    const all = document.querySelectorAll('.emp-chk');
    const hdr = document.getElementById('chk-all');
    if (hdr) {
      const pageChecked = ids.length;
      hdr.indeterminate = pageChecked > 0 && pageChecked < all.length;
      hdr.checked       = pageChecked === all.length && all.length > 0;
    }
  }

  // Header checkbox: select / deselect CURRENT PAGE ONLY
  // Never auto-selects other pages — that requires an explicit click on the banner.
  function toggleAll(hdr) {
    _selectAllPages = false;   // always reset cross-page selection
    document.querySelectorAll('.emp-chk').forEach(c => c.checked = hdr.checked);
    updateBulkBar();
  }

  // Explicit "Select all X employees" banner click → cross-page selection
  function selectAllPages() {
    _selectAllPages = true;
    document.querySelectorAll('.emp-chk').forEach(c => c.checked = true);
    updateBulkBar();
  }

  function clearAllPages() {
    _selectAllPages = false;
    document.querySelectorAll('.emp-chk').forEach(c => c.checked = false);
    const hdr = document.getElementById('chk-all');
    if (hdr) { hdr.checked = false; hdr.indeterminate = false; }
    updateBulkBar();
  }

  function submitBulk(action) {
    const ids   = getChecked();
    const count = _selectAllPages ? _totalCount : ids.length;
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
    // Build the form
    document.getElementById('bulk-action').value     = action;
    document.getElementById('bulk-select-all').value = _selectAllPages ? '1' : '0';

    const container = document.getElementById('bulk-id-inputs');
    container.innerHTML = '';

    if (!_selectAllPages) {
      ids.forEach(id => {
        const inp = document.createElement('input');
        inp.type  = 'hidden';
        inp.name  = 'ids[]';
        inp.value = id;
        container.appendChild(inp);
      });
    }

    document.getElementById('bulk-form').submit();
  }  // end _doSubmitBulk
  </script>
  @endpush
@endsection
