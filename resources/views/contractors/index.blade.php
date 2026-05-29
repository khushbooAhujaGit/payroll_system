@extends('layouts.app')
@section('title', 'Contractors')

@section('page_actions')
  <a href="{{ route('contractors.template') }}" class="btn">📥 Download Template</a>
  <button class="btn" onclick="om('ctr-add-modal')">+ Add Contractor</button>
  <button class="btn acc" onclick="om('ctr-import-modal')">📤 Import</button>
@endsection

@section('content')

<div class="g4" style="margin-bottom:16px">
  <div class="sc">
    <div class="lbl">Total Contractors</div>
    <div class="val">{{ $total }}</div>
  </div>
  <div class="sc">
    <div class="lbl">Active</div>
    <div class="val" style="color:var(--grn)">{{ $activeCount }}</div>
  </div>
  <div class="sc">
    <div class="lbl">Inactive</div>
    <div class="val" style="color:var(--t3)">{{ $total - $activeCount }}</div>
  </div>
</div>

{{-- Filters --}}
<form method="GET" action="{{ route('contractors') }}" class="card"
      style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
  <div class="fld" style="flex:1;min-width:200px">
    <label>Search (name / PAN / mobile / email)</label>
    <input type="text" name="search" value="{{ request('search') }}" placeholder="e.g. Ramesh or ABCDE1234F">
  </div>
  <div class="fld" style="min-width:140px">
    <label>Type</label>
    <select name="type">
      <option value="">All Types</option>
      <option value="individual" {{ request('type') === 'individual' ? 'selected' : '' }}>Individual</option>
      <option value="company"    {{ request('type') === 'company'    ? 'selected' : '' }}>Company</option>
    </select>
  </div>
  <div class="fld" style="min-width:120px">
    <label>Status</label>
    <select name="status">
      <option value="">All</option>
      <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option>
      <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option>
    </select>
  </div>
  <button type="submit" class="btn acc">Filter</button>
  <a href="{{ route('contractors') }}" class="btn">Reset</a>
</form>

<div class="card">
  @if($contractors->isNotEmpty())
  <table>
    <thead>
      <tr>
        <th>CTR ID</th>
        <th>Name</th>
        <th>Type</th>
        <th>PAN</th>
        <th>Mobile</th>
        <th>Email</th>
        <th>Status</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      @foreach($contractors as $c)
      <tr>
        <td class="mono" style="font-size:12px;color:var(--t3)">{{ $c->contractor_id }}</td>
        <td>{{ $c->name }}</td>
        <td>
          <span class="bx" style="font-size:11px;text-transform:capitalize">{{ $c->contractor_type }}</span>
        </td>
        <td class="mono" style="font-size:12px">{{ $c->pan ?: '—' }}</td>
        <td style="font-size:12px">{{ $c->mobile ?: '—' }}</td>
        <td style="font-size:12px">{{ $c->email ?: '—' }}</td>
        <td>
          @if($c->is_active)
            <span class="bx" style="color:var(--grn)">Active</span>
          @else
            <span class="bx" style="color:var(--t3)">Inactive</span>
          @endif
        </td>
        <td style="white-space:nowrap">
          <button class="btn sm" style="font-size:10px"
            onclick="openCtrEdit({{ $c->id }}, {{ json_encode($c->only(['name','contractor_type','pan','gstin','mobile','email','bank_account_number','bank_name','ifsc_code','address','is_active'])) }})">
            Edit
          </button>
          <form method="POST" action="{{ route('contractors.destroy', $c) }}" style="display:inline">
            @csrf @method('DELETE')
            <button type="button" class="btn sm" style="font-size:10px;color:var(--red)"
              onclick="swConfirm(this.closest('form'), 'Delete Contractor?', '{{ $c->name }} will be permanently removed.', 'warning', 'Yes, delete', '#dc2626')">Del</button>
          </form>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
  <div style="margin-top:14px">{{ $contractors->links() }}</div>
  @else
  <div class="empty">
    <div class="ico">🏭</div>
    <p>No contractors found.</p>
    <p style="font-size:12px;color:var(--t3);margin-top:4px">
      Download the template, fill in contractor details, and import using the button above.
    </p>
  </div>
  @endif
