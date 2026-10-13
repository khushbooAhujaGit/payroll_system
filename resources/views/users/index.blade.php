@extends('layouts.app')
@section('title', 'User Creation')

@section('page_actions')
  <button class="btn acc" onclick="om('user-modal')">+ Add User</button>
@endsection

@section('content')

  <form method="GET" action="{{ route('users.index') }}" class="card" data-ajax-filter
    style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
    <div class="fld" style="flex:1;min-width:220px">
      <label>Search</label>
      <input type="text" name="search" value="{{ request('search') }}" placeholder="Name, email, or employee code…">
    </div>
    <div class="fld" style="min-width:160px">
      <label>Status</label>
      <select name="status">
        <option value="">All</option>
        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
      </select>
    </div>
    <button type="submit" class="btn acc">Filter</button>
    <a href="{{ route('users.index') }}" class="btn" data-ajax-reset>Reset</a>
  </form>

  <div id="ajax-results">
    <div class="card" style="margin-bottom:14px">
      <div class="ch"><div class="ct">Users</div></div>
      @if($users->isEmpty())
        <div class="empty">
          <div class="ico">👤</div>
          @if(request('search'))
            <p>No users match "{{ request('search') }}".</p>
          @else
            <p>No users yet. <a href="#" onclick="om('user-modal')">Create one to get started.</a></p>
          @endif
        </div>
      @else
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Employee</th>
              <th>Roles</th>
              <th>Status</th>
              <th>Reimbursement Approver</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @foreach($users as $user)
              <tr>
                <td style="font-weight:600">{{ $user->name }}</td>
                <td style="font-size:12px">{{ $user->email }}</td>
                <td style="font-size:12px;color:var(--t3)">{{ $user->employee?->full_name ?? '—' }}</td>
                <td style="font-size:12px">
                  @forelse($user->roles as $role)
                    <span style="display:inline-block;background:var(--bg2,#eef1f5);border-radius:12px;padding:2px 8px;font-size:11px;margin:2px">{{ $role->name }}</span>
                  @empty
                    <span style="color:var(--t3)">No roles assigned</span>
                  @endforelse
                </td>
                <td>
                  @if($user->is_active)
                    <span style="display:inline-block;background:#dcfce7;color:#166534;border-radius:12px;padding:2px 10px;font-size:11px;font-weight:600">Active</span>
                  @else
                    <span style="display:inline-block;background:#fee2e2;color:#991b1b;border-radius:12px;padding:2px 10px;font-size:11px;font-weight:600">Inactive</span>
                  @endif
                </td>
                <td>
                  <form method="POST" action="{{ route('users.toggle-reimbursement-approver', $user) }}" style="display:inline">
                    @csrf
                    @if($user->hasPermissionTo('reimbursment_approve'))
                      <button type="button" class="btn sm"
                        onclick="swConfirm(this.closest('form'), 'Remove approver?', 'Reimbursement claims will no longer route to {{ addslashes($user->name) }}.', 'warning', 'Yes, remove', '#dc2626')">
                        <span style="display:inline-block;background:#dcfce7;color:#166534;border-radius:12px;padding:2px 10px;font-size:11px;font-weight:600">Approver</span>
                      </button>
                    @else
                      <button type="button" class="btn sm"
                        onclick="swConfirm(this.closest('form'), 'Make this the approver?', 'All reimbursement claims will route to {{ addslashes($user->name) }} for approval, regardless of role.', 'question', 'Yes, set as approver', '#2563eb')">
                        Set as Approver
                      </button>
                    @endif
                  </form>
                </td>
                <td style="display:flex;gap:6px">
                  <button type="button" class="btn sm" onclick="om('edit-user-modal-{{ $user->id }}')">Edit</button>
                  @if($user->id !== auth()->id())
                    <form method="POST" action="{{ route('users.toggle-active', $user) }}" style="display:inline">
                      @csrf
                      <button type="button" class="btn sm"
                        onclick="swConfirm(this.closest('form'), '{{ $user->is_active ? 'Deactivate' : 'Activate' }} User?', '{{ $user->is_active ? 'Deactivate' : 'Activate' }} {{ addslashes($user->name) }}?{{ $user->is_active ? ' They will immediately lose access.' : '' }}', 'warning', 'Yes, {{ $user->is_active ? 'deactivate' : 'activate' }}', '{{ $user->is_active ? '#dc2626' : '#16a34a' }}')">{{ $user->is_active ? 'Deactivate' : 'Activate' }}</button>
                    </form>
                    <form method="POST" action="{{ route('users.destroy', $user) }}" style="display:inline">
                      @csrf @method('DELETE')
                      <button type="button" class="btn sm" style="color:var(--red)"
                        onclick="swConfirm(this.closest('form'), 'Delete User?', 'Delete {{ addslashes($user->name) }}? They will immediately lose access.', 'warning', 'Yes, delete', '#dc2626')">Del</button>
                    </form>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
        <div style="margin-top:14px">{{ $users->links() }}</div>
      @endif
    </div>

