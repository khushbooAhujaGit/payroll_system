@extends('layouts.app')
@section('title', 'Role & Permissions')

@section('page_actions')
  <button class="btn acc" onclick="om('group-modal')">+ Add Permission Group</button>
@endsection

@section('content')

  @include('rbac._nav')

    <div class="card">
      <div class="ch"><div class="ct">Permission Groups</div></div>
      @if($permissionGroups->isEmpty())
        <div class="empty">
          <div class="ico">🗂️</div>
          <p>No permission groups yet. <a href="#" onclick="om('group-modal')">Create one to get started.</a></p>
        </div>
      @else
        <table>
          <thead>
            <tr>
              <th>Group</th>
              <th>Permissions</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @foreach($permissionGroups as $group)
              <tr>
                <td style="font-weight:600">{{ $group->name }}</td>
                <td style="font-size:12px;color:var(--t3)">{{ $group->permissions_count }}</td>
                <td style="display:flex;gap:6px">
                  <button type="button" class="btn sm" onclick="om('edit-group-modal-{{ $group->id }}')">Edit</button>
                  @if($group->permissions_count === 0)
                    <form method="POST" action="{{ route('rbac.permission_groups.destroy', $group) }}" style="display:inline">
                      @csrf @method('DELETE')
                      <button type="button" class="btn sm" style="color:var(--red)"
                        onclick="swConfirm(this.closest('form'), 'Delete Group?', 'Delete the empty group {{ addslashes($group->name) }}?', 'warning', 'Yes, delete', '#dc2626')">Del</button>
                    </form>
                  @else
                    <span style="font-size:11px;color:var(--t3);align-self:center">Delete its permissions first</span>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
        <div style="margin-top:14px;padding:0 16px 16px">{{ $permissionGroups->links() }}</div>
      @endif
    </div>

{{-- Add Permission Group Modal --}}
<div class="modal" id="group-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Add Permission Group</span>
      <button class="mc" onclick="cm('group-modal')">✕</button>
    </div>
    <form method="POST" action="{{ route('rbac.permission_groups.store') }}">
      @csrf
      <input type="hidden" name="_form" value="group">
      <div class="fld">
        <label class="req">Group Name</label>
        <input type="text" name="name" required maxlength="100" placeholder="e.g. Employee, Payroll, Reports"
          value="{{ old('_form') === 'group' ? old('name') : '' }}" @error('name') class="inp-err" @enderror>
        @error('name') <div class="ferr">{{ $message }}</div> @enderror
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('group-modal')">Cancel</button>
        <button type="submit" class="btn acc">Create Group</button>
      </div>
    </form>
  </div>
</div>

{{-- Edit Permission Group Modals (one per group) --}}
@foreach($permissionGroups as $group)
  <div class="modal" id="edit-group-modal-{{ $group->id }}">
    <div class="mb">
      <div class="mh">
        <span class="mt">Rename Group</span>
        <button class="mc" onclick="cm('edit-group-modal-{{ $group->id }}')">✕</button>
      </div>
      <form method="POST" action="{{ route('rbac.permission_groups.update', $group) }}">
        @csrf @method('PUT')
        <input type="hidden" name="_form" value="edit_group">
        <input type="hidden" name="_group_id" value="{{ $group->id }}">
        <div class="fld">
          <label class="req">Group Name</label>
          <input type="text" name="name" required maxlength="100"
            value="{{ (old('_form') === 'edit_group' && (int) old('_group_id') === $group->id) ? old('name') : $group->name }}">
          @if(old('_form') === 'edit_group' && (int) old('_group_id') === $group->id)
            @error('name') <div class="ferr">{{ $message }}</div> @enderror
          @endif
        </div>
        <div class="mf">
          <button type="button" class="btn" onclick="cm('edit-group-modal-{{ $group->id }}')">Cancel</button>
          <button type="submit" class="btn acc">Save</button>
        </div>
      </form>
    </div>
  </div>
@endforeach

@if($errors->any())
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      @if(old('_form') === 'group')
        om('group-modal');
      @elseif(old('_form') === 'edit_group')
        om('edit-group-modal-{{ old('_group_id') }}');
      @endif
    });
  </script>
@endif

@endsection
