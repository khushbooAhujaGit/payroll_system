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

class PoiSubmission extends Model
{
    protected $fillable = ['investment_declaration_id','declaration_investment_id','financial_year','section','investment_type','claimed_amount','file_path','file_name','remarks','status','approved_amount','hr_remarks','reviewed_by','reviewed_at'];
    protected $casts    = ['claimed_amount' => 'decimal:2', 'approved_amount' => 'decimal:2', 'reviewed_at' => 'datetime'];

    public function investmentDeclaration(): BelongsTo    { return $this->belongsTo(InvestmentDeclaration::class); }
    public function declarationInvestment(): BelongsTo    { return $this->belongsTo(DeclarationInvestment::class); }
    public function reviewedBy(): BelongsTo               { return $this->belongsTo(User::class, 'reviewed_by'); }
}
