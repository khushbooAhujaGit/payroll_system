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

class LeaveApplication extends Model
{
    protected $fillable = ['application_number','employee_id','leave_type_id','from_date','to_date','days','is_half_day','half_day_type','reason','document_path','status','is_lop','applied_at'];
    protected $casts = ['from_date' => 'date', 'to_date' => 'date', 'days' => 'decimal:2', 'is_half_day' => 'boolean', 'is_lop' => 'boolean', 'applied_at' => 'datetime'];

    public function employee(): BelongsTo   { return $this->belongsTo(Employee::class); }
    public function leaveType(): BelongsTo  { return $this->belongsTo(LeaveType::class); }
    public function approvals(): HasMany    { return $this->hasMany(LeaveApproval::class); }
}
