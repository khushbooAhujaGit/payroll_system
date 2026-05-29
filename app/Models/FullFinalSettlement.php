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

class FullFinalSettlement extends Model
{
    protected $fillable = ['employee_id','exit_request_id','last_working_date','settlement_year','settlement_month','payable_days','salary_payable','leave_encashment','gratuity_amount','notice_pay_recovery','notice_pay_payable','bonus_payable','bonus_recovery','loan_recovery','advance_recovery','other_deductions','other_payments','tds_deduction','total_payable','total_recoverable','net_payable','remarks','status','payment_date','approved_by'];
    protected $casts    = ['last_working_date' => 'date', 'payment_date' => 'date', 'net_payable' => 'decimal:2'];

    public function employee(): BelongsTo    { return $this->belongsTo(Employee::class); }
    public function exitRequest(): BelongsTo { return $this->belongsTo(ExitRequest::class); }
}
