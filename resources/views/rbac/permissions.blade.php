@extends('layouts.app')
@section('title', 'Role & Permissions')

@section('page_actions')
  <button class="btn acc" onclick="om('permission-modal')">+ Add Permission</button>
@endsection

@section('content')

  @include('rbac._nav')

  <form method="GET" action="{{ route('rbac.permissions') }}" class="card" data-ajax-filter
    style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
    <div class="fld" style="flex:1;min-width:220px">
      <label>Search</label>
      <input type="text" name="search" value="{{ request('search') }}" placeholder="Permission or group name…">
    </div>
    <button type="submit" class="btn acc">Filter</button>
    <a href="{{ route('rbac.permissions') }}" class="btn" data-ajax-reset>Reset</a>
  </form>

  <div id="ajax-results">
    <div class="card">
      <div class="ch"><div class="ct">Permissions</div></div>
      @if($permissions->isEmpty())
        <div class="empty">
          <div class="ico">🔑</div>
          @if(request('search'))
            <p>No permissions match "{{ request('search') }}".</p>
          @else
            <p>No permissions yet. <a href="#" onclick="om('permission-modal')">Create one to get started.</a></p>
          @endif
        </div>
      @else
        <table>
          <thead>
            <tr>
              <th>Permission</th>
              <th>Group</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @foreach($permissions as $perm)
              <tr>
                <td style="font-weight:600">{{ $perm->name }}</td>
                <td style="font-size:12px;color:var(--t3)">{{ $perm->group->name ?? '—' }}</td>
                <td style="display:flex;gap:6px">
                  <button type="button" class="btn sm" onclick="om('edit-permission-modal-{{ $perm->id }}')">Edit</button>
                  <form method="POST" action="{{ route('rbac.permissions.destroy', $perm) }}" style="display:inline">
                    @csrf @method('DELETE')
                    <button type="button" class="btn sm" style="color:var(--red)"
                      onclick="swConfirm(this.closest('form'), 'Delete Permission?', 'Delete {{ addslashes($perm->name) }}? This removes it from any role that has it.', 'warning', 'Yes, delete', '#dc2626')">Del</button>
                  </form>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
        <div style="margin-top:14px;padding:0 16px 16px">{{ $permissions->links() }}</div>
      @endif
    </div>

{{-- Add Permission Modal --}}
<div class="modal" id="permission-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Add Permission</span>
      <button class="mc" onclick="cm('permission-modal')">✕</button>
    </div>
    @if($allGroups->isEmpty())
      <div style="padding:16px">
        <p style="color:var(--t3);font-size:13px">You need at least one Permission Group before adding permissions.</p>
        <div class="mf">
          <button type="button" class="btn" onclick="cm('permission-modal')">Close</button>
          <a class="btn acc" href="{{ route('rbac.permission_groups') }}">Create a Group First</a>
        </div>
      </div>
    @else
    <form method="POST" action="{{ route('rbac.permissions.store') }}">
      @csrf
      <input type="hidden" name="_form" value="permission">
      <div class="fld" style="margin-bottom:16px">
        <label class="req">Group</label>
        <select name="permission_group_id" required @error('permission_group_id') class="inp-err" @enderror>
          <option value="">Select a group…</option>
          @foreach($allGroups as $group)
            <option value="{{ $group->id }}" {{ (old('_form') === 'permission' && old('permission_group_id') == $group->id) ? 'selected' : '' }}>{{ $group->name }}</option>
          @endforeach
        </select>
        @if(old('_form') === 'permission')
          @error('permission_group_id') <div class="ferr">{{ $message }}</div> @enderror
        @endif
      </div>
      <div class="fld">
        <label class="req">Permission Code</label>
        <input type="text" name="name" required maxlength="150" pattern="[a-z0-9]+(_[a-z0-9]+)+"
          class="mono @error('name') inp-err @enderror" style="letter-spacing:.02em"
          placeholder="employee_view" value="{{ old('_form') === 'permission' ? old('name') : '' }}"
          oninput="this.value = this.value.toLowerCase().replace(/[^a-z0-9_]/g, '')">
        @if(old('_form') === 'permission')
          @error('name') <div class="ferr">{{ $message }}</div> @enderror
        @endif
      </div>
      <div style="background:var(--bg2,#f5f7fa);border:1px solid var(--b1);border-radius:8px;padding:10px 12px;font-size:11.5px;color:var(--t3);line-height:1.6;margin-top:2px">
        <strong style="color:var(--t2)">Format:</strong> lowercase words separated by underscores, e.g. <span class="mono" style="background:var(--s2);padding:1px 6px;border-radius:4px">module_action</span>.
        This is the exact code the app checks against — it can't be renamed later without also updating the code.
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('permission-modal')">Cancel</button>
        <button type="submit" class="btn acc">Create Permission</button>
      </div>
    </form>
    @endif
  </div>
