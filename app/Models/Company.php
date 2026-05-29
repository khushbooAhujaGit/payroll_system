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

class Company extends Model
{
    use SoftDeletes;
    protected $table = 'companies';
    protected $fillable = ['name','short_name','logo','website','email','phone','address','city','state','country','pincode','pan','tan','gstin','cin','is_active'];
    protected $casts    = ['is_active' => 'boolean'];
    public function entities(): HasMany { return $this->hasMany(Entity::class); }
}
