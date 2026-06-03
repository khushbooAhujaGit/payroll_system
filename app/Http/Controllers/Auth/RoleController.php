<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

// ══════════════════════════════════════════════════════════════
// ROLE CONTROLLER  (RBAC management)
// ══════════════════════════════════════════════════════════════

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $roles = Role::with('permissions:id,module,name,slug')
            ->when($request->company_id, fn($q) => $q->where('company_id', $request->company_id))
            ->get();

        return response()->json(['data' => $roles]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'slug'        => 'required|string|max:50|unique:roles,slug',
            'description' => 'nullable|string',
            'company_id'  => 'nullable|exists:companies,id',
        ]);

        $role = Role::create(array_merge($validated, ['is_system_role' => false, 'is_active' => true]));
        return response()->json(['data' => $role], 201);
    }

    public function show(Role $role): JsonResponse
    {
        return response()->json([
            'data' => $role->load(['permissions' => fn($q) => $q->withPivot('can_view','can_create','can_edit','can_delete','can_export','can_approve')])
        ]);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        if ($role->is_system_role) {
            return response()->json(['message' => 'System roles cannot be modified.'], 422);
        }

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:100',
            'description' => 'sometimes|nullable|string',
            'is_active'   => 'sometimes|boolean',
        ]);

        $role->update($validated);
        return response()->json(['data' => $role]);
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->is_system_role) {
            return response()->json(['message' => 'System roles cannot be deleted.'], 422);
        }

        $role->delete();
        return response()->json(['message' => 'Role deleted.']);
    }

    // Sync permissions for a role
    public function syncPermissions(Request $request, Role $role): JsonResponse
    {
        $request->validate([
            'permissions'              => 'required|array',
            'permissions.*.id'         => 'required|exists:permissions,id',
            'permissions.*.can_view'   => 'boolean',
            'permissions.*.can_create' => 'boolean',
            'permissions.*.can_edit'   => 'boolean',
            'permissions.*.can_delete' => 'boolean',
            'permissions.*.can_export' => 'boolean',
            'permissions.*.can_approve'=> 'boolean',
        ]);

        $syncData = [];
        foreach ($request->permissions as $perm) {
            $syncData[$perm['id']] = [
                'can_view'   => $perm['can_view']    ?? false,
                'can_create' => $perm['can_create']  ?? false,
                'can_edit'   => $perm['can_edit']    ?? false,
                'can_delete' => $perm['can_delete']  ?? false,
                'can_export' => $perm['can_export']  ?? false,
                'can_approve'=> $perm['can_approve'] ?? false,
            ];
        }

        $role->permissions()->sync($syncData);

        // Flush permission cache for all users with this role
        $userIds = User::whereHas('roles', fn($q) => $q->where('roles.id', $role->id))->pluck('id');
        foreach ($userIds as $uid) {
            Cache::forget("user_permissions_{$uid}");
        }

        return response()->json(['message' => 'Permissions updated.', 'data' => $role->fresh('permissions')]);
    }

    // List all users with a given role
    public function users(Role $role): JsonResponse
    {
        $users = User::whereHas('roles', fn($q) => $q->where('roles.id', $role->id))
            ->with('employee:id,employee_id,first_name,middle_name,last_name')
            ->get();

        return response()->json(['data' => $users]);
    }

    // Assign roles to a user
    public function assignToUser(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'role_ids'    => 'required|array',
            'role_ids.*'  => 'exists:roles,id',
            'company_id' => 'nullable|exists:companies,id',
        ]);

        foreach ($request->role_ids as $roleId) {
            if (!$user->roles()->where('roles.id', $roleId)->exists()) {
                $user->roles()->attach($roleId, ['company_id' => $request->company_id]);
            }
        }

        Cache::forget("user_permissions_{$user->id}");
        return response()->json(['message' => 'Roles assigned.', 'data' => $user->fresh('roles')]);
    }

    // Remove role from user
    public function removeFromUser(Request $request, User $user, Role $role): JsonResponse
    {
        $user->roles()->detach($role->id);
        Cache::forget("user_permissions_{$user->id}");
        return response()->json(['message' => 'Role removed.']);
    }
}
