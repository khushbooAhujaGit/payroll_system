<?php $__env->startSection('title', 'Role & Permissions'); ?>

<?php $__env->startSection('page_actions'); ?>
  <button class="btn acc" onclick="om('role-modal')">+ Add Role</button>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

  <?php echo $__env->make('rbac._nav', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <div class="card" style="margin-bottom:14px">
      <div class="ch"><div class="ct">Roles</div></div>
      <table>
        <thead>
          <tr>
            <th>Role</th>
            <th>Permissions</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
              <td style="font-weight:600"><?php echo e($role->name); ?><?php if($role->name === 'Admin'): ?> <span style="opacity:.6;font-weight:400">(all access)</span><?php endif; ?></td>
              <td style="font-size:12px;color:var(--t3)"><?php echo e($role->name === 'Admin' ? '—' : $role->permissions->count()); ?></td>
              <td>
                <?php if($role->name !== 'Admin'): ?>
                  <form method="POST" action="<?php echo e(route('rbac.roles.destroy', $role)); ?>" style="display:inline">
                    <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                    <button type="button" class="btn sm" style="color:var(--red)"
                      onclick="swConfirm(this.closest('form'), 'Delete Role?', 'Delete <?php echo e(addslashes($role->name)); ?>? This removes it from any user who has it.', 'warning', 'Yes, delete', '#dc2626')">Del</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
      </table>
    </div>

    <div class="card" style="margin-bottom:14px">
      <div class="ch"><div class="ct">Select a role to view / edit its permissions</div></div>
      <div style="padding:12px 16px;display:flex;gap:8px;flex-wrap:wrap">
        <?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <button type="button" class="btn sm role-select-btn <?php echo e($loop->first ? 'on' : ''); ?>"
            data-role-id="<?php echo e($role->id); ?>" onclick="selectRole(<?php echo e($role->id); ?>)">
            <?php echo e($role->name); ?>

            <?php if($role->name === 'Admin'): ?><span style="opacity:.6">(all access)</span><?php endif; ?>
          </button>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    </div>

    <?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <div class="card role-matrix" id="role-matrix-<?php echo e($role->id); ?>" style="<?php echo e($loop->first ? '' : 'display:none'); ?>">
        <div class="ch">
          <div class="ct"><?php echo e($role->name); ?> — Permissions</div>
          <?php if($role->name === 'Admin'): ?>
            <div style="font-size:12px;color:var(--t3)">Admin always has full access regardless of these checkboxes — saving here just keeps the permission records in sync for reference.</div>
          <?php endif; ?>
        </div>

        <form method="POST" action="<?php echo e(route('rbac.roles.permissions', $role)); ?>">
          <?php echo csrf_field(); ?>

          <div style="max-height:600px;overflow-y:auto" id="role-matrix-body-<?php echo e($role->id); ?>">
            <table>
              <thead>
                <tr>
                  <th style="width:48px">Sno</th>
                  <th style="width:180px">Permission Group</th>
                  <th style="text-align:center;width:70px">All</th>
                  <th>Permissions</th>
                </tr>
              </thead>
              <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $permissionGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                  <?php
                    $groupPermIds  = $group->permissions->pluck('id');
                    $allChecked    = $role->name === 'Admin'
                        || ($groupPermIds->isNotEmpty() && $groupPermIds->every(fn($id) => $role->permissions->contains('id', $id)));
                    $rowId         = 'role-'.$role->id.'-group-'.$group->id;
                  ?>
                  <tr>
                    <td style="color:var(--t3);font-size:12px"><?php echo e($loop->iteration); ?></td>
                    <td style="font-weight:600"><?php echo e($group->name); ?></td>
                    <td style="text-align:center">
                      <input type="checkbox" id="<?php echo e($rowId); ?>-all"
                        <?php echo e($allChecked ? 'checked' : ''); ?>

                        onchange="toggleAllIn('<?php echo e($rowId); ?>', this.checked)">
                    </td>
                    <td id="<?php echo e($rowId); ?>">
                      <?php $__empty_2 = true; $__currentLoopData = $group->permissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $perm): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?>
                        <label style="display:inline-flex;align-items:center;gap:6px;font-weight:400;white-space:nowrap;margin:3px 18px 3px 0">
                          <input type="checkbox"
                            name="permissions[]"
                            value="<?php echo e($perm->id); ?>"
                            <?php echo e(($role->name === 'Admin' || $role->permissions->contains('id', $perm->id)) ? 'checked' : ''); ?>

                            onchange="syncGroupAll('<?php echo e($rowId); ?>')">
                          <?php echo e(Str::of($perm->name)->replace('_', ' ')->title()); ?>

                        </label>
                      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?>
                        <span style="color:var(--t3);font-size:12px">No permissions in this group yet.</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                  <tr><td colspan="4" style="color:var(--t3);font-size:12px;padding:16px">No permission groups yet — create one first.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <div class="mf" style="padding:12px 16px">
            <button type="submit" class="btn acc">Save <?php echo e($role->name); ?> Permissions</button>
          </div>
        </form>
      </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>


<div class="modal" id="role-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Add Role</span>
      <button class="mc" onclick="cm('role-modal')">✕</button>
    </div>
    <form method="POST" action="<?php echo e(route('rbac.roles.store')); ?>">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="_form" value="role">
      <div class="fld">
        <label class="req">Role Name</label>
        <input type="text" name="name" required value="<?php echo e(old('name')); ?>" <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
        <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('role-modal')">Cancel</button>
        <button type="submit" class="btn acc">Create Role</button>
      </div>
    </form>
  </div>
</div>

<?php if($errors->any() && old('_form') === 'role'): ?>
  <script>
    document.addEventListener('DOMContentLoaded', () => om('role-modal'));
  </script>
<?php endif; ?>

<script>
  function selectRole(id) {
    document.querySelectorAll('.role-matrix').forEach(el => el.style.display = 'none');
    document.getElementById('role-matrix-' + id).style.display = '';
    document.querySelectorAll('.role-select-btn').forEach(el => el.classList.toggle('on', String(el.dataset.roleId) === String(id)));
  }
  function toggleAllIn(containerId, checked) {
    document.querySelectorAll('#' + containerId + ' input[type=checkbox]:not(:disabled)').forEach(cb => cb.checked = checked);
  }
  function syncGroupAll(rowId) {
    const boxes = document.querySelectorAll('#' + rowId + ' input[type=checkbox]');
    const allBox = document.getElementById(rowId + '-all');
    if (!allBox || !boxes.length) return;
    allBox.checked = Array.from(boxes).every(cb => cb.checked);
  }
</script>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/rbac/roles.blade.php ENDPATH**/ ?>