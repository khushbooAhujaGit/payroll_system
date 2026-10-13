<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The one mapped home/client-site point an employee sets from the app —
 * re-mapping replaces it (no history). See ATTENDANCE_APP_PLAN.md §3.1.
 */
class EmployeeAttendanceLocation extends Model
{
    protected $table = 'employee_attendance_locations';

    protected $fillable = [
        'employee_id', 'latitude', 'longitude', 'label', 'mapped_at',
    ];

    protected $casts = [
        'latitude'  => 'decimal:7',
        'longitude' => 'decimal:7',
        'mapped_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
