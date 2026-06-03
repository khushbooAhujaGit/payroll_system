<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReimbursementReportSnapshot extends Model
{
    protected $table = 'reimbursement_report_snapshots';

    protected $fillable = [
        'year_month',
        'snapshot_data',
        'row_count',
        'total_amount',
        'locked_at',
        'locked_by',
    ];

    protected $casts = [
        'snapshot_data' => 'array',
        'locked_at'     => 'datetime',
        'total_amount'  => 'decimal:2',
    ];

    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }
}
