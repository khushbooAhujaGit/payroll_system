<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // ─────────────────────────────────────────────
    // LOGIN
    // ─────────────────────────────────────────────

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Your account has been deactivated.'], 403);
        }

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        $token = $user->createToken('hrms-token')->plainTextToken;

        AuditLog::create([
            'user_id'     => $user->id,
            'action'      => 'login',
            'module'      => 'auth',
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'description' => 'User logged in',
        ]);

        return response()->json([
            'token' => $token,
            'user'  => $this->buildUserProfile($user),
        ]);
    }

    // ─────────────────────────────────────────────
    // LOGOUT
    // ─────────────────────────────────────────────

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    // ─────────────────────────────────────────────
    // PROFILE
    // ─────────────────────────────────────────────

    public function profile(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->buildUserProfile($request->user())]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => 'sometimes|string|max:200',
            'mobile'   => 'sometimes|string|size:10',
            'timezone' => 'sometimes|string',
        ]);

        $request->user()->update($validated);

        return response()->json(['message' => 'Profile updated.', 'data' => $request->user()]);
    }

    // ─────────────────────────────────────────────
    // CHANGE PASSWORD
    // ─────────────────────────────────────────────

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($validated['current_password'], $request->user()->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        $request->user()->update(['password' => Hash::make($validated['new_password'])]);

        return response()->json(['message' => 'Password changed successfully.']);
    }

    // ─────────────────────────────────────────────
    // MY PERMISSIONS
    // ─────────────────────────────────────────────

    public function permissions(Request $request): JsonResponse
    {
        $user = $request->user()->load(['roles.permissions']);

        $permissions = $user->roles->flatMap(fn($role) => $role->permissions->map(fn($perm) => [
            'slug'       => $perm->slug,
            'can_view'   => $perm->pivot->can_view,
            'can_create' => $perm->pivot->can_create,
            'can_edit'   => $perm->pivot->can_edit,
            'can_delete' => $perm->pivot->can_delete,
            'can_export' => $perm->pivot->can_export,
            'can_approve'=> $perm->pivot->can_approve,
        ]))->keyBy('slug');

        return response()->json(['data' => $permissions]);
    }

    // ─────────────────────────────────────────────
    // HELPER
    // ─────────────────────────────────────────────

    protected function buildUserProfile(User $user): array
    {
        $user->load(['roles', 'employee.department', 'employee.designation', 'employee.entity']);

        return [
            'id'            => $user->id,
            'name'          => $user->name,
            'email'         => $user->email,
            'mobile'        => $user->mobile,
            'avatar'        => $user->avatar,
            'is_active'     => $user->is_active,
            'last_login_at' => $user->last_login_at,
            'roles'         => $user->roles->pluck('slug'),
            'employee'      => $user->employee ? [
                'id'          => $user->employee->id,
                'employee_id' => $user->employee->employee_id,
                'company_id' => $user->employee->company_id,
                'full_name'   => $user->employee->full_name,
                'department'  => $user->employee->department?->name,
                'designation' => $user->employee->designation?->name,
            ] : null,
        ];
    }
}
