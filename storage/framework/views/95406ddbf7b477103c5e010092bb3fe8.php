<?php $__env->startSection('title', 'Companies'); ?>

<?php $__env->startSection('page_actions'); ?>
  <button class="btn acc" onclick="om('company-modal')">+ Add Company</button>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

  <div class="card">
    <div class="ch"><div class="ct">Companies</div></div>
    <?php if($companies->isNotEmpty()): ?>
    <table>
      <thead>
        <tr>
          <th>Company Name</th>
          <th>Short Name</th>
          <th>PAN</th>
          <th>TAN</th>
          <th>GSTIN</th>
          <th>CIN</th>
          <th>Email</th>
          <th>Phone</th>
          <th>State / City</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php $__currentLoopData = $companies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $co): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
          <td style="font-weight:600"><?php echo e($co->name); ?></td>
          <td class="mono"><?php echo e($co->short_name ?? '—'); ?></td>
          <td class="mono" style="font-size:11px"><?php echo e($co->pan ?? '—'); ?></td>
          <td class="mono" style="font-size:11px"><?php echo e($co->tan ?? '—'); ?></td>
          <td class="mono" style="font-size:11px"><?php echo e($co->gstin ?? '—'); ?></td>
          <td class="mono" style="font-size:11px"><?php echo e($co->cin ?? '—'); ?></td>
          <td style="font-size:12px"><?php echo e($co->email ?? '—'); ?></td>
          <td style="font-size:12px"><?php echo e($co->phone ?? '—'); ?></td>
          <td style="font-size:12px;color:var(--t3)">
            <?php echo e(implode(', ', array_filter([$co->state, $co->city])) ?: '—'); ?>

          </td>
          <td>
            <form method="POST" action="<?php echo e(route('masters.company.destroy', $co)); ?>" style="display:inline">
              <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
              <button type="button" class="btn sm" style="color:var(--red)"
                onclick="swConfirm(this.closest('form'), 'Delete Company?',
                  'Delete <?php echo e(addslashes($co->name)); ?>? This cannot be undone.',
                  'warning', 'Yes, delete', '#dc2626')">Del</button>
            </form>
          </td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="empty">
      <div class="ico">🏢</div>
      <p>No companies yet. <a href="#" onclick="om('company-modal')">Add one now.</a></p>
    </div>
    <?php endif; ?>
  </div>


<div class="modal" id="company-modal">
  <div class="mb wide">
    <div class="mh">
      <span class="mt">Add Company</span>
      <button class="mc" onclick="cm('company-modal')">✕</button>
    </div>
    <form method="POST" action="<?php echo e(route('masters.company')); ?>">
      <?php echo csrf_field(); ?>
      <div style="margin:14px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
        Basic Info
      </div>
      <div class="fg2">
        <div class="fld">
          <label class="req">Company Name</label>
          <input type="text" name="name" required value="<?php echo e(old('name')); ?>"
            <?php $__errorArgs = ['name'];
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
        <div class="fld">
          <label>Short Name</label>
          <input type="text" name="short_name" maxlength="50" value="<?php echo e(old('short_name')); ?>">
        </div>
        <div class="fld">
          <label>PAN</label>
          <input type="text" name="pan" maxlength="10" value="<?php echo e(old('pan')); ?>"
            placeholder="ABCDE1234F" style="text-transform:uppercase"
            oninput="this.value=this.value.toUpperCase()">
        </div>
        <div class="fld">
          <label>TAN</label>
          <input type="text" name="tan" maxlength="10" value="<?php echo e(old('tan')); ?>"
            style="text-transform:uppercase" oninput="this.value=this.value.toUpperCase()">
        </div>
        <div class="fld">
          <label>GSTIN</label>
          <input type="text" name="gstin" maxlength="15" value="<?php echo e(old('gstin')); ?>"
            style="text-transform:uppercase" oninput="this.value=this.value.toUpperCase()">
        </div>
        <div class="fld">
          <label>CIN</label>
          <input type="text" name="cin" maxlength="21" value="<?php echo e(old('cin')); ?>">
        </div>
      </div>

      <div style="margin:14px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
        Contact & Address
      </div>
      <div class="fg2">
        <div class="fld">
          <label>Email</label>
          <input type="email" name="email" value="<?php echo e(old('email')); ?>">
        </div>
        <div class="fld">
          <label>Phone</label>
          <input type="text" name="phone" maxlength="20" value="<?php echo e(old('phone')); ?>">
        </div>
        <div class="fld">
          <label>State</label>
          <input type="text" name="state" maxlength="100" value="<?php echo e(old('state')); ?>">
        </div>
        <div class="fld">
          <label>City</label>
          <input type="text" name="city" maxlength="100" value="<?php echo e(old('city')); ?>">
        </div>
        <div class="fld full">
          <label>Address</label>
          <textarea name="address" rows="2" style="width:100%"><?php echo e(old('address')); ?></textarea>
        </div>
      </div>

      <div class="mf">
        <button type="button" class="btn" onclick="cm('company-modal')">Cancel</button>
        <button type="submit" class="btn acc">Create Company</button>
      </div>
    </form>
  </div>
</div>

<?php if($errors->any()): ?>
  <script>document.addEventListener('DOMContentLoaded', () => om('company-modal'))</script>
<?php endif; ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/masters/index.blade.php ENDPATH**/ ?>