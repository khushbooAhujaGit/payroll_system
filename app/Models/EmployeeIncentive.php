<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeIncentive extends Model
{
    protected $fillable = ['employee_id', 'month', 'amount', 'reason', 'created_by'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
