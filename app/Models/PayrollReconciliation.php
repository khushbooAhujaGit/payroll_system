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

class PayrollReconciliation extends Model
{
    protected $table = 'payroll_reconciliation';
    protected $fillable = ['payroll_run_id','employee_id','prev_net','curr_net','net_diff','component_wise_diff','reasons'];
    protected $casts    = ['component_wise_diff' => 'array', 'reasons' => 'array', 'net_diff' => 'decimal:2'];

    public function payrollRun(): BelongsTo { return $this->belongsTo(PayrollRun::class); }
    public function employee(): BelongsTo   { return $this->belongsTo(Employee::class); }
}
