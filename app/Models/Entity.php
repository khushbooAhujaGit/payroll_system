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

class Entity extends Model
{
    use SoftDeletes;
    protected $table = 'entities';
    protected $fillable = ['company_id','name','short_name','pan','tan','gstin','pf_number','esic_number','pt_number','lwf_number','payroll_cycle','payroll_cutoff_day','state','is_active'];
    protected $casts    = ['is_active' => 'boolean'];

    public function company(): BelongsTo    { return $this->belongsTo(Company::class); }
    public function branches(): HasMany     { return $this->hasMany(Branch::class); }
    public function departments(): HasMany  { return $this->hasMany(Department::class); }
    public function designations(): HasMany { return $this->hasMany(Designation::class); }
    public function grades(): HasMany       { return $this->hasMany(Grade::class); }
    public function employees(): HasMany    { return $this->hasMany(Employee::class); }
    public function pfSetting(): HasOne     { return $this->hasOne(PfSetting::class); }
    public function esicSetting(): HasOne   { return $this->hasOne(EsicSetting::class); }
}
