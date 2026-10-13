@extends('layouts.app')
@section('title', 'Locations')

@section('page_actions')
  <button class="btn acc" onclick="om('location-modal')">+ Add Location</button>
@endsection

@section('content')

<div class="card">
  <div class="ch">
    <div class="ct">Locations</div>
    <div style="display:flex;align-items:center;gap:10px">
      <button type="button" class="btn sm" id="location-bulk-del-btn" style="display:none;color:var(--red)" onclick="bulkDelete('location-bulk-form', 'location-chk', 'Locations')">Delete Selected (<span id="location-sel-count">0</span>)</button>
      <span style="font-size:11px;color:var(--t3)">{{ $locations->count() }} total</span>
    </div>
  </div>
  @if($locations->isNotEmpty())
  <form id="location-bulk-form" method="POST" action="{{ route('locations.bulk-delete') }}">@csrf</form>
  <table>
    <thead><tr>
      <th style="width:30px"><input type="checkbox" id="location-chk-all" onclick="toggleAll(this, 'location-chk', 'location-sel-count', 'location-bulk-del-btn')"></th>
      <th>Name</th><th>Status</th><th></th>
    </tr></thead>
    <tbody>
      @foreach($locations as $location)
      <tr>
        <td><input type="checkbox" class="location-chk" value="{{ $location->id }}" onclick="onRowCheck('location-chk', 'location-chk-all', 'location-sel-count', 'location-bulk-del-btn')"></td>
        <td>{{ $location->name }}</td>
        <td><span class="bx {{ $location->is_active ? 'g' : 'gr' }}">{{ $location->is_active ? 'Active' : 'Inactive' }}</span></td>
        <td>
          <button type="button" class="btn sm" onclick='openEditLocation(@json($location))'>Edit</button>
          <form method="POST" action="{{ route('locations.destroy', $location) }}" style="display:inline">
            @csrf @method('DELETE')
            <button type="button" class="btn sm" style="color:var(--red)"
              onclick="swConfirm(this.closest('form'), 'Delete Location?',
                'Delete {{ addslashes($location->name) }}? This cannot be undone.',
                'warning', 'Yes, delete', '#dc2626')">Del</button>
          </form>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @else
  <div class="empty"><div class="ico">📍</div><p>No locations yet.</p></div>
  @endif
</div>

{{-- Add Location Modal --}}
<div class="modal" id="location-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Add Location</span>
      <button class="mc" onclick="cm('location-modal')">✕</button>
    </div>
    <form method="POST" action="{{ route('masters.locations.store') }}">
      @csrf
      <div class="fg2">
        <div class="fld full">
          <label>Name *</label>
          <select name="name" required>
            <option value="">Select City</option>
            @foreach(\App\Models\Location::CITIES as $city)
              <option value="{{ $city }}" {{ old('name') === $city ? 'selected' : '' }}>{{ $city }}</option>
            @endforeach
          </select>
        </div>
        <div class="fld full">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }} style="width:16px;height:16px">
            Active
          </label>
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('location-modal')">Cancel</button>
        <button type="submit" class="btn acc">Create</button>
      </div>
    </form>
  </div>
</div>

{{-- Edit Location Modal --}}
<div class="modal" id="location-edit-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Edit Location</span>
      <button class="mc" onclick="cm('location-edit-modal')">✕</button>
    </div>
    <form method="POST" id="location-edit-form">
      @csrf @method('PUT')
      <div class="fg2">
        <div class="fld full">
          <label>Name *</label>
          <select name="name" id="location-e-name" required>
            <option value="">Select City</option>
            @foreach(\App\Models\Location::CITIES as $city)
              <option value="{{ $city }}">{{ $city }}</option>
            @endforeach
          </select>
        </div>
        <div class="fld full">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="is_active" id="location-e-active" value="1" style="width:16px;height:16px">
            Active
          </label>
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('location-edit-modal')">Cancel</button>
        <button type="submit" class="btn acc">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditLocation(l) {
  document.getElementById('location-edit-form').action = '{{ url('/locations') }}/' + l.id;
  const nameSel = document.getElementById('location-e-name');
  nameSel.value = l.name;
  window._ssSync?.(nameSel); // sync the searchable-select display — see layouts/app.blade.php
  document.getElementById('location-e-active').checked = !!l.is_active;
  om('location-edit-modal');
}
</script>
@endsection
