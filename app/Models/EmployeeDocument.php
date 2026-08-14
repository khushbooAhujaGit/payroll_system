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

class EmployeeDocument extends Model
{
    /**
     * The document types offered on the employee upload form (row 8 of the
     * Master Details spec) — a subset of the full document_type enum, which
     * also carries legacy types (passport, nda, agreement, etc.) not exposed
     * there.
     */
    const UPLOAD_TYPES = [
        'pan'               => 'PAN Card',
        'aadhaar'           => 'Aadhaar Card',
        'marksheet'         => 'Marksheet',
        'reference_letter'  => 'Reference Letter',
        'experience_letter' => 'Experience Letter',
        'cancelled_cheque'  => 'Cancelled Cheque',
        'uan_document'      => 'UAN Document',
        'other'             => 'Other Document',
    ];

    protected $fillable = ['employee_id','document_type','document_number','document_name','file_path','file_name','file_type','issue_date','expiry_date','is_verified','verified_by','verified_at','remarks','uploaded_by'];
    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        // Encrypted at rest — see database/migrations/2026_07_15_000001_add_column_encryption_support.php
        'document_number' => 'encrypted',
    ];

    public function employee(): BelongsTo  { return $this->belongsTo(Employee::class); }
    public function verifiedBy(): BelongsTo{ return $this->belongsTo(User::class, 'verified_by'); }
}
