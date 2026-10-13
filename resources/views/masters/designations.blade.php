@extends('layouts.app')
@section('title', 'Designations')

@section('page_actions')
  <button class="btn acc" onclick="om('desig-modal')">+ Add Designation</button>
@endsection

@section('content')

<div class="card">
  <div class="ch">
    <div class="ct">Designations</div>
    <div style="display:flex;align-items:center;gap:10px">
      <button type="button" class="btn sm" id="desig-bulk-del-btn" style="display:none;color:var(--red)" onclick="bulkDelete('desig-bulk-form', 'desig-chk', 'Designations')">Delete Selected (<span id="desig-sel-count">0</span>)</button>
      <span style="font-size:11px;color:var(--t3)">{{ $designations->count() }} total</span>
    </div>
  </div>
  @if($designations->isNotEmpty())
  <form id="desig-bulk-form" method="POST" action="{{ route('designations.bulk-delete') }}">@csrf</form>
  <table>
    <thead><tr>
      <th style="width:30px"><input type="checkbox" id="desig-chk-all" onclick="toggleAll(this, 'desig-chk', 'desig-sel-count', 'desig-bulk-del-btn')"></th>
      <th>Code</th><th>Name</th><th>Level</th><th></th>
    </tr></thead>
    <tbody>
      @foreach($designations as $desig)
      <tr>
        <td><input type="checkbox" class="desig-chk" value="{{ $desig->id }}" onclick="onRowCheck('desig-chk', 'desig-chk-all', 'desig-sel-count', 'desig-bulk-del-btn')"></td>
        <td class="mono">{{ $desig->code }}</td>
        <td>{{ $desig->name }}</td>
        <td>{{ $desig->level ?? '—' }}</td>
        <td>
          <button type="button" class="btn sm" onclick='openEditDesig(@json($desig))'>Edit</button>
          <form method="POST" action="{{ route('designations.destroy', $desig) }}" style="display:inline">
            @csrf @method('DELETE')
            <button type="button" class="btn sm" style="color:var(--red)"
              onclick="swConfirm(this.closest('form'), 'Delete Designation?',
                'Delete {{ addslashes($desig->name) }}? This cannot be undone.',
                'warning', 'Yes, delete', '#dc2626')">Del</button>
          </form>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @else
  <div class="empty"><div class="ico">🎯</div><p>No designations yet.</p></div>
  @endif
</div>

{{-- Add Designation Modal --}}
<div class="modal" id="desig-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Add Designation</span>
      <button class="mc" onclick="cm('desig-modal')">✕</button>
    </div>
    <form method="POST" action="{{ route('masters.designations.store') }}">
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
          <input type="text" name="code" required maxlength="10" value="{{ old('code') }}">
        </div>
        <div class="fld full">
          <label>Level (optional)</label>
          <input type="number" name="level" min="1" value="{{ old('level') }}" placeholder="1 = intern, 10 = CEO">
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('desig-modal')">Cancel</button>
        <button type="submit" class="btn acc">Create</button>
      </div>
    </form>
  </div>
</div>

{{-- Edit Designation Modal --}}
<div class="modal" id="desig-edit-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Edit Designation</span>
      <button class="mc" onclick="cm('desig-edit-modal')">✕</button>
    </div>
    <form method="POST" id="desig-edit-form">
      @csrf @method('PUT')
      <div class="fg2">
        <div class="fld">
          <label>Name *</label>
          <input type="text" name="name" id="desig-e-name" required>
        </div>
        <div class="fld">
          <label>Code *</label>
          <input type="text" name="code" id="desig-e-code" required maxlength="10">
        </div>
        <div class="fld full">
          <label>Level (optional)</label>
          <input type="number" name="level" id="desig-e-level" min="1" placeholder="1 = intern, 10 = CEO">
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('desig-edit-modal')">Cancel</button>
        <button type="submit" class="btn acc">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditDesig(d) {
  document.getElementById('desig-edit-form').action = '{{ url('/designations') }}/' + d.id;
  document.getElementById('desig-e-name').value = d.name;
  document.getElementById('desig-e-code').value = d.code;
  document.getElementById('desig-e-level').value = d.level || '';
  om('desig-edit-modal');
}
</script>
@endsection
