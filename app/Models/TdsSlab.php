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

class TdsSlab extends Model
{
    protected $fillable = ['financial_year','regime','income_from','income_to','tax_rate','surcharge_rate','cess_rate'];
    protected $casts    = ['income_from' => 'decimal:2', 'income_to' => 'decimal:2', 'tax_rate' => 'decimal:2', 'surcharge_rate' => 'decimal:2', 'cess_rate' => 'decimal:2'];
}
