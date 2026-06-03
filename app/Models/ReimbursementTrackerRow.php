<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReimbursementTrackerRow extends Model
{
    protected $table = 'reimbursement_tracker_rows';

    protected $fillable = [
        'year_month',
        'expense_date',
        'emp_id',
        'employee_name',
        'amount',
        'remarks',
        'image_filename',
        'status',
        'company_id',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
