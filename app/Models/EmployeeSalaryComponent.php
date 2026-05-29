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

class EmployeeSalaryComponent extends Model
{
    protected $fillable = ['employee_salary_id','salary_component_id','monthly_amount','annual_amount'];
    protected $casts    = ['monthly_amount' => 'decimal:2', 'annual_amount' => 'decimal:2'];

    public function employeeSalary(): BelongsTo  { return $this->belongsTo(EmployeeSalary::class); }
    public function salaryComponent(): BelongsTo { return $this->belongsTo(SalaryComponent::class); }
}
