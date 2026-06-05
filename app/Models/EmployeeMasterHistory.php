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

    protected $casts = [
        'old_value' => 'array',
        'new_value' => 'array',
    ];

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function changedBy(): BelongsTo { return $this->belongsTo(User::class, 'changed_by'); }
}
