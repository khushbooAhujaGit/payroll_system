<?php $__env->startSection('title', 'Designations'); ?>

<?php $__env->startSection('page_actions'); ?>
  <button class="btn acc" onclick="om('desig-modal')">+ Add Designation</button>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<div class="card">
  <div class="ch"><div class="ct">Designations</div><span style="font-size:11px;color:var(--t3)"><?php echo e($designations->count()); ?> total</span></div>
  <?php if($designations->isNotEmpty()): ?>
  <table>
    <thead><tr><th>Code</th><th>Name</th><th>Level</th></tr></thead>
    <tbody>
      <?php $__currentLoopData = $designations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $desig): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <tr>
        <td class="mono"><?php echo e($desig->code); ?></td>
        <td><?php echo e($desig->name); ?></td>
        <td><?php echo e($desig->level ?? '—'); ?></td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
  </table>
  <?php else: ?>
  <div class="empty"><div class="ico">🎯</div><p>No designations yet.</p></div>
  <?php endif; ?>
</div>


<div class="modal" id="desig-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Add Designation</span>
      <button class="mc" onclick="cm('desig-modal')">✕</button>
    </div>
    <form method="POST" action="<?php echo e(route('masters.designations.store')); ?>">
      <?php echo csrf_field(); ?>
      <div class="fg2">
        <div class="fld">
          <label>Name *</label>
          <input type="text" name="name" required value="<?php echo e(old('name')); ?>">
        </div>
        <div class="fld">
          <label>Code *</label>
          <input type="text" name="code" required maxlength="10" value="<?php echo e(old('code')); ?>">
        </div>
        <div class="fld full">
          <label>Level (optional)</label>
          <input type="number" name="level" min="1" value="<?php echo e(old('level')); ?>" placeholder="1 = intern, 10 = CEO">
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('desig-modal')">Cancel</button>
        <button type="submit" class="btn acc">Create</button>
      </div>
    </form>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/masters/designations.blade.php ENDPATH**/ ?>