<?php $__env->startSection('title', 'Leave Policies'); ?>

<?php $__env->startSection('page_actions'); ?>
  <button class="btn acc" onclick="om('add-policy-modal')">+ Add Policy</button>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<div class="g4" style="margin-bottom:16px">
  <div class="sc">
    <div class="lbl">Total Policies</div>
    <div class="val"><?php echo e($policies->count()); ?></div>
  </div>
  <div class="sc">
    <div class="lbl">Active</div>
    <div class="val" style="color:var(--grn)"><?php echo e($policies->where('is_active', true)->count()); ?></div>
  </div>
  <div class="sc">
    <div class="lbl">Leave Types Available</div>
    <div class="val"><?php echo e($leaveTypes->count()); ?></div>
  </div>
</div>


<div class="card" style="margin-bottom:20px">
  <div class="ch">
    <div class="ct">Leave Policies</div>
  </div>
  <?php if($policies->isNotEmpty()): ?>
  <table>
    <thead>
      <tr>
        <th>Policy Name</th>
        <th>Leave Type</th>
        <th>Days / Year</th>
        <th>Accrual</th>
        <th>Proration</th>
        <th>Sandwich</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php $__currentLoopData = $policies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $policy): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <tr>
        <td style="font-weight:500"><?php echo e($policy->name); ?></td>
        <td>
          <?php if($policy->leaveType): ?>
            <span class="bx" style="background:var(--acc2);color:var(--acc)"><?php echo e($policy->leaveType->code); ?></span>
            <?php echo e($policy->leaveType->name); ?>

          <?php else: ?>
            <span style="color:var(--t3)">—</span>
          <?php endif; ?>
        </td>
        <td class="mono"><?php echo e(number_format($policy->days_per_year, 1)); ?></td>
        <td style="text-transform:capitalize"><?php echo e($policy->accrual_type); ?></td>
        <td style="text-transform:capitalize;font-size:11px;color:var(--t2)">
          <?php echo e(str_replace('_', ' ', $policy->proration)); ?>

        </td>
        <td>
          <?php if($policy->sandwich_policy): ?>
            <span style="color:var(--grn)">Yes</span>
          <?php else: ?>
            <span style="color:var(--t3)">No</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if($policy->is_active): ?>
            <span class="bx" style="color:var(--grn)">Active</span>
          <?php else: ?>
            <span class="bx" style="color:var(--red)">Inactive</span>
          <?php endif; ?>
        </td>
        <td style="white-space:nowrap">
          <button class="btn sm" onclick="openEdit(<?php echo e($policy->id); ?>,
            <?php echo e(json_encode($policy->name)); ?>,
            <?php echo e($policy->leave_type_id); ?>,
            <?php echo e($policy->days_per_year); ?>,
            <?php echo e(json_encode($policy->accrual_type)); ?>,
            <?php echo e(json_encode((string)$policy->accrual_days)); ?>,
            <?php echo e(json_encode($policy->proration)); ?>,
            <?php echo e($policy->sandwich_policy ? 1 : 0); ?>,
            <?php echo e($policy->is_active ? 1 : 0); ?>

          )">Edit</button>
          <form method="POST" action="<?php echo e(route('leave.policies.destroy', $policy)); ?>" style="display:inline">
            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
            <button type="button" class="btn sm" style="background:var(--red2);color:var(--red)"
              onclick="swConfirm(this.closest('form'), 'Delete Policy?', 'The leave policy &quot;<?php echo e(addslashes($policy->name)); ?>&quot; will be permanently deleted.', 'warning', 'Yes, delete', '#dc2626')">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
  </table>
  <?php else: ?>
  <div class="empty">
    <div class="ico">📋</div>
    <p>No leave policies configured yet.</p>
    <p style="font-size:12px;color:var(--t3);margin-top:4px">Click <strong>+ Add Policy</strong> to create one.</p>
  </div>
  <?php endif; ?>
</div>


<div class="card">
  <div class="ch">
    <div class="ct">Leave Type Codes Reference</div>
    <div style="font-size:11px;color:var(--t3)"><?php echo e($leaveTypes->count()); ?> types available</div>
  </div>
  <div style="display:flex;flex-wrap:wrap;gap:8px;padding:4px 0 8px">
    <?php $__currentLoopData = $leaveTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $lt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div style="background:var(--s2);border:1px solid var(--b2);border-radius:6px;
                padding:6px 12px;font-size:12px;min-width:160px">
      <span style="font-weight:700;color:var(--acc);font-size:13px"><?php echo e($lt->code); ?></span>
      <span style="color:var(--t2);margin-left:6px"><?php echo e($lt->name); ?></span>
      <div style="font-size:10px;color:var(--t3);margin-top:2px;text-transform:capitalize">
        <?php echo e(str_replace('_', ' ', $lt->category)); ?>

        <?php echo e($lt->is_paid ? ' · Paid' : ' · Unpaid'); ?>

      </div>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>
</div>


<div class="modal" id="add-policy-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Add Leave Policy</span>
      <button class="mc" onclick="cm('add-policy-modal')">✕</button>
    </div>
    <form method="POST" action="<?php echo e(route('leave.policies.store')); ?>">
      <?php echo csrf_field(); ?>
      <?php echo $__env->make('leave._policy-form', ['policy' => null, 'leaveTypes' => $leaveTypes,
        'accrualTypes' => $accrualTypes, 'prorationsTypes' => $prorationsTypes], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('add-policy-modal')">Cancel</button>
        <button type="submit" class="btn acc">Save Policy</button>
      </div>
    </form>
  </div>
</div>


<div class="modal" id="edit-policy-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Edit Leave Policy</span>
      <button class="mc" onclick="cm('edit-policy-modal')">✕</button>
    </div>
    <form method="POST" id="edit-policy-form" action="">
      <?php echo csrf_field(); ?>
      <?php echo $__env->make('leave._policy-form', ['policy' => null, 'leaveTypes' => $leaveTypes,
        'accrualTypes' => $accrualTypes, 'prorationsTypes' => $prorationsTypes, 'edit' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('edit-policy-modal')">Cancel</button>
        <button type="submit" class="btn acc">Update Policy</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEdit(id, name, ltId, days, accrual, accrualDays, proration, sandwich, isActive) {
  document.getElementById('edit-policy-form').action = '/leave-policies/' + id;
  var f = document.getElementById('edit-policy-form');
  f.querySelector('[name="name"]').value         = name;
  f.querySelector('[name="leave_type_id"]').value = ltId;
  f.querySelector('[name="days_per_year"]').value  = days;
  f.querySelector('[name="accrual_type"]').value   = accrual;
  f.querySelector('[name="accrual_days"]').value   = accrualDays;
  f.querySelector('[name="proration"]').value      = proration;
  f.querySelector('[name="sandwich_policy"]').checked = sandwich == 1;
  f.querySelector('[name="is_active"]').checked    = isActive == 1;
  om('edit-policy-modal');
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/leave/policies.blade.php ENDPATH**/ ?>