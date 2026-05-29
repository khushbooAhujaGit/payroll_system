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

class PayrollEmployeeRecord extends Model
{
    protected $fillable = [
        'payroll_run_id','employee_id','working_days','present_days','absent_days',
        'leave_days','lop_days','holiday_days','weekly_off_days','overtime_minutes',
        'payable_days','gross_salary','total_earnings','total_deductions',
        'total_employer_contribution','net_salary',
        'pf_employee','pf_employer','esic_employee','esic_employer',
        'pt_amount','lwf_employee','lwf_employer','tds_amount',
        'loan_deduction','arrear_amount',
        'is_manually_edited','manual_net_override','manual_edit_reason',
        'manually_edited_by','manually_edited_at',
        'status','is_blocked','block_reason','payslip_path',
    ];
    protected $casts = [
        'is_manually_edited' => 'boolean',
        'is_blocked'         => 'boolean',
        'manually_edited_at' => 'datetime',
        'net_salary'         => 'decimal:2',
        'total_earnings'     => 'decimal:2',
        'total_deductions'   => 'decimal:2',
        'lop_days'           => 'decimal:2',
        'payable_days'       => 'decimal:2',
    ];

    public function payrollRun(): BelongsTo  { return $this->belongsTo(PayrollRun::class); }
    public function employee(): BelongsTo    { return $this->belongsTo(Employee::class); }
    public function editedBy(): BelongsTo    { return $this->belongsTo(User::class, 'manually_edited_by'); }
    public function components(): HasMany    { return $this->hasMany(PayrollComponent::class); }

    public function getFinalNetAttribute(): float
    {
        return $this->manual_net_override ?? $this->net_salary;
    }
}
