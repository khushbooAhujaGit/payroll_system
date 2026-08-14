<?php

namespace App\Notifications;

use App\Models\AttendanceRegularization;
use Illuminate\Notifications\Notification;

/**
 * Bell notification to HR when an employee raises a weekly-shortfall
 * Change Request — see WebMyAttendanceController::requestChange(). Database
 * only, no email.
 */
class AttendanceChangeRequestSubmitted extends Notification
{
    public function __construct(protected AttendanceRegularization $regularization)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $employee  = $this->regularization->employee;
        $weekLabel = $this->regularization->attendance_date->format('d M Y');

        return [
            'title'   => 'Attendance Change Request',
            'message' => "{$employee?->full_name} raised a change request for the week of {$weekLabel}.",
            'url'     => route('attendance.change_requests'),
        ];
    }
}
