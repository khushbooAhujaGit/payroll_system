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

class Contractor extends Model
{
    protected $fillable = ['company_id','name','contractor_id','pan','gstin','mobile','email','address','bank_account_number','bank_name','ifsc_code','contractor_type','is_active'];
    protected $casts    = ['is_active' => 'boolean'];

    public function entity(): BelongsTo  { return $this->belongsTo(Company::class); }
    public function invoices(): HasMany  { return $this->hasMany(ContractorInvoice::class); }
}
