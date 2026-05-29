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

class LoanInstallment extends Model
{
    protected $fillable = ['employee_loan_id','employee_id','installment_number','emi_year','emi_month','principal_amount','interest_amount','total_emi_amount','outstanding_before','outstanding_after','status','payroll_run_id'];
    protected $casts = ['principal_amount' => 'decimal:2', 'total_emi_amount' => 'decimal:2', 'outstanding_before' => 'decimal:2', 'outstanding_after' => 'decimal:2'];

    public function employeeLoan(): BelongsTo { return $this->belongsTo(EmployeeLoan::class); }
    public function employee(): BelongsTo     { return $this->belongsTo(Employee::class); }
    public function payrollRun(): BelongsTo   { return $this->belongsTo(PayrollRun::class); }
}
