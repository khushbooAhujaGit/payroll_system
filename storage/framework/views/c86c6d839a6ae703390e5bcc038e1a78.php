<?php $__env->startSection('title', 'Dashboard'); ?>

<?php $__env->startSection('content'); ?>
<form method="GET" action="<?php echo e(route('dashboard')); ?>" style="display:flex;gap:14px;margin-bottom:14px;flex-wrap:wrap">
  <div class="fld" style="max-width:180px">
    <label>Month</label>
    <input type="month" name="month" value="<?php echo e($month); ?>" onchange="this.form.submit()">
  </div>
  <?php if($companies->isNotEmpty()): ?>
  <div class="fld" style="max-width:220px">
    <label>Firm</label>
    <select name="company_id" onchange="this.form.submit()">
      <option value="0" <?php echo e($companyId == 0 ? 'selected' : ''); ?>>All Firms</option>
      <?php $__currentLoopData = $companies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <option value="<?php echo e($c->id); ?>" <?php echo e($c->id == $companyId ? 'selected' : ''); ?>><?php echo e($c->short_name ?? $c->name); ?></option>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select>
  </div>
  <?php endif; ?>
</form>

<?php
  $leaveColor  = $pendingLeaves > 0 ? 'yel' : 'grn';
  $ticketColor = $openTickets   > 0 ? 'red' : 'grn';
  $monthLabel  = \Carbon\Carbon::createFromFormat('Y-m', $month)->format('M Y');
?>

<div class="g4">
  <div class="sc">
    <div class="stat-ico" style="background:var(--acc2);color:var(--acc)">👥</div>
    <div class="lbl">Active Employees</div>
    <div class="val"><?php echo e($totalActive); ?></div>
  </div>
  <div class="sc">
    <div class="stat-ico" style="background:var(--pur2);color:var(--pur)">✨</div>
    <div class="lbl">New Joiners</div>
    <div class="val"><?php echo e($newJoiners); ?></div>
    <div class="sub"><?php echo e($monthLabel); ?></div>
  </div>
  <div class="sc">
    <div class="stat-ico" style="background:var(--<?php echo e($leaveColor); ?>2);color:var(--<?php echo e($leaveColor); ?>)">🗓️</div>
    <div class="lbl">Pending Leaves</div>
    <div class="val" style="color:var(--<?php echo e($leaveColor); ?>)"><?php echo e($pendingLeaves); ?></div>
    <div class="sub"><?php echo e($pendingLeaves > 0 ? 'Awaiting approval' : 'All caught up'); ?></div>
  </div>
  <div class="sc">
    <div class="stat-ico" style="background:var(--<?php echo e($ticketColor); ?>2);color:var(--<?php echo e($ticketColor); ?>)">🎫</div>
    <div class="lbl">Open Tickets</div>
    <div class="val" style="color:var(--<?php echo e($ticketColor); ?>)"><?php echo e($openTickets); ?></div>
    <div class="sub"><?php echo e($openTickets > 0 ? 'Needs attention' : 'No open tickets'); ?></div>
  </div>
</div>

<?php
  $approvalsTotal = $pendingLeaves + $pendingLoans + $pendingReimbursements;
  $showPayrollCard   = $monthPayroll !== null;
  $showApprovalsCard = $approvalsTotal > 0;
?>

<?php if($showPayrollCard || $showApprovalsCard): ?>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px;margin-top:18px">
  <?php if($showPayrollCard): ?>
  <div class="card" style="margin-bottom:0">
    <div class="ch"><div class="ct">Payroll Summary</div></div>
    <div style="font-size:11px;color:var(--t3);margin-bottom:4px">
      <?php echo e($monthLabel); ?> &nbsp;<span class="bx <?php echo e($monthPayroll['status'] === 'mixed' ? 'y' : 'g'); ?>"><?php echo e(strtoupper($monthPayroll['status'])); ?></span>
    </div>
    <div style="font-size:22px;font-weight:700;font-family:'JetBrains Mono',monospace">₹<?php echo e(number_format($monthPayroll['total_net'], 0)); ?></div>
    <div class="sub" style="margin-bottom:10px">Net pay &middot; <?php echo e($monthPayroll['total_employees']); ?> employee<?php echo e($monthPayroll['total_employees'] == 1 ? '' : 's'); ?></div>
    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('payroll_view')): ?>
    <a href="<?php echo e(route('payroll')); ?>" class="btn sm">View Payroll →</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if($showApprovalsCard): ?>
  <div class="card" style="margin-bottom:0">
    <div class="ch"><div class="ct">Approvals Queue</div></div>
    <div style="display:flex;flex-direction:column;gap:2px">
      <?php if($pendingLeaves > 0): ?>
      <a href="<?php echo e(route('leave')); ?>" class="qa-item" style="justify-content:space-between">
        <span>🗓️ Leave Requests</span>
        <span class="bx y"><?php echo e($pendingLeaves); ?></span>
      </a>
      <?php endif; ?>
      <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('loan_tab_view')): ?>
      <?php if($pendingLoans > 0): ?>
      <a href="<?php echo e(route('loans')); ?>" class="qa-item" style="justify-content:space-between">
        <span>💳 Loan / Advance</span>
        <span class="bx y"><?php echo e($pendingLoans); ?></span>
      </a>
      <?php endif; ?>
      <?php endif; ?>
      <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('reimbursment_approve')): ?>
      <?php if($pendingReimbursements > 0): ?>
      <a href="<?php echo e(route('reimbursements.approvals')); ?>" class="qa-item" style="justify-content:space-between">
        <span>🧾 Reimbursements</span>
        <span class="bx y"><?php echo e($pendingReimbursements); ?></span>
      </a>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if($payrollTrend->isNotEmpty()): ?>
