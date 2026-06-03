<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use SoftDeletes;

    protected $table    = 'companies';
    protected $fillable = [
        'name', 'short_name', 'logo', 'website', 'email', 'phone',
        'address', 'city', 'state', 'country', 'pincode',
        'pan', 'tan', 'gstin', 'cin', 'is_active',
    ];
    protected $casts = ['is_active' => 'boolean'];

    public function employees(): HasMany   { return $this->hasMany(Employee::class); }
    public function departments(): HasMany { return $this->hasMany(Department::class); }
    public function designations(): HasMany{ return $this->hasMany(Designation::class); }
    public function branches(): HasMany    { return $this->hasMany(Branch::class); }
}
