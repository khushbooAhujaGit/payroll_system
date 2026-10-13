<?php

namespace App\Models;

use App\Casts\EncryptedDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReimbursementTrackerRow extends Model
{
    protected $table = 'reimbursement_tracker_rows';

    /** Fixed category list employees choose from when submitting a claim (item 63). */
    public const CATEGORIES = [
        'Fuel / Petrol',
        'Courier & Porter',
        'Staff Welfare & Celebrations',
        'Mobile & Internet',
        'Travel & Conveyance',
        'Printing & Stationery',
        'Office Pantry & Housekeeping',
        'Food & Meals',
        'Credit Card Settlement',
        'Audit / Client Site Expenses',
        'Office Maintenance & Cleaning',
        'IT Hardware & Repairs',
        'Salary & Advances',
        'Miscellaneous / Needs Review',
        'Professional Fees & Payments',
        'Software & Subscriptions',
        'Other',
    ];

    protected $fillable = [
        'year_month',
        'expense_date',
        'emp_id',
        'employee_id',
        'employee_name',
        'amount',
        'remarks',
        'category',
        'other_category_text',
        'image_filename',
        'document_path',
        'document_name',
        'status',
        'approved_by',
        'approved_at',
        'approval_remarks',
        'payment_status',
        'payment_date',
        'payment_reference',
        'paid_amount',
        'carried_forward_from_row_id',
        'carried_forward_to_row_id',
        'company_id',
        'created_by',
    ];

    protected $casts = [
        'amount'      => EncryptedDecimal::class . ':2',
        'paid_amount' => EncryptedDecimal::class . ':2',
        'approved_at' => 'datetime',
        'payment_date' => 'date',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** The next-month row holding the unpaid remainder of this claim, if it was ever partially paid. */
    public function carriedForwardTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'carried_forward_to_row_id');
    }

    /** The prior-month claim this row's balance was carried forward from, if any. */
    public function carriedForwardFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'carried_forward_from_row_id');
    }

    /**
     * Single source of truth for recording a payment against an approved
     * claim — used by both the single-row Payment Status modal
     * (WebMyReimbursementController::updatePayment()) and the bulk Excel
     * re-upload path (WebReimbursementReportController::trackerImport()).
     *
     * A 'partial' payment creates (once — re-saving 'partial' again doesn't
     * duplicate it) a new row for the unpaid remainder in next month's
     * tracker, already pre-approved, so it keeps showing up until settled.
     */
    public function applyPayment(string $status, ?string $date, ?string $reference, ?float $paidAmount, int $actorUserId): void
    {
        $this->update([
            'payment_status'    => $status,
            'payment_date'      => in_array($status, ['paid', 'partial'], true) ? ($date ?: now()->toDateString()) : null,
            'payment_reference' => in_array($status, ['paid', 'partial'], true) ? $reference : null,
            'paid_amount'       => $status === 'partial' ? $paidAmount : null,
        ]);

        if ($status === 'partial' && !$this->carried_forward_to_row_id && $paidAmount !== null) {
            $remaining = round(((float) $this->amount) - $paidAmount, 2);
            if ($remaining > 0) {
                $nextMonth = \Carbon\Carbon::createFromFormat('Y-m', $this->year_month)->addMonth()->format('Y-m');
                $carried = self::create([
                    'year_month'           => $nextMonth,
                    'expense_date'         => $this->expense_date,
                    'emp_id'               => $this->emp_id,
                    'employee_id'          => $this->employee_id,
                    'employee_name'        => $this->employee_name,
                    'amount'               => $remaining,
                    'category'             => $this->category,
                    'other_category_text'  => $this->other_category_text,
                    'remarks'              => 'Carried forward — balance of ' . \Carbon\Carbon::createFromFormat('Y-m', $this->year_month)->format('M Y') . ' claim'
                                             . ($this->remarks ? " ({$this->remarks})" : ''),
                    'status'               => 'approved',
                    'payment_status'       => 'unpaid',
                    'carried_forward_from_row_id' => $this->id,
                    'company_id'           => $this->company_id,
                    'created_by'           => $actorUserId,
                ]);
                $this->update(['carried_forward_to_row_id' => $carried->id]);
            }
        }
    }
}
