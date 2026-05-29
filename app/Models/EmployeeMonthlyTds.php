<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeMonthlyTds extends Model
{
    protected $table    = 'employee_monthly_tds';
    protected $fillable = ['employee_id', 'month', 'amount', 'remarks', 'created_by'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
