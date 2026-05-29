<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasAuditLog;

class Employee extends Model
{
    use HasFactory, SoftDeletes, HasAuditLog;

    protected $fillable = [
        'entity_id', 'branch_id', 'department_id', 'designation_id',
        'grade_id', 'cost_center_id', 'reporting_manager_id', 'team_leader_id',
        'holiday_calendar_id',
        'title', 'employee_id', 'external_emp_id', 'badge_number', 'badge_id',
        'first_name', 'middle_name', 'last_name', 'gender', 'date_of_birth',
        'blood_group', 'marital_status', 'number_of_dependents', 'nationality',
        'religion', 'caste_category', 'is_physically_handicapped', 'photo',
        'aadhaar_document_path',
        'personal_email', 'official_email', 'mobile', 'alternate_phone',
        'current_address', 'current_city', 'current_state', 'current_pincode',
        'permanent_address', 'permanent_city', 'permanent_state', 'permanent_pincode',
        'date_of_joining', 'date_of_leaving', 'offer_letter_date', 'date_of_confirmation',
        'probation_period_months', 'probation_end_date',
        'employment_type', 'work_type', 'attendance_group', 'employee_status',
        'is_payroll_active', 'is_pf_applicable', 'is_esic_applicable',
        'is_pt_applicable', 'is_lwf_applicable', 'is_tds_applicable', 'tds_regime',
        'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation',
        'created_by',
    ];

    protected $casts = [
        'date_of_birth'              => 'date',
        'date_of_joining'            => 'date',
        'date_of_leaving'            => 'date',
        'offer_letter_date'          => 'date',
        'date_of_confirmation'       => 'date',
        'probation_end_date'         => 'date',
        'is_physically_handicapped'  => 'boolean',
        'is_payroll_active'          => 'boolean',
        'is_pf_applicable'           => 'boolean',
        'is_esic_applicable'         => 'boolean',
        'is_pt_applicable'           => 'boolean',
        'is_lwf_applicable'          => 'boolean',
        'is_tds_applicable'          => 'boolean',
    ];

    // ──────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function reportingManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reporting_manager_id');
    }

    public function teamLeader(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'team_leader_id');
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(Employee::class, 'team_leader_id');
    }

    public function bioIds(): HasMany
    {
        return $this->hasMany(EmployeeBioId::class);
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'reporting_manager_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function bankDetails(): HasMany
    {
        return $this->hasMany(EmployeeBankDetail::class);
    }

    public function primaryBank(): HasOne
    {
        return $this->hasOne(EmployeeBankDetail::class)->where('is_primary', true);
    }

    public function statutoryDetails(): HasOne
    {
        return $this->hasOne(EmployeeStatutoryDetail::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(EmployeeHistory::class);
    }

    public function currentSalary(): HasOne
    {
        return $this->hasOne(EmployeeSalary::class)
            ->where('is_current', true)
            ->orderByDesc('effective_from')
            ->orderByDesc('id');
    }

    public function salaries(): HasMany
    {
        return $this->hasMany(EmployeeSalary::class)->orderByDesc('effective_from');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function leaveApplications(): HasMany
    {
        return $this->hasMany(LeaveApplication::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(EmployeeLoan::class);
    }

    public function investmentDeclarations(): HasMany
    {
        return $this->hasMany(InvestmentDeclaration::class);
    }

    public function currentInvestmentDeclaration(): HasOne
    {
        $fy = $this->currentFinancialYear();
        return $this->hasOne(InvestmentDeclaration::class)
                    ->where('financial_year', $fy);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function helpdeskTickets(): HasMany
    {
        return $this->hasMany(HelpdeskTicket::class);
    }

    public function onboarding(): HasMany
    {
        return $this->hasMany(EmployeeOnboarding::class);
    }

    public function exitRequest(): HasOne
    {
        return $this->hasOne(ExitRequest::class);
    }

    // ──────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }

    public function getServiceYearsAttribute(): float
    {
        return round($this->date_of_joining->diffInYears(now(), true), 2);
    }

    public function getServiceMonthsAttribute(): int
    {
        return $this->date_of_joining->diffInMonths(now());
    }

    // ──────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('employee_status', 'active');
    }

    public function scopePayrollActive($query)
    {
        return $query->where('is_payroll_active', true)
                     ->where('employee_status', 'active');
    }

    public function scopeByEntity($query, int $entityId)
    {
        return $query->where('entity_id', $entityId);
    }

    public function scopeByDepartment($query, int $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeOnProbation($query)
    {
        return $query->whereNull('date_of_confirmation')
                     ->whereNotNull('probation_end_date')
                     ->where('probation_end_date', '>', now());
    }

    // ──────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────

    public function currentFinancialYear(): int
    {
        $month = now()->month;
        return $month >= 4 ? now()->year : now()->year - 1;
    }

    public function isOnProbation(): bool
    {
        return is_null($this->date_of_confirmation) &&
               $this->probation_end_date &&
               $this->probation_end_date->isFuture();
    }

    public function hasCompletedFiveYears(): bool
    {
        return $this->date_of_joining->diffInYears(now()) >= 5;
    }
}
