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

class InvestmentDeclaration extends Model
{
    protected $fillable = ['employee_id','financial_year','regime','status','submitted_at','approved_at','approved_by'];
    protected $casts    = ['submitted_at' => 'datetime', 'approved_at' => 'datetime'];

    public function employee(): BelongsTo  { return $this->belongsTo(Employee::class); }
    public function approvedBy(): BelongsTo{ return $this->belongsTo(User::class, 'approved_by'); }
    public function investments(): HasMany { return $this->hasMany(DeclarationInvestment::class); }
    public function hra(): HasOne          { return $this->hasOne(DeclarationHra::class); }
    public function poiSubmissions(): HasMany { return $this->hasMany(PoiSubmission::class); }
}
