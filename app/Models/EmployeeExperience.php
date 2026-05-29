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

class EmployeeExperience extends Model
{
    protected $table = 'employee_experience';
    protected $fillable = ['employee_id','company_name','designation','from_date','to_date','is_current','responsibilities','experience_letter'];
    protected $casts    = ['from_date' => 'date', 'to_date' => 'date', 'is_current' => 'boolean'];
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
}
