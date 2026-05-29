<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Frozen salary report row for a specific employee + month.
 *
 * Once locked, this snapshot is used instead of live-computed data,
 * ensuring historical reports are immune to changes in salary, advances,
 * leave tracker records, or any other source data.
 *
 * @property int    $id
 * @property int    $employee_id
 * @property string $year_month    e.g. "2025-04"
 * @property array  $snapshot_data Full computed row (all columns)
 * @property \Carbon\Carbon|null $locked_at
 * @property int|null $locked_by
 */
class SalaryReportSnapshot extends Model
{
    protected $table = 'salary_report_snapshots';

    protected $fillable = [
        'employee_id',
        'year_month',
        'snapshot_data',
        'locked_at',
        'locked_by',
    ];

    protected $casts = [
        'snapshot_data' => 'array',
        'locked_at'     => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }
}
