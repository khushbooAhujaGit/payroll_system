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

class EmployeeBankDetail extends Model
{
    protected $fillable = ['employee_id','bank_name','branch_name','account_number','account_type','ifsc_code','micr_code','account_holder_name','is_primary','is_verified','cancelled_cheque'];
    protected $casts = [
        'is_primary' => 'boolean',
        'is_verified' => 'boolean',
        // Encrypted at rest — see database/migrations/2026_07_15_000001_add_column_encryption_support.php
        'account_number' => 'encrypted',
        'ifsc_code' => 'encrypted',
        'micr_code' => 'encrypted',
        'account_holder_name' => 'encrypted',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $bank) {
            if ($bank->isDirty('account_number')) {
                $bank->account_number_hash = $bank->account_number ? hash('sha256', $bank->account_number) : null;
            }
        });
    }

    /**
     * Exact-match lookup helper — account_number is encrypted (non-deterministic
     * ciphertext), so WHERE account_number = ? can't be used at the DB layer.
     */
    public static function whereAccountNumber(string $accountNumber)
    {
        return static::where('account_number_hash', hash('sha256', $accountNumber));
    }

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
}
