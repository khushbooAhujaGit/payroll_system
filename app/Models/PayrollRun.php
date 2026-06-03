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

class PayrollRun extends Model
{
    protected $fillable = [
        'payroll_period_id','company_id','run_type','run_name',
        'total_employees','total_gross','total_deductions','total_net',
        'total_employer_contribution','status','is_editable',
        'calculated_at','calculated_by','approved_at','approved_by','remarks',
    ];
    protected $casts = [
        'is_editable'    => 'boolean',
        'calculated_at'  => 'datetime',
        'approved_at'    => 'datetime',
        'total_gross'    => 'decimal:2',
        'total_net'      => 'decimal:2',
    ];

    public function period(): BelongsTo      { return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id'); }
    public function entity(): BelongsTo      { return $this->belongsTo(Company::class); }
    public function calculatedBy(): BelongsTo{ return $this->belongsTo(User::class, 'calculated_by'); }
    public function approvedBy(): BelongsTo  { return $this->belongsTo(User::class, 'approved_by'); }
    public function employeeRecords(): HasMany{ return $this->hasMany(PayrollEmployeeRecord::class); }
    public function bankFiles(): HasMany     { return $this->hasMany(BankTransferFile::class); }
}
