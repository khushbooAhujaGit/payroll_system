<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Leave data imported from the "Mark Leave" sheet of the Leave Tracker xlsm.
 *
 * @property int         $id
 * @property string      $leave_ref_id      Original ID from the tracker sheet
 * @property int|null    $employee_id       Resolved employee (null if EMP ID unmatched)
 * @property string|null $sheet_emp_id      Raw EMP ID string from the sheet
 * @property string|null $employee_name     Employee name as in the sheet
 * @property \Carbon\Carbon $leave_date
 * @property string      $leave_type        CL, SL, WFH, HSL …
 * @property string|null $team_leader_name
 * @property string|null $reason_type
 * @property string|null $reason
 * @property string|null $source_file
 * @property int|null    $uploaded_by
 */
class LeaveTrackerRecord extends Model
{
    protected $table = 'leave_tracker_records';

    protected $fillable = [
        'leave_ref_id',
        'employee_id',
        'sheet_emp_id',
        'employee_name',
        'leave_date',
        'leave_type',
        'team_leader_name',
        'reason_type',
        'reason',
        'source_file',
        'uploaded_by',
        'is_active',
        'is_approved',
        'superseded_by_id',
    ];

    protected $casts = [
        'leave_date'  => 'date',
        'is_active'   => 'boolean',
        'is_approved' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'uploaded_by');
    }
}
