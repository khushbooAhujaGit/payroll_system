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

class ExitRequest extends Model
{
    protected $fillable = ['employee_id','exit_type','notice_date','last_working_date','notice_period_days','actual_notice_days','exit_reason','status','approved_by','approved_at','remarks'];
    protected $casts    = ['notice_date' => 'date', 'last_working_date' => 'date', 'approved_at' => 'datetime'];

    public function employee(): BelongsTo   { return $this->belongsTo(Employee::class); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
