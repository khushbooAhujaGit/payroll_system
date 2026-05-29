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

class PayrollArrear extends Model
{
    protected $fillable = ['employee_id','payroll_run_id','from_year','from_month','to_year','to_month','arrear_amount','reason','status'];
    protected $casts    = ['arrear_amount' => 'decimal:2'];

    public function employee(): BelongsTo  { return $this->belongsTo(Employee::class); }
    public function payrollRun(): BelongsTo{ return $this->belongsTo(PayrollRun::class); }
}
