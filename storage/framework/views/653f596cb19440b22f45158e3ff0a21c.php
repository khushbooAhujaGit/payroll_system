<?php $__env->startSection('title', 'Branches'); ?>

<?php $__env->startSection('page_actions'); ?>
  <button class="btn acc" onclick="om('branch-modal')">+ Add Branch</button>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<div class="card">
  <div class="ch"><div class="ct">Branches</div><span style="font-size:11px;color:var(--t3)"><?php echo e($branches->count()); ?> total</span></div>
  <?php if($branches->isNotEmpty()): ?>
  <table>
    <thead><tr><th>Code</th><th>Name</th><th>City</th><th>State</th><th>Status</th></tr></thead>
    <tbody>
      <?php $__currentLoopData = $branches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $branch): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <tr>
        <td class="mono"><?php echo e($branch->code ?? '—'); ?></td>
        <td><?php echo e($branch->name); ?></td>
        <td><?php echo e($branch->city ?? '—'); ?></td>
        <td><?php echo e($branch->state ?? '—'); ?></td>
        <td><span class="bx <?php echo e($branch->is_active ? 'g' : 'gr'); ?>"><?php echo e($branch->is_active ? 'Active' : 'Inactive'); ?></span></td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
  </table>
  <?php else: ?>
  <div class="empty"><div class="ico">🏢</div><p>No branches yet.</p></div>
  <?php endif; ?>
</div>


<div class="modal" id="branch-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Add Branch</span>
      <button class="mc" onclick="cm('branch-modal')">✕</button>
    </div>
    <form method="POST" action="<?php echo e(route('masters.branches.store')); ?>">
      <?php echo csrf_field(); ?>
      <div class="fg2">
        <div class="fld">
          <label>Name *</label>
          <input type="text" name="name" required value="<?php echo e(old('name')); ?>">
        </div>
        <div class="fld">
          <label>Code</label>
          <input type="text" name="code" maxlength="20" value="<?php echo e(old('code')); ?>">
        </div>
        <div class="fld">
          <label>City</label>
          <input type="text" name="city" value="<?php echo e(old('city')); ?>">
        </div>
        <div class="fld">
          <label>State</label>
          <input type="text" name="state" value="<?php echo e(old('state')); ?>">
        </div>
        <div class="fld full">
          <label>Address</label>
          <input type="text" name="address" value="<?php echo e(old('address')); ?>">
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('branch-modal')">Cancel</button>
        <button type="submit" class="btn acc">Create</button>
      </div>
    </form>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/masters/branches.blade.php ENDPATH**/ ?>