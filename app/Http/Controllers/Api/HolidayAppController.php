<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Mobile mirror of WebHolidayController, scoped to the employee's own company's calendar only (no company switcher — that's an HR/Admin web-only concern). */
class HolidayAppController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('holidays_view');

        $employee = $request->user()->employee;
        abort_unless($employee, 404, 'No employee profile linked to this account.');

        $year = (int) $request->get('year', now()->year);

        $holidays = Holiday::whereHas('calendar', fn($q) => $q->where('company_id', $employee->company_id))
            ->whereYear('date', $year)
            ->orderBy('date')
            ->get(['date', 'name', 'type', 'description']);

        return response()->json(['data' => $holidays]);
    }
}
