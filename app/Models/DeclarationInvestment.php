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

class DeclarationInvestment extends Model
{
    protected $fillable = ['investment_declaration_id','section','investment_type','declared_amount','approved_amount','proof_path','proof_status'];
    protected $casts    = ['declared_amount' => 'decimal:2', 'approved_amount' => 'decimal:2'];
    public function declaration(): BelongsTo { return $this->belongsTo(InvestmentDeclaration::class, 'investment_declaration_id'); }
}
