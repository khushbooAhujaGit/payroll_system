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

class EmployeeTdsComputation extends Model
{
    protected $table = 'employee_tds_computation';
    protected $fillable = ['employee_id','financial_year','month','gross_salary_ytd','declared_investments','approved_investments','hra_exemption','standard_deduction','taxable_income','tax_payable_annual','tax_paid_ytd','monthly_tds','regime'];
    protected $casts    = ['gross_salary_ytd' => 'decimal:2', 'taxable_income' => 'decimal:2', 'monthly_tds' => 'decimal:2'];
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
}
