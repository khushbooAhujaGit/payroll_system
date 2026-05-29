<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class EmployeeSalary extends Model
{
    protected $table = 'employee_salaries';

    protected $fillable = [
        'employee_id', 'salary_structure_id',
        // Plaintext cols are always stored as 0 — real values in *_enc cols
        'ctc_annual', 'ctc_monthly', 'gross_monthly',
        'gross_monthly_enc', 'ctc_annual_enc', 'ctc_monthly_enc',
        'net_monthly', 'effective_from', 'effective_to', 'is_current',
        'revision_type', 'remarks', 'increment_letter_path',
        'approved_by', 'created_by',
    ];

    protected $casts = [
        'ctc_annual'   => 'decimal:2',
        'ctc_monthly'  => 'decimal:2',
        'gross_monthly'=> 'decimal:2',
        'effective_from'=> 'date',
        'effective_to'  => 'date',
        'is_current'    => 'boolean',
    ];

    // ── Decrypt helpers ───────────────────────────────────────────────────────

    /** Returns the decrypted gross monthly salary, or 0 on failure. */
    public function getGrossDecrypted(): float
    {
        if ($this->gross_monthly_enc) {
            try { return (float) Crypt::decrypt($this->gross_monthly_enc); }
            catch (\Throwable) {}
        }
        return (float) $this->gross_monthly; // fallback (0 for new records)
    }

    /** Returns the decrypted CTC annual, or 0 on failure. */
    public function getCtcAnnualDecrypted(): float
    {
        if ($this->ctc_annual_enc) {
            try { return (float) Crypt::decrypt($this->ctc_annual_enc); }
            catch (\Throwable) {}
        }
        return (float) $this->ctc_annual;
    }

    /** Returns the decrypted CTC monthly, or 0 on failure. */
    public function getCtcMonthlyDecrypted(): float
    {
        if ($this->ctc_monthly_enc) {
            try { return (float) Crypt::decrypt($this->ctc_monthly_enc); }
            catch (\Throwable) {}
        }
        return (float) $this->ctc_monthly;
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function employee(): BelongsTo        { return $this->belongsTo(Employee::class); }
    public function salaryStructure(): BelongsTo { return $this->belongsTo(SalaryStructure::class); }
    public function approvedBy(): BelongsTo      { return $this->belongsTo(User::class, 'approved_by'); }
    public function components(): HasMany
    {
        return $this->hasMany(EmployeeSalaryComponent::class)->orderBy('salary_component_id');
    }
}
