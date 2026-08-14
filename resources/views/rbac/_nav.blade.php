<div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap">
  <a class="btn sm rbac-tab-btn {{ request()->routeIs('rbac.roles') ? 'on' : '' }}" href="{{ route('rbac.roles') }}">Roles</a>
  <a class="btn sm rbac-tab-btn {{ request()->routeIs('rbac.permission_groups') ? 'on' : '' }}" href="{{ route('rbac.permission_groups') }}">Permission Groups</a>
  <a class="btn sm rbac-tab-btn {{ request()->routeIs('rbac.permissions') ? 'on' : '' }}" href="{{ route('rbac.permissions') }}">Permissions</a>
</div>
