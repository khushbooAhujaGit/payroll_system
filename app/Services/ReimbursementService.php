<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeHistory;
use App\Models\EmployeeSalary;
use App\Models\EmployeeSalaryComponent;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\PayrollRun;
use App\Models\PayrollEmployeeRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReimbursementService
{
    public function approve(\App\Models\ReimbursementClaim $claim, array $itemApprovals): \App\Models\ReimbursementClaim
    {
        return DB::transaction(function () use ($claim, $itemApprovals) {
            $totalApproved = 0;

            foreach ($claim->items as $item) {
                $approvalData = $itemApprovals[$item->id] ?? null;
                if (!$approvalData) continue;

                $status   = $approvalData['action'] === 'approved' ? 'approved' : 'rejected';
                $approved = $approvalData['approved_amount'] ?? ($status === 'approved' ? $item->claimed_amount : 0);

                $item->update([
                    'status'           => $status,
                    'approved_amount'  => $approved,
                    'rejection_reason' => $approvalData['reason'] ?? null,
                ]);

                if ($status === 'approved') {
                    $totalApproved += $approved;
                }
            }

            $claim->update([
                'status'         => $totalApproved > 0 ? 'approved' : 'rejected',
                'total_approved' => $totalApproved,
                'approved_by'    => auth()->id(),
                'approved_at'    => now(),
            ]);

            return $claim->fresh('items');
        });
    }
}
