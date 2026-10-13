<?php $__env->startSection('title', 'Attendance Change Requests'); ?>

<?php $__env->startSection('page_actions'); ?>
  <a href="<?php echo e(route('attendance.change_requests.export', ['company_id' => $companyId, 'search' => $search, 'month' => $month])); ?>" class="btn">⬇ Export</a>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<?php if($companies->count() > 1): ?>
<form method="GET" action="<?php echo e(route('attendance.change_requests')); ?>" class="card"
  style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
  <input type="hidden" name="search" value="<?php echo e($search); ?>">
  <input type="hidden" name="month" value="<?php echo e($month); ?>">
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

<form method="GET" action="<?php echo e(route('attendance.change_requests')); ?>" class="card"
  style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
  <?php if($companyId): ?>
    <input type="hidden" name="company_id" value="<?php echo e($companyId); ?>">
  <?php endif; ?>
  <div class="fld" style="min-width:220px">
    <label>Search by Name / Emp ID</label>
    <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="e.g. EMP001 or John Doe">
  </div>
  <div class="fld" style="min-width:160px">
    <label>Month / Year</label>
    <input type="month" name="month" value="<?php echo e($month); ?>">
  </div>
  <button type="submit" class="btn acc">Filter</button>
  <?php if($search !== '' || $month !== ''): ?>
    <a href="<?php echo e(route('attendance.change_requests', $companyId ? ['company_id' => $companyId] : [])); ?>" class="btn">✕ Clear</a>
  <?php endif; ?>
</form>

<div class="card">
  <div class="ch"><div class="ct">Pending & Recent Change Requests</div></div>
  <?php if($requests->isNotEmpty()): ?>
  <table>
    <thead><tr><th>Employee</th><th>Type</th><th>Date</th><th>Check In</th><th>Check Out</th><th>Remark</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php $__currentLoopData = $requests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $req): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <tr>
        <td><?php echo e($req->employee?->full_name ?? '—'); ?> <span style="color:var(--t3);font-size:11px">(<?php echo e($req->employee?->employee_id); ?>)</span></td>
        <td><span class="bx gr"><?php echo e($req->reason === 'weekly_hours_shortfall' ? 'Week' : 'Day'); ?></span></td>
        <td><?php echo e($req->attendance_date->format('d M Y')); ?></td>
        <?php if($req->reason === 'daily_hours_shortfall'): ?>
          <?php $actual = $actualPunches->get($req->employee_id . '_' . $req->attendance_date->format('Y-m-d')); ?>
          <td style="<?php echo e($req->requested_check_in ? 'background:var(--yel2);font-weight:600' : ''); ?>">
            <?php echo e($actual['check_in'] ?? null ? substr($actual['check_in'], 0, 5) : '—'); ?>

            <?php if($req->requested_check_in): ?>
              <div style="font-size:11px;color:var(--red);font-weight:400">→ requested <?php echo e(substr($req->requested_check_in, 0, 5)); ?></div>
            <?php endif; ?>
          </td>
          <td style="<?php echo e($req->requested_check_out ? 'background:var(--yel2);font-weight:600' : ''); ?>">
            <?php echo e($actual['check_out'] ?? null ? substr($actual['check_out'], 0, 5) : '—'); ?>

            <?php if($req->requested_check_out): ?>
              <div style="font-size:11px;color:var(--red);font-weight:400">→ requested <?php echo e(substr($req->requested_check_out, 0, 5)); ?></div>
            <?php endif; ?>
          </td>
        <?php else: ?>
          <td colspan="2" style="color:var(--t3);text-align:center">—</td>
        <?php endif; ?>
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