</div>

{{-- Edit Permission Modals (one per permission on this page) --}}
@foreach($permissions as $perm)
  <div class="modal" id="edit-permission-modal-{{ $perm->id }}">
    <div class="mb">
      <div class="mh">
        <span class="mt">Edit Permission</span>
        <button class="mc" onclick="cm('edit-permission-modal-{{ $perm->id }}')">✕</button>
      </div>
      <form method="POST" action="{{ route('rbac.permissions.update', $perm) }}">
        @csrf @method('PUT')
        <input type="hidden" name="_form" value="edit_permission">
        <input type="hidden" name="_permission_id" value="{{ $perm->id }}">
        @php $isThisEditErr = old('_form') === 'edit_permission' && (int) old('_permission_id') === $perm->id; @endphp
        <div class="fld" style="margin-bottom:16px">
          <label class="req">Group</label>
          <select name="permission_group_id" required @if($isThisEditErr) @error('permission_group_id') class="inp-err" @enderror @endif>
            @foreach($allGroups as $group)
              <option value="{{ $group->id }}" {{ ($isThisEditErr ? old('permission_group_id') == $group->id : $perm->permission_group_id == $group->id) ? 'selected' : '' }}>{{ $group->name }}</option>
            @endforeach
          </select>
          @if($isThisEditErr)
            @error('permission_group_id') <div class="ferr">{{ $message }}</div> @enderror
          @endif
        </div>
        <div class="fld">
          <label class="req">Permission Code</label>
          <input type="text" name="name" required maxlength="150" pattern="[a-z0-9]+(_[a-z0-9]+)+"
            class="mono @if($isThisEditErr) @error('name') inp-err @enderror @endif" style="letter-spacing:.02em"
            value="{{ $isThisEditErr ? old('name') : $perm->name }}"
            oninput="this.value = this.value.toLowerCase().replace(/[^a-z0-9_]/g, '')">
          @if($isThisEditErr)
            @error('name') <div class="ferr">{{ $message }}</div> @enderror
          @endif
        </div>
        <div style="background:var(--bg2,#f5f7fa);border:1px solid var(--b1);border-radius:8px;padding:10px 12px;font-size:11.5px;color:var(--t3);line-height:1.6;margin-top:2px">
          Renaming this code doesn't update any <span class="mono">$this-&gt;authorize()</span>/<span class="mono">@@can</span> checks already written against the old name in the codebase.
        </div>
        <div class="mf">
          <button type="button" class="btn" onclick="cm('edit-permission-modal-{{ $perm->id }}')">Cancel</button>
          <button type="submit" class="btn acc">Save</button>
        </div>
      </form>
    </div>
  </div>
@endforeach
  </div> {{-- #ajax-results --}}

@if($errors->any())
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      @if(old('_form') === 'permission')
        om('permission-modal');
      @elseif(old('_form') === 'edit_permission')
        om('edit-permission-modal-{{ old('_permission_id') }}');
      @endif
    });
  </script>
@endif

@endsection
