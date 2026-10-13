@extends('layouts.app')
@section('title', 'Departments')

@section('page_actions')
  <button class="btn acc" onclick="om('dept-modal')">+ Add Department</button>
  <button class="btn" onclick="om('upload-modal')">📤 Bulk Upload</button>
@endsection

@section('content')

<div class="card">
  <div class="ch">
    <div class="ct">Departments</div>
    <div style="display:flex;align-items:center;gap:10px">
      <button type="button" class="btn sm" id="dept-bulk-del-btn" style="display:none;color:var(--red)" onclick="bulkDelete('dept-bulk-form', 'dept-chk', 'Departments')">Delete Selected (<span id="dept-sel-count">0</span>)</button>
      <span style="font-size:11px;color:var(--t3)">{{ $departments->count() }} total</span>
    </div>
  </div>
  @if($departments->isNotEmpty())
  <form id="dept-bulk-form" method="POST" action="{{ route('departments.bulk-delete') }}">@csrf</form>
  <table>
    <thead><tr>
      <th style="width:30px"><input type="checkbox" id="dept-chk-all" onclick="toggleAll(this, 'dept-chk', 'dept-sel-count', 'dept-bulk-del-btn')"></th>
      <th>Code</th><th>Name</th><th>Status</th><th></th>
    </tr></thead>
    <tbody>
      @foreach($departments as $dept)
      <tr>
        <td><input type="checkbox" class="dept-chk" value="{{ $dept->id }}" onclick="onRowCheck('dept-chk', 'dept-chk-all', 'dept-sel-count', 'dept-bulk-del-btn')"></td>
        <td class="mono">{{ $dept->code }}</td>
        <td>{{ $dept->name }}</td>
        <td><span class="bx {{ $dept->is_active ? 'g' : 'gr' }}">{{ $dept->is_active ? 'Active' : 'Inactive' }}</span></td>
        <td>
          <button type="button" class="btn sm" onclick='openEditDept(@json($dept))'>Edit</button>
          <form method="POST" action="{{ route('departments.destroy', $dept) }}" style="display:inline">
            @csrf @method('DELETE')
            <button type="button" class="btn sm" style="color:var(--red)"
              onclick="swConfirm(this.closest('form'), 'Delete Department?',
                'Delete {{ addslashes($dept->name) }}? This cannot be undone.',
                'warning', 'Yes, delete', '#dc2626')">Del</button>
          </form>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @else
  <div class="empty"><div class="ico">🏗️</div><p>No departments yet.</p></div>
  @endif
</div>

{{-- Add Department Modal --}}
<div class="modal" id="dept-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Add Department</span>
      <button class="mc" onclick="cm('dept-modal')">✕</button>
    </div>
    <form method="POST" action="{{ route('departments.store') }}">
      @csrf
      <div class="fg2">
        <div class="fld full">
          <label>Company *</label>
          <select name="company_id" required>
            <option value="">Select Company</option>
            @foreach($companies as $co)
            <option value="{{ $co->id }}" {{ old('company_id', $companyId) == $co->id ? 'selected' : '' }}>{{ $co->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="fld">
          <label>Name *</label>
          <input type="text" name="name" required value="{{ old('name') }}">
        </div>
        <div class="fld">
          <label>Code *</label>
          <input type="text" name="code" required maxlength="10" value="{{ old('code') }}" placeholder="HR, FIN, ENG…">
        </div>
        <div class="fld full">
          <label>Parent Department</label>
          <select name="parent_id">
            <option value="">None (top-level)</option>
            @foreach($departments as $d)
            <option value="{{ $d->id }}">{{ $d->name }}</option>
            @endforeach
          </select>
          <span style="font-size:10px;color:var(--t3)">Only shows departments for the company this page is currently viewing — if you pick a different Company above, set Parent Department from that company's own Departments page instead.</span>
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('dept-modal')">Cancel</button>
        <button type="submit" class="btn acc">Create</button>
      </div>
    </form>
  </div>
</div>

{{-- Edit Department Modal --}}
<div class="modal" id="dept-edit-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Edit Department</span>
      <button class="mc" onclick="cm('dept-edit-modal')">✕</button>
    </div>
    <form method="POST" id="dept-edit-form">
      @csrf @method('PUT')
      <div class="fg2">
        <div class="fld">
          <label>Name *</label>
          <input type="text" name="name" id="dept-e-name" required>
        </div>
        <div class="fld">
          <label>Code *</label>
          <input type="text" name="code" id="dept-e-code" required maxlength="10">
        </div>
        <div class="fld full">
          <label>Parent Department</label>
          <select name="parent_id" id="dept-e-parent">
            <option value="">None (top-level)</option>
            @foreach($departments as $d)
            <option value="{{ $d->id }}">{{ $d->name }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('dept-edit-modal')">Cancel</button>
        <button type="submit" class="btn acc">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditDept(d) {
  document.getElementById('dept-edit-form').action = '{{ url('/departments') }}/' + d.id;
  document.getElementById('dept-e-name').value = d.name;
  document.getElementById('dept-e-code').value = d.code;
  document.getElementById('dept-e-parent').value = d.parent_id || '';
  om('dept-edit-modal');
}
</script>
@endsection

@include('partials.upload-modal', [
    'uploadId'      => 'upload-modal',
    'uploadTitle'   => 'Bulk Upload Departments & Designations',
    'uploadRoute'   => 'departments.import',
    'templateRoute' => 'departments.template',
    'uploadHint'    => 'Sheet 1 — Departments: Name, Code, Parent Department Code (optional). Sheet 2 — Designations: Name, Code, Level (optional). One row per entry.',
])
