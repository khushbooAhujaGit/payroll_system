<?php $__env->startSection('title', 'Dashboard'); ?>

<?php $__env->startSection('content'); ?>
<div class="g4">
  <div class="sc">
    <div class="lbl">Active Employees</div>
    <div class="val"><?php echo e($totalActive); ?></div>
  </div>
  <div class="sc">
    <div class="lbl">New Joiners</div>
    <div class="val"><?php echo e($newJoiners); ?></div>
    <div class="sub">This month</div>
  </div>
  <div class="sc">
    <div class="lbl">Pending Leaves</div>
    <div class="val" style="color:var(--yel)"><?php echo e($pendingLeaves); ?></div>
  </div>
  <div class="sc">
    <div class="lbl">Open Tickets</div>
    <div class="val" style="color:var(--red)"><?php echo e($openTickets); ?></div>
  </div>
</div>

<div class="g2" style="margin-top:18px">
  <div class="card">
    <div class="ch">
      <div class="ct">Recent Employees</div>
      <a href="<?php echo e(route('employees')); ?>" class="btn sm">View All →</a>
    </div>
    <?php if($recentEmployees->isNotEmpty()): ?>
    <table>
      <thead><tr><th>Emp ID</th><th>Name</th><th>Department</th><th>Status</th></tr></thead>
      <tbody>
        <?php $__currentLoopData = $recentEmployees; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $emp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
          <td class="mono"><?php echo e($emp->employee_id); ?></td>
          <td><?php echo e($emp->full_name); ?></td>
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
      <a href="<?php echo e(route('employees')); ?>" class="btn acc" style="margin-top:10px">+ Add Employee</a>
    </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="ch"><div class="ct">Quick Actions</div></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
      <a href="<?php echo e(route('employees')); ?>" class="btn" style="justify-content:flex-start">+ Add Employee</a>
      <a href="<?php echo e(route('attendance')); ?>" class="btn" style="justify-content:flex-start">✔ Attendance</a>
      <a href="<?php echo e(route('leave')); ?>" class="btn" style="justify-content:flex-start">Apply Leave</a>
      <a href="<?php echo e(route('payroll')); ?>" class="btn" style="justify-content:flex-start">Run Payroll</a>
      <a href="<?php echo e(route('reports')); ?>" class="btn" style="justify-content:flex-start">📊 Reports</a>
      <a href="<?php echo e(route('statutory')); ?>" class="btn" style="justify-content:flex-start">📋 Statutory</a>
    </div>

    <?php if($deptStrength->isNotEmpty()): ?>
    <div style="margin-top:14px">
      <div style="font-size:11px;font-weight:600;color:var(--t3);margin-bottom:8px">DEPARTMENT STRENGTH</div>
      <?php $__currentLoopData = $deptStrength->take(5); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dept): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <?php $pct = $totalActive ? round($dept->count / $totalActive * 100) : 0; ?>
      <div style="margin-bottom:7px">
        <div style="display:flex;justify-content:space-between;font-size:11px;margin-bottom:2px">
          <span><?php echo e($dept->department?->name ?? 'Unknown'); ?></span>
          <span style="color:var(--t3)"><?php echo e($dept->count); ?></span>
        </div>
        <div style="background:var(--bg);border-radius:3px;height:4px">
          <div style="background:var(--acc);width:<?php echo e($pct); ?>%;height:100%;border-radius:3px"></div>
        </div>
      </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
    <?php endif; ?>

    <?php if($lastRun): ?>
    <div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--b1)">
      <div style="font-size:11px;font-weight:600;color:var(--t3);margin-bottom:6px">LAST PAYROLL RUN</div>
      <div style="font-size:12px">
        <span class="bx g"><?php echo e(strtoupper($lastRun->status)); ?></span>
        &nbsp;<?php echo e($lastRun->run_name); ?>

        &mdash; <?php echo e($lastRun->period?->year); ?>/<?php echo e(str_pad($lastRun->period?->month, 2, '0', STR_PAD_LEFT)); ?>

      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="card" style="margin-top:18px">
  <div class="ch"><div class="ct">Today's Attendance</div></div>
  <div class="g4">
    <?php
      $statuses = ['present' => ['label'=>'Present','color'=>'var(--grn)'],
                   'absent'  => ['label'=>'Absent', 'color'=>'var(--red)'],
                   'leave'   => ['label'=>'On Leave','color'=>'var(--yel)'],
                   'wfh'     => ['label'=>'WFH',    'color'=>'var(--pur)']];
    ?>
    <?php $__currentLoopData = $statuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $info): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div class="sc">
      <div class="lbl"><?php echo e($info['label']); ?></div>
      <div class="val" style="color:<?php echo e($info['color']); ?>"><?php echo e($todayAttendance[$key] ?? 0); ?></div>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/dashboard/index.blade.php ENDPATH**/ ?>