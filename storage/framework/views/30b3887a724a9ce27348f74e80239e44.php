<?php $__env->startSection('title', 'User Creation'); ?>

<?php $__env->startSection('page_actions'); ?>
  <button class="btn acc" onclick="om('user-modal')">+ Add User</button>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

  <form method="GET" action="<?php echo e(route('users.index')); ?>" class="card"
    style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
    <div class="fld" style="flex:1;min-width:220px">
      <label>Search</label>
      <input type="text" name="search" value="<?php echo e(request('search')); ?>" placeholder="Name, email, or employee code…">
    </div>
    <div class="fld" style="min-width:160px">
      <label>Status</label>
      <select name="status">
        <option value="">All</option>
        <option value="active" <?php echo e(request('status') === 'active' ? 'selected' : ''); ?>>Active</option>
        <option value="inactive" <?php echo e(request('status') === 'inactive' ? 'selected' : ''); ?>>Inactive</option>
      </select>
    </div>
    <button type="submit" class="btn acc">Filter</button>
    <a href="<?php echo e(route('users.index')); ?>" class="btn">Reset</a>
  </form>

    <div class="card" style="margin-bottom:14px">
      <div class="ch"><div class="ct">Users</div></div>
      <?php if($users->isEmpty()): ?>
        <div class="empty">
          <div class="ico">👤</div>
          <?php if(request('search')): ?>
            <p>No users match "<?php echo e(request('search')); ?>".</p>
          <?php else: ?>
            <p>No users yet. <a href="#" onclick="om('user-modal')">Create one to get started.</a></p>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Employee</th>
              <th>Roles</th>
              <th>Status</th>
              <th>Reimbursement Approver</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <tr>
                <td style="font-weight:600"><?php echo e($user->name); ?></td>
                <td style="font-size:12px"><?php echo e($user->email); ?></td>
                <td style="font-size:12px;color:var(--t3)"><?php echo e($user->employee?->full_name ?? '—'); ?></td>
                <td style="font-size:12px">
                  <?php $__empty_1 = true; $__currentLoopData = $user->roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <span style="display:inline-block;background:var(--bg2,#eef1f5);border-radius:12px;padding:2px 8px;font-size:11px;margin:2px"><?php echo e($role->name); ?></span>
                  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <span style="color:var(--t3)">No roles assigned</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if($user->is_active): ?>
                    <span style="display:inline-block;background:#dcfce7;color:#166534;border-radius:12px;padding:2px 10px;font-size:11px;font-weight:600">Active</span>
                  <?php else: ?>
                    <span style="display:inline-block;background:#fee2e2;color:#991b1b;border-radius:12px;padding:2px 10px;font-size:11px;font-weight:600">Inactive</span>
                  <?php endif; ?>
                </td>
                <td>
                  <form method="POST" action="<?php echo e(route('users.toggle-reimbursement-approver', $user)); ?>" style="display:inline">
                    <?php echo csrf_field(); ?>
                    <?php if($user->hasPermissionTo('reimbursment_approve')): ?>
                      <button type="button" class="btn sm"
                        onclick="swConfirm(this.closest('form'), 'Remove approver?', 'Reimbursement claims will no longer route to <?php echo e(addslashes($user->name)); ?>.', 'warning', 'Yes, remove', '#dc2626')">
                        <span style="display:inline-block;background:#dcfce7;color:#166534;border-radius:12px;padding:2px 10px;font-size:11px;font-weight:600">Approver</span>
                      </button>
                    <?php else: ?>
                      <button type="button" class="btn sm"
                        onclick="swConfirm(this.closest('form'), 'Make this the approver?', 'All reimbursement claims will route to <?php echo e(addslashes($user->name)); ?> for approval, regardless of role.', 'question', 'Yes, set as approver', '#2563eb')">
                        Set as Approver
                      </button>
                    <?php endif; ?>
                  </form>
                </td>
                <td style="display:flex;gap:6px">
                  <button type="button" class="btn sm" onclick="om('edit-user-modal-<?php echo e($user->id); ?>')">Edit</button>
                  <?php if($user->id !== auth()->id()): ?>
                    <form method="POST" action="<?php echo e(route('users.toggle-active', $user)); ?>" style="display:inline">
                      <?php echo csrf_field(); ?>
                      <button type="button" class="btn sm"
                        onclick="swConfirm(this.closest('form'), '<?php echo e($user->is_active ? 'Deactivate' : 'Activate'); ?> User?', '<?php echo e($user->is_active ? 'Deactivate' : 'Activate'); ?> <?php echo e(addslashes($user->name)); ?>?<?php echo e($user->is_active ? ' They will immediately lose access.' : ''); ?>', 'warning', 'Yes, <?php echo e($user->is_active ? 'deactivate' : 'activate'); ?>', '<?php echo e($user->is_active ? '#dc2626' : '#16a34a'); ?>')"><?php echo e($user->is_active ? 'Deactivate' : 'Activate'); ?></button>
                    </form>
                    <form method="POST" action="<?php echo e(route('users.destroy', $user)); ?>" style="display:inline">
                      <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                      <button type="button" class="btn sm" style="color:var(--red)"
                        onclick="swConfirm(this.closest('form'), 'Delete User?', 'Delete <?php echo e(addslashes($user->name)); ?>? They will immediately lose access.', 'warning', 'Yes, delete', '#dc2626')">Del</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </tbody>
        </table>
        <div style="margin-top:14px"><?php echo e($users->links()); ?></div>
      <?php endif; ?>
    </div>


