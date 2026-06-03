<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// ══════════════════════════════════════════════════════════════
// SHIFT CONTROLLER
// ══════════════════════════════════════════════════════════════

class ShiftController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => Shift::where('company_id', $request->company_id)->where('is_active', true)->get()
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name'                       => 'required|string|max:100',
            'code'                       => 'required|string|max:10',
            'start_time'                 => 'required|date_format:H:i',
            'end_time'                   => 'required|date_format:H:i',
            'working_hours'              => 'required|numeric|min:1|max:24',
            'is_night_shift'             => 'boolean',
            'grace_period_minutes'       => 'integer|min:0',
            'late_mark_after_minutes'    => 'integer|min:0',
            'half_day_after_minutes'     => 'integer|min:0',
            'is_flexible'                => 'boolean',
            'min_working_hours_flexible' => 'nullable|numeric',
        ]);

        $shift = Shift::create($validated);
        return response()->json(['data' => $shift], 201);
    }

    public function show(Shift $shift): JsonResponse
    {
        return response()->json(['data' => $shift]);
    }

    public function update(Request $request, Shift $shift): JsonResponse
    {
        $shift->update($request->validate([
            'name'                    => 'sometimes|string|max:100',
            'start_time'              => 'sometimes|date_format:H:i',
            'end_time'                => 'sometimes|date_format:H:i',
            'grace_period_minutes'    => 'sometimes|integer|min:0',
            'late_mark_after_minutes' => 'sometimes|integer|min:0',
            'is_active'               => 'sometimes|boolean',
        ]));
        return response()->json(['data' => $shift]);
    }

    public function destroy(Shift $shift): JsonResponse
    {
        $shift->update(['is_active' => false]);
        return response()->json(['message' => 'Shift deactivated.']);
    }

    // Assign a shift to an employee (or group)
    public function assignToEmployees(Request $request, Shift $shift): JsonResponse
    {
        $request->validate([
            'employee_ids'   => 'required|array',
            'employee_ids.*' => 'exists:employees,id',
            'effective_from' => 'required|date',
        ]);

        foreach ($request->employee_ids as $empId) {
            \App\Models\EmployeeShift::updateOrCreate(
                ['employee_id' => $empId],
                ['shift_id' => $shift->id, 'effective_from' => $request->effective_from]
            );
        }

        return response()->json(['message' => count($request->employee_ids) . ' employees assigned to shift.']);
    }
}
