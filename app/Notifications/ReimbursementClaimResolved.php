<?php

namespace App\Notifications;

use App\Models\ReimbursementTrackerRow;
use Illuminate\Notifications\Notification;

/**
 * Bell notification back to the employee once their reimbursement claim is
 * approved/rejected — see WebMyReimbursementController::resolve(). Database
 * only, no email.
 */
class ReimbursementClaimResolved extends Notification
{
    public function __construct(protected ReimbursementTrackerRow $row)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title'   => 'Reimbursement Claim ' . ucfirst($this->row->status),
            'message' => 'Your claim of ₹' . number_format((float) $this->row->amount, 2) . " for {$this->row->year_month} was {$this->row->status}"
                . ($this->row->approval_remarks ? " — \"{$this->row->approval_remarks}\"" : '.'),
            'url'     => route('my-reimbursements'),
        ];
    }
}
