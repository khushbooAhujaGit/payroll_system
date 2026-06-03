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

class LeaveType extends Model
{
    protected $fillable = ['company_id','name','code','color','category','is_paid','is_carry_forward','max_carry_forward','is_encashable','is_half_day_allowed','requires_document','document_required_after_days','min_days','max_days_per_application','max_days_per_year','applicable_on_probation','notice_days_required','gender_specific','is_lop_type','is_active'];
    protected $casts = ['is_paid' => 'boolean', 'is_carry_forward' => 'boolean', 'is_encashable' => 'boolean'];
    public function entity(): BelongsTo { return $this->belongsTo(Company::class); }
    public function policies(): HasMany { return $this->hasMany(LeavePolicy::class); }
    public function balances(): HasMany { return $this->hasMany(LeaveBalance::class); }
}
