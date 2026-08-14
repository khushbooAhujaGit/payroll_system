
<?php $tabValue = $tabValue ?? null; ?>
<div class="card" style="margin-top:16px">
  <div class="ch"><div class="ct"><?php echo e($title); ?></div></div>

  <form method="GET" action="<?php echo e(route('leave')); ?>"
    style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;padding:10px 0 14px;border-bottom:1px solid var(--b2)">
    
    <?php $__currentLoopData = request()->except([$searchParam, 'page', 'self_page', 'team_page']); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $val): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <?php if(is_array($val)): ?>
        <?php $__currentLoopData = $val; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><input type="hidden" name="<?php echo e($key); ?>[]" value="<?php echo e($v); ?>"><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      <?php else: ?>
        <input type="hidden" name="<?php echo e($key); ?>" value="<?php echo e($val); ?>">
      <?php endif; ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <div class="fld" style="min-width:200px">
      <label style="font-size:11px">Search</label>
      <input type="text" name="<?php echo e($searchParam); ?>" value="<?php echo e($searchValue); ?>" placeholder="Name, emp code, leave type…" style="font-size:12px">
    </div>
    <button type="submit" class="btn sm acc">Filter</button>
    <?php if($searchValue): ?>
      <a href="<?php echo e(route('leave', request()->except([$searchParam]))); ?>" class="btn sm">Reset</a>
    <?php endif; ?>
  </form>

  <?php if($rows->isNotEmpty()): ?>
  <form method="POST" action="<?php echo e(route('leave.bulk_approve')); ?>" id="<?php echo e($formId); ?>">
    <?php echo csrf_field(); ?>
    <?php if($tabValue): ?><input type="hidden" name="applications_tab" value="<?php echo e($tabValue); ?>"><?php endif; ?>
    <table>
      <thead>
        <tr>
          <?php if($showBulk): ?>
          <th style="width:24px"><input type="checkbox" onclick="document.querySelectorAll('#<?php echo e($formId); ?> .row-chk').forEach(c => c.checked = this.checked)"></th>
          <?php endif; ?>
          <th>Employee</th><th>Leave Type</th><th>From</th><th>To</th><th>Days</th><th>Reason</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php
          $canApprove = $isAdmin || ($row->employee?->reporting_manager_id === $viewerEmployeeId && $viewerEmployeeId !== null);
          $isActionable = $row->status === 'pending' && $canApprove;
          $approveUrl = $row->kind === 'application'
              ? route('leave.approve', $row->application)
              : route('leave.tracker_request.approve', $row->trackerRecord);
          $checkboxName = $row->kind === 'application' ? 'application_ids[]' : 'tracker_ids[]';
        ?>
        <tr>
          <?php if($showBulk): ?>
          <td>
            <?php if($isActionable): ?>
              <input type="checkbox" class="row-chk" name="<?php echo e($checkboxName); ?>" value="<?php echo e($row->id); ?>">
            <?php endif; ?>
          </td>
          <?php endif; ?>
          <td><?php echo e($row->employee?->full_name); ?></td>
          <td><?php echo e($row->leave_type); ?></td>
          <td><?php echo e(\Carbon\Carbon::parse($row->from_date)->format('d M Y')); ?></td>
          <td><?php echo e(\Carbon\Carbon::parse($row->to_date)->format('d M Y')); ?></td>
          <td><?php echo e($row->days); ?></td>
          <td>
            <?php if($row->reason): ?>
              <span class="leave-reason-cell" data-reason="<?php echo e($row->reason); ?>" onclick="showLeaveReason(this)"
                style="display:block;max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:12px;color:var(--t2);cursor:pointer"
                title="Click to view full reason"><?php echo e($row->reason); ?></span>
            <?php else: ?>
              <span style="font-size:12px;color:var(--t3)">—</span>
            <?php endif; ?>
          </td>
          <td>
            <?php $color = ['pending'=>'var(--yel)','approved'=>'var(--grn)','rejected'=>'var(--red)'][$row->status] ?? 'var(--t3)'; ?>
            <span class="bx" style="color:<?php echo e($color); ?>"><?php echo e(ucfirst($row->status)); ?></span>
          </td>
          <td>
            
            <?php if($isActionable): ?>
            <button type="submit" name="action" value="approved" formaction="<?php echo e($approveUrl); ?>"
              class="btn sm" style="background:var(--grn2);color:var(--grn)">Approve</button>
            <button type="submit" name="action" value="rejected" formaction="<?php echo e($approveUrl); ?>"
              class="btn sm" style="background:var(--red2);color:var(--red)">Reject</button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </tbody>
    </table>

    <?php if($showBulk): ?>
    <div style="display:flex;gap:8px;margin-top:12px">
      <button type="submit" name="action" value="approved" class="btn sm" style="background:var(--grn2);color:var(--grn)"
        onclick="return confirm('Approve all selected rows?')">✔ Approve Selected</button>
      <button type="submit" name="action" value="rejected" class="btn sm" style="background:var(--red2);color:var(--red)"
        onclick="return confirm('Reject all selected rows?')">✕ Reject Selected</button>
    </div>
    <?php endif; ?>
  </form>
  <div style="margin-top:14px"><?php echo e($rows->links()); ?></div>
  <?php else: ?>
  <div class="empty"><div class="ico">🏖️</div><p><?php echo e($emptyText); ?></p></div>
  <?php endif; ?>
</div>
<?php /**PATH D:\hrms-payroll\resources\views/leave/_applications_table.blade.php ENDPATH**/ ?>