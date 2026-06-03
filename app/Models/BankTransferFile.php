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

class BankTransferFile extends Model
{
    protected $fillable = ['payroll_run_id','company_id','bank_name','file_format','total_records','total_amount','file_path','reference_number','status'];
    protected $casts    = ['total_amount' => 'decimal:2'];

    public function payrollRun(): BelongsTo { return $this->belongsTo(PayrollRun::class); }
    public function entity(): BelongsTo     { return $this->belongsTo(Company::class); }
}
