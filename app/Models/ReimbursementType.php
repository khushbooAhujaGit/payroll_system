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

class ReimbursementType extends Model
{
    protected $fillable = ['company_id','name','code','monthly_limit','annual_limit','is_taxable','requires_bill','is_active'];
    protected $casts = ['is_taxable' => 'boolean', 'requires_bill' => 'boolean'];
}
