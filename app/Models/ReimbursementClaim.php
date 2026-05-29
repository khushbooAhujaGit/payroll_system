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

class ReimbursementClaim extends Model
{
    protected $fillable = ['claim_number','employee_id','year','month','total_claimed','total_approved','status','approved_by','approved_at','payroll_run_id'];
    protected $casts = ['total_claimed' => 'decimal:2', 'total_approved' => 'decimal:2', 'approved_at' => 'datetime'];

    public function employee(): BelongsTo  { return $this->belongsTo(Employee::class); }
    public function approvedBy(): BelongsTo{ return $this->belongsTo(User::class, 'approved_by'); }
    public function items(): HasMany       { return $this->hasMany(ReimbursementItem::class); }
}
