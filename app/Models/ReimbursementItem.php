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

class ReimbursementItem extends Model
{
    protected $fillable = ['reimbursement_claim_id','reimbursement_type_id','expense_date','description','claimed_amount','approved_amount','bill_path','status','rejection_reason'];
    protected $casts = ['expense_date' => 'date', 'claimed_amount' => 'decimal:2', 'approved_amount' => 'decimal:2'];

    public function claim(): BelongsTo            { return $this->belongsTo(ReimbursementClaim::class, 'reimbursement_claim_id'); }
    public function reimbursementType(): BelongsTo{ return $this->belongsTo(ReimbursementType::class); }
}
