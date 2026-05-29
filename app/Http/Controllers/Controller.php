<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Resolve the current entity ID for the logged-in user.
     *
     * Priority:
     *   1. The entity the user's own employee record belongs to.
     *   2. An explicit entity_id from the current request (used by admin pages
     *      that show an entity selector).
     *   3. The first entity that has at least one active employee in the DB.
     *   4. Hard fallback: entity primary key 1.
     *
     * Pass the request object only when the page has an entity selector.
     */
    protected function resolveEntityId(?\Illuminate\Http\Request $request = null): int
    {
        // 1. User's own employee entity
        $userEntityId = auth()->user()?->employee?->entity_id;
        if ($userEntityId) {
            return (int) $userEntityId;
        }

        // 2. Explicit request param (admin entity selector)
        if ($request?->filled('entity_id')) {
            return (int) $request->get('entity_id');
        }

        // 3. First entity that has active employees
        $firstWithEmployees = Employee::where('employee_status', 'active')
            ->whereNotNull('entity_id')
            ->value('entity_id');
        if ($firstWithEmployees) {
            return (int) $firstWithEmployees;
        }

        return 1;
    }
}
