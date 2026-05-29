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

class ComplianceChallan extends Model
{
    protected $fillable = ['entity_id','challan_type','year','month','amount','challan_number','due_date','payment_date','status','receipt_path','penalty','remarks'];
    protected $casts    = ['due_date' => 'date', 'payment_date' => 'date', 'amount' => 'decimal:2', 'penalty' => 'decimal:2'];
    public function entity(): BelongsTo { return $this->belongsTo(Entity::class); }
}
