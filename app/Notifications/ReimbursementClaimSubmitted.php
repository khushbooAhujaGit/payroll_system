<?php

namespace App\Notifications;

use App\Models\ReimbursementTrackerRow;
use Illuminate\Notifications\Notification;

/**
 * Bell notification to the designated reimbursement approver (whoever
 * currently holds the reimbursment_approve permission — see
 * WebMyReimbursementController::submit()) when an employee raises a claim.
 * Database only, no email.
 */
class ReimbursementClaimSubmitted extends Notification
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
            'title'   => 'Reimbursement Claim Submitted',
            'message' => "{$this->row->employee_name} submitted a claim of ₹" . number_format((float) $this->row->amount, 2) . " for {$this->row->year_month}.",
            'url'     => route('reimbursements.approvals'),
        ];
    }
}
