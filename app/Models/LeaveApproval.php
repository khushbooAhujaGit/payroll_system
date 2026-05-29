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

class LeaveApproval extends Model
{
    protected $fillable = ['leave_application_id','approver_id','level','action','remarks','actioned_at'];
    protected $casts    = ['actioned_at' => 'datetime'];
    public function application(): BelongsTo { return $this->belongsTo(LeaveApplication::class); }
    public function approver(): BelongsTo    { return $this->belongsTo(User::class, 'approver_id'); }
}
