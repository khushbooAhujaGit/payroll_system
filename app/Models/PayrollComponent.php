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

class PayrollComponent extends Model
{
    protected $fillable = ['payroll_employee_record_id','salary_component_id','computed_amount','override_amount','final_amount','is_overridden','override_reason'];
    protected $casts = ['is_overridden' => 'boolean', 'computed_amount' => 'decimal:2', 'final_amount' => 'decimal:2'];

    public function payrollEmployeeRecord(): BelongsTo { return $this->belongsTo(PayrollEmployeeRecord::class); }
    public function salaryComponent(): BelongsTo       { return $this->belongsTo(SalaryComponent::class); }
}
