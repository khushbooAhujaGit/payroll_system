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
  <div class="fld" style="min-width:200px">
    <label>Entity / Company</label>
    <select name="company_id" onchange="this.form.submit()">
      <?php $__currentLoopData = $companies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <option value="<?php echo e($ent->id); ?>" <?php echo e($companyId == $ent->id ? 'selected' : ''); ?>><?php echo e($ent->name); ?></option>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select>
  </div>
</form>
<?php endif; ?>

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
        <th>Average Weekly Attendance</th>
        <th>Average Monthly Attendance</th>
        <th>Days With Attendance</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <tr>
        <td><?php echo e($row['employee']->full_name); ?> <span style="color:var(--t3);font-size:11px">(<?php echo e($row['employee']->employee_id); ?>)</span></td>
        <td><?php echo e($row['weekly_avg_hours'] !== null ? $row['weekly_avg_hours'] . ' hrs/day' : '—'); ?></td>
        <td><?php echo e($row['monthly_avg_hours'] !== null ? $row['monthly_avg_hours'] . ' hrs/day' : '—'); ?></td>
        <td><?php echo e($row['monthly_day_count']); ?></td>
        <td>
          <?php if($row['is_shortfall']): ?>
            <span class="bx r">Below 9h Average</span>
          <?php elseif($row['monthly_avg_hours'] !== null): ?>
            <span class="bx g">OK</span>
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