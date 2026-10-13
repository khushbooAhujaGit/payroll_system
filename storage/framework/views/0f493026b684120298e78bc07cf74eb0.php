<?php $__env->startSection('title', 'Dashboard'); ?>

<?php $__env->startSection('content'); ?>
<?php
  $presentDays = $monthAttendance['present'] ?? 0;
  $reimbColor = $pendingReimbursements > 0 ? 'yel' : 'grn';
  $leaveIcons = ['Casual Leave' => '🌴', 'Sick Leave' => '🤒', 'Compensation Off' => '🔄'];
?>

<div class="card" style="margin-bottom:18px">
  <div style="font-size:15px;font-weight:700">Welcome back, <?php echo e($employee->first_name); ?></div>
  <div class="sub"><?php echo e($employee->designation?->name ?? $employee->employee_id); ?> &middot; <?php echo e(now()->format('l, F j, Y')); ?></div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px">
  <?php $__currentLoopData = $leaveBalance; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bal): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <div class="sc">
    <div class="stat-ico" style="background:var(--acc2);color:var(--acc)"><?php echo e($leaveIcons[$bal->leaveType?->name] ?? '🌴'); ?></div>
    <div class="lbl"><?php echo e($bal->leaveType?->name ?? 'Leave'); ?></div>
    <div class="val"><?php echo e(rtrim(rtrim(number_format($bal->available, 1), '0'), '.')); ?></div>
    <div class="sub">days available</div>
  </div>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  <div class="sc">
    <div class="stat-ico" style="background:var(--grn2);color:var(--grn)">✅</div>
    <div class="lbl">Present This Month</div>
    <div class="val"><?php echo e($presentDays); ?></div>
    <div class="sub"><?php echo e(now()->format('F')); ?></div>
  </div>
  <div class="sc">
    <div class="stat-ico" style="background:var(--<?php echo e($reimbColor); ?>2);color:var(--<?php echo e($reimbColor); ?>)">🧾</div>
    <div class="lbl">Reimbursements</div>
    <div class="val" style="color:var(--<?php echo e($reimbColor); ?>)"><?php echo e($pendingReimbursements); ?></div>
    <div class="sub"><?php echo e($pendingReimbursements > 0 ? 'Pending review' : 'All settled'); ?></div>
  </div>
</div>

<div class="card" style="margin-top:18px">
  <div class="ch"><div class="ct">Quick Actions</div></div>
  <div class="qa-grid">
    <a href="<?php echo e(route('leave')); ?>" class="qa-item"><span class="ico">🌴</span> Apply Leave</a>
    <a href="<?php echo e(route('my-attendance')); ?>" class="qa-item"><span class="ico">✔️</span> My Attendance</a>
    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('salary_self_view')): ?>
    <a href="<?php echo e(route('my-payslips')); ?>" class="qa-item"><span class="ico">💰</span> My Payslips</a>
    <?php endif; ?>
    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('reimbursment_self_view')): ?>
    <a href="<?php echo e(route('my-reimbursements')); ?>" class="qa-item"><span class="ico">🧾</span> My Reimbursements</a>
    <?php endif; ?>
    <?php if($isManager): ?>
    <a href="<?php echo e(route('my-team')); ?>" class="qa-item"><span class="ico">👥</span> My Team</a>
    <a href="<?php echo e(route('attendance.change_requests')); ?>" class="qa-item"><span class="ico">🗓️</span> Attendance Requests</a>
    <?php endif; ?>
  </div>
</div>

<?php if($activeLoan): ?>
<div class="card" style="margin-top:18px">
  <div class="ch"><div class="ct">Active Loan / Advance</div></div>
  <div style="display:flex;justify-content:space-between;align-items:center">
    <div>
      <div style="font-size:12px;color:var(--t2)"><?php echo e($activeLoan->loan_number); ?> &middot; <?php echo e($activeLoan->loanType?->name ?? ''); ?></div>
      <div class="sub">EMI ₹<?php echo e(number_format($activeLoan->emi_amount, 0)); ?>/month</div>
    </div>
    <div style="font-size:18px;font-weight:700;font-family:'JetBrains Mono',monospace">₹<?php echo e(number_format($activeLoan->outstanding_amount, 0)); ?></div>
  </div>
</div>
<?php endif; ?>

<?php if($isManager && ($teamPendingLeaves > 0 || $teamPendingAttendance > 0)): ?>
<div class="card" style="margin-top:18px">
  <div class="ch"><div class="ct">Team Approvals</div></div>
  <div style="display:flex;flex-direction:column;gap:2px">
    <?php if($teamPendingLeaves > 0): ?>
    <a href="<?php echo e(route('leave')); ?>" class="qa-item" style="justify-content:space-between">
      <span>🗓️ Leave Requests</span>
      <span class="bx y"><?php echo e($teamPendingLeaves); ?></span>
    </a>
    <?php endif; ?>
    <?php if($teamPendingAttendance > 0): ?>
    <a href="<?php echo e(route('attendance.change_requests')); ?>" class="qa-item" style="justify-content:space-between">
      <span>✔️ Attendance Requests</span>
      <span class="bx y"><?php echo e($teamPendingAttendance); ?></span>
    </a>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/dashboard/my.blade.php ENDPATH**/ ?>