<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Carbon\Carbon;

class Attendance extends Model
{
    protected $table = 'attendance';
    protected $fillable = [
        'employee_id','attendance_date','shift_id','check_in','check_out',
        'working_minutes','overtime_minutes','late_by_minutes','early_exit_minutes',
        'status','work_type','source','is_regularized','is_locked','remarks',
    ];
    protected $casts = [
        'attendance_date' => 'date',
        'is_regularized'  => 'boolean',
        'is_locked'       => 'boolean',
    ];

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function shift(): BelongsTo    { return $this->belongsTo(Shift::class); }

    public function scopeForPeriod($q, string $from, string $to)
    {
        return $q->whereBetween('attendance_date', [$from, $to]);
    }
}