<div class="card" style="margin-top:18px">
  <div class="ch"><div class="ct">Payroll Cost Trend</div></div>
  <?php $maxNet = $payrollTrend->max('total_net') ?: 1; ?>
  <div style="display:flex;align-items:flex-end;gap:14px;height:150px;padding-top:10px">
    <?php $__currentLoopData = $payrollTrend; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $point): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <?php $barH = max(6, round($point['total_net'] / $maxNet * 100)); ?>
    <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%">
      <div style="font-size:10px;color:var(--t3);margin-bottom:5px;font-family:'JetBrains Mono',monospace">₹<?php echo e(number_format($point['total_net'] / 1000, 0)); ?>k</div>
      <div style="width:100%;max-width:44px;background:var(--acc);border-radius:5px 5px 0 0;height:<?php echo e($barH); ?>%"></div>
      <div style="font-size:10px;color:var(--t3);margin-top:6px"><?php echo e($point['label']); ?></div>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>
</div>
<?php endif; ?>

<div class="g2" style="margin-top:18px">
  <div class="card">
    <div class="ch">
      <div class="ct">Recent Employees</div>
      <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('employee_view')): ?>
      <a href="<?php echo e(route('employees')); ?>" class="btn sm">View All →</a>
      <?php endif; ?>
    </div>
    <?php if($recentEmployees->isNotEmpty()): ?>
    <table>
      <thead><tr><th>Emp ID</th><th>Name</th><th>Department</th><th>Status</th></tr></thead>
      <tbody>
        <?php $__currentLoopData = $recentEmployees; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $emp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
          <td class="mono"><?php echo e($emp->employee_id); ?></td>
          <td>
            <div class="name-cell">
              <span class="avatar-sm"><?php echo e(strtoupper(substr($emp->first_name ?? $emp->full_name, 0, 1))); ?></span>
              <?php echo e($emp->full_name); ?>

            </div>
          </td>
          <td><?php echo e($emp->department?->name ?? '—'); ?></td>
          <td><span class="bx <?php echo e($emp->employee_status === 'active' ? 'g' : 'gr'); ?>"><?php echo e($emp->employee_status); ?></span></td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="empty">
      <div class="ico">👥</div>
      <p>No employees yet</p>
      <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('employee_add')): ?>
      <a href="<?php echo e(route('employees')); ?>" class="btn acc" style="margin-top:10px">+ Add Employee</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="ch"><div class="ct">Quick Actions</div></div>
    <div class="qa-grid">
      <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('employee_add')): ?>
      <a href="<?php echo e(route('employees')); ?>" class="qa-item"><span class="ico">➕</span> Add Employee</a>
      <?php endif; ?>
      <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('attendance_view')): ?>
      <a href="<?php echo e(route('attendance')); ?>" class="qa-item"><span class="ico">✔️</span> Attendance</a>
      <?php endif; ?>
      <a href="<?php echo e(route('leave')); ?>" class="qa-item"><span class="ico">🌴</span> Apply Leave</a>
      <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('payroll_view')): ?>
      <a href="<?php echo e(route('payroll')); ?>" class="qa-item"><span class="ico">💰</span> Run Payroll</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card" style="margin-top:18px">
  <div class="ch"><div class="ct">Today's Attendance</div></div>
  <div class="g4">
    <?php
      $statuses = ['present' => ['label'=>'Present','ico'=>'✅','var'=>'grn'],
                   'absent'  => ['label'=>'Absent', 'ico'=>'❌','var'=>'red'],
                   'leave'   => ['label'=>'On Leave','ico'=>'🌴','var'=>'yel'],
                   'wfh'     => ['label'=>'WFH',    'ico'=>'🏠','var'=>'pur']];
    ?>
    <?php $__currentLoopData = $statuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $info): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div class="sc">
      <div class="stat-ico" style="background:var(--<?php echo e($info['var']); ?>2);color:var(--<?php echo e($info['var']); ?>)"><?php echo e($info['ico']); ?></div>
      <div class="lbl"><?php echo e($info['label']); ?></div>
      <div class="val" style="color:var(--<?php echo e($info['var']); ?>)"><?php echo e($todayAttendance[$key] ?? 0); ?></div>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/dashboard/index.blade.php ENDPATH**/ ?>