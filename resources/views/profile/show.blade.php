@extends('layouts.app')
@section('title', 'My Profile')

@section('content')

  @php $emp = $user->employee; @endphp

  {{-- ── Master Details — set by HR at creation, read-only here ─────────── --}}
  <div class="card" style="margin-bottom:16px;max-width:800px">
    <div class="ch"><div class="ct">Master Details</div><span style="font-size:11px;color:var(--t3)">Set by HR — contact HR to change</span></div>
    <div style="padding:14px 18px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px 24px;font-size:13px">
      @foreach ([
          'Employee Code'     => $emp?->employee_id,
          'Company'           => $emp?->company?->name,
          'First Name'        => $emp?->first_name,
          'Last Name'         => $emp?->last_name,
          'Gender'            => $emp?->gender,
          'Official Email'    => $emp?->official_email,
          'Mobile'            => $emp?->mobile,
          'Date of Joining'   => $emp?->date_of_joining?->format('d M Y'),
          'Employment Type'   => $emp ? ucwords(str_replace('_',' ',$emp->employment_type)) : null,
          'Work Type'         => $emp ? ucwords(str_replace('_',' ',$emp->work_type)) : null,
          'Department'        => $emp?->department?->name,
          'Designation'       => $emp?->designation?->name,
          'Reporting Manager' => $emp?->reportingManager?->full_name,
          'Branch'            => $emp?->branch?->name,
          'Work Location'     => $emp?->location?->name,
          'Probation Period'  => $emp?->probation_period_months !== null ? $emp->probation_period_months . ' months' : null,
          'PF Applicable'     => $emp ? ($emp->is_pf_applicable ? 'Yes' : 'No') : null,
        ] as $label => $value)
        <div>
          <div style="font-size:11px;color:var(--t3);text-transform:uppercase;letter-spacing:.04em;margin-bottom:3px">{{ $label }}</div>
          <div>{{ $value ?: '—' }}</div>
        </div>
      @endforeach
      @if($emp?->work_type === 'client_location')
        <div>
          <div style="font-size:11px;color:var(--t3);text-transform:uppercase;letter-spacing:.04em;margin-bottom:3px">Client Name</div>
          <div>{{ $emp->client_name ?: '—' }}</div>
        </div>
      @endif
      <div>
        <div style="font-size:11px;color:var(--t3);text-transform:uppercase;letter-spacing:.04em;margin-bottom:3px">Role</div>
        <div>
          @forelse($user->roles as $role)
            <span style="display:inline-block;background:var(--bg2,#eef1f5);border-radius:12px;padding:2px 8px;font-size:11px;margin:2px 4px 2px 0">{{ $role->name }}</span>
          @empty
            <span style="color:var(--t3)">No roles assigned</span>
          @endforelse
        </div>
      </div>
    </div>
  </div>

  @if($emp)
  {{-- ── Additional Details — anything HR left blank, you can fill in ───── --}}
  <div class="card" style="margin-bottom:16px;max-width:800px">
    <div class="ch"><div class="ct">Additional Details</div><span style="font-size:11px;color:var(--t3)">Fill in anything still missing</span></div>
    <form method="POST" action="{{ route('profile.employee_details.update') }}" enctype="multipart/form-data" style="padding:14px 18px">
      @csrf

      <div style="margin:0 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">Personal</div>
      <div class="fg2">
        <div class="fld">
          <label>Middle Name</label>
          <input type="text" name="middle_name" value="{{ old('middle_name', $emp->middle_name) }}">
        </div>
        <div class="fld">
          <label>Date of Birth</label>
          <input type="date" name="date_of_birth" value="{{ old('date_of_birth', $emp->date_of_birth ? \Carbon\Carbon::parse($emp->date_of_birth)->format('Y-m-d') : null) }}">
        </div>
        <div class="fld">
          <label>Marital Status</label>
          <select name="marital_status">
            <option value="">Select</option>
            @foreach(['single'=>'Single','married'=>'Married','divorced'=>'Divorced','widowed'=>'Widowed'] as $val => $lbl)
              <option value="{{ $val }}" {{ old('marital_status', $emp->marital_status) === $val ? 'selected' : '' }}>{{ $lbl }}</option>
            @endforeach
          </select>
        </div>
        <div class="fld">
          <label>Nationality</label>
          <input type="text" name="nationality" value="{{ old('nationality', $emp->nationality) }}">
        </div>
        <div class="fld">
          <label>Personal Email</label>
          <input type="email" name="personal_email" value="{{ old('personal_email', $emp->personal_email) }}"
            @error('personal_email') class="inp-err" @enderror>
          @error('personal_email') <div class="ferr">{{ $message }}</div> @enderror
        </div>
        <div class="fld">
          <label>Alternate Phone</label>
          <input type="text" name="alternate_phone" maxlength="10" placeholder="10 digits"
            value="{{ old('alternate_phone', $emp->alternate_phone) }}" @error('alternate_phone') class="inp-err" @enderror>
          @error('alternate_phone') <div class="ferr">{{ $message }}</div> @enderror
        </div>
      </div>

      <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">Current Address</div>
      <div class="fg2">
        <div class="fld full">
          <label>Address</label>
          <input type="text" name="current_address" value="{{ old('current_address', $emp->current_address) }}">
        </div>
        <div class="fld">
          <label>City</label>
          <input type="text" name="current_city" value="{{ old('current_city', $emp->current_city) }}">
        </div>
        <div class="fld">
          <label>State</label>
          <input type="text" name="current_state" value="{{ old('current_state', $emp->current_state) }}">
        </div>
        <div class="fld">
          <label>Pincode</label>
          <input type="text" name="current_pincode" maxlength="10" value="{{ old('current_pincode', $emp->current_pincode) }}">
        </div>
      </div>

      <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">Permanent Address</div>
      <div class="fg2">
        <div class="fld full">
          <label>Address</label>
          <input type="text" name="permanent_address" value="{{ old('permanent_address', $emp->permanent_address) }}">
        </div>
        <div class="fld">
          <label>City</label>
          <input type="text" name="permanent_city" value="{{ old('permanent_city', $emp->permanent_city) }}">
        </div>
        <div class="fld">
          <label>State</label>
          <input type="text" name="permanent_state" value="{{ old('permanent_state', $emp->permanent_state) }}">
        </div>
        <div class="fld">
          <label>Pincode</label>
          <input type="text" name="permanent_pincode" maxlength="10" value="{{ old('permanent_pincode', $emp->permanent_pincode) }}">
        </div>
      </div>

      <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">Emergency Contact</div>
      <div class="fg2">
        <div class="fld">
          <label>Contact Name</label>
          <input type="text" name="emergency_contact_name" value="{{ old('emergency_contact_name', $emp->emergency_contact_name) }}">
        </div>
        <div class="fld">
          <label>Contact Phone</label>
          <input type="text" name="emergency_contact_phone" maxlength="10"
            value="{{ old('emergency_contact_phone', $emp->emergency_contact_phone) }}" @error('emergency_contact_phone') class="inp-err" @enderror>
          @error('emergency_contact_phone') <div class="ferr">{{ $message }}</div> @enderror
        </div>
        <div class="fld">
          <label>Relation</label>
          <input type="text" name="emergency_contact_relation" value="{{ old('emergency_contact_relation', $emp->emergency_contact_relation) }}">
        </div>
      </div>

      <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">Statutory Details</div>
      <div class="fg2">
        <div class="fld">
          <label>PAN Number</label>
          <input type="text" name="pan_number" maxlength="10" style="text-transform:uppercase" oninput="this.value=this.value.toUpperCase()"
            value="{{ old('pan_number', $emp->statutoryDetails?->pan_number) }}" @error('pan_number') class="inp-err" @enderror>
          @error('pan_number') <div class="ferr">{{ $message }}</div> @enderror
        </div>
        <div class="fld">
          <label>Aadhaar Number</label>
          <input type="text" name="aadhaar_number" maxlength="12"
            value="{{ old('aadhaar_number', $emp->statutoryDetails?->aadhaar_number) }}" @error('aadhaar_number') class="inp-err" @enderror>
          @error('aadhaar_number') <div class="ferr">{{ $message }}</div> @enderror
        </div>
        <div class="fld">
          <label>UAN Number</label>
          <input type="text" name="uan_number" maxlength="12" value="{{ old('uan_number', $emp->statutoryDetails?->uan_number) }}">
        </div>
      </div>

      <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">Bank Details</div>
      <div class="fg2">
        <div class="fld">
          <label>Bank Name</label>
          <input type="text" name="bank_name" value="{{ old('bank_name', $emp->primaryBank?->bank_name) }}">
        </div>
        <div class="fld">
          <label>Account Number</label>
          <input type="text" name="account_number" value="{{ old('account_number', $emp->primaryBank?->account_number) }}">
        </div>
        <div class="fld">
          <label>IFSC Code</label>
          <input type="text" name="ifsc_code" maxlength="11" style="text-transform:uppercase" oninput="this.value=this.value.toUpperCase()"
            value="{{ old('ifsc_code', $emp->primaryBank?->ifsc_code) }}" @error('ifsc_code') class="inp-err" @enderror>
          @error('ifsc_code') <div class="ferr">{{ $message }}</div> @enderror
        </div>
        <div class="fld">
          <label>Account Holder Name</label>
          <input type="text" name="account_holder_name" value="{{ old('account_holder_name', $emp->primaryBank?->account_holder_name) }}">
        </div>
      </div>

      <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">Documents</div>
      <div class="fg2">
        @foreach (\App\Models\EmployeeDocument::UPLOAD_TYPES as $docType => $docLabel)
          @php $existingDoc = $emp->documents->firstWhere('document_type', $docType); @endphp
          <div class="fld">
            <label>{{ $docLabel }}
              @if($existingDoc)
                <a href="{{ route('employees.documents.download', $existingDoc) }}" target="_blank" style="font-weight:400;font-size:11px;margin-left:6px">(view: {{ $existingDoc->file_name }})</a>
              @endif
            </label>
            <input type="file" name="documents[{{ $docType }}]" accept=".pdf,.jpg,.jpeg,.png">
          </div>
        @endforeach
      </div>

      <div class="mf" style="border-top:none;padding-top:12px;margin-top:4px">
        <button type="submit" class="btn acc">Save Details</button>
      </div>
    </form>
  </div>
  @endif

  <div class="card" style="max-width:800px">
    <div class="ch"><div class="ct">Account Details</div></div>
    <form method="POST" action="{{ route('profile.update') }}" style="padding:14px 18px">
      @csrf @method('PUT')
      <div class="fld" style="margin-bottom:16px">
        <label class="req">Name</label>
        <input type="text" name="name" required maxlength="150" value="{{ old('name', $user->name) }}"
          @error('name') class="inp-err" @enderror>
        @error('name') <div class="ferr">{{ $message }}</div> @enderror
      </div>
      <div class="fld" style="margin-bottom:16px">
        <label class="req">Email</label>
        <input type="email" name="email" required maxlength="150" value="{{ old('email', $user->email) }}"
          @error('email') class="inp-err" @enderror>
        @error('email') <div class="ferr">{{ $message }}</div> @enderror
      </div>
      <div class="fld" style="margin-bottom:16px">
        <label>New Password <span style="font-weight:400;color:var(--t3)">(leave blank to keep current)</span></label>
        <input type="password" name="password" minlength="8" autocomplete="new-password"
          @error('password') class="inp-err" @enderror>
        @error('password') <div class="ferr">{{ $message }}</div> @enderror
      </div>
      <div class="fld" style="margin-bottom:16px">
        <label>Confirm New Password</label>
        <input type="password" name="password_confirmation" minlength="8" autocomplete="new-password">
      </div>
      <div class="mf" style="border-top:none;padding-top:0;margin-top:4px">
        <button type="submit" class="btn acc">Save Changes</button>
      </div>
    </form>
  </div>

@endsection
