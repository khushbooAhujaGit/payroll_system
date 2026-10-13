<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The "wins over everything" row for an employee+date — when one exists,
 * AttendanceDayResolver uses its check_in/check_out exclusively and ignores
 * attendance_raw_punches / attendance_app_punch_events for that day
 * entirely. One row per employee+date (see unique constraint).
 * See ATTENDANCE_APP_PLAN.md §3.4 / §4.
 */
class AttendanceHrOverride extends Model
{
    protected $table = 'attendance_hr_overrides';

    protected $fillable = [
        'employee_id', 'attendance_date', 'check_in', 'check_out',
        'reason', 'source', 'created_by', 'source_file',
    ];

    protected $casts = [
        'attendance_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