</div>

{{-- Add Contractor Modal --}}
<div class="modal" id="ctr-add-modal">
  <div class="mb wide">
    <div class="mh">
      <span class="mt">Add Contractor</span>
      <button class="mc" onclick="cm('ctr-add-modal')">✕</button>
    </div>
    <form method="POST" action="{{ route('contractors.store') }}">
      @csrf
      <div style="margin:0 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
        Basic Details
      </div>
      <div class="fg2">
        <div class="fld full">
          <label class="req">Name</label>
          <input type="text" name="name" required value="{{ old('name') }}"
            placeholder="Individual or company name"
            @error('name') class="inp-err" @enderror>
          @error('name') <div class="ferr">{{ $message }}</div> @enderror
        </div>
        <div class="fld">
          <label class="req">Type</label>
          <select name="contractor_type" required @error('contractor_type') class="inp-err" @enderror>
            <option value="individual" {{ old('contractor_type', 'individual') === 'individual' ? 'selected' : '' }}>Individual</option>
            <option value="company"    {{ old('contractor_type') === 'company'    ? 'selected' : '' }}>Company</option>
          </select>
        </div>
        <div class="fld">
          <label>PAN</label>
          <input type="text" name="pan" value="{{ old('pan') }}"
            placeholder="ABCDE1234F" maxlength="10" style="text-transform:uppercase"
            oninput="this.value=this.value.toUpperCase()">
        </div>
        <div class="fld">
          <label>GSTIN</label>
          <input type="text" name="gstin" value="{{ old('gstin') }}"
            placeholder="27AABCT1234T1Z5" maxlength="20" style="text-transform:uppercase"
            oninput="this.value=this.value.toUpperCase()">
        </div>
        <div class="fld">
          <label>Mobile (10 digits)</label>
          <input type="text" name="mobile" value="{{ old('mobile') }}"
            pattern="\d{10}" maxlength="10" placeholder="9876543210">
        </div>
        <div class="fld">
          <label>Email</label>
          <input type="email" name="email" value="{{ old('email') }}">
        </div>
        <div class="fld full">
          <label>Address</label>
          <input type="text" name="address" value="{{ old('address') }}">
        </div>
      </div>

      <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
        Bank Details
      </div>
      <div class="fg2">
        <div class="fld">
          <label>Bank Name</label>
          <input type="text" name="bank_name" value="{{ old('bank_name') }}" placeholder="e.g. HDFC Bank">
        </div>
        <div class="fld">
          <label>Account Number</label>
          <input type="text" name="bank_account_number" value="{{ old('bank_account_number') }}">
        </div>
        <div class="fld">
          <label>IFSC Code</label>
          <input type="text" name="ifsc_code" value="{{ old('ifsc_code') }}"
            placeholder="HDFC0001234" maxlength="11" style="text-transform:uppercase"
            oninput="this.value=this.value.toUpperCase()">
        </div>
      </div>

      <div class="mf">
        <button type="button" class="btn" onclick="cm('ctr-add-modal')">Cancel</button>
        <button type="submit" class="btn acc">Add Contractor</button>
      </div>
    </form>
  </div>
</div>

