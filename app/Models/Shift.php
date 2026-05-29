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

class Shift extends Model
{
    protected $fillable = ['entity_id','name','code','start_time','end_time','is_night_shift','working_hours','grace_period_minutes','late_mark_after_minutes','half_day_after_minutes','is_flexible','flexible_start','flexible_end','min_working_hours_flexible','is_active'];
    protected $casts = ['is_night_shift' => 'boolean', 'is_flexible' => 'boolean'];
    public function entity(): BelongsTo { return $this->belongsTo(Entity::class); }
}
