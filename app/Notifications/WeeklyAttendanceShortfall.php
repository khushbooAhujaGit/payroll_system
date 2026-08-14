<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Notifications\Notification;

/**
 * In-app (bell/dropdown) counterpart to the weekly shortfall email sent by
 * CheckWeeklyAverageAttendance. Database-only — the email itself is still
 * sent separately through OutlookMailerService, not through this class.
 */
class WeeklyAttendanceShortfall extends Notification
{
    public function __construct(
        protected Carbon $weekStart,
        protected Carbon $weekEnd,
        protected float $averageHours
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'   => 'Attendance Notice',
            'message' => "Your average clocked hours for the week of {$this->weekStart->format('d M')} – {$this->weekEnd->format('d M Y')} were {$this->averageHours}h/day, below the required 9h average.",
            'url'     => route('my-attendance', ['month' => $this->weekStart->format('Y-m')]),
        ];
    }
}
