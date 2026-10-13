<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * The single "average hours worked this week" calculation, shared by the
 * weekly notification command and the employee's My Attendance page, so
 * both always agree on what counts as a shortfall.
 *
 * Sums clocked time (earliest check-in to latest check-out per day — the
 * same method WebReportController's daily 9-hour report column already
 * uses) across days with punch data in the week, divided by that day
 * count. No exclusions for leave types, Early Leave, Late Arrival, or
 * anything else — a literal weekly average.
 */
class AttendanceWeeklyAverageService
{
    public const THRESHOLD_HOURS = 9;

    public function __construct(private AttendanceDayResolver $resolver)
    {
    }

    /**
     * Average clocked hours/day over any date range — used with a Mon-Sun
     * range for the weekly check, and reused with a full-month range for
     * the "Monthly Attendance" analytics figure (same calculation, wider window).
     *
     * @return array{average_hours: float, day_count: int, total_seconds: int}|null null when there's no punch data at all in range
     */
    public function forEmployeeDateRange(int $employeeId, Carbon $rangeStart, Carbon $rangeEnd): ?array
    {
        $punchesByDate = $this->resolver->punchesForEmployeeRange(
            $employeeId, $rangeStart->toDateString(), $rangeEnd->toDateString()
        );

        $totalSeconds = 0;
        $dayCount     = 0;

        foreach ($punchesByDate as $date => $dayPunches) {
            $checkIn  = $dayPunches->filter(fn($p) => $p->check_in  !== null)->min('check_in');
            $checkOut = $dayPunches->filter(fn($p) => $p->check_out !== null)->max('check_out');

            // Punches before 05:30 are biometric artifacts (previous day's late
            // punch bleeding into the next day's date) — same guard already
            // applied by WebReportController's daily clocked-time calculation.
            if ($checkIn !== null && substr($checkIn, 0, 5) < '05:30') {
                $checkIn = null;
            }

            if (!$checkIn || !$checkOut) {
                continue;
            }

            $inTs  = strtotime($date . ' ' . $checkIn);
            $outTs = strtotime($date . ' ' . $checkOut);
            if ($outTs <= $inTs) {
                continue;
            }

            $totalSeconds += ($outTs - $inTs);
            $dayCount++;
        }

        if ($dayCount === 0) {
            return null;
        }

        return [
            'average_hours' => round($totalSeconds / $dayCount / 3600, 2),
            'day_count'     => $dayCount,
            'total_seconds' => $totalSeconds,
        ];
    }

    public function isShortfall(?array $result): bool
    {
        return $result !== null && $result['average_hours'] < self::THRESHOLD_HOURS;
    }
}
