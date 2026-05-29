@extends('layouts.app')
@section('title', 'Bio IDs')

@section('page_actions')
  <a href="{{ route('bio_ids.template') }}" class="btn">📥 Download Template</a>
  <button class="btn" onclick="om('bioid-add-modal')">+ Add Bio ID</button>
  <button class="btn acc" onclick="om('bioid-import-modal')">📤 Import Bio IDs</button>
@endsection

@section('content')

<div class="g4" style="margin-bottom:16px">
  <div class="sc">
    <div class="lbl">Total Bio IDs</div>
    <div class="val">{{ $totalBioIds }}</div>
  </div>
  <div class="sc">
    <div class="lbl">Machines</div>
    <div class="val">{{ $machines->count() }}</div>
    <div style="font-size:10px;color:var(--t3);margin-top:2px">{{ $machines->implode(', ') }}</div>
  </div>
</div>

{{-- Filters --}}
<form method="GET" action="{{ route('bio_ids') }}" class="card"
      style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
  <div class="fld" style="min-width:220px">
    <label>Search (name / emp ID / bio ID)</label>
    <input type="text" name="search" value="{{ request('search') }}" placeholder="e.g. I-20 or Parth">
  </div>
  <div class="fld" style="min-width:130px">
    <label>Machine</label>
    <select name="machine">
      <option value="">All Machines</option>
      @foreach($machines as $m)
      <option value="{{ $m }}" {{ request('machine') === $m ? 'selected' : '' }}>Machine {{ $m }}</option>
      @endforeach
    </select>
  </div>
  <button type="submit" class="btn acc">Filter</button>
  <a href="{{ route('bio_ids') }}" class="btn">Reset</a>
</form>

<div class="card">
  @if($bioIds->isNotEmpty())
  <table>
    <thead>
      <tr>
        <th>Bio ID</th>
        <th>Type</th>
        <th>Number</th>
        <th>Machine</th>
        <th>Employee</th>
        <th>Emp Code</th>
        <th>Status</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      @foreach($bioIds as $bio)
      <tr>
        <td>
          <span class="bx mono" style="background:var(--acc2);color:var(--acc);font-weight:700">
            {{ $bio->bio_id_full }}
          </span>
        </td>
        <td class="mono">{{ $bio->bio_type }}</td>
        <td class="mono">{{ $bio->bio_number }}</td>
        <td>
          <span class="bx" style="font-size:11px">Machine {{ $bio->machine_name }}</span>
        </td>
        <td>
          @if($bio->employee)
            {{ $bio->employee->first_name }} {{ $bio->employee->last_name }}
          @else
            <span style="color:var(--t3)">— unlinked —</span>
          @endif
        </td>
        <td class="mono" style="font-size:12px;color:var(--t3)">
          {{ $bio->employee?->employee_id ?? '—' }}
        </td>
        <td>
          @if($bio->is_active)
            <span class="bx" style="color:var(--grn)">Active</span>
          @else
            <span class="bx" style="color:var(--t3)">Inactive</span>
          @endif
        </td>
        <td style="white-space:nowrap">
          <button class="btn sm" style="font-size:10px"
            onclick="openBioEdit({{ $bio->id }}, '{{ $bio->bio_id_full }}', {{ $bio->is_active ? 'true' : 'false' }})">
            Edit
          </button>
          <form method="POST" action="{{ route('bio_ids.destroy', $bio) }}" style="display:inline">
            @csrf @method('DELETE')
            <button type="button" class="btn sm" style="font-size:10px;color:var(--red)"
              onclick="swConfirm(this.closest('form'), 'Delete Bio ID?', 'Bio ID {{ $bio->bio_id_full }} will be permanently removed.', 'warning', 'Yes, delete', '#dc2626')">Del</button>
          </form>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
  <div style="margin-top:14px">{{ $bioIds->links() }}</div>
  @else
  <div class="empty">
    <div class="ico">🔖</div>
    <p>No Bio IDs registered yet.</p>
    <p style="font-size:12px;color:var(--t3);margin-top:4px">
      Download the template, fill it in, and import using the button above.
    </p>
  </div>
  @endif
