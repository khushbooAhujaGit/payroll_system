<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

// ══════════════════════════════════════════════════════════════
// ROLE & PERMISSION MANAGEMENT  (Blade page — Admin only)
//
// Backed by spatie/laravel-permission. Permission groups are a thin
// App-level concept on top of Spatie's Permission model (permission_group_id
// column) purely for organizing the admin UI — Spatie itself has no notion
// of groups.
// ══════════════════════════════════════════════════════════════

class WebRbacController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            // Reachable only by the Admin role, regardless of whether Admin has
            // been explicitly granted the 'rbac_roles_manage' permission itself —
            // Admin bypasses all permission checks (see User::isSuperAdmin()).
            new Middleware(function (Request $request, Closure $next) {
                abort_unless($request->user()?->isSuperAdmin(), 403);
                return $next($request);
            }),
        ];
    }

    public function roles(Request $request)
    {
        $roles            = Role::with('permissions')->orderBy('name')->get();
        $permissionGroups = PermissionGroup::with('permissions')->orderBy('name')->get();

        return view('rbac.roles', compact('roles', 'permissionGroups'));
    }

    public function permissionGroups(Request $request)
    {
        $permissionGroups = PermissionGroup::withCount('permissions')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('rbac.permission_groups', compact('permissionGroups'));
    }

    public function permissions(Request $request)
    {
        $permissions = Permission::with('group')
            ->when(request('search'), function ($q) {
                $search = request('search');
                $q->where(function ($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%")
                       ->orWhereHas('group', fn($gq) => $gq->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $allGroups = PermissionGroup::orderBy('name')->get();

        return view('rbac.permissions', compact('permissions', 'allGroups'));
    }

    public function storePermissionGroup(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:permission_groups,name',
        ]);

        PermissionGroup::create($validated);

        return redirect()->route('rbac.permission_groups')->with('success', 'Permission group created.');
    }

    public function updatePermissionGroup(Request $request, PermissionGroup $permissionGroup)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:permission_groups,name,' . $permissionGroup->id,
        ]);

        $permissionGroup->update($validated);

        return redirect()->route('rbac.permission_groups')->with('success', 'Permission group renamed.');
    }

    public function destroyPermissionGroup(PermissionGroup $permissionGroup)
    {
        if ($permissionGroup->permissions()->exists()) {
            return redirect()->route('rbac.permission_groups')
                ->with('error', 'Cannot delete a group that still has permissions in it. Delete those first.');
        }

        $permissionGroup->delete();

        return redirect()->route('rbac.permission_groups')->with('success', 'Permission group deleted.');
    }

    public function storePermission(Request $request)
    {
        $validated = $request->validate([
            'permission_group_id' => 'required|exists:permission_groups,id',
            'name'                => 'required|string|max:150|unique:permissions,name|regex:/^[a-z0-9]+(_[a-z0-9]+)+$/',
        ]);

        Permission::create([
            'permission_group_id' => $validated['permission_group_id'],
            'name'                => $validated['name'],
            'guard_name'          => 'web',
        ]);

        return redirect()->route('rbac.permissions')->with('success', 'Permission created.');
    }

    public function updatePermission(Request $request, Permission $permission)
    {
        $validated = $request->validate([
            'permission_group_id' => 'required|exists:permission_groups,id',
            'name'                => 'required|string|max:150|unique:permissions,name,' . $permission->id . '|regex:/^[a-z0-9]+(_[a-z0-9]+)+$/',
        ]);

        $permission->update($validated);

        return redirect()->route('rbac.permissions')->with('success', 'Permission updated.');
    }

    public function destroyPermission(Permission $permission)
    {
        // role_has_permissions has an FK cascade, so this also detaches it
        // from every role automatically.
        $permission->delete();

        return redirect()->route('rbac.permissions')->with('success', 'Permission deleted.');
    }

    public function storeRole(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:roles,name',
        ]);

        Role::create([
            'name'       => $validated['name'],
            'guard_name' => 'web',
        ]);

        return redirect()->route('rbac.roles')->with('success', 'Role created.');
    }

    public function destroyRole(Role $role)
    {
        if ($role->name === 'Admin') {
            return redirect()->route('rbac.roles')->with('error', 'The Admin role cannot be deleted.');
        }

        // model_has_roles / role_has_permissions both have FK cascades, so
        // this also detaches it from every user and permission automatically.
        $role->delete();

        return redirect()->route('rbac.roles')->with('success', 'Role deleted.');
    }

    public function syncPermissions(Request $request, Role $role)
    {
        $validated = $request->validate([
            'permissions'   => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        // Spatie's syncPermissions() only treats native PHP ints as IDs — a
        // numeric string (which is what every HTML form field arrives as)
        // gets looked up as a permission *name* instead and throws
        // PermissionDoesNotExist. Cast explicitly.
        $permissionIds = array_map('intval', $validated['permissions'] ?? []);

        $role->syncPermissions($permissionIds);

        return redirect()->route('rbac.roles')->with('success', "Permissions updated for {$role->name}.");
    }
}
