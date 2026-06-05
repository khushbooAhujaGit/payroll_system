<?php $__env->startSection('title', 'Bio IDs'); ?>

<?php $__env->startSection('page_actions'); ?>
  <a href="<?php echo e(route('bio_ids.template')); ?>" class="btn">📥 Download Template</a>
  <button class="btn" onclick="om('bioid-add-modal')">+ Add Bio ID</button>
  <button class="btn acc" onclick="om('bioid-import-modal')">📤 Import Bio IDs</button>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<div class="g4" style="margin-bottom:16px">
  <div class="sc">
    <div class="lbl">Total Bio IDs</div>
    <div class="val"><?php echo e($totalBioIds); ?></div>
  </div>
  <div class="sc">
    <div class="lbl">Machines</div>
    <div class="val"><?php echo e($machines->count()); ?></div>
    <div style="font-size:10px;color:var(--t3);margin-top:2px"><?php echo e($machines->implode(', ')); ?></div>
  </div>
</div>


<form method="GET" action="<?php echo e(route('bio_ids')); ?>" class="card"
      style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
  <div class="fld" style="min-width:220px">
    <label>Search (name / emp ID / bio ID)</label>
    <input type="text" name="search" value="<?php echo e(request('search')); ?>" placeholder="e.g. I-20 or Parth">
  </div>
  <div class="fld" style="min-width:130px">
    <label>Machine</label>
    <select name="machine">
      <option value="">All Machines</option>
      <?php $__currentLoopData = $machines; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <option value="<?php echo e($m); ?>" <?php echo e(request('machine') === $m ? 'selected' : ''); ?>>Machine <?php echo e($m); ?></option>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select>
  </div>
  <button type="submit" class="btn acc">Filter</button>
  <a href="<?php echo e(route('bio_ids')); ?>" class="btn">Reset</a>
</form>

<div class="card">
  <?php if($bioIds->isNotEmpty()): ?>
  <table>
    <thead>
      <tr>
        <th>Bio ID</th>
        <th>Type</th>
        <th>Number</th>
        <th>Machine</th>
        <th>Employee</th>
        <th>Emp Code</th>
        <th>Status</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php $__currentLoopData = $bioIds; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bio): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <tr>
        <td>
          <span class="bx mono" style="background:var(--acc2);color:var(--acc);font-weight:700">
            <?php echo e($bio->bio_id_full); ?>

          </span>
        </td>
        <td class="mono"><?php echo e($bio->bio_type); ?></td>
        <td class="mono"><?php echo e($bio->bio_number); ?></td>
        <td>
          <span class="bx" style="font-size:11px">Machine <?php echo e($bio->machine_name); ?></span>
        </td>
        <td>
          <?php if($bio->employee): ?>
            <?php echo e($bio->employee->full_name); ?>

          <?php else: ?>
            <span style="color:var(--t3)">— unlinked —</span>
          <?php endif; ?>
        </td>
        <td class="mono" style="font-size:12px;color:var(--t3)">
          <?php echo e($bio->employee?->employee_id ?? '—'); ?>

        </td>
        <td>
          <?php if($bio->is_active): ?>
            <span class="bx" style="color:var(--grn)">Active</span>
          <?php else: ?>
            <span class="bx" style="color:var(--t3)">Inactive</span>
          <?php endif; ?>
        </td>
        <td style="white-space:nowrap">
          <button class="btn sm" style="font-size:10px"
            onclick="openBioEdit(<?php echo e($bio->id); ?>, '<?php echo e($bio->bio_id_full); ?>', <?php echo e($bio->is_active ? 'true' : 'false'); ?>)">
            Edit
          </button>
          <form method="POST" action="<?php echo e(route('bio_ids.destroy', $bio)); ?>" style="display:inline">
            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
            <button type="button" class="btn sm" style="font-size:10px;color:var(--red)"
              onclick="swConfirm(this.closest('form'), 'Delete Bio ID?', 'Bio ID <?php echo e($bio->bio_id_full); ?> will be permanently removed.', 'warning', 'Yes, delete', '#dc2626')">Del</button>
          </form>
        </td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
  </table>
  <div style="margin-top:14px"><?php echo e($bioIds->links()); ?></div>
  <?php else: ?>
  <div class="empty">
    <div class="ico">🔖</div>
    <p>No Bio IDs registered yet.</p>
    <p style="font-size:12px;color:var(--t3);margin-top:4px">
      Download the template, fill it in, and import using the button above.
    </p>
  </div>
  <?php endif; ?>
