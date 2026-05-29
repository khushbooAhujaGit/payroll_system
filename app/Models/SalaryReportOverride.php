<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Manual overrides for non-auto-computed salary report fields.
 *
 * @property int         $id
 * @property int         $employee_id
 * @property string      $year_month       e.g. "2025-04"
 * @property int|null    $original_lop
 * @property float|null  $original_incentive
 * @property float|null  $original_advance
 * @property float|null  $original_tds
 * @property float|null  $original_bonus
 * @property int|null    $lop_override
 * @property float|null  $incentive_override
 * @property float|null  $advance_override
 * @property float|null  $tds_override
 * @property float|null  $bonus_override
 * @property string|null $notes
 * @property bool        $is_active
 * @property int|null    $superseded_by_id
 * @property int|null    $created_by
 */
class SalaryReportOverride extends Model
{
    protected $table = 'salary_report_overrides';

    protected $fillable = [
        'employee_id', 'year_month',
        'original_lop', 'original_incentive', 'original_advance', 'original_tds', 'original_bonus', 'original_payable_gross',
        'lop_override', 'incentive_override', 'advance_override', 'tds_override', 'bonus_override', 'payable_gross_override',
        'notes', 'is_active', 'superseded_by_id', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }
}
