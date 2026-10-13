<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Admin-managed wifi network an employee's app watches for connect/disconnect
 * to auto mark-in/out. See ATTENDANCE_APP_PLAN.md §3.2.
 */
class OfficeWifiNetwork extends Model
{
    protected $table = 'office_wifi_networks';

    protected $fillable = [
        'company_id', 'branch_id', 'ssid', 'bssid', 'is_active', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
