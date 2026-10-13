<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Designation extends Model
{
    protected $fillable = ['company_id', 'name', 'code', 'level', 'is_active'];

    public function company(): BelongsTo  { return $this->belongsTo(Company::class); }
    public function employees(): HasMany  { return $this->hasMany(Employee::class); }
}
