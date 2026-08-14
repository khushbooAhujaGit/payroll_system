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

class DeclarationHra extends Model
{
    protected $table = 'declaration_hra';
    protected $fillable = ['investment_declaration_id','landlord_name','landlord_pan','rental_address','monthly_rent','from_date','to_date'];
    protected $casts    = [
        'from_date' => 'date',
        'to_date' => 'date',
        'monthly_rent' => 'decimal:2',
        // Encrypted at rest — see database/migrations/2026_07_15_000001_add_column_encryption_support.php
        'landlord_pan' => 'encrypted',
        'rental_address' => 'encrypted',
    ];
    public function declaration(): BelongsTo { return $this->belongsTo(InvestmentDeclaration::class); }
}
