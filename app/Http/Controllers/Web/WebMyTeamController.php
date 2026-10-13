<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

/**
 * A Reporting Manager's view of their own direct reports — currently just
 * enough to raise the "Mark as Left" action (see
 * WebEmployeeController::markAsLeft()) without needing HR's employee_edit
 * permission. Reusable later for other manager-facing team features.
 */
class WebMyTeamController extends Controller
{
    public function index()
    {
        $this->authorize('my_team_view');

        $employee = auth()->user()->employee;
        abort_unless($employee, 404, 'No employee profile linked to your account.');

        $reports = $employee->subordinates()
            ->with(['designation', 'department'])
            ->orderBy('first_name')
            ->get();

        return view('employees.my-team', compact('reports'));
    }
}
