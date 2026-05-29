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

class PayrollPeriod extends Model
{
    protected $fillable = [
        'entity_id','year','month','period_from','period_to',
        'attendance_lock_date','payroll_run_date','payment_date',
        'status','is_locked','locked_at','locked_by',
    ];
    protected $casts = [
        'period_from'           => 'date',
        'period_to'             => 'date',
        'attendance_lock_date'  => 'date',
        'payment_date'          => 'date',
        'is_locked'             => 'boolean',
        'locked_at'             => 'datetime',
    ];

    public function entity(): BelongsTo   { return $this->belongsTo(Entity::class); }
    public function lockedBy(): BelongsTo { return $this->belongsTo(User::class, 'locked_by'); }
    public function runs(): HasMany       { return $this->hasMany(PayrollRun::class); }
    public function regularRun(): HasOne  { return $this->hasOne(PayrollRun::class)->where('run_type', 'regular'); }
}
