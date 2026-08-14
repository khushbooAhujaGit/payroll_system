<?php

namespace App\Notifications;

use App\Models\AttendanceRegularization;
use Illuminate\Notifications\Notification;

/**
 * Bell notification back to the employee once HR approves/rejects their
 * weekly-shortfall Change Request — see
 * WebMyAttendanceController::resolveRequest(). Database only, no email.
 */
class AttendanceChangeRequestResolved extends Notification
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
        $weekLabel = $this->regularization->attendance_date->format('d M Y');
        $status    = ucfirst($this->regularization->status);

        return [
            'title'   => "Change Request {$status}",
            'message' => "Your change request for the week of {$weekLabel} was {$this->regularization->status}"
                . ($this->regularization->approval_remarks ? " — \"{$this->regularization->approval_remarks}\"" : '.'),
            'url'     => route('my-attendance', ['month' => $this->regularization->attendance_date->format('Y-m')]),
        ];
    }
}
