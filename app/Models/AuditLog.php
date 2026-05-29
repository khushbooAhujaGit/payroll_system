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

class AuditLog extends Model
{
    protected $fillable = ['user_id','action','module','model_type','model_id','old_values','new_values','ip_address','user_agent','description'];
    protected $casts    = ['old_values' => 'array', 'new_values' => 'array'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
