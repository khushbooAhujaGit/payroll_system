<div style="font-family:Arial,Helvetica,sans-serif;max-width:680px;margin:0 auto;padding:24px;color:#1f2937">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:24px">
        <span style="font-size:24px">💼</span>
        <div>
            <div style="font-size:16px;font-weight:700">HRMS Payroll</div>
            <div style="font-size:11px;color:#6b7280">Enterprise Platform</div>
        </div>
    </div>

    <h2 style="font-size:18px;margin:0 0 4px">Daily Attendance Exceptions — All Companies</h2>
    <p style="font-size:12px;color:#6b7280;margin:0 0 20px"><?php echo e($today->format('l, d M Y')); ?></p>

    <table style="width:100%;font-size:13px;border-collapse:collapse;margin-bottom:24px">
        <tr>
            <td style="padding:10px 14px;background:#f9fafb;border-radius:6px 0 0 6px;text-align:center;width:25%">
                <div style="font-size:20px;font-weight:700"><?php echo e($totalActive); ?></div>
                <div style="font-size:11px;color:#6b7280">Total Active</div>
            </td>
            <td style="padding:10px 14px;background:#f9fafb;text-align:center;width:25%">
                <div style="font-size:20px;font-weight:700;color:#16a34a"><?php echo e($totalPresent); ?></div>
                <div style="font-size:11px;color:#6b7280">Present</div>
            </td>
            <td style="padding:10px 14px;background:#f9fafb;text-align:center;width:25%">
                <div style="font-size:20px;font-weight:700;color:#2563eb"><?php echo e($totalOnLeave); ?></div>
                <div style="font-size:11px;color:#6b7280">On Leave</div>
            </td>
            <td style="padding:10px 14px;background:#f9fafb;border-radius:0 6px 6px 0;text-align:center;width:25%">
                <div style="font-size:20px;font-weight:700;color:#dc2626"><?php echo e($totalNotArrived); ?></div>
                <div style="font-size:11px;color:#6b7280">Not Yet Arrived</div>
            </td>
        </tr>
    </table>

    <div style="margin-bottom:28px">
        <h3 style="font-size:14px;color:#111827;margin:0 0 6px;padding-bottom:6px;border-bottom:2px solid #e5e7eb">
            Not Yet Arrived
            <span style="font-size:11px;color:#6b7280;font-weight:400">— <?php echo e($notArrivedRows->count()); ?></span>
        </h3>

        <?php if($notArrivedRows->isEmpty()): ?>
            <p style="font-size:12px;color:#16a34a;background:#dcfce7;border-radius:6px;padding:10px 12px;margin:8px 0 0">
                Everyone across every company has arrived.
            </p>
        <?php else: ?>
            <table style="width:100%;font-size:12px;border-collapse:collapse;margin-top:8px">
                <thead>
                    <tr style="background:#f3f4f6;text-align:left">
                        <th style="padding:7px 10px;font-weight:600">Company</th>
                        <th style="padding:7px 10px;font-weight:600">Emp ID</th>
                        <th style="padding:7px 10px;font-weight:600">Name</th>
                        <th style="padding:7px 10px;font-weight:600">Department</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $notArrivedRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr style="border-bottom:1px solid #f3f4f6">
                        <td style="padding:7px 10px"><?php echo e($row['company']); ?></td>
                        <td style="padding:7px 10px;font-family:monospace"><?php echo e($row['emp_id']); ?></td>
                        <td style="padding:7px 10px"><?php echo e($row['name']); ?></td>
                        <td style="padding:7px 10px;color:#6b7280"><?php echo e($row['department'] ?: '—'); ?></td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div style="margin-bottom:0">
        <h3 style="font-size:14px;color:#111827;margin:0 0 6px;padding-bottom:6px;border-bottom:2px solid #e5e7eb">
            Other Status
            <span style="font-size:11px;color:#6b7280;font-weight:400">— <?php echo e($otherStatusRows->count()); ?></span>
        </h3>

        <?php if($otherStatusRows->isEmpty()): ?>
            <p style="font-size:12px;color:#16a34a;background:#dcfce7;border-radius:6px;padding:10px 12px;margin:8px 0 0">
                No other exceptions to report.
            </p>
        <?php else: ?>
            <table style="width:100%;font-size:12px;border-collapse:collapse;margin-top:8px">
                <thead>
                    <tr style="background:#f3f4f6;text-align:left">
                        <th style="padding:7px 10px;font-weight:600">Company</th>
                        <th style="padding:7px 10px;font-weight:600">Emp ID</th>
                        <th style="padding:7px 10px;font-weight:600">Name</th>
                        <th style="padding:7px 10px;font-weight:600">Department</th>
                        <th style="padding:7px 10px;font-weight:600">Status</th>
                        <th style="padding:7px 10px;font-weight:600">Reason</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $otherStatusRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr style="border-bottom:1px solid #f3f4f6">
                        <td style="padding:7px 10px"><?php echo e($row['company']); ?></td>
                        <td style="padding:7px 10px;font-family:monospace"><?php echo e($row['emp_id']); ?></td>
                        <td style="padding:7px 10px"><?php echo e($row['name']); ?></td>
                        <td style="padding:7px 10px;color:#6b7280"><?php echo e($row['department'] ?: '—'); ?></td>
                        <td style="padding:7px 10px;color:#2563eb;font-weight:600"><?php echo e($row['status']); ?></td>
                        <td style="padding:7px 10px;color:#6b7280"><?php echo e($row['reason'] ?: '—'); ?></td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <p style="font-size:11px;color:#6b7280;line-height:1.6;border-top:1px solid #e5e7eb;padding-top:12px;margin-top:24px">
        Full attendance for every active employee across every company today is attached as one Excel file.
        This is an automated report sent daily at 10:30 AM — recipients are managed from the Attendance page in HRMS Payroll.
    </p>
</div>
<?php /**PATH D:\hrms-payroll\resources\views/emails/attendance-daily-exception.blade.php ENDPATH**/ ?>