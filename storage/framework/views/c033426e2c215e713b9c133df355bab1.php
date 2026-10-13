<?php $__env->startSection('title', 'Attendance Analytics'); ?>

<?php $__env->startSection('content'); ?>

<?php
  $prevMonth = $monthStart->copy()->subMonth()->format('Y-m');
  $nextMonth = $monthStart->copy()->addMonth()->format('Y-m');
?>

<?php if($isLeaveAdmin && $companies->count() > 1): ?>
<form method="GET" action="<?php echo e(route('attendance.analytics')); ?>" class="card"
  style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
  <input type="hidden" name="month" value="<?php echo e($month); ?>">
  <input type="hidden" name="search" value="<?php echo e($search); ?>">
  <div class="fld" style="min-width:200px">
    <label>Entity / Company</label>
    <select name="company_id" onchange="this.form.submit()">
      <option value="all" <?php echo e($companyId === 'all' ? 'selected' : ''); ?>>All Companies</option>
      <?php $__currentLoopData = $companies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <option value="<?php echo e($ent->id); ?>" <?php echo e($companyId == $ent->id ? 'selected' : ''); ?>><?php echo e($ent->name); ?></option>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select>
  </div>
</form>
<?php endif; ?>


<form method="GET" action="<?php echo e(route('attendance.analytics')); ?>" class="card"
  style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
  <?php if($isLeaveAdmin): ?>
    <input type="hidden" name="company_id" value="<?php echo e($companyId); ?>">
  <?php endif; ?>
  <div class="fld" style="min-width:160px">
    <label>Month / Year</label>
    <input type="month" name="month" value="<?php echo e($month); ?>">
  </div>
  <div class="fld" style="min-width:220px">
    <label>Search</label>
    <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="Name or Employee ID…">
  </div>
  <button type="submit" class="btn acc">Go</button>
  <?php if($search): ?>
    <a href="<?php echo e(route('attendance.analytics', array_filter(['month' => $month, 'company_id' => $isLeaveAdmin ? $companyId : null]))); ?>" class="btn sm">Clear Search</a>
  <?php endif; ?>
</form>

<div class="card" style="margin-bottom:16px">
  <div class="ch">
    <div class="ct"><?php echo e($monthStart->format('F Y')); ?> — <?php echo e($isLeaveAdmin ? 'All Employees' : ($isManager ? 'My Team' : 'My Attendance')); ?></div>
    <div style="display:flex;gap:6px">
      <a href="<?php echo e(route('attendance.analytics', ['month' => $prevMonth])); ?>" class="btn sm">← Prev</a>
      <a href="<?php echo e(route('attendance.analytics')); ?>" class="btn sm">This Month</a>
      <a href="<?php echo e(route('attendance.analytics', ['month' => $nextMonth])); ?>" class="btn sm">Next →</a>
    </div>
  </div>

  <?php if($rows->isNotEmpty()): ?>
  <table>
    <thead>
      <tr>
        <th>Employee</th>
        <th>Average Monthly Attendance</th>
        <th>Days With Attendance</th>
        <th>CL</th>
        <th>SL</th>
        <th>CO</th>
        <th>Other Leave Types</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <tr>
        <td><?php echo e($row['employee']->full_name); ?> <span style="color:var(--t3);font-size:11px">(<?php echo e($row['employee']->employee_id); ?>)</span></td>
        <td><?php echo e($row['monthly_avg_hours'] !== null ? $row['monthly_avg_hours'] . ' hrs/day' : '—'); ?></td>
        <td><?php echo e($row['monthly_day_count']); ?></td>
        <td><?php echo e($row['leave_cl'] ?: '—'); ?></td>
        <td><?php echo e($row['leave_sl'] ?: '—'); ?></td>
        <td><?php echo e($row['leave_co'] ?: '—'); ?></td>
        <td>
          <?php if(!empty($row['leave_other_breakdown'])): ?>
            <?php $__currentLoopData = $row['leave_other_breakdown']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type => $count): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <span class="bx gr" style="margin:1px 2px 1px 0;display:inline-block"><?php echo e($type); ?>: <?php echo e($count); ?></span>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          <?php else: ?>
            —
          <?php endif; ?>
        </td>
        <td>
          <?php if($row['is_shortfall']): ?>
            <span class="bx r">Below 9h Average</span>
          <?php elseif($row['monthly_avg_hours'] !== null): ?>
            <span class="bx g">OK</span>
          <?php elseif($row['leave_days_total'] > 0): ?>
            <span class="bx y">On Leave</span>
          <?php else: ?>
            <span class="bx gr">No Data</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
  </table>
  <?php else: ?>
  <div class="empty"><div class="ico">📊</div><p>No attendance data for this period.</p></div>
  <?php endif; ?>
</div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/attendance/analytics.blade.php ENDPATH**/ ?>