</div>

{{-- Add Bio ID Modal --}}
<div class="modal" id="bioid-add-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Add Bio ID</span>
      <button class="mc" onclick="cm('bioid-add-modal')">✕</button>
    </div>
    <form method="POST" action="{{ route('bio_ids.store') }}">
      @csrf
      <div class="fg2">
        <div class="fld full">
          <label>Employee *</label>
          <select name="employee_id" required>
            <option value="">Select employee</option>
            @foreach($employees as $emp)
            <option value="{{ $emp->id }}">{{ $emp->employee_id }} — {{ $emp->first_name }} {{ $emp->last_name }}</option>
            @endforeach
          </select>
        </div>
        <div class="fld">
          <label>Bio ID (format: I-20, II-5, K-3) *</label>
          <input type="text" name="bio_id_full" required placeholder="e.g. I-20">
        </div>
        <div class="fld">
          <label>Machine Name *</label>
          <input type="text" name="machine_name" required placeholder="e.g. 10, 12, K">
        </div>
        <div class="fld full">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="is_active" value="1" checked style="width:16px;height:16px">
            Active
          </label>
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('bioid-add-modal')">Cancel</button>
        <button type="submit" class="btn acc">Add Bio ID</button>
      </div>
    </form>
  </div>
</div>

{{-- Edit Bio ID Modal --}}
<div class="modal" id="bioid-edit-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Edit Bio ID</span>
      <button class="mc" onclick="cm('bioid-edit-modal')">✕</button>
    </div>
    <form method="POST" id="bioid-edit-form">
      @csrf
      <div class="fg2">
        <div class="fld full">
          <label>Bio ID (format: I-20, II-5, III-3) *</label>
          <input type="text" name="bio_id_full" id="bioid-edit-val" required placeholder="e.g. I-20">
        </div>
        <div class="fld full">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="is_active" value="1" id="bioid-edit-active" style="width:16px;height:16px">
            Active
          </label>
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('bioid-edit-modal')">Cancel</button>
        <button type="submit" class="btn acc">Save</button>
      </div>
    </form>
  </div>
</div>

{{-- Import Modal --}}
<div class="modal" id="bioid-import-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Import Bio ID Mappings</span>
      <button class="mc" onclick="cm('bioid-import-modal')">✕</button>
    </div>
    <div style="background:var(--acc2);border:1px solid var(--acc);border-radius:8px;
                padding:12px 14px;margin-bottom:16px;font-size:12px;line-height:1.6;color:var(--t2)">
      <strong>Template columns:</strong>
      <span class="mono" style="font-size:11px">Employee Name · New Emp ID · BioID</span><br>
      BioID can contain multiple comma-separated values: <span class="mono">I-20,II-6,III-8</span>
    </div>
    <form method="POST" action="{{ route('bio_ids.import') }}" enctype="multipart/form-data">
      @csrf
      <div class="fld" style="margin-bottom:14px">
        <label>Bio ID Mapping File (.xlsx / .xls) *</label>
        <input type="file" name="file" accept=".xlsx,.xls" required
          style="background:var(--s2);border:1.5px dashed var(--b2);border-radius:8px;padding:14px;cursor:pointer;width:100%">
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('bioid-import-modal')">Cancel</button>
        <button type="submit" class="btn acc">📤 Import</button>
      </div>
    </form>
  </div>
</div>

@push('scripts')
<script>
function openBioEdit(id, bioId, isActive) {
  document.getElementById('bioid-edit-val').value        = bioId;
  document.getElementById('bioid-edit-active').checked  = isActive;
  document.getElementById('bioid-edit-form').action      = '/bio-ids/' + id;
  om('bioid-edit-modal');
}
</script>
@endpush
@endsection
