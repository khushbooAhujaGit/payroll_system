<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryRawUpload extends Model
{
    protected $fillable = [
        'employee_id',
        'emp_id_text',
        'gross_monthly_enc',
        'ctc_annual_enc',
        'ctc_monthly_enc',
        'effective_from',
        'import_month',
        'source',
        'uploaded_by',
    ];

    protected $casts = [
        'effective_from' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
