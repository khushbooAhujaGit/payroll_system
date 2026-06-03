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

class LeavePolicy extends Model
{
    protected $table = 'leave_policies';
    protected $fillable = ['company_id','leave_type_id','name','days_per_year','accrual_type','accrual_days','accrual_on_day','proration','sandwich_policy','min_balance_to_apply','allow_negative_balance','max_negative_days','is_active'];
    protected $casts = ['sandwich_policy' => 'boolean', 'allow_negative_balance' => 'boolean'];
    public function leaveType(): BelongsTo { return $this->belongsTo(LeaveType::class); }
}