{{-- Add User Modal --}}
<div class="modal" id="user-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Add User</span>
      <button class="mc" onclick="cm('user-modal')">✕</button>
    </div>
    <form method="POST" action="{{ route('users.store') }}">
      @csrf
      <input type="hidden" name="_form" value="user">
      <div class="fld" style="margin-bottom:16px">
        <label class="req">Name</label>
        <input type="text" name="name" required maxlength="150"
          value="{{ old('_form') === 'user' ? old('name') : '' }}" @error('name') class="inp-err" @enderror>
        @if(old('_form') === 'user') @error('name') <div class="ferr">{{ $message }}</div> @enderror @endif
      </div>
      <div class="fld" style="margin-bottom:16px">
        <label class="req">Company</label>
        <select name="company_id" required @error('company_id') class="inp-err" @enderror>
          <option value="">Select company…</option>
          @foreach($companies as $company)
            <option value="{{ $company->id }}" {{ old('company_id') == $company->id ? 'selected' : '' }}>{{ $company->name }}</option>
          @endforeach
        </select>
        @if(old('_form') === 'user') @error('company_id') <div class="ferr">{{ $message }}</div> @enderror @endif
        <div style="font-size:11px;color:var(--t3);margin-top:2px">A new employee record is created for them automatically.</div>
      </div>
      <div class="fld" style="margin-bottom:16px">
        <label class="req">Email</label>
        <input type="email" name="email" required maxlength="150"
          value="{{ old('_form') === 'user' ? old('email') : '' }}" @error('email') class="inp-err" @enderror>
        @if(old('_form') === 'user') @error('email') <div class="ferr">{{ $message }}</div> @enderror @endif
      </div>
      <div class="fld" style="margin-bottom:16px">
        <label class="req">Password</label>
        <input type="password" name="password" required minlength="8" autocomplete="new-password"
          @error('password') class="inp-err" @enderror>
        @if(old('_form') === 'user') @error('password') <div class="ferr">{{ $message }}</div> @enderror @endif
      </div>
      <div class="fld" style="margin-bottom:16px">
        <label class="req">Confirm Password</label>
        <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
      </div>
      
      <div class="fld">
        <label>Role</label>
        @if($roles->isEmpty())
          <span style="color:var(--t3);font-size:12px">No roles exist yet — create one on the Role &amp; Permissions page first.</span>
        @else
          <select name="roles[]">
            <option value="">— No role —</option>
            @foreach($roles as $role)
              <option value="{{ $role->id }}" {{ collect(old('roles'))->contains($role->id) ? 'selected' : '' }}>
                {{ $role->name }}{{ $role->name === 'Admin' ? ' (all access)' : '' }}
              </option>
            @endforeach
          </select>
        @endif
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('user-modal')">Cancel</button>
        <button type="submit" class="btn acc">Create User</button>
      </div>
    </form>
  </div>
</div>

{{-- Edit User Modals (one per user) --}}
@foreach($users as $user)
  @php
    $editErr = old('_form') === 'edit_user' && (int) old('_user_id') === $user->id;
    // concat() (not push()) so we don't mutate $unlinkedEmployees, which is
    // shared across every iteration of this @foreach.
    $employeeOptions = ($user->employee ? $unlinkedEmployees->concat([$user->employee]) : $unlinkedEmployees)->sortBy('first_name');
  @endphp
  <div class="modal" id="edit-user-modal-{{ $user->id }}">
    <div class="mb">
      <div class="mh">
        <span class="mt">Edit User</span>
        <button class="mc" onclick="cm('edit-user-modal-{{ $user->id }}')">✕</button>
      </div>
      <form method="POST" action="{{ route('users.update', $user) }}">
        @csrf @method('PUT')
        <input type="hidden" name="_form" value="edit_user">
        <input type="hidden" name="_user_id" value="{{ $user->id }}">
        <div class="fld" style="margin-bottom:16px">
          <label class="req">Name</label>
          <input type="text" name="name" required maxlength="150"
            value="{{ $editErr ? old('name') : $user->name }}" @if($editErr) @error('name') class="inp-err" @enderror @endif>
          @if($editErr) @error('name') <div class="ferr">{{ $message }}</div> @enderror @endif
        </div>
        <div class="fld" style="margin-bottom:16px">
          <label class="req">Email</label>
          <input type="email" name="email" required maxlength="150"
            value="{{ $editErr ? old('email') : $user->email }}" @if($editErr) @error('email') class="inp-err" @enderror @endif>
          @if($editErr) @error('email') <div class="ferr">{{ $message }}</div> @enderror @endif
        </div>
        <div class="fld" style="margin-bottom:16px">
          <label>New Password <span style="font-weight:400;color:var(--t3)">(leave blank to keep current)</span></label>
          <input type="password" name="password" minlength="8" autocomplete="new-password"
            @if($editErr) @error('password') class="inp-err" @enderror @endif>
          @if($editErr) @error('password') <div class="ferr">{{ $message }}</div> @enderror @endif
        </div>
        <div class="fld" style="margin-bottom:16px">
          <label>Confirm New Password</label>
          <input type="password" name="password_confirmation" minlength="8" autocomplete="new-password">
        </div>
        
        <div class="fld">
          <label>Role</label>
          @if($roles->isEmpty())
            <span style="color:var(--t3);font-size:12px">No roles exist yet — create one on the Role &amp; Permissions page first.</span>
          @else
            <select name="roles[]">
              <option value="">— No role —</option>
              @foreach($roles as $role)
                @php
                  $isSelected = $editErr ? collect(old('roles'))->contains($role->id) : $user->roles->contains('id', $role->id);
                @endphp
                <option value="{{ $role->id }}" {{ $isSelected ? 'selected' : '' }}>
                  {{ $role->name }}{{ $role->name === 'Admin' ? ' (all access)' : '' }}
                </option>
              @endforeach
            </select>
          @endif
        </div>
        <div class="mf">
          <button type="button" class="btn" onclick="cm('edit-user-modal-{{ $user->id }}')">Cancel</button>
          <button type="submit" class="btn acc">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
@endforeach
  </div> {{-- #ajax-results --}}

@if($errors->any())
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      @if(old('_form') === 'user')
        om('user-modal');
      @elseif(old('_form') === 'edit_user')
        om('edit-user-modal-{{ old('_user_id') }}');
      @endif
    });
  </script>
@endif

@endsection
