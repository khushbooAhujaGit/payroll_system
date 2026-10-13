<?php $__env->startSection('title', 'Departments'); ?>

<?php $__env->startSection('page_actions'); ?>
  <button class="btn acc" onclick="om('dept-modal')">+ Add Department</button>
  <button class="btn" onclick="om('upload-modal')">📤 Bulk Upload</button>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<div class="card">
  <div class="ch">
    <div class="ct">Departments</div>
    <div style="display:flex;align-items:center;gap:10px">
      <button type="button" class="btn sm" id="dept-bulk-del-btn" style="display:none;color:var(--red)" onclick="bulkDelete('dept-bulk-form', 'dept-chk', 'Departments')">Delete Selected (<span id="dept-sel-count">0</span>)</button>
      <span style="font-size:11px;color:var(--t3)"><?php echo e($departments->count()); ?> total</span>
    </div>
  </div>
  <?php if($departments->isNotEmpty()): ?>
  <form id="dept-bulk-form" method="POST" action="<?php echo e(route('departments.bulk-delete')); ?>"><?php echo csrf_field(); ?></form>
  <table>
    <thead><tr>
      <th style="width:30px"><input type="checkbox" id="dept-chk-all" onclick="toggleAll(this, 'dept-chk', 'dept-sel-count', 'dept-bulk-del-btn')"></th>
      <th>Code</th><th>Name</th><th>Status</th><th></th>
    </tr></thead>
    <tbody>
      <?php $__currentLoopData = $departments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dept): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <tr>
        <td><input type="checkbox" class="dept-chk" value="<?php echo e($dept->id); ?>" onclick="onRowCheck('dept-chk', 'dept-chk-all', 'dept-sel-count', 'dept-bulk-del-btn')"></td>
        <td class="mono"><?php echo e($dept->code); ?></td>
        <td><?php echo e($dept->name); ?></td>
        <td><span class="bx <?php echo e($dept->is_active ? 'g' : 'gr'); ?>"><?php echo e($dept->is_active ? 'Active' : 'Inactive'); ?></span></td>
        <td>
          <button type="button" class="btn sm" onclick='openEditDept(<?php echo json_encode($dept, 15, 512) ?>)'>Edit</button>
          <form method="POST" action="<?php echo e(route('departments.destroy', $dept)); ?>" style="display:inline">
            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
            <button type="button" class="btn sm" style="color:var(--red)"
              onclick="swConfirm(this.closest('form'), 'Delete Department?',
                'Delete <?php echo e(addslashes($dept->name)); ?>? This cannot be undone.',
                'warning', 'Yes, delete', '#dc2626')">Del</button>
          </form>
        </td>
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
          <span style="font-size:10px;color:var(--t3)">Only shows departments for the company this page is currently viewing — if you pick a different Company above, set Parent Department from that company's own Departments page instead.</span>
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('dept-modal')">Cancel</button>
        <button type="submit" class="btn acc">Create</button>
      </div>
    </form>
  </div>
</div>


<div class="modal" id="dept-edit-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Edit Department</span>
      <button class="mc" onclick="cm('dept-edit-modal')">✕</button>
    </div>
    <form method="POST" id="dept-edit-form">
      <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
      <div class="fg2">
        <div class="fld">
          <label>Name *</label>
          <input type="text" name="name" id="dept-e-name" required>
        </div>
        <div class="fld">
          <label>Code *</label>
          <input type="text" name="code" id="dept-e-code" required maxlength="10">
        </div>
        <div class="fld full">
          <label>Parent Department</label>
          <select name="parent_id" id="dept-e-parent">
            <option value="">None (top-level)</option>
            <?php $__currentLoopData = $departments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($d->id); ?>"><?php echo e($d->name); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('dept-edit-modal')">Cancel</button>
        <button type="submit" class="btn acc">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditDept(d) {
  document.getElementById('dept-edit-form').action = '<?php echo e(url('/departments')); ?>/' + d.id;
  document.getElementById('dept-e-name').value = d.name;
  document.getElementById('dept-e-code').value = d.code;
  document.getElementById('dept-e-parent').value = d.parent_id || '';
  om('dept-edit-modal');
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('partials.upload-modal', [
    'uploadId'      => 'upload-modal',
    'uploadTitle'   => 'Bulk Upload Departments & Designations',
    'uploadRoute'   => 'departments.import',
    'templateRoute' => 'departments.template',
    'uploadHint'    => 'Sheet 1 — Departments: Name, Code, Parent Department Code (optional). Sheet 2 — Designations: Name, Code, Level (optional). One row per entry.',
], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/masters/departments.blade.php ENDPATH**/ ?>