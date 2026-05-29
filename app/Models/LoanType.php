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

class LoanType extends Model
{
    protected $fillable = ['entity_id','name','code','max_amount','max_emi','interest_rate','is_active'];
    protected $casts    = ['max_amount' => 'decimal:2', 'interest_rate' => 'decimal:2', 'is_active' => 'boolean'];
    public function entity(): BelongsTo { return $this->belongsTo(Entity::class); }
    public function loans(): HasMany    { return $this->hasMany(EmployeeLoan::class); }
}
