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

class EmployeeHistory extends Model
{
    protected $table = 'employee_history';
    protected $fillable = ['employee_id','event_type','effective_date','old_values','new_values','remarks','letter_path','created_by'];
    protected $casts = ['effective_date' => 'date', 'old_values' => 'array', 'new_values' => 'array'];

    public function employee(): BelongsTo  { return $this->belongsTo(Employee::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
