<?php $__env->startSection('title', 'Locations'); ?>

<?php $__env->startSection('page_actions'); ?>
  <button class="btn acc" onclick="om('location-modal')">+ Add Location</button>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<div class="card">
  <div class="ch">
    <div class="ct">Locations</div>
    <div style="display:flex;align-items:center;gap:10px">
      <button type="button" class="btn sm" id="location-bulk-del-btn" style="display:none;color:var(--red)" onclick="bulkDelete('location-bulk-form', 'location-chk', 'Locations')">Delete Selected (<span id="location-sel-count">0</span>)</button>
      <span style="font-size:11px;color:var(--t3)"><?php echo e($locations->count()); ?> total</span>
    </div>
  </div>
  <?php if($locations->isNotEmpty()): ?>
  <form id="location-bulk-form" method="POST" action="<?php echo e(route('locations.bulk-delete')); ?>"><?php echo csrf_field(); ?></form>
  <table>
    <thead><tr>
      <th style="width:30px"><input type="checkbox" id="location-chk-all" onclick="toggleAll(this, 'location-chk', 'location-sel-count', 'location-bulk-del-btn')"></th>
      <th>Name</th><th>Status</th><th></th>
    </tr></thead>
    <tbody>
      <?php $__currentLoopData = $locations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $location): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <tr>
        <td><input type="checkbox" class="location-chk" value="<?php echo e($location->id); ?>" onclick="onRowCheck('location-chk', 'location-chk-all', 'location-sel-count', 'location-bulk-del-btn')"></td>
        <td><?php echo e($location->name); ?></td>
        <td><span class="bx <?php echo e($location->is_active ? 'g' : 'gr'); ?>"><?php echo e($location->is_active ? 'Active' : 'Inactive'); ?></span></td>
        <td>
          <button type="button" class="btn sm" onclick='openEditLocation(<?php echo json_encode($location, 15, 512) ?>)'>Edit</button>
          <form method="POST" action="<?php echo e(route('locations.destroy', $location)); ?>" style="display:inline">
            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
            <button type="button" class="btn sm" style="color:var(--red)"
              onclick="swConfirm(this.closest('form'), 'Delete Location?',
                'Delete <?php echo e(addslashes($location->name)); ?>? This cannot be undone.',
                'warning', 'Yes, delete', '#dc2626')">Del</button>
          </form>
        </td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
  </table>
  <?php else: ?>
  <div class="empty"><div class="ico">📍</div><p>No locations yet.</p></div>
  <?php endif; ?>
</div>


<div class="modal" id="location-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Add Location</span>
      <button class="mc" onclick="cm('location-modal')">✕</button>
    </div>
    <form method="POST" action="<?php echo e(route('masters.locations.store')); ?>">
      <?php echo csrf_field(); ?>
      <div class="fg2">
        <div class="fld full">
          <label>Name *</label>
          <select name="name" required>
            <option value="">Select City</option>
            <?php $__currentLoopData = \App\Models\Location::CITIES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $city): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($city); ?>" <?php echo e(old('name') === $city ? 'selected' : ''); ?>><?php echo e($city); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
        </div>
        <div class="fld full">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="is_active" value="1" <?php echo e(old('is_active', '1') ? 'checked' : ''); ?> style="width:16px;height:16px">
            Active
          </label>
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('location-modal')">Cancel</button>
        <button type="submit" class="btn acc">Create</button>
      </div>
    </form>
  </div>
</div>


<div class="modal" id="location-edit-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Edit Location</span>
      <button class="mc" onclick="cm('location-edit-modal')">✕</button>
    </div>
    <form method="POST" id="location-edit-form">
      <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
      <div class="fg2">
        <div class="fld full">
          <label>Name *</label>
          <select name="name" id="location-e-name" required>
            <option value="">Select City</option>
            <?php $__currentLoopData = \App\Models\Location::CITIES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $city): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($city); ?>"><?php echo e($city); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
        </div>
        <div class="fld full">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="is_active" id="location-e-active" value="1" style="width:16px;height:16px">
            Active
          </label>
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('location-edit-modal')">Cancel</button>
        <button type="submit" class="btn acc">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditLocation(l) {
  document.getElementById('location-edit-form').action = '<?php echo e(url('/locations')); ?>/' + l.id;
  const nameSel = document.getElementById('location-e-name');
  nameSel.value = l.name;
  window._ssSync?.(nameSel); // sync the searchable-select display — see layouts/app.blade.php
  document.getElementById('location-e-active').checked = !!l.is_active;
  om('location-edit-modal');
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/masters/locations.blade.php ENDPATH**/ ?>