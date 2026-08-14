<?php $__env->startSection('title', 'My Attendance'); ?>

<?php $__env->startSection('page_actions'); ?>
  <a href="<?php echo e(route('attendance.analytics')); ?>" class="btn">Analytics</a>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<?php
  $prevMonth = $monthStart->copy()->subMonth()->format('Y-m');
  $nextMonth = $monthStart->copy()->addMonth()->format('Y-m');
?>

<div class="card" style="margin-bottom:16px">
  <div class="ch">
    <div class="ct"><?php echo e($monthStart->format('F Y')); ?></div>
    <div style="display:flex;gap:6px">
      <a href="<?php echo e(route('my-attendance', ['month' => $prevMonth])); ?>" class="btn sm">← Prev</a>
      <a href="<?php echo e(route('my-attendance')); ?>" class="btn sm">This Month</a>
      <a href="<?php echo e(route('my-attendance', ['month' => $nextMonth])); ?>" class="btn sm">Next →</a>
    </div>
  </div>
</div>


<div class="card" style="margin-bottom:16px">
  <div class="ch"><div class="ct">Weekly Average Hours</div></div>
  <table>
    <thead><tr><th>Week</th><th>Days With Attendance</th><th>Average Hours/Day</th><th>Status</th><th>Change Request</th></tr></thead>
    <tbody>
      <?php $__currentLoopData = $weeks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $week): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <tr>
        <td><?php echo e($week['label']); ?></td>
        <td><?php echo e($week['day_count']); ?></td>
        <td><?php echo e($week['average_hours'] !== null ? $week['average_hours'] . ' hrs' : '—'); ?></td>
        <td>
          <?php if($week['is_shortfall']): ?>
            <span class="bx r">Below 9h Average</span>
          <?php elseif($week['average_hours'] !== null): ?>
            <span class="bx g">OK</span>
          <?php else: ?>
            <span class="bx gr">No Data</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if($week['existing_request']): ?>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
              <span class="bx <?php echo e($week['existing_request']->status === 'approved' ? 'g' : ($week['existing_request']->status === 'rejected' ? 'r' : 'y')); ?>">
                <?php echo e(ucfirst($week['existing_request']->status)); ?>

              </span>
              <span style="font-size:12px;color:var(--t2)">"<?php echo e($week['existing_request']->remarks); ?>"</span>
            </div>
            <?php if($week['existing_request']->approval_remarks): ?>
              <div style="font-size:11px;color:var(--t3);margin-top:3px">HR: <?php echo e($week['existing_request']->approval_remarks); ?></div>
            <?php endif; ?>
          <?php elseif($week['is_shortfall']): ?>
            <button class="btn sm" onclick="om('change-request-<?php echo e($week['week_start']); ?>')">Raise Change Request</button>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
  </table>
</div>


<div class="card" style="margin-bottom:16px">
  <div class="ch"><div class="ct">Daily Attendance</div></div>
  <table>
    <thead><tr><th>Date</th><th>Check In</th><th>Check Out</th><th>Hours</th></tr></thead>
    <tbody>
      <?php $__currentLoopData = $days; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $day): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <tr>
        <td><?php echo e(\Carbon\Carbon::parse($day['date'])->format('d M Y (D)')); ?></td>
        <td class="mono"><?php echo e($day['check_in'] ?? '—'); ?></td>
        <td class="mono"><?php echo e($day['check_out'] ?? '—'); ?></td>
        <td><?php echo e($day['hours'] !== null ? $day['hours'] . ' hrs' : '—'); ?></td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
  </table>
</div>


<div class="card">
  <div class="ch"><div class="ct">My Change Requests</div></div>
  <?php if($myRequests->isNotEmpty()): ?>
  <table>
    <thead><tr><th>Week Of</th><th>Remark</th><th>Status</th><th>HR Remark</th></tr></thead>
    <tbody>
      <?php $__currentLoopData = $myRequests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $req): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <tr>
        <td><?php echo e($req->attendance_date->format('d M Y')); ?></td>
        <td><?php echo e($req->remarks); ?></td>
        <td>
          <span class="bx <?php echo e($req->status === 'approved' ? 'g' : ($req->status === 'rejected' ? 'r' : 'y')); ?>">
            <?php echo e(ucfirst($req->status)); ?>

          </span>
        </td>
        <td><?php echo e($req->approval_remarks ?: '—'); ?></td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
  </table>
  <?php else: ?>
  <div class="empty"><div class="ico">📋</div><p>No change requests yet.</p></div>
  <?php endif; ?>
</div>


<?php $__currentLoopData = $weeks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $week): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <?php if($week['is_shortfall'] && !$week['existing_request']): ?>
    <div class="modal" id="change-request-<?php echo e($week['week_start']); ?>">
      <div class="mb">
        <div class="mh">
          <span class="mt">Change Request — Week of <?php echo e($week['label']); ?></span>
          <button class="mc" onclick="cm('change-request-<?php echo e($week['week_start']); ?>')">✕</button>
        </div>
        <form method="POST" action="<?php echo e(route('my-attendance.change_request')); ?>">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="week_start" value="<?php echo e($week['week_start']); ?>">
          <div class="fld full">
            <label class="req">Remark</label>
            <textarea name="remarks" rows="4" required placeholder="Explain why your hours were short this week…"></textarea>
          </div>
          <div class="mf">
            <button type="button" class="btn" onclick="cm('change-request-<?php echo e($week['week_start']); ?>')">Cancel</button>
            <button type="submit" class="btn acc">Submit</button>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/attendance/my-attendance.blade.php ENDPATH**/ ?>