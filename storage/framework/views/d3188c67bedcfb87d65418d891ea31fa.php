<?php $__env->startSection('title', 'Designations'); ?>

<?php $__env->startSection('page_actions'); ?>
  <button class="btn acc" onclick="om('desig-modal')">+ Add Designation</button>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<div class="card">
  <div class="ch">
    <div class="ct">Designations</div>
    <div style="display:flex;align-items:center;gap:10px">
      <button type="button" class="btn sm" id="desig-bulk-del-btn" style="display:none;color:var(--red)" onclick="bulkDelete('desig-bulk-form', 'desig-chk', 'Designations')">Delete Selected (<span id="desig-sel-count">0</span>)</button>
      <span style="font-size:11px;color:var(--t3)"><?php echo e($designations->count()); ?> total</span>
    </div>
  </div>
  <?php if($designations->isNotEmpty()): ?>
  <form id="desig-bulk-form" method="POST" action="<?php echo e(route('designations.bulk-delete')); ?>"><?php echo csrf_field(); ?></form>
  <table>
    <thead><tr>
      <th style="width:30px"><input type="checkbox" id="desig-chk-all" onclick="toggleAll(this, 'desig-chk', 'desig-sel-count', 'desig-bulk-del-btn')"></th>
      <th>Code</th><th>Name</th><th>Level</th><th></th>
    </tr></thead>
    <tbody>
      <?php $__currentLoopData = $designations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $desig): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <tr>
        <td><input type="checkbox" class="desig-chk" value="<?php echo e($desig->id); ?>" onclick="onRowCheck('desig-chk', 'desig-chk-all', 'desig-sel-count', 'desig-bulk-del-btn')"></td>
        <td class="mono"><?php echo e($desig->code); ?></td>
        <td><?php echo e($desig->name); ?></td>
        <td><?php echo e($desig->level ?? '—'); ?></td>
        <td>
          <button type="button" class="btn sm" onclick='openEditDesig(<?php echo json_encode($desig, 15, 512) ?>)'>Edit</button>
          <form method="POST" action="<?php echo e(route('designations.destroy', $desig)); ?>" style="display:inline">
            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
            <button type="button" class="btn sm" style="color:var(--red)"
              onclick="swConfirm(this.closest('form'), 'Delete Designation?',
                'Delete <?php echo e(addslashes($desig->name)); ?>? This cannot be undone.',
                'warning', 'Yes, delete', '#dc2626')">Del</button>
          </form>
        </td>
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
        <div class="fld full">
          <label>Company *</label>
          <select name="company_id" required>
            <option value="">Select Company</option>
            <?php $__currentLoopData = $companies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $co): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($co->id); ?>" <?php echo e(old('company_id', $companyId) == $co->id ? 'selected' : ''); ?>><?php echo e($co->name); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
        </div>
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


<div class="modal" id="desig-edit-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Edit Designation</span>
      <button class="mc" onclick="cm('desig-edit-modal')">✕</button>
    </div>
    <form method="POST" id="desig-edit-form">
      <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
      <div class="fg2">
        <div class="fld">
          <label>Name *</label>
          <input type="text" name="name" id="desig-e-name" required>
        </div>
        <div class="fld">
          <label>Code *</label>
          <input type="text" name="code" id="desig-e-code" required maxlength="10">
        </div>
        <div class="fld full">
          <label>Level (optional)</label>
          <input type="number" name="level" id="desig-e-level" min="1" placeholder="1 = intern, 10 = CEO">
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('desig-edit-modal')">Cancel</button>
        <button type="submit" class="btn acc">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditDesig(d) {
  document.getElementById('desig-edit-form').action = '<?php echo e(url('/designations')); ?>/' + d.id;
  document.getElementById('desig-e-name').value = d.name;
  document.getElementById('desig-e-code').value = d.code;
  document.getElementById('desig-e-level').value = d.level || '';
  om('desig-edit-modal');
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/masters/designations.blade.php ENDPATH**/ ?>