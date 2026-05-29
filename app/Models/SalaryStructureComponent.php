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

class SalaryStructureComponent extends Model
{
    protected $fillable = ['salary_structure_id','salary_component_id','calculation_override','fixed_value','percentage_value','percentage_of','formula','sort_order'];
    protected $casts    = ['fixed_value' => 'decimal:2', 'percentage_value' => 'decimal:2'];

    public function salaryStructure(): BelongsTo  { return $this->belongsTo(SalaryStructure::class); }
    public function salaryComponent(): BelongsTo  { return $this->belongsTo(SalaryComponent::class); }
}
