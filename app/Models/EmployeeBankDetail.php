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
    protected $casts = ['is_primary' => 'boolean', 'is_verified' => 'boolean'];
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
}
