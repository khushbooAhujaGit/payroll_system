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

class SalaryRevision extends Model
{
    protected $fillable = [
        'employee_id','old_ctc','new_ctc','increment_amount','increment_percentage',
        'effective_date','status','reason','approved_by','approved_at',
        'employee_salary_id','created_by',
    ];
    protected $casts = [
        'effective_date'       => 'date',
        'approved_at'          => 'datetime',
        'old_ctc'              => 'decimal:2',
        'new_ctc'              => 'decimal:2',
        'increment_amount'     => 'decimal:2',
        'increment_percentage' => 'decimal:2',
    ];

    public function employee(): BelongsTo      { return $this->belongsTo(Employee::class); }
    public function approvedBy(): BelongsTo    { return $this->belongsTo(User::class, 'approved_by'); }
    public function createdBy(): BelongsTo     { return $this->belongsTo(User::class, 'created_by'); }
    public function employeeSalary(): BelongsTo{ return $this->belongsTo(EmployeeSalary::class); }
}
