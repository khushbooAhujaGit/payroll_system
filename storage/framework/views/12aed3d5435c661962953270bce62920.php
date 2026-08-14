<?php $__env->startSection('title', 'Attendance Change Requests'); ?>

<?php $__env->startSection('content'); ?>

<?php if($companies->count() > 1): ?>
<form method="GET" action="<?php echo e(route('attendance.change_requests')); ?>" class="card"
  style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
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

<div class="card">
  <div class="ch"><div class="ct">Pending & Recent Change Requests</div></div>
  <?php if($requests->isNotEmpty()): ?>
  <table>
    <thead><tr><th>Employee</th><th>Week Of</th><th>Remark</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php $__currentLoopData = $requests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $req): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <tr>
        <td><?php echo e($req->employee?->full_name ?? '—'); ?> <span style="color:var(--t3);font-size:11px">(<?php echo e($req->employee?->employee_id); ?>)</span></td>
        <td><?php echo e($req->attendance_date->format('d M Y')); ?></td>
        <td><?php echo e($req->remarks); ?></td>
        <td>
          <span class="bx <?php echo e($req->status === 'approved' ? 'g' : ($req->status === 'rejected' ? 'r' : 'y')); ?>">
            <?php echo e(ucfirst($req->status)); ?>

          </span>
        </td>
        <td>
          <?php if($req->status === 'pending'): ?>
            <button class="btn sm" onclick="om('resolve-<?php echo e($req->id); ?>')">Review</button>
          <?php else: ?>
            <span style="color:var(--t3);font-size:11px"><?php echo e($req->approval_remarks ?: '—'); ?></span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
  </table>
  <div style="margin-top:14px"><?php echo e($requests->links()); ?></div>
  <?php else: ?>
  <div class="empty"><div class="ico">📋</div><p>No change requests yet.</p></div>
  <?php endif; ?>
</div>

<?php $__currentLoopData = $requests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $req): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <?php if($req->status === 'pending'): ?>
    <div class="modal" id="resolve-<?php echo e($req->id); ?>">
      <div class="mb">
        <div class="mh">
          <span class="mt">Review — <?php echo e($req->employee?->full_name); ?> (<?php echo e($req->attendance_date->format('d M Y')); ?>)</span>
          <button class="mc" onclick="cm('resolve-<?php echo e($req->id); ?>')">✕</button>
        </div>
        <p style="font-size:13px;color:var(--t2);margin-bottom:12px"><strong>Employee's remark:</strong> <?php echo e($req->remarks); ?></p>
        <form method="POST" action="<?php echo e(route('attendance.change_requests.resolve', $req)); ?>">
          <?php echo csrf_field(); ?>
          <div class="fld full">
            <label>HR Remark (optional)</label>
            <textarea name="approval_remarks" rows="3"></textarea>
          </div>
          <div class="mf">
            <button type="submit" name="action" value="rejected" class="btn" style="color:var(--red)">Reject</button>
            <button type="submit" name="action" value="approved" class="btn acc">Approve</button>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/attendance/change-requests.blade.php ENDPATH**/ ?>