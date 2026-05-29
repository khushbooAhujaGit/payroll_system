<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    public function index(int $calendarId): JsonResponse
    {
        return response()->json([
            'data' => \App\Models\Holiday::where('holiday_calendar_id', $calendarId)->orderBy('date')->get()
        ]);
    }

    public function store(Request $request, int $calendarId): JsonResponse
    {
        $validated = $request->validate([
            'date'        => 'required|date',
            'name'        => 'required|string|max:200',
            'type'        => 'required|in:national,regional,optional',
            'description' => 'nullable|string',
        ]);
        $holiday = \App\Models\Holiday::create(array_merge($validated, ['holiday_calendar_id' => $calendarId]));
        return response()->json(['data' => $holiday], 201);
    }

    public function update(Request $request, int $calendarId, int $id): JsonResponse
    {
        $holiday = \App\Models\Holiday::where('holiday_calendar_id', $calendarId)->findOrFail($id);
        $holiday->update($request->validate(['name' => 'sometimes|string', 'type' => 'sometimes|string', 'date' => 'sometimes|date']));
        return response()->json(['data' => $holiday]);
    }

    public function destroy(int $calendarId, int $id): JsonResponse
    {
        \App\Models\Holiday::where('holiday_calendar_id', $calendarId)->findOrFail($id)->delete();
        return response()->json(['message' => 'Holiday deleted.']);
    }
}
