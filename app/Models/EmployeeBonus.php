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

class EmployeeBonus extends Model
{
    protected $table = 'employee_bonus';
    protected $fillable = ['employee_id','bonus_rule_id','bonus_name','amount','year','month','is_deferred','deferred_till','remarks','status','payroll_run_id'];
    protected $casts = ['amount' => 'decimal:2', 'is_deferred' => 'boolean', 'deferred_till' => 'date'];

    public function employee(): BelongsTo  { return $this->belongsTo(Employee::class); }
    public function bonusRule(): BelongsTo { return $this->belongsTo(BonusRule::class); }
}
