@extends('layouts.app')
@section('title', 'Role & Permissions')

@section('page_actions')
  <button class="btn acc" onclick="om('role-modal')">+ Add Role</button>
@endsection

@section('content')

  @include('rbac._nav')

    <div class="card" style="margin-bottom:14px">
      <div class="ch"><div class="ct">Roles</div></div>
      <table>
        <thead>
          <tr>
            <th>Role</th>
            <th>Permissions</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach($roles as $role)
            <tr>
              <td style="font-weight:600">{{ $role->name }}@if($role->name === 'Admin') <span style="opacity:.6;font-weight:400">(all access)</span>@endif</td>
              <td style="font-size:12px;color:var(--t3)">{{ $role->name === 'Admin' ? '—' : $role->permissions->count() }}</td>
              <td>
                @if($role->name !== 'Admin')
                  <form method="POST" action="{{ route('rbac.roles.destroy', $role) }}" style="display:inline">
                    @csrf @method('DELETE')
                    <button type="button" class="btn sm" style="color:var(--red)"
                      onclick="swConfirm(this.closest('form'), 'Delete Role?', 'Delete {{ addslashes($role->name) }}? This removes it from any user who has it.', 'warning', 'Yes, delete', '#dc2626')">Del</button>
                  </form>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="card" style="margin-bottom:14px">
      <div class="ch"><div class="ct">Select a role to view / edit its permissions</div></div>
      <div style="padding:12px 16px;display:flex;gap:8px;flex-wrap:wrap">
        @foreach($roles as $role)
          <button type="button" class="btn sm role-select-btn {{ $loop->first ? 'on' : '' }}"
            data-role-id="{{ $role->id }}" onclick="selectRole({{ $role->id }})">
            {{ $role->name }}
            @if($role->name === 'Admin')<span style="opacity:.6">(all access)</span>@endif
          </button>
        @endforeach
      </div>
    </div>

    @foreach($roles as $role)
      <div class="card role-matrix" id="role-matrix-{{ $role->id }}" style="{{ $loop->first ? '' : 'display:none' }}">
        <div class="ch">
          <div class="ct">{{ $role->name }} — Permissions</div>
          @if($role->name === 'Admin')
            <div style="font-size:12px;color:var(--t3)">Admin always has full access regardless of these checkboxes — saving here just keeps the permission records in sync for reference.</div>
          @endif
        </div>

        <form method="POST" action="{{ route('rbac.roles.permissions', $role) }}">
          @csrf

          <div style="max-height:600px;overflow-y:auto" id="role-matrix-body-{{ $role->id }}">
            <table>
              <thead>
                <tr>
                  <th style="width:48px">Sno</th>
                  <th style="width:180px">Permission Group</th>
                  <th style="text-align:center;width:70px">All</th>
                  <th>Permissions</th>
                </tr>
              </thead>
              <tbody>
                @forelse($permissionGroups as $group)
                  @php
                    $groupPermIds  = $group->permissions->pluck('id');
                    $allChecked    = $role->name === 'Admin'
                        || ($groupPermIds->isNotEmpty() && $groupPermIds->every(fn($id) => $role->permissions->contains('id', $id)));
                    $rowId         = 'role-'.$role->id.'-group-'.$group->id;
                  @endphp
                  <tr>
                    <td style="color:var(--t3);font-size:12px">{{ $loop->iteration }}</td>
                    <td style="font-weight:600">{{ $group->name }}</td>
                    <td style="text-align:center">
                      <input type="checkbox" id="{{ $rowId }}-all"
                        {{ $allChecked ? 'checked' : '' }}
                        onchange="toggleAllIn('{{ $rowId }}', this.checked)">
                    </td>
                    <td id="{{ $rowId }}">
                      @forelse($group->permissions as $perm)
                        <label style="display:inline-flex;align-items:center;gap:6px;font-weight:400;white-space:nowrap;margin:3px 18px 3px 0">
                          <input type="checkbox"
                            name="permissions[]"
                            value="{{ $perm->id }}"
                            {{ ($role->name === 'Admin' || $role->permissions->contains('id', $perm->id)) ? 'checked' : '' }}
                            onchange="syncGroupAll('{{ $rowId }}')">
                          {{ Str::of($perm->name)->replace('_', ' ')->title() }}
                        </label>
                      @empty
                        <span style="color:var(--t3);font-size:12px">No permissions in this group yet.</span>
                      @endforelse
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="4" style="color:var(--t3);font-size:12px;padding:16px">No permission groups yet — create one first.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <div class="mf" style="padding:12px 16px">
            <button type="submit" class="btn acc">Save {{ $role->name }} Permissions</button>
          </div>
        </form>
      </div>
    @endforeach

{{-- Add Role Modal --}}
<div class="modal" id="role-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Add Role</span>
      <button class="mc" onclick="cm('role-modal')">✕</button>
    </div>
    <form method="POST" action="{{ route('rbac.roles.store') }}">
      @csrf
      <input type="hidden" name="_form" value="role">
      <div class="fld">
        <label class="req">Role Name</label>
        <input type="text" name="name" required value="{{ old('name') }}" @error('name') class="inp-err" @enderror>
        @error('name') <div class="ferr">{{ $message }}</div> @enderror
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('role-modal')">Cancel</button>
        <button type="submit" class="btn acc">Create Role</button>
      </div>
    </form>
  </div>
</div>

@if($errors->any() && old('_form') === 'role')
  <script>
    document.addEventListener('DOMContentLoaded', () => om('role-modal'));
  </script>
@endif

<script>
  function selectRole(id) {
    document.querySelectorAll('.role-matrix').forEach(el => el.style.display = 'none');
    document.getElementById('role-matrix-' + id).style.display = '';
    document.querySelectorAll('.role-select-btn').forEach(el => el.classList.toggle('on', String(el.dataset.roleId) === String(id)));
  }
  function toggleAllIn(containerId, checked) {
    document.querySelectorAll('#' + containerId + ' input[type=checkbox]:not(:disabled)').forEach(cb => cb.checked = checked);
  }
  function syncGroupAll(rowId) {
    const boxes = document.querySelectorAll('#' + rowId + ' input[type=checkbox]');
    const allBox = document.getElementById(rowId + '-all');
    if (!allBox || !boxes.length) return;
    allBox.checked = Array.from(boxes).every(cb => cb.checked);
  }
</script>

@endsection