<div class="modal" id="user-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Add User</span>
      <button class="mc" onclick="cm('user-modal')">✕</button>
    </div>
    <form method="POST" action="<?php echo e(route('users.store')); ?>">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="_form" value="user">
      <div class="fld" style="margin-bottom:16px">
        <label class="req">Name</label>
        <input type="text" name="name" required maxlength="150"
          value="<?php echo e(old('_form') === 'user' ? old('name') : ''); ?>" <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
        <?php if(old('_form') === 'user'): ?> <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> <?php endif; ?>
      </div>
      <div class="fld" style="margin-bottom:16px">
        <label class="req">Company</label>
        <select name="company_id" required <?php $__errorArgs = ['company_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
          <option value="">Select company…</option>
          <?php $__currentLoopData = $companies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $company): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($company->id); ?>" <?php echo e(old('company_id') == $company->id ? 'selected' : ''); ?>><?php echo e($company->name); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
        <?php if(old('_form') === 'user'): ?> <?php $__errorArgs = ['company_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> <?php endif; ?>
        <div style="font-size:11px;color:var(--t3);margin-top:2px">A new employee record is created for them automatically.</div>
      </div>
      <div class="fld" style="margin-bottom:16px">
        <label class="req">Email</label>
        <input type="email" name="email" required maxlength="150"
          value="<?php echo e(old('_form') === 'user' ? old('email') : ''); ?>" <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
        <?php if(old('_form') === 'user'): ?> <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> <?php endif; ?>
      </div>
      <div class="fld" style="margin-bottom:16px">
        <label class="req">Password</label>
        <input type="password" name="password" required minlength="8" autocomplete="new-password"
          <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
        <?php if(old('_form') === 'user'): ?> <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> <?php endif; ?>
      </div>
      <div class="fld" style="margin-bottom:16px">
        <label class="req">Confirm Password</label>
        <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
      </div>
      
      <div class="fld">
        <label>Role</label>
        <?php if($roles->isEmpty()): ?>
          <span style="color:var(--t3);font-size:12px">No roles exist yet — create one on the Role &amp; Permissions page first.</span>
        <?php else: ?>
          <select name="roles[]">
            <option value="">— No role —</option>
            <?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($role->id); ?>" <?php echo e(collect(old('roles'))->contains($role->id) ? 'selected' : ''); ?>>
                <?php echo e($role->name); ?><?php echo e($role->name === 'Admin' ? ' (all access)' : ''); ?>

              </option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
        <?php endif; ?>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('user-modal')">Cancel</button>
        <button type="submit" class="btn acc">Create User</button>
      </div>
    </form>
  </div>
</div>


<?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <?php
    $editErr = old('_form') === 'edit_user' && (int) old('_user_id') === $user->id;
    // concat() (not push()) so we don't mutate $unlinkedEmployees, which is
    // shared across every iteration of this @foreach.
    $employeeOptions = ($user->employee ? $unlinkedEmployees->concat([$user->employee]) : $unlinkedEmployees)->sortBy('first_name');
  ?>
  <div class="modal" id="edit-user-modal-<?php echo e($user->id); ?>">
    <div class="mb">
      <div class="mh">
        <span class="mt">Edit User</span>
        <button class="mc" onclick="cm('edit-user-modal-<?php echo e($user->id); ?>')">✕</button>
      </div>
      <form method="POST" action="<?php echo e(route('users.update', $user)); ?>">
        <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
        <input type="hidden" name="_form" value="edit_user">
        <input type="hidden" name="_user_id" value="<?php echo e($user->id); ?>">
        <div class="fld" style="margin-bottom:16px">
          <label class="req">Name</label>
          <input type="text" name="name" required maxlength="150"
            value="<?php echo e($editErr ? old('name') : $user->name); ?>" <?php if($editErr): ?> <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> <?php endif; ?>>
          <?php if($editErr): ?> <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> <?php endif; ?>
        </div>
        <div class="fld" style="margin-bottom:16px">
          <label class="req">Email</label>
          <input type="email" name="email" required maxlength="150"
            value="<?php echo e($editErr ? old('email') : $user->email); ?>" <?php if($editErr): ?> <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> <?php endif; ?>>
          <?php if($editErr): ?> <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> <?php endif; ?>
        </div>
        <div class="fld" style="margin-bottom:16px">
          <label>New Password <span style="font-weight:400;color:var(--t3)">(leave blank to keep current)</span></label>
          <input type="password" name="password" minlength="8" autocomplete="new-password"
            <?php if($editErr): ?> <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> <?php endif; ?>>
          <?php if($editErr): ?> <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> <?php endif; ?>
        </div>
        <div class="fld" style="margin-bottom:16px">
          <label>Confirm New Password</label>
          <input type="password" name="password_confirmation" minlength="8" autocomplete="new-password">
        </div>
        
        <div class="fld">
          <label>Role</label>
          <?php if($roles->isEmpty()): ?>
            <span style="color:var(--t3);font-size:12px">No roles exist yet — create one on the Role &amp; Permissions page first.</span>
          <?php else: ?>
            <select name="roles[]">
              <option value="">— No role —</option>
              <?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                  $isSelected = $editErr ? collect(old('roles'))->contains($role->id) : $user->roles->contains('id', $role->id);
                ?>
                <option value="<?php echo e($role->id); ?>" <?php echo e($isSelected ? 'selected' : ''); ?>>
                  <?php echo e($role->name); ?><?php echo e($role->name === 'Admin' ? ' (all access)' : ''); ?>

                </option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
          <?php endif; ?>
        </div>
        <div class="mf">
          <button type="button" class="btn" onclick="cm('edit-user-modal-<?php echo e($user->id); ?>')">Cancel</button>
          <button type="submit" class="btn acc">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

<?php if($errors->any()): ?>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      <?php if(old('_form') === 'user'): ?>
        om('user-modal');
      <?php elseif(old('_form') === 'edit_user'): ?>
        om('edit-user-modal-<?php echo e(old('_user_id')); ?>');
      <?php endif; ?>
    });
  </script>
<?php endif; ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/users/index.blade.php ENDPATH**/ ?>