{{-- Edit Contractor Modal --}}
<div class="modal" id="ctr-edit-modal">
  <div class="mb wide">
    <div class="mh">
      <span class="mt">Edit Contractor</span>
      <button class="mc" onclick="cm('ctr-edit-modal')">✕</button>
    </div>
    <form method="POST" id="ctr-edit-form">
      @csrf
      <div style="margin:0 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
        Basic Details
      </div>
      <div class="fg2">
        <div class="fld full">
          <label>Name *</label>
          <input type="text" name="name" id="ce-name" required>
        </div>
        <div class="fld">
          <label>Type *</label>
          <select name="contractor_type" id="ce-type" required>
            <option value="individual">Individual</option>
            <option value="company">Company</option>
          </select>
        </div>
        <div class="fld">
          <label>PAN</label>
          <input type="text" name="pan" id="ce-pan" maxlength="10"
            style="text-transform:uppercase" oninput="this.value=this.value.toUpperCase()">
        </div>
        <div class="fld">
          <label>GSTIN</label>
          <input type="text" name="gstin" id="ce-gstin" maxlength="20"
            style="text-transform:uppercase" oninput="this.value=this.value.toUpperCase()">
        </div>
        <div class="fld">
          <label>Mobile</label>
          <input type="text" name="mobile" id="ce-mobile" maxlength="10">
        </div>
        <div class="fld">
          <label>Email</label>
          <input type="email" name="email" id="ce-email">
        </div>
        <div class="fld full">
          <label>Address</label>
          <input type="text" name="address" id="ce-address">
        </div>
        <div class="fld full">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="is_active" value="1" id="ce-active" style="width:16px;height:16px">
            Active
          </label>
        </div>
      </div>

      <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
        Bank Details
      </div>
      <div class="fg2">
        <div class="fld">
          <label>Bank Name</label>
          <input type="text" name="bank_name" id="ce-bank-name">
        </div>
        <div class="fld">
          <label>Account Number</label>
          <input type="text" name="bank_account_number" id="ce-acc-no">
        </div>
        <div class="fld">
          <label>IFSC Code</label>
          <input type="text" name="ifsc_code" id="ce-ifsc" maxlength="11"
            style="text-transform:uppercase" oninput="this.value=this.value.toUpperCase()">
        </div>
      </div>

      <div class="mf">
        <button type="button" class="btn" onclick="cm('ctr-edit-modal')">Cancel</button>
        <button type="submit" class="btn acc">Save</button>
      </div>
    </form>
  </div>
</div>

{{-- Import Modal --}}
<div class="modal" id="ctr-import-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Import Contractors</span>
      <button class="mc" onclick="cm('ctr-import-modal')">✕</button>
    </div>
    <div style="background:var(--acc2);border:1px solid var(--acc);border-radius:8px;
                padding:12px 14px;margin-bottom:16px;font-size:12px;line-height:1.6;color:var(--t2)">
      <strong>Template columns:</strong>
      <span class="mono" style="font-size:11px">Name · Type (individual/company) · PAN · GSTIN · Mobile · Email · Bank Account · Bank Name · IFSC Code · Address</span>
    </div>
    <form method="POST" action="{{ route('contractors.import') }}" enctype="multipart/form-data">
      @csrf
      <div class="fld" style="margin-bottom:14px">
        <label>Contractor File (.xlsx / .xls) *</label>
        <input type="file" name="file" accept=".xlsx,.xls" required
          style="background:var(--s2);border:1.5px dashed var(--b2);border-radius:8px;padding:14px;cursor:pointer;width:100%">
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('ctr-import-modal')">Cancel</button>
        <button type="submit" class="btn acc">📤 Import</button>
      </div>
    </form>
  </div>
</div>

@if($errors->any())
<script>document.addEventListener('DOMContentLoaded',()=>om('ctr-add-modal'))</script>
@endif

@push('scripts')
<script>
function openCtrEdit(id, data) {
  document.getElementById('ce-name').value      = data.name ?? '';
  document.getElementById('ce-type').value      = data.contractor_type ?? 'individual';
  document.getElementById('ce-pan').value       = data.pan ?? '';
  document.getElementById('ce-gstin').value     = data.gstin ?? '';
  document.getElementById('ce-mobile').value    = data.mobile ?? '';
  document.getElementById('ce-email').value     = data.email ?? '';
  document.getElementById('ce-address').value   = data.address ?? '';
  document.getElementById('ce-active').checked  = !!data.is_active;
  document.getElementById('ce-bank-name').value = data.bank_name ?? '';
  document.getElementById('ce-acc-no').value    = data.bank_account_number ?? '';
  document.getElementById('ce-ifsc').value      = data.ifsc_code ?? '';
  document.getElementById('ctr-edit-form').action = '/contractors/' + id;
  om('ctr-edit-modal');
}
</script>
@endpush
@endsection
