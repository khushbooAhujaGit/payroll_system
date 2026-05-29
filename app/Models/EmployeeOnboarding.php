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

class EmployeeOnboarding extends Model
{
    protected $table = 'employee_onboarding';
    protected $fillable = ['employee_id','onboarding_task_id','status','due_date','completed_date','remarks','completed_by'];
    protected $casts    = ['due_date' => 'date', 'completed_date' => 'date'];

    public function employee(): BelongsTo      { return $this->belongsTo(Employee::class); }
    public function onboardingTask(): BelongsTo{ return $this->belongsTo(OnboardingTask::class); }
}
