<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\HolidayCalendar;
use App\Models\Holiday;
use App\Models\Employee;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class AttendanceService
{
    /**
     * Returns a consolidated attendance summary for payroll.
     * This is the single source of truth for payroll LOP/payable days.
     */
    public function getMonthlySummary(int $employeeId, string $from, string $to): array
    {
        $employee    = Employee::with('entity')->findOrFail($employeeId);
        $records     = Attendance::where('employee_id', $employeeId)
            ->whereBetween('attendance_date', [$from, $to])
            ->get()
            ->keyBy(fn($r) => $r->attendance_date->format('Y-m-d'));

        $calendarDays = collect(CarbonPeriod::create($from, $to));
        $holidays     = $this->getHolidayDates($employee, $from, $to);
        $rosterDays   = $this->getRosterWorkingDays($employee);  // Mon-Fri etc.

        $workingDays = 0;
        $presentDays = 0;
        $absentDays  = 0;
        $leaveDays   = 0.0;
        $lopDays     = 0.0;
        $holidayDays = 0;
        $weeklyOffDays  = 0;
        $wfhDays     = 0;
        $overtimeMinutes = 0;

        foreach ($calendarDays as $day) {
            $dateStr  = $day->format('Y-m-d');
            $isHoliday  = in_array($dateStr, $holidays);
            $isWeeklyOff = !in_array($day->format('D'), $rosterDays);

            if ($isHoliday) {
                $holidayDays++;
                continue;
            }
            if ($isWeeklyOff) {
                $weeklyOffDays++;
                continue;
            }

            $workingDays++;
            $attendance = $records->get($dateStr);

            if (!$attendance) {
                // No record = absent (LOP)
                $absentDays++;
                $lopDays++;
                continue;
            }

            switch ($attendance->status) {
                case 'present':
                case 'on_duty':
                case 'tour':
                    $presentDays++;
                    $overtimeMinutes += $attendance->overtime_minutes ?? 0;
                    if ($attendance->work_type === 'wfh') $wfhDays++;
                    break;

                case 'work_from_home':
                    $presentDays++;
                    $wfhDays++;
                    break;

                case 'half_day':
                    $presentDays += 0.5;
                    $lopDays     += 0.5;
                    $absentDays  += 0.5;
                    break;

                case 'late':
                    $presentDays++;
                    // If half_day_after_minutes exceeded → auto half-day LOP (configurable)
                    break;

                case 'leave':
                    $leaveDays++;
                    // Check if it should be LOP (no balance)
                    if ($attendance->is_lop ?? false) {
                        $lopDays++;
                    }
                    break;

                case 'lop':
                    $lopDays++;
                    $absentDays++;
                    break;

                case 'absent':
                    $absentDays++;
                    $lopDays++;
                    break;

                case 'holiday':
                    $holidayDays++;
                    $workingDays--;  // Was counted as working, adjust back
                    break;

                case 'weekly_off':
                    $weeklyOffDays++;
                    $workingDays--;
                    break;
            }
        }

        return [
            'working_days'     => $workingDays,
            'present_days'     => round($presentDays, 2),
            'absent_days'      => round($absentDays, 2),
            'leave_days'       => round($leaveDays, 2),
            'lop_days'         => round($lopDays, 2),
            'holiday_days'     => $holidayDays,
            'weekly_off_days'  => $weeklyOffDays,
            'wfh_days'         => $wfhDays,
            'overtime_minutes' => $overtimeMinutes,
        ];
    }

    /**
     * Upsert an attendance record (from biometric / manual / API).
     */
    public function markAttendance(int $employeeId, string $date, array $data): \App\Models\Attendance
    {
        return Attendance::updateOrCreate(
            ['employee_id' => $employeeId, 'attendance_date' => $date],
            array_merge($data, ['source' => $data['source'] ?? 'manual'])
        );
    }

    /**
     * Bulk attendance upsert (from Excel upload).
     */
    public function bulkUpsert(array $rows): array
    {
        $success = 0;
        $errors  = [];

        foreach ($rows as $idx => $row) {
            try {
                $this->markAttendance($row['employee_id'], $row['attendance_date'], [
                    'check_in'  => $row['check_in'] ?? null,
                    'check_out' => $row['check_out'] ?? null,
                    'status'    => $row['status'] ?? 'present',
                    'source'    => 'excel',
                    'remarks'   => $row['remarks'] ?? null,
                ]);
                $success++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $idx + 2, 'message' => $e->getMessage()];
            }
        }

        return ['success' => $success, 'errors' => $errors];
    }

    /**
     * Process regularization approvals.
     */
    public function approveRegularization(\App\Models\AttendanceRegularization $reg): void
    {
        $attendance = Attendance::where('employee_id', $reg->employee_id)
            ->where('attendance_date', $reg->attendance_date)
            ->first();

        if ($attendance) {
            $attendance->update([
                'check_in'       => $reg->requested_check_in,
                'check_out'      => $reg->requested_check_out,
                'is_regularized' => true,
                'status'         => $this->computeStatus(
                    $reg->requested_check_in,
                    $reg->requested_check_out,
                    $attendance->shift
                ),
            ]);
        }

        $reg->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);
    }

    /**
     * Lock all attendance for a period (called by payroll lock).
     */
    public function lockPeriod(int $entityId, string $from, string $to): int
    {
        return Attendance::whereHas('employee', fn($q) => $q->where('entity_id', $entityId))
            ->whereBetween('attendance_date', [$from, $to])
            ->update(['is_locked' => true]);
    }

    // ─────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────

    protected function getHolidayDates(Employee $employee, string $from, string $to): array
    {
        if (!$employee->holiday_calendar_id) return [];

        return Holiday::where('holiday_calendar_id', $employee->holiday_calendar_id)
            ->whereBetween('date', [$from, $to])
            ->pluck('date')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
            ->toArray();
    }

    protected function getRosterWorkingDays(Employee $employee): array
    {
        // Default: Mon-Fri. Could be fetched from employee_rosters.
        $roster = \App\Models\Roster::whereHas('employeeRosters', fn($q) => $q->where('employee_id', $employee->id)
            ->where('effective_from', '<=', now())
            ->where(fn($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', now())))
            ->first();

        if ($roster) {
            return array_map(fn($d) => substr($d, 0, 3), $roster->working_days); // Mon, Tue, …
        }

        return ['Mon', 'Tue', 'Wed', 'Thu', 'Fri']; // default
    }

    protected function computeStatus(?string $checkIn, ?string $checkOut, $shift): string
    {
        if (!$checkIn) return 'absent';
        if (!$shift)   return 'present';

        $shiftStart = Carbon::parse($shift->start_time);
        $actualIn   = Carbon::parse($checkIn);
        $lateBy     = $shiftStart->diffInMinutes($actualIn, false);

        if ($lateBy > $shift->half_day_after_minutes && $shift->half_day_after_minutes > 0) return 'half_day';
        if ($lateBy > $shift->late_mark_after_minutes) return 'late';
        return 'present';
    }
}
