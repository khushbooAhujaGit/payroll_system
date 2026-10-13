<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;padding:24px;color:#1f2937">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:24px">
        <span style="font-size:24px">💼</span>
        <div>
            <div style="font-size:16px;font-weight:700">HRMS Payroll</div>
            <div style="font-size:11px;color:#6b7280">Enterprise Platform</div>
        </div>
    </div>

    <h2 style="font-size:18px;margin:0 0 6px">Pending Leave Approvals</h2>
    <p style="font-size:13px;color:#6b7280;margin:0 0 20px">
        Hi <?php echo e($recipientName); ?>, here's every leave application from <?php echo e($scopeLabel); ?> still waiting on a decision as of <?php echo e(now()->format('d M Y')); ?>.
    </p>

    <table style="width:100%;font-size:12.5px;border-collapse:collapse;border:1px solid #e5e7eb">
        <tr style="background:#f3f4f6">
            <th style="text-align:left;padding:8px;border:1px solid #e5e7eb">Employee</th>
            <th style="text-align:left;padding:8px;border:1px solid #e5e7eb">Leave Type</th>
            <th style="text-align:left;padding:8px;border:1px solid #e5e7eb">Date</th>
            <th style="text-align:left;padding:8px;border:1px solid #e5e7eb">Applied On</th>
            <th style="text-align:left;padding:8px;border:1px solid #e5e7eb">Reason</th>
        </tr>
        <?php $__currentLoopData = $records; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rec): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
            <td style="padding:8px;border:1px solid #e5e7eb"><?php echo e($rec->employee->full_name ?? $rec->employee_name); ?></td>
            <td style="padding:8px;border:1px solid #e5e7eb">
                <?php echo e($rec->leave_type); ?><?php if($rec->half_day_period): ?> (<?php echo e($rec->half_day_period === 'first_half' ? 'First Half' : 'Second Half'); ?>)<?php endif; ?>
            </td>
            <td style="padding:8px;border:1px solid #e5e7eb"><?php echo e(\Carbon\Carbon::parse($rec->leave_date)->format('d M Y')); ?></td>
            <td style="padding:8px;border:1px solid #e5e7eb"><?php echo e($rec->created_at?->format('d M Y') ?? '—'); ?></td>
            <td style="padding:8px;border:1px solid #e5e7eb"><?php echo e($rec->reason ?: '—'); ?></td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </table>

    <p style="font-size:12px;color:#6b7280;margin-top:24px;border-top:1px solid #e5e7eb;padding-top:12px">
        Sign in to HRMS Payroll to approve or reject these. You'll keep getting this reminder each morning until they're resolved.
    </p>
</div>
<?php /**PATH D:\hrms-payroll\resources\views/emails/pending-leave-reminder.blade.php ENDPATH**/ ?>