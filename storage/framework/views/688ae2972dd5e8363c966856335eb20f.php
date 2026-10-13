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
  <div class="tw">
  <table>
    <thead><tr><th>Week</th><th>Days With Attendance</th><th>Average Hours/Day</th><th>Status</th><th>Remark</th></tr></thead>
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
            <button class="btn sm" style="font-size:15px;padding:4px 9px" title="Add a remark explaining this week's shortfall" onclick="om('change-request-week-<?php echo e($week['week_start']); ?>')">📝</button>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
  </table>
  </div>
</div>


<div class="card" style="margin-bottom:16px">
  <div class="ch"><div class="ct">Daily Attendance</div></div>
  <div class="tw">
  <table>
    <thead><tr><th>Date</th><th>Check In</th><th>Check Out</th><th>Hours</th><th>Change Request</th></tr></thead>
    <tbody>
      <?php $__currentLoopData = $days; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $day): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <tr>
        <td><?php echo e(\Carbon\Carbon::parse($day['date'])->format('d M Y (D)')); ?></td>
        <td class="mono"><?php echo e($day['check_in'] ?? '—'); ?></td>
        <td class="mono"><?php echo e($day['check_out'] ?? '—'); ?></td>
        <td><?php echo e($day['hours'] !== null ? $day['hours'] . ' hrs' : '—'); ?></td>
        <td>
          <?php if($day['existing_request']): ?>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
              <span class="bx <?php echo e($day['existing_request']->status === 'approved' ? 'g' : ($day['existing_request']->status === 'rejected' ? 'r' : 'y')); ?>">
                <?php echo e(ucfirst($day['existing_request']->status)); ?>

              </span>
              <span style="font-size:12px;color:var(--t2)">"<?php echo e($day['existing_request']->remarks); ?>"</span>
            </div>
            <?php if($day['existing_request']->requested_check_in): ?>
              <div style="font-size:11px;color:var(--t2);margin-top:3px">Check In → <?php echo e(substr($day['existing_request']->requested_check_in, 0, 5)); ?></div>
            <?php endif; ?>
            <?php if($day['existing_request']->requested_check_out): ?>
              <div style="font-size:11px;color:var(--t2);margin-top:3px">Check Out → <?php echo e(substr($day['existing_request']->requested_check_out, 0, 5)); ?></div>
            <?php endif; ?>
            <?php if($day['existing_request']->approval_remarks): ?>
              <div style="font-size:11px;color:var(--t3);margin-top:3px">HR: <?php echo e($day['existing_request']->approval_remarks); ?></div>
            <?php endif; ?>
          <?php elseif($day['can_request_day']): ?>
            <button class="btn sm" style="font-size:15px;padding:4px 9px" title="Raise a change request for this day's Check In/Out" onclick="om('change-request-day-<?php echo e($day['date']); ?>')">📝</button>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
  </table>
  </div>
</div>


<div class="card">
  <div class="ch"><div class="ct">My Change Requests</div></div>
  <?php if($myRequests->isNotEmpty()): ?>
  <div class="tw">
  <table>
    <thead><tr><th>Type</th><th>Date</th><th>Remark</th><th>Status</th><th>HR Remark</th></tr></thead>
    <tbody>
      <?php $__currentLoopData = $myRequests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $req): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <tr>
        <td><span class="bx gr"><?php echo e($req->reason === 'weekly_hours_shortfall' ? 'Week' : 'Day'); ?></span></td>
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
  </div>
  <?php else: ?>
  <div class="empty"><div class="ico">📋</div><p>No change requests yet.</p></div>
  <?php endif; ?>
</div>


