<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Services\EmployeeService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

// ══════════════════════════════════════════════════════════════
// USER CREATION  (Blade page — Admin only)
//
// Standalone module for creating/editing/deleting login accounts and
// assigning roles to them. Separate from RBAC's role/permission
// management pages (WebRbacController).
// ══════════════════════════════════════════════════════════════

class WebUserController extends Controller implements HasMiddleware
{
    public function __construct(protected EmployeeService $employeeService) {}

    public static function middleware(): array
    {
        return [
            new Middleware(function (Request $request, Closure $next) {
                abort_unless($request->user()?->isSuperAdmin(), 403);
                return $next($request);
            }),
        ];
    }

    public function index(Request $request)
    {
        $roles = Role::orderBy('name')->get();
        $users = User::with(['roles', 'employee:id,employee_id,first_name,middle_name,last_name'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->search;
                $q->where(function ($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%")
                       ->orWhere('email', 'like', "%{$search}%")
                       ->orWhereHas('employee', fn($eq) => $eq->where('employee_id', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('is_active', $request->status === 'active');
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        // Employees who don't already have a login account, for the
        // "link to employee" field on the Add/Edit User forms.
        $unlinkedEmployees = Employee::whereDoesntHave('user')
            ->orderBy('first_name')
            ->get(['id', 'employee_id', 'first_name', 'middle_name', 'last_name']);

        // For the Add User form — a brand-new employee record is created
        // alongside the login account, and needs a company.
        $companies = Company::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('users.index', compact('roles', 'users', 'unlinkedEmployees', 'companies'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:150',
            'email'       => 'required|email|max:150|unique:users,email',
            'password'    => 'required|string|min:8|confirmed',
            'company_id'  => 'required|exists:companies,id',
            'roles'       => 'array',
            'roles.*'     => 'nullable|exists:roles,id',
        ]);

        // A new employee record is created alongside the login account —
        // this is what gives them an auto-generated employee code and a
        // row on the Employees page. EmployeeService::create() would also
        // try to auto-provision a login itself, but since we don't set an
        // official/personal email on this bare-bones employee record, that
        // step finds nothing to work with and skips — no duplicate account.
        [$firstName, $lastName] = $this->splitName($validated['name']);
        $employee = $this->employeeService->create([
            'company_id'      => $validated['company_id'],
            'first_name'      => $firstName,
            'last_name'       => $lastName ?: $firstName,
            'date_of_joining' => now()->toDateString(),
        ]);

        $user = User::create([
            'name'        => $validated['name'],
            'email'       => $validated['email'],
            'password'    => Hash::make($validated['password']),
            'employee_id' => $employee->id,
            'company_id'  => $employee->company_id,
            'is_active'   => true,
        ]);

        $roleIds = array_values(array_filter(array_map('intval', $validated['roles'] ?? [])));
        if ($roleIds) {
            $user->syncRoles($roleIds);
        }

        return redirect()->route('users.index')
            ->with('success', "User {$user->name} created (employee code {$employee->employee_id}).");
    }

    /**
     * "Add User" only collects a single Name field, not first/last
     * separately — split it on the first space so the auto-created
     * employee record has both.
     */
    protected function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2);
        return [$parts[0], $parts[1] ?? ''];
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:150',
            'email'       => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'password'    => 'nullable|string|min:8|confirmed',
            'roles'       => 'array',
            'roles.*'     => 'nullable|exists:roles,id',
        ]);

        // Note: employee_id/company_id are intentionally left untouched —
        // there's no "link to employee" control on this form (removed), so
        // touching them here would silently unlink the user from their
        // employee record on every unrelated edit (e.g. a password change).
        $user->name  = $validated['name'];
        $user->email = $validated['email'];
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        $user->save();

        $roleIds = array_values(array_filter(array_map('intval', $validated['roles'] ?? [])));
        $user->syncRoles($roleIds);

        return redirect()->route('users.index')->with('success', "User {$user->name} updated.");
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return redirect()->route('users.index')->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted.');
    }

    public function toggleActive(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return redirect()->route('users.index')->with('error', 'You cannot deactivate your own account.');
        }

        $user->update(['is_active' => !$user->is_active]);

        return redirect()->route('users.index')
            ->with('success', "User {$user->name} " . ($user->is_active ? 'activated.' : 'deactivated.'));
    }

    /**
     * Grants/revokes reimbursment_approve directly on this one user —
     * deliberately not role-based (see RepairRbacBaseline's catalog comment
     * and app/Http/Controllers/Web/WebMyReimbursementController.php), so
     * the reimbursement approver queue routes to exactly whoever this was
     * last toggled on for, independent of what role(s) they hold.
     */
    public function toggleReimbursementApprover(Request $request, User $user)
    {
        if ($user->hasPermissionTo('reimbursment_approve')) {
            $user->revokePermissionTo('reimbursment_approve');
            $message = "{$user->name} is no longer the reimbursement approver.";
        } else {
            $user->givePermissionTo('reimbursment_approve');
            $message = "{$user->name} is now the reimbursement approver.";
        }

        return redirect()->route('users.index')->with('success', $message);
    }
}
