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

class Grade extends Model
{
    protected $fillable = ['company_id','name','code','level','min_ctc','max_ctc','is_active'];
    protected $casts    = ['min_ctc' => 'decimal:2', 'max_ctc' => 'decimal:2'];
    public function entity(): BelongsTo  { return $this->belongsTo(Company::class); }
    public function employees(): HasMany { return $this->hasMany(Employee::class); }
}
