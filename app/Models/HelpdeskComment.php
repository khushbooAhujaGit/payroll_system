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

class HelpdeskComment extends Model
{
    protected $fillable = ['ticket_id','user_id','comment','attachment_path','is_internal'];
    protected $casts    = ['is_internal' => 'boolean'];

    public function ticket(): BelongsTo { return $this->belongsTo(HelpdeskTicket::class, 'ticket_id'); }
    public function user(): BelongsTo   { return $this->belongsTo(User::class); }
}
