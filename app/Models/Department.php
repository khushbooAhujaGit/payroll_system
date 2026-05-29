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

class Department extends Model
{
    protected $fillable = ['entity_id','parent_id','name','code','is_active'];
    public function entity(): BelongsTo   { return $this->belongsTo(Entity::class); }
    public function parent(): BelongsTo   { return $this->belongsTo(Department::class, 'parent_id'); }
    public function children(): HasMany   { return $this->hasMany(Department::class, 'parent_id'); }
    public function employees(): HasMany  { return $this->hasMany(Employee::class); }
}
