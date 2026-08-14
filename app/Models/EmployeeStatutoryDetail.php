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

class EmployeeStatutoryDetail extends Model
{
    protected $fillable = ['employee_id','uan_number','pf_number','esic_number','pan_number','aadhaar_number','pt_number','nps_pran'];
    // Encrypted at rest — see database/migrations/2026_07_15_000001_add_column_encryption_support.php
    protected $casts = [
        'uan_number' => 'encrypted',
        'pf_number' => 'encrypted',
        'esic_number' => 'encrypted',
        'pan_number' => 'encrypted',
        'aadhaar_number' => 'encrypted',
        'pt_number' => 'encrypted',
        'nps_pran' => 'encrypted',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $statutory) {
            if ($statutory->isDirty('pan_number')) {
                $statutory->pan_number_hash = $statutory->pan_number ? hash('sha256', strtoupper($statutory->pan_number)) : null;
            }
        });
    }

    /**
     * Exact-match lookup helper — pan_number is encrypted (non-deterministic
     * ciphertext), so WHERE pan_number = ? can't be used at the DB layer.
     */
    public static function wherePan(string $pan)
    {
        return static::where('pan_number_hash', hash('sha256', strtoupper($pan)));
    }

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
}
