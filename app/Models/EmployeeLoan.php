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

class EmployeeLoan extends Model
{
    protected $fillable = ['loan_number','employee_id','loan_type_id','loan_amount','total_emis','percent_deduction','emi_amount','interest_rate','disbursement_date','first_emi_month','outstanding_amount','status','purpose','approved_by','approved_at','created_by'];
    protected $casts = ['loan_amount' => 'decimal:2', 'emi_amount' => 'decimal:2', 'outstanding_amount' => 'decimal:2', 'disbursement_date' => 'date', 'first_emi_month' => 'date', 'approved_at' => 'datetime'];

    public function employee(): BelongsTo    { return $this->belongsTo(Employee::class); }
    public function loanType(): BelongsTo    { return $this->belongsTo(LoanType::class); }
    public function approvedBy(): BelongsTo  { return $this->belongsTo(User::class, 'approved_by'); }
    public function installments(): HasMany  { return $this->hasMany(LoanInstallment::class); }

    public function getPendingEmisAttribute(): int
    {
        return $this->installments()->where('status', 'pending')->count();
    }
}
