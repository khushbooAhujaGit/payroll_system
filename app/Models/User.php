<?php

namespace App\Models;

use App\Services\OutlookMailerService;
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
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'company_id','employee_id','name','email','password','mobile',
        'is_active','avatar','last_login_at','last_login_ip','timezone',
    ];
    protected $hidden   = ['password','remember_token'];
    protected $casts    = ['is_active' => 'boolean', 'last_login_at' => 'datetime'];

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function company(): BelongsTo  { return $this->belongsTo(Company::class); }
    public function assignedTickets(): HasMany { return $this->hasMany(HelpdeskTicket::class, 'assigned_to'); }

    /**
     * The one role treated as full, unconditional access — see
     * AppServiceProvider's Gate::before. Everyone else's access is
     * entirely permission-driven.
     */
    public function isSuperAdmin(): bool { return $this->hasRole('Admin'); }

    /**
     * Employee-role holders (including structural Managers — Manager isn't a
     * separate role in this app) get their own personal Dashboard (their
     * leave balance, attendance, payslip, team approvals if they manage
     * anyone) plus Leave Management and their own Profile — not the
     * company-wide HR/Admin dashboard or any other module. Anyone who also
     * holds Admin or HR keeps full access.
     */
    public function isRestrictedToLeaveOnly(): bool
    {
        return $this->hasRole('Employee') && !$this->hasAnyRole(['Admin', 'HR']);
    }

    /**
     * Sends the reset link through the Outlook/Graph mailer instead of
     * Laravel's default mail notification — this app routes all mail through
     * Microsoft Graph, not SMTP.
     */
    public function sendPasswordResetNotification($token): void
    {
        $url = route('password.reset', ['token' => $token, 'email' => $this->email]);
        $expiryMinutes = config('auth.passwords.' . config('auth.defaults.passwords') . '.expire', 60);

        $body = view('emails.reset-password', [
            'url'           => $url,
            'email'         => $this->email,
            'expiryMinutes' => $expiryMinutes,
        ])->render();

        try {
            app(OutlookMailerService::class)->sendMail($this->email, 'Reset Your Password - HRMS Payroll (Test Mail)', $body);
        } catch (\Throwable $e) {
            Log::error('Failed to send password reset email via Outlook: ' . $e->getMessage());
        }
    }
}
