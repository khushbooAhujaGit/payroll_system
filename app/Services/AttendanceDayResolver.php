<?php

namespace App\Services;

use App\Models\AttendanceAppPunchEvent;
use App\Models\AttendanceHrOverride;
use App\Models\AttendanceRawPunch;
use Illuminate\Support\Collection;

/**
 * Single source of truth for "what were this employee's check-in/check-out
 * on this day", once the mobile attendance app is in the picture
 * (ATTENDANCE_APP_PLAN.md §4). Resolution order per employee+date:
 *
 *   1. attendance_hr_overrides — if a row exists, it wins outright. The
 *      underlying attendance_raw_punches / attendance_app_punch_events for
 *      that day are left untouched in the DB but excluded from the result.
 *   2. Otherwise, attendance_raw_punches (biometric/manual-web) merged with
 *      attendance_app_punch_events (mobile app), using the same
 *      earliest-check-in / latest-check-out rule already used everywhere
 *      in this codebase today.
 *
 * Deliberately returns punch-*shaped* objects (->check_in / ->check_out,
 * same as AttendanceRawPunch) rather than a final resolved pair — every
 * existing consumer already does its own min()/max()/artifact-filtering
 * across a punches collection for the day, and this lets each one keep
 * doing exactly that, completely unchanged, against a collection that now
 * happens to account for overrides and app events too. Only the fetch
 * line changes at each call site; every downstream calculation (clocked
 * time, punch-in type, the 05:30 biometric-artifact guard, etc.) is
 * untouched — this is deliberate: those quirks differ slightly between
 * consumers today, and preserving that behavior exactly (not unifying it)
 * is the whole point of swapping only the input, not the calculation.
 */
class AttendanceDayResolver
{
    /**
     * Bulk resolution for many employees over a date range — e.g. the
     * Monthly Attendance Report, which already batches this way for
     * attendance_raw_punches/leaves/approvals.
     *
     * @param  int[]  $employeeIds
     * @return Collection<string, Collection>  keyed "{employee_id}_{Y-m-d}" => Collection of punch-like objects
     */
    public function punchesForRange(array $employeeIds, string $from, string $to): Collection
    {
        if (empty($employeeIds)) {
            return collect();
        }

        $overrides = AttendanceHrOverride::whereIn('employee_id', $employeeIds)
            ->whereBetween('attendance_date', [$from, $to])
            ->get()
            ->keyBy(fn($o) => $o->employee_id . '_' . $o->attendance_date->format('Y-m-d'));

        $rawPunches = AttendanceRawPunch::whereIn('employee_id', $employeeIds)
            ->whereBetween('attendance_date', [$from, $to])
            ->get()
            ->groupBy(fn($p) => $p->employee_id . '_' . $p->attendance_date->format('Y-m-d'));

        $appEvents = AttendanceAppPunchEvent::whereIn('employee_id', $employeeIds)
            ->whereBetween('event_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ->get()
            ->groupBy(fn($e) => $e->employee_id . '_' . $e->event_at->format('Y-m-d'));

        $keys = $overrides->keys()
            ->merge($rawPunches->keys())
            ->merge($appEvents->keys())
            ->unique();

        return $keys->mapWithKeys(fn($key) => [
            $key => $this->resolveKey($key, $overrides, $rawPunches, $appEvents),
        ]);
    }

    /**
     * Single-employee convenience wrapper — same resolution, keyed just by
     * date (no employee_id prefix) since the caller already knows which
     * employee it's asking about. Used by AttendanceWeeklyAverageService
     * and WebMyAttendanceController, both already single-employee call sites.
     *
     * @return Collection<string, Collection>  keyed "Y-m-d" => Collection of punch-like objects
     */
    public function punchesForEmployeeRange(int $employeeId, string $from, string $to): Collection
    {
        return $this->punchesForRange([$employeeId], $from, $to)
            ->mapWithKeys(fn($punches, $key) => [substr($key, strlen((string) $employeeId) + 1) => $punches]);
    }

    private function resolveKey(string $key, Collection $overrides, Collection $rawPunches, Collection $appEvents): Collection
    {
        if ($override = $overrides->get($key)) {
            // Override wins outright — a single synthetic punch-like row,
            // so every existing consumer's min()/max() across "punches for
            // the day" picks this up as the only entry, with no other
            // changes needed on their end.
            return collect([(object) [
                'check_in'  => $override->check_in,
                'check_out' => $override->check_out,
            ]]);
        }

        $merged = collect();

        foreach ($rawPunches->get($key, collect()) as $punch) {
            $merged->push((object) [
                'check_in'  => $punch->check_in,
                'check_out' => $punch->check_out,
            ]);
        }

        // Each app event becomes its own synthetic punch row (an 'in' event
        // contributes only a check_in, an 'out' event only a check_out) —
        // merged into the same collection as raw punches so the existing
        // earliest-in/latest-out rule naturally spans both sources.
        foreach ($appEvents->get($key, collect()) as $event) {
            $merged->push((object) [
                'check_in'  => $event->event_type === 'in'  ? $event->event_at->format('H:i:s') : null,
                'check_out' => $event->event_type === 'out' ? $event->event_at->format('H:i:s') : null,
            ]);
        }

        return $merged;
    }
}
