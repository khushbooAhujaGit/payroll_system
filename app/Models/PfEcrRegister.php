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

class PfEcrRegister extends Model
{
    protected $fillable = ['entity_id','payroll_run_id','year','month','total_employees','total_pf_wages','total_employee_pf','total_employer_pf','total_eps','total_edli','ecr_file_path','status'];
    public function entity(): BelongsTo    { return $this->belongsTo(Entity::class); }
    public function payrollRun(): BelongsTo{ return $this->belongsTo(PayrollRun::class); }
}
