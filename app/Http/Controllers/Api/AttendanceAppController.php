<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceAppPunchEvent;
use App\Models\EmployeeAttendanceLocation;
use App\Models\OfficeWifiNetwork;
use App\Services\AttendanceDayResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The mobile app's attendance API — ATTENDANCE_APP_PLAN.md §5. Auth is
 * Sanctum-token (reuses the existing, verified-working App\Http\Controllers
 * \Auth\AuthController::login() — this controller only adds the
 * app-specific attendance actions on top of that same token).
 *
 * Deviation (>50m from the mapped point) is only computed for GPS-method
 * events. Wifi-method events aren't distance-checked — connecting to a
 * registered office network is itself the proof of legitimate location,
 * per item 54.2-54.4. WFO employees with no mapped point and no registered
 * office coordinates (branches have no lat/long in this schema) simply
 * never get a GPS deviation flag — wifi is their real mechanism.
 */
class AttendanceAppController extends Controller
{
    private const DEVIATION_RADIUS_M = 50;

    public function __construct(private AttendanceDayResolver $resolver) {}

    /**
     * Today's resolved Check In/Check Out for the Home screen — same
     * override-aware resolution the web app's My Attendance/Attendance
     * Report pages use (AttendanceDayResolver merges HR overrides, this
     * app's own punch events, and biometric raw punches), not just a
     * same-session memory of the last mark-in/out — so it's still correct
     * after closing and reopening the app, or if today's attendance was
     * touched from the web side. Mark In/Out are only this employee's own
     * action buttons; "has a check-in but no check-out yet" is what the app
     * uses to decide which button(s) to allow.
     */
    public function today(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 404, 'No employee profile linked to this account.');

        $today = now()->toDateString();
        $punches = $this->resolver->punchesForEmployeeRange($employee->id, $today, $today)
            ->get($today, collect());

        $checkIn  = $punches->filter(fn($p) => $p->check_in  !== null)->min('check_in');
        $checkOut = $punches->filter(fn($p) => $p->check_out !== null)->max('check_out');

        return response()->json([
            'data' => [
                'date'      => $today,
                'check_in'  => $checkIn,
                'check_out' => $checkOut,
            ],
        ]);
    }

    /**
     * Current mapped location + the wifi networks valid for this employee's
     * company/branch, so the app knows what to watch for.
     */
    public function location(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 404, 'No employee profile linked to this account.');

        $location = EmployeeAttendanceLocation::where('employee_id', $employee->id)->first();

        $wifiNetworks = OfficeWifiNetwork::where('company_id', $employee->company_id)
            ->where('is_active', true)
            ->where(fn($q) => $q->whereNull('branch_id')->orWhere('branch_id', $employee->branch_id))
            ->get(['id', 'ssid', 'bssid', 'branch_id']);

        return response()->json([
            'data' => [
                'work_type'      => $employee->work_type,
                'requires_mapping' => in_array($employee->work_type, ['wfh', 'client_location'], true),
                'mapped_location' => $location ? [
                    'latitude'  => (float) $location->latitude,
                    'longitude' => (float) $location->longitude,
                    'label'     => $location->label,
                    'mapped_at' => $location->mapped_at,
                ] : null,
                'wifi_networks' => $wifiNetworks,
                'deviation_radius_m' => self::DEVIATION_RADIUS_M,
            ],
        ]);
    }

    /**
     * Set/replace the employee's mapped point — one at a time, re-mapping
     * overwrites (no history, per the plan's confirmed design).
     */
    public function setLocation(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 404, 'No employee profile linked to this account.');

        $validated = $request->validate([
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'label'     => 'nullable|string|max:100',
        ]);

        $location = EmployeeAttendanceLocation::updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'latitude'  => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'label'     => $validated['label'] ?? null,
                'mapped_at' => now(),
            ]
        );

        return response()->json(['data' => $location]);
    }

    public function markIn(Request $request): JsonResponse
    {
        return $this->recordEvent($request, 'in');
    }

    public function markOut(Request $request): JsonResponse
    {
        return $this->recordEvent($request, 'out');
    }

    /**
     * Attach an explanation to a flagged (is_deviation) event — the app
     * prompts for this right after a mark-in/out comes back deviated.
     */
    public function deviationReason(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 404, 'No employee profile linked to this account.');

        $validated = $request->validate([
            'event_id' => 'required|integer',
            'reason'   => 'required|string|max:500',
        ]);

        $event = AttendanceAppPunchEvent::where('id', $validated['event_id'])
            ->where('employee_id', $employee->id)
            ->first();

        abort_unless($event, 404, 'Event not found.');
        abort_unless($event->is_deviation, 422, 'This event was not flagged as a deviation.');

        $event->update(['deviation_reason' => $validated['reason']]);

        return response()->json(['data' => $event]);
    }

    private function recordEvent(Request $request, string $eventType): JsonResponse
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 404, 'No employee profile linked to this account.');

        $validated = $request->validate([
            'method'              => 'required|in:gps,wifi,manual_app',
            'latitude'            => 'required_if:method,gps|nullable|numeric|between:-90,90',
            'longitude'           => 'required_if:method,gps|nullable|numeric|between:-180,180',
            'wifi_ssid'           => 'required_if:method,wifi|nullable|string|max:100',
            'source_app_version'  => 'nullable|string|max:20',
        ]);

        $distanceM  = null;
        $isDeviation = false;

        if ($validated['method'] === 'gps') {
            $mapped = EmployeeAttendanceLocation::where('employee_id', $employee->id)->first();
            if ($mapped) {
                $distanceM = $this->haversineMeters(
                    (float) $mapped->latitude, (float) $mapped->longitude,
                    (float) $validated['latitude'], (float) $validated['longitude']
                );
                $isDeviation = $distanceM > self::DEVIATION_RADIUS_M;
            }
        }

        $event = AttendanceAppPunchEvent::create([
            'employee_id'             => $employee->id,
            'event_type'              => $eventType,
            'method'                  => $validated['method'],
            'event_at'                => now(),
            'latitude'                => $validated['latitude']  ?? null,
            'longitude'               => $validated['longitude'] ?? null,
            'distance_from_mapped_m'  => $distanceM,
            'is_deviation'            => $isDeviation,
            'wifi_ssid'               => $validated['wifi_ssid'] ?? null,
            'source_app_version'      => $validated['source_app_version'] ?? null,
        ]);

        return response()->json(['data' => $event], 201);
    }

    /** Great-circle distance between two lat/long points, in meters. */
    private function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusM = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadiusM * $c, 2);
    }
}
