<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeMasterHistory extends Model
{
    protected $table = 'employee_master_history';

    protected $fillable = [
        'employee_id', 'field_type', 'old_value', 'new_value',
        'changed_via', 'changed_by',
    ];

    // Encrypted at rest — snapshots bank_details/pan/email values verbatim.
    // See database/migrations/2026_07_15_000001_add_column_encryption_support.php
    protected $casts = [
        'old_value' => 'encrypted:array',
        'new_value' => 'encrypted:array',
    ];

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function changedBy(): BelongsTo { return $this->belongsTo(User::class, 'changed_by'); }
}
