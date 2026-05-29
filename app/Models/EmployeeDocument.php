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
    protected $fillable = ['employee_id','document_type','document_number','document_name','file_path','file_name','file_type','issue_date','expiry_date','is_verified','verified_by','verified_at','remarks','uploaded_by'];
    protected $casts = ['issue_date' => 'date', 'expiry_date' => 'date', 'is_verified' => 'boolean', 'verified_at' => 'datetime'];

    public function employee(): BelongsTo  { return $this->belongsTo(Employee::class); }
    public function verifiedBy(): BelongsTo{ return $this->belongsTo(User::class, 'verified_by'); }
}
