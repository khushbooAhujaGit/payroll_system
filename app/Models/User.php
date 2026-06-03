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

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'company_id','employee_id','name','email','password','mobile',
        'is_active','avatar','last_login_at','last_login_ip','timezone',
    ];
    protected $hidden   = ['password','remember_token'];
    protected $casts    = ['is_active' => 'boolean', 'last_login_at' => 'datetime'];

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function company(): BelongsTo  { return $this->belongsTo(Company::class); }
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
                    ->withPivot('company_id','department_id')
                    ->withTimestamps();
    }
    public function assignedTickets(): HasMany { return $this->hasMany(HelpdeskTicket::class, 'assigned_to'); }

    public function hasRole(string $slug): bool
    {
        return $this->roles->contains('slug', $slug);
    }
    public function isSuperAdmin(): bool { return $this->hasRole('super_admin'); }
}
