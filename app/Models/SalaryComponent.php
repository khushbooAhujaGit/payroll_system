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

class SalaryComponent extends Model
{
    protected $fillable = [
        'entity_id','name','code','component_type','calculation_type',
        'percentage_of','percentage_value','formula','is_taxable',
        'is_pf_applicable','is_esic_applicable','is_pt_applicable','is_lwf_applicable',
        'is_in_ctc','is_in_gross','show_in_payslip','is_arrear_applicable',
        'frequency','sort_order','is_active',
    ];
    protected $casts = [
        'is_taxable' => 'boolean', 'is_pf_applicable' => 'boolean',
        'is_esic_applicable' => 'boolean', 'is_in_ctc' => 'boolean',
        'is_in_gross' => 'boolean', 'show_in_payslip' => 'boolean',
        'percentage_value' => 'decimal:2',
    ];
    public function entity(): BelongsTo { return $this->belongsTo(Entity::class); }
}