<?php $__currentLoopData = $weeks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $week): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <?php if($week['is_shortfall'] && !$week['existing_request']): ?>
    <div class="modal" id="change-request-week-<?php echo e($week['week_start']); ?>">
      <div class="mb">
        <div class="mh">
          <span class="mt">Change Request — Week of <?php echo e($week['label']); ?></span>
          <button class="mc" onclick="cm('change-request-week-<?php echo e($week['week_start']); ?>')">✕</button>
        </div>
        <form method="POST" action="<?php echo e(route('my-attendance.change_request')); ?>">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="request_type" value="week">
          <input type="hidden" name="attendance_date" value="<?php echo e($week['week_start']); ?>">
          <div class="fld full">
            <label class="req">Remark</label>
            <textarea name="remarks" rows="4" required placeholder="Explain why your hours were short this week…"></textarea>
          </div>
          <div class="mf">
            <button type="button" class="btn" onclick="cm('change-request-week-<?php echo e($week['week_start']); ?>')">Cancel</button>
            <button type="submit" class="btn acc">Submit</button>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>


<?php $__currentLoopData = $days; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $day): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <?php if($day['can_request_day'] && !$day['existing_request']): ?>
    <div class="modal" id="change-request-day-<?php echo e($day['date']); ?>">
      <div class="mb">
        <div class="mh">
          <span class="mt">Change Request — <?php echo e(\Carbon\Carbon::parse($day['date'])->format('d M Y (D)')); ?></span>
          <button class="mc" onclick="cm('change-request-day-<?php echo e($day['date']); ?>')">✕</button>
        </div>
        <form method="POST" action="<?php echo e(route('my-attendance.change_request')); ?>" onsubmit="return validateDayRequest(this)">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="request_type" value="day">
          <input type="hidden" name="attendance_date" value="<?php echo e($day['date']); ?>">
          <div class="fld full">
            <label class="req">What would you like to correct?</label>
            <div style="display:flex;flex-direction:column;gap:10px;margin-top:6px">
              <div>
                <label style="display:flex;align-items:center;gap:8px;font-weight:400;cursor:pointer">
                  <input type="checkbox" name="request_check_in" value="1"
                    onchange="toggleDayTimeField(this, 'ci-<?php echo e($day['date']); ?>')">
                  Check In <span style="color:var(--t3)">(currently <?php echo e(substr($day['check_in'], 0, 5)); ?>)</span>
                </label>
                <input type="time" id="ci-<?php echo e($day['date']); ?>" name="requested_check_in"
                  style="display:none;margin-top:4px" disabled>
              </div>
              <div>
                <label style="display:flex;align-items:center;gap:8px;font-weight:400;cursor:pointer">
                  <input type="checkbox" name="request_check_out" value="1"
                    onchange="toggleDayTimeField(this, 'co-<?php echo e($day['date']); ?>')">
                  Check Out <span style="color:var(--t3)">(currently <?php echo e(substr($day['check_out'], 0, 5)); ?>)</span>
                </label>
                <input type="time" id="co-<?php echo e($day['date']); ?>" name="requested_check_out"
                  style="display:none;margin-top:4px" disabled>
              </div>
            </div>
          </div>
          <div class="fld full">
            <label class="req">Reason</label>
            <textarea name="remarks" rows="4" required placeholder="Explain why this time needs correcting…"></textarea>
          </div>
          <div class="mf">
            <button type="button" class="btn" onclick="cm('change-request-day-<?php echo e($day['date']); ?>')">Cancel</button>
            <button type="submit" class="btn acc">Submit</button>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
function toggleDayTimeField(checkbox, inputId) {
  const el = document.getElementById(inputId);
  if (checkbox.checked) {
    el.style.display = '';
    el.disabled = false;
    el.required = true;
  } else {
    el.style.display = 'none';
    el.disabled = true;
    el.required = false;
    el.value = '';
  }
}

function validateDayRequest(form) {
  const ciChecked = form.querySelector('input[name="request_check_in"]')?.checked;
  const coChecked = form.querySelector('input[name="request_check_out"]')?.checked;
  if (!ciChecked && !coChecked) {
    Swal.fire('Select at least one', 'Tick Check In, Check Out, or both to say what you want corrected.', 'warning');
    return false;
  }
  return true;
}
</script>
<?php $__env->stopPush(); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/attendance/my-attendance.blade.php ENDPATH**/ ?>