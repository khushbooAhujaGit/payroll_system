<?php

namespace App\Services;

use App\Models\LeaveApplication;
use App\Models\LeaveTrackerRecord;
use Carbon\CarbonPeriod;

/**
 * Mirrors a LeaveApplication into leave_tracker_records — one row per day in
 * its range, keyed by a stable 'APP-{id}-{Ymd}' ref id so repeated calls
 * (apply → approve/reject, or a backfill re-run) converge on the same rows
 * instead of duplicating them. Used by WebLeaveController (live apply/approve/
 * reject) and the leave:backfill-tracker-mirror console command (one-time
 * backfill for applications created before this mirroring existed).
 */
class LeaveApplicationTrackerSyncService
{
    public function sync(LeaveApplication $application, bool $isApproved, bool $isActive, ?int $uploadedBy = null): void
    {
        $employee = $application->employee;
        $period   = CarbonPeriod::create($application->from_date, $application->to_date);

        foreach ($period as $day) {
            LeaveTrackerRecord::updateOrCreate(
                ['leave_ref_id' => 'APP-' . $application->id . '-' . $day->format('Ymd')],
                [
                    'employee_id'      => $employee?->id,
                    'sheet_emp_id'     => $employee?->employee_id,
                    'employee_name'    => $employee?->full_name,
                    'leave_date'       => $day->format('Y-m-d'),
                    'leave_type'       => strtoupper($application->leaveType?->code ?? $application->leaveType?->name ?? 'LEAVE'),
                    'reason_type'      => 'Leave Application',
                    'reason'           => $application->reason,
                    'source_file'      => 'leave_application',
                    'uploaded_by'      => $uploadedBy,
                    'is_active'        => $isActive,
                    'is_approved'      => $isApproved,
                ]
            );
        }
    }

    /**
     * Derive is_approved/is_active straight from the application's current
     * status — used by the backfill command, where there's no explicit
     * approve/reject action being performed right now.
     */
    public function syncFromStatus(LeaveApplication $application, ?int $uploadedBy = null): void
    {
        $this->sync(
            $application,
            isApproved: $application->status === 'approved',
            isActive: $application->status !== 'rejected',
            uploadedBy: $uploadedBy,
        );
    }
}
