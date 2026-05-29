<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRawPunch extends Model
{
    protected $table = 'attendance_raw_punches';

    protected $fillable = [
        'employee_id', 'bio_id_full', 'bio_number', 'machine_name',
        'attendance_date', 'check_in', 'check_out', 'is_absent',
        'source_file', 'uploaded_by',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'is_absent'       => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
