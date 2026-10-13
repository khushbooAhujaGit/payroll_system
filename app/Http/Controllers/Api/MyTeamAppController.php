<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Mobile mirror of WebMyTeamController — direct reports only, empty list (not hidden) for a non-manager, same as the web page's own behavior. */
class MyTeamAppController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('my_team_view');

        $employee = $request->user()->employee;
        abort_unless($employee, 404, 'No employee profile linked to this account.');

        $reports = $employee->subordinates()
            ->with(['designation:id,name', 'department:id,name'])
            ->orderBy('first_name')
            ->get(['id', 'employee_id', 'first_name', 'middle_name', 'last_name', 'designation_id', 'department_id', 'employee_status'])
            ->map(fn($e) => [
                'id'          => $e->id,
                'employee_id' => $e->employee_id,
                'full_name'   => $e->full_name,
                'designation' => $e->designation?->name,
                'department'  => $e->department?->name,
                'status'      => $e->employee_status,
            ]);

        return response()->json(['data' => $reports]);
    }
}
