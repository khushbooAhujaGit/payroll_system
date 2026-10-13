<?php

namespace App\Notifications;

use App\Models\Employee;
use App\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Notifications\Notification;

/**
 * In-app (bell/dropdown) notification fired once per Apply Leave
 * submission — see WebLeaveController::apply() — to the applicant's
 * reporting manager and every HR user scoped to their company. Stays in
 * the recipient's unread list (so it keeps showing "on every login") until
 * either they open it or the underlying tracker record(s) get resolved,
 * at which point WebLeaveController::cleanupLeavePendingNotification()
 * removes it — see that method for why it's tracked per tracker-record-id
 * rather than per employee/date-range.
 */
class LeaveApplicationPending extends Notification
{
    public function __construct(
        protected Employee $employee,
        protected LeaveType $leaveType,
        protected string $fromDate,
        protected string $toDate,
        protected array $trackerRecordIds
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $duration = $this->fromDate === $this->toDate
            ? Carbon::parse($this->fromDate)->format('d M Y')
            : Carbon::parse($this->fromDate)->format('d M Y') . ' – ' . Carbon::parse($this->toDate)->format('d M Y');

        return [
            'title'              => 'Leave Approval Pending',
            'message'            => "{$this->employee->full_name} applied for {$this->leaveType->name} ({$duration}).",
            'url'                => route('leave', ['applications_tab' => 'team']),
            'tracker_record_ids' => $this->trackerRecordIds,
        ];
    }
}
