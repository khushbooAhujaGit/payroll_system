<?php $__env->startSection('title', 'Departments'); ?>

<?php $__env->startSection('page_actions'); ?>
  <button class="btn acc" onclick="om('dept-modal')">+ Add Department</button>
  <button class="btn" onclick="om('upload-modal')">📤 Bulk Upload</button>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<div class="card">
  <div class="ch"><div class="ct">Departments</div><span style="font-size:11px;color:var(--t3)"><?php echo e($departments->count()); ?> total</span></div>
  <?php if($departments->isNotEmpty()): ?>
  <table>
    <thead><tr><th>Code</th><th>Name</th><th>Status</th></tr></thead>
    <tbody>
      <?php $__currentLoopData = $departments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dept): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <tr>
        <td class="mono"><?php echo e($dept->code); ?></td>
        <td><?php echo e($dept->name); ?></td>
        <td><span class="bx <?php echo e($dept->is_active ? 'g' : 'gr'); ?>"><?php echo e($dept->is_active ? 'Active' : 'Inactive'); ?></span></td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
  </table>
  <?php else: ?>
  <div class="empty"><div class="ico">🏗️</div><p>No departments yet.</p></div>
  <?php endif; ?>
</div>


<div class="modal" id="dept-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Add Department</span>
      <button class="mc" onclick="cm('dept-modal')">✕</button>
    </div>
    <form method="POST" action="<?php echo e(route('departments.store')); ?>">
      <?php echo csrf_field(); ?>
      <div class="fg2">
        <div class="fld">
          <label>Name *</label>
          <input type="text" name="name" required value="<?php echo e(old('name')); ?>">
        </div>
        <div class="fld">
          <label>Code *</label>
          <input type="text" name="code" required maxlength="10" value="<?php echo e(old('code')); ?>" placeholder="HR, FIN, ENG…">
        </div>
        <div class="fld full">
          <label>Parent Department</label>
          <select name="parent_id">
            <option value="">None (top-level)</option>
            <?php $__currentLoopData = $departments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($d->id); ?>"><?php echo e($d->name); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('dept-modal')">Cancel</button>
        <button type="submit" class="btn acc">Create</button>
      </div>
    </form>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('partials.upload-modal', [
    'uploadId'      => 'upload-modal',
    'uploadTitle'   => 'Bulk Upload Departments & Designations',
    'uploadRoute'   => 'departments.import',
    'templateRoute' => 'departments.template',
    'uploadHint'    => 'Sheet 1 — Departments: Name, Code, Parent Department Code (optional). Sheet 2 — Designations: Name, Code, Level (optional). One row per entry.',
], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/masters/departments.blade.php ENDPATH**/ ?>