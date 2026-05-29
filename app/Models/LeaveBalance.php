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

class LeaveBalance extends Model
{
    protected $fillable = ['employee_id','leave_type_id','year','month','opening_balance','accrued','carry_forwarded','availed','encashed','lapsed','adjusted','closing_balance'];
    protected $casts = ['opening_balance' => 'decimal:2', 'accrued' => 'decimal:2', 'availed' => 'decimal:2', 'closing_balance' => 'decimal:2'];

    public function employee(): BelongsTo  { return $this->belongsTo(Employee::class); }
    public function leaveType(): BelongsTo { return $this->belongsTo(LeaveType::class); }

    public function getAvailableAttribute(): float
    {
        return max(0, $this->opening_balance + $this->accrued + $this->carry_forwarded + $this->adjusted - $this->availed - $this->encashed);
    }
}
