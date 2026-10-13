<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One raw mark in/out event sent by the mobile app — every event is kept
 * (never summarized away), so this is the full log for analytics.
 * AttendanceDayResolver reduces these to a first-in/last-out pair per
 * employee+day. See ATTENDANCE_APP_PLAN.md §3.3 / §4.
 */
class AttendanceAppPunchEvent extends Model
{
    protected $table = 'attendance_app_punch_events';

    protected $fillable = [
        'employee_id', 'event_type', 'method', 'event_at',
        'latitude', 'longitude', 'distance_from_mapped_m',
        'is_deviation', 'deviation_reason', 'wifi_ssid', 'source_app_version',
    ];

    protected $casts = [
        'event_at'                => 'datetime',
        'latitude'                => 'decimal:7',
        'longitude'               => 'decimal:7',
        'distance_from_mapped_m'  => 'decimal:2',
        'is_deviation'            => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
