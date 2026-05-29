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

class ContractorInvoice extends Model
{
    protected $fillable = ['contractor_id','entity_id','invoice_number','invoice_date','year','month','invoice_amount','tds_rate','tds_amount','net_payable','description','invoice_path','status','approved_by','payment_date'];
    protected $casts = ['invoice_date' => 'date', 'payment_date' => 'date', 'invoice_amount' => 'decimal:2', 'tds_amount' => 'decimal:2', 'net_payable' => 'decimal:2'];

    public function contractor(): BelongsTo { return $this->belongsTo(Contractor::class); }
    public function entity(): BelongsTo     { return $this->belongsTo(Entity::class); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
