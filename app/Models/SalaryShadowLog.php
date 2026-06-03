<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Private plaintext salary log — owner's eyes only.
 * Never expose this model via public API or shared views.
 */
class SalaryShadowLog extends Model
{
    protected $table = 'salary_shadow_log';

    protected $fillable = [
        'employee_id',
        'emp_id_text',
        'employee_name',
        'gross_monthly',
        'ctc_monthly',
        'ctc_annual',
        'effective_from',
        'import_month',
        'source',
        'recorded_by',
    ];

    protected $casts = [
        'gross_monthly'  => 'decimal:2',
        'ctc_monthly'    => 'decimal:2',
        'ctc_annual'     => 'decimal:2',
        'effective_from' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
