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

class PtSlab extends Model
{
    protected $fillable = ['company_id','state','salary_from','salary_to','pt_amount','effective_year','is_active'];
    protected $casts    = ['salary_from' => 'decimal:2', 'salary_to' => 'decimal:2', 'pt_amount' => 'decimal:2'];
    public function entity(): BelongsTo { return $this->belongsTo(Company::class); }
}
