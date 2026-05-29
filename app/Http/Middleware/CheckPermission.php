<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Checks if the authenticated user has a specific permission.
 * Usage in routes: ->middleware('permission:employee.create')
 * Or multi-permission: ->middleware('permission:payroll.run,payroll.approve')
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): mixed
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        foreach ($permissions as $permission) {
            if ($this->userHasPermission($user, $permission)) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => 'Access denied. Required permission: ' . implode(' or ', $permissions),
        ], 403);
    }

    protected function userHasPermission($user, string $permissionSlug): bool
    {
        $cacheKey = "user_permissions_{$user->id}";

        $userPermissions = Cache::remember($cacheKey, 300, function () use ($user) {
            return $this->loadUserPermissions($user);
        });

        return isset($userPermissions[$permissionSlug]);
    }

    protected function loadUserPermissions($user): array
    {
        $permissions = [];

        $user->load(['roles.permissions' => fn($q) => $q->withPivot(
            'can_view', 'can_create', 'can_edit', 'can_delete', 'can_export', 'can_approve'
        )]);

        // Super admin gets everything
        if ($user->roles->contains('slug', 'super_admin')) {
            return ['*' => true];
        }

        foreach ($user->roles as $role) {
            foreach ($role->permissions as $perm) {
                $permissions[$perm->slug] = [
                    'can_view'   => (bool) $perm->pivot->can_view,
                    'can_create' => (bool) $perm->pivot->can_create,
                    'can_edit'   => (bool) $perm->pivot->can_edit,
                    'can_delete' => (bool) $perm->pivot->can_delete,
                    'can_export' => (bool) $perm->pivot->can_export,
                    'can_approve'=> (bool) $perm->pivot->can_approve,
                ];
            }
        }

        return $permissions;
    }
}
