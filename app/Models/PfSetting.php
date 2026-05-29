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

class PfSetting extends Model
{
    protected $fillable = ['entity_id','employee_contribution_pct','employer_contribution_pct','eps_contribution_pct','edli_contribution_pct','admin_charges_pct','cap_at_wage_limit','wage_limit','allow_vpf'];
    protected $casts = ['cap_at_wage_limit' => 'boolean', 'allow_vpf' => 'boolean', 'wage_limit' => 'decimal:2'];
    public function entity(): BelongsTo { return $this->belongsTo(Entity::class); }
}
