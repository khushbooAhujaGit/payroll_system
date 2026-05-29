<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceApproval extends Model
{
    protected $fillable = [
        'employee_id',
        'attendance_date',
        'punch_in_approval',
        'punch_out_approval',
        'updated_by',
    ];

    protected $casts = [
        'attendance_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
