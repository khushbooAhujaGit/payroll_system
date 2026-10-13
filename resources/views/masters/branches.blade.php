@extends('layouts.app')
@section('title', 'Branches')

@section('page_actions')
  <button class="btn acc" onclick="om('branch-modal')">+ Add Branch</button>
@endsection

@section('content')

<div class="card">
  <div class="ch">
    <div class="ct">Branches</div>
    <div style="display:flex;align-items:center;gap:10px">
      <button type="button" class="btn sm" id="branch-bulk-del-btn" style="display:none;color:var(--red)" onclick="bulkDelete('branch-bulk-form', 'branch-chk', 'Branches')">Delete Selected (<span id="branch-sel-count">0</span>)</button>
      <span style="font-size:11px;color:var(--t3)">{{ $branches->count() }} total</span>
    </div>
  </div>
  @if($branches->isNotEmpty())
  <form id="branch-bulk-form" method="POST" action="{{ route('branches.bulk-delete') }}">@csrf</form>
  <table>
    <thead><tr>
      <th style="width:30px"><input type="checkbox" id="branch-chk-all" onclick="toggleAll(this, 'branch-chk', 'branch-sel-count', 'branch-bulk-del-btn')"></th>
      <th>Code</th><th>Name</th><th>City</th><th>State</th><th>Status</th><th></th>
    </tr></thead>
    <tbody>
      @foreach($branches as $branch)
      <tr>
        <td><input type="checkbox" class="branch-chk" value="{{ $branch->id }}" onclick="onRowCheck('branch-chk', 'branch-chk-all', 'branch-sel-count', 'branch-bulk-del-btn')"></td>
        <td class="mono">{{ $branch->code ?? '—' }}</td>
        <td>{{ $branch->name }}</td>
        <td>{{ $branch->city ?? '—' }}</td>
        <td>{{ $branch->state ?? '—' }}</td>
        <td><span class="bx {{ $branch->is_active ? 'g' : 'gr' }}">{{ $branch->is_active ? 'Active' : 'Inactive' }}</span></td>
        <td>
          <button type="button" class="btn sm" onclick='openEditBranch(@json($branch))'>Edit</button>
          <form method="POST" action="{{ route('branches.destroy', $branch) }}" style="display:inline">
            @csrf @method('DELETE')
            <button type="button" class="btn sm" style="color:var(--red)"
              onclick="swConfirm(this.closest('form'), 'Delete Branch?',
                'Delete {{ addslashes($branch->name) }}? This cannot be undone.',
                'warning', 'Yes, delete', '#dc2626')">Del</button>
          </form>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @else
  <div class="empty"><div class="ico">🏢</div><p>No branches yet.</p></div>
  @endif
</div>

{{-- Add Branch Modal --}}
<div class="modal" id="branch-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Add Branch</span>
      <button class="mc" onclick="cm('branch-modal')">✕</button>
    </div>
    <form method="POST" action="{{ route('masters.branches.store') }}">
      @csrf
      <div class="fg2">
        <div class="fld">
          <label>Name *</label>
          <input type="text" name="name" required value="{{ old('name') }}">
        </div>
        <div class="fld">
          <label>Code</label>
          <input type="text" name="code" maxlength="20" value="{{ old('code') }}">
        </div>
        <div class="fld">
          <label>City</label>
          <input type="text" name="city" value="{{ old('city') }}">
        </div>
        <div class="fld">
          <label>State</label>
          <input type="text" name="state" value="{{ old('state') }}">
        </div>
        <div class="fld full">
          <label>Address</label>
          <input type="text" name="address" value="{{ old('address') }}">
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('branch-modal')">Cancel</button>
        <button type="submit" class="btn acc">Create</button>
      </div>
    </form>
  </div>
</div>

{{-- Edit Branch Modal --}}
<div class="modal" id="branch-edit-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Edit Branch</span>
      <button class="mc" onclick="cm('branch-edit-modal')">✕</button>
    </div>
    <form method="POST" id="branch-edit-form">
      @csrf @method('PUT')
      <div class="fg2">
        <div class="fld">
          <label>Name *</label>
          <input type="text" name="name" id="branch-e-name" required>
        </div>
        <div class="fld">
          <label>Code</label>
          <input type="text" name="code" id="branch-e-code" maxlength="20">
        </div>
        <div class="fld">
          <label>City</label>
          <input type="text" name="city" id="branch-e-city">
        </div>
        <div class="fld">
          <label>State</label>
          <input type="text" name="state" id="branch-e-state">
        </div>
        <div class="fld full">
          <label>Address</label>
          <input type="text" name="address" id="branch-e-address">
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('branch-edit-modal')">Cancel</button>
        <button type="submit" class="btn acc">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditBranch(b) {
  document.getElementById('branch-edit-form').action = '{{ url('/branches') }}/' + b.id;
  document.getElementById('branch-e-name').value = b.name;
  document.getElementById('branch-e-code').value = b.code || '';
  document.getElementById('branch-e-city').value = b.city || '';
  document.getElementById('branch-e-state').value = b.state || '';
  document.getElementById('branch-e-address').value = b.address || '';
  om('branch-edit-modal');
}
</script>
@endsection
