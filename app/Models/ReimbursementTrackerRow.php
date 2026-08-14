<?php

namespace App\Models;

use App\Casts\EncryptedDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReimbursementTrackerRow extends Model
{
    protected $table = 'reimbursement_tracker_rows';

    protected $fillable = [
        'year_month',
        'expense_date',
        'emp_id',
        'employee_id',
        'employee_name',
        'amount',
        'remarks',
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
        'company_id',
        'created_by',
    ];

    protected $casts = [
        'amount'      => EncryptedDecimal::class . ':2',
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
}