</div>


<div class="modal" id="bioid-add-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Add Bio ID</span>
      <button class="mc" onclick="cm('bioid-add-modal')">✕</button>
    </div>
    <form method="POST" action="<?php echo e(route('bio_ids.store')); ?>">
      <?php echo csrf_field(); ?>
      <div class="fg2">
        <div class="fld full">
          <label>Employee *</label>
          <select name="employee_id" required>
            <option value="">Select employee</option>
            <?php $__currentLoopData = $employees; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $emp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($emp->id); ?>"><?php echo e($emp->employee_id); ?> — <?php echo e($emp->full_name); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
        </div>
        <div class="fld">
          <label>Bio ID (format: I-20, II-5, K-3) *</label>
          <input type="text" name="bio_id_full" required placeholder="e.g. I-20">
        </div>
        <div class="fld">
          <label>Machine Name *</label>
          <input type="text" name="machine_name" required placeholder="e.g. 10, 12, K">
        </div>
        <div class="fld full">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="is_active" value="1" checked style="width:16px;height:16px">
            Active
          </label>
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('bioid-add-modal')">Cancel</button>
        <button type="submit" class="btn acc">Add Bio ID</button>
      </div>
    </form>
  </div>
</div>


<div class="modal" id="bioid-edit-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Edit Bio ID</span>
      <button class="mc" onclick="cm('bioid-edit-modal')">✕</button>
    </div>
    <form method="POST" id="bioid-edit-form">
      <?php echo csrf_field(); ?>
      <div class="fg2">
        <div class="fld full">
          <label>Bio ID (format: I-20, II-5, III-3) *</label>
          <input type="text" name="bio_id_full" id="bioid-edit-val" required placeholder="e.g. I-20">
        </div>
        <div class="fld full">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="is_active" value="1" id="bioid-edit-active" style="width:16px;height:16px">
            Active
          </label>
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('bioid-edit-modal')">Cancel</button>
        <button type="submit" class="btn acc">Save</button>
      </div>
    </form>
  </div>
</div>


<div class="modal" id="bioid-import-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Import Bio ID Mappings</span>
      <button class="mc" onclick="cm('bioid-import-modal')">✕</button>
    </div>
    <div style="background:var(--acc2);border:1px solid var(--acc);border-radius:8px;
                padding:12px 14px;margin-bottom:16px;font-size:12px;line-height:1.6;color:var(--t2)">
      <strong>Template columns:</strong>
      <span class="mono" style="font-size:11px">Employee Name · New Emp ID · BioID</span><br>
      BioID can contain multiple comma-separated values: <span class="mono">I-20,II-6,III-8</span>
    </div>
    <form method="POST" action="<?php echo e(route('bio_ids.import')); ?>" enctype="multipart/form-data">
      <?php echo csrf_field(); ?>
      <div class="fld" style="margin-bottom:14px">
        <label>Bio ID Mapping File (.xlsx / .xls) *</label>
        <input type="file" name="file" accept=".xlsx,.xls" required
          style="background:var(--s2);border:1.5px dashed var(--b2);border-radius:8px;padding:14px;cursor:pointer;width:100%">
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('bioid-import-modal')">Cancel</button>
        <button type="submit" class="btn acc">📤 Import</button>
      </div>
    </form>
  </div>
</div>

<?php $__env->startPush('scripts'); ?>
<script>
function openBioEdit(id, bioId, isActive) {
  document.getElementById('bioid-edit-val').value        = bioId;
  document.getElementById('bioid-edit-active').checked  = isActive;
  document.getElementById('bioid-edit-form').action      = '/bio-ids/' + id;
  om('bioid-edit-modal');
}
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/bio-ids/index.blade.php ENDPATH**/ ?>