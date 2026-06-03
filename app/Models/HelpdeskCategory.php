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

class HelpdeskCategory extends Model
{
    protected $table = 'helpdesk_categories';
    protected $fillable = ['company_id','name','parent_id','assigned_team','sla_hours','is_active'];
    protected $casts    = ['is_active' => 'boolean'];

    public function entity(): BelongsTo   { return $this->belongsTo(Company::class); }
    public function parent(): BelongsTo   { return $this->belongsTo(HelpdeskCategory::class, 'parent_id'); }
    public function children(): HasMany   { return $this->hasMany(HelpdeskCategory::class, 'parent_id'); }
    public function tickets(): HasMany    { return $this->hasMany(HelpdeskTicket::class, 'category_id'); }
}
