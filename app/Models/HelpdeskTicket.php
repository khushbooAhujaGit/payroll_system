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

class HelpdeskTicket extends Model
{
    protected $fillable = ['ticket_number','employee_id','company_id','category_id','subject','description','attachment_path','priority','status','assigned_to','due_by','resolved_at','closed_at','rating','closure_remarks'];
    protected $casts = ['due_by' => 'datetime', 'resolved_at' => 'datetime', 'closed_at' => 'datetime'];

    public function employee(): BelongsTo   { return $this->belongsTo(Employee::class); }
    public function entity(): BelongsTo     { return $this->belongsTo(Company::class); }
    public function category(): BelongsTo   { return $this->belongsTo(HelpdeskCategory::class); }
    public function assignedTo(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function comments(): HasMany     { return $this->hasMany(HelpdeskComment::class, 'ticket_id'); }

    public function isSlaBreached(): bool
    {
        return $this->due_by && $this->due_by->isPast() && !in_array($this->status, ['resolved', 'closed']);
    }
}
