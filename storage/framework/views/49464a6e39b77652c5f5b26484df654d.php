
<?php $edit = $edit ?? false; ?>

<div class="fg2" style="gap:14px;padding:4px 0 8px">

  <div class="fld full">
    <label class="req">Policy Name</label>
    <input type="text" name="name" required maxlength="100"
      value="<?php echo e($edit ? old('name', $policy?->name ?? '') : old('name')); ?>"
      placeholder="e.g. Annual Leave – Staff"
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

  <div class="fld full">
    <label class="req">Leave Type</label>
    <select name="leave_type_id" required <?php $__errorArgs = ['leave_type_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
      <option value="">— Select leave type —</option>
      <?php $__currentLoopData = $leaveTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $lt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <option value="<?php echo e($lt->id); ?>"
          <?php echo e(($edit && ($policy?->leave_type_id == $lt->id)) ? 'selected' : ''); ?>>
          <?php echo e($lt->code); ?> – <?php echo e($lt->name); ?>

        </option>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select>
    <?php $__errorArgs = ['leave_type_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
  </div>

  <div class="fld">
    <label class="req">Days Per Year</label>
    <input type="number" name="days_per_year" required min="0" max="365" step="0.5"
      value="<?php echo e($edit ? old('days_per_year', $policy?->days_per_year ?? '') : old('days_per_year')); ?>"
      placeholder="e.g. 12"
      <?php $__errorArgs = ['days_per_year'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
    <?php $__errorArgs = ['days_per_year'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
  </div>

  <div class="fld">
    <label class="req">Accrual Type</label>
    <select name="accrual_type" required <?php $__errorArgs = ['accrual_type'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
      <?php $__currentLoopData = $accrualTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $lbl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <option value="<?php echo e($val); ?>"
          <?php echo e(($edit && ($policy?->accrual_type == $val)) ? 'selected' : ''); ?>>
          <?php echo e($lbl); ?>

        </option>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select>
    <?php $__errorArgs = ['accrual_type'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
  </div>

  <div class="fld">
    <label>Accrual Days <span style="color:var(--t3);font-size:10px">(optional)</span></label>
    <input type="number" name="accrual_days" min="0" step="0.5"
      value="<?php echo e($edit ? old('accrual_days', $policy?->accrual_days ?? '') : old('accrual_days')); ?>"
      placeholder="Auto if blank">
  </div>

  <div class="fld">
    <label class="req">Proration</label>
    <select name="proration" required <?php $__errorArgs = ['proration'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
      <?php $__currentLoopData = $prorationsTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $lbl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <option value="<?php echo e($val); ?>"
          <?php echo e(($edit && ($policy?->proration == $val)) ? 'selected' : ''); ?>>
          <?php echo e($lbl); ?>

        </option>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select>
    <?php $__errorArgs = ['proration'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
  </div>

  <div class="fld" style="display:flex;flex-direction:column;justify-content:center;gap:10px;padding-top:4px">
    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
      <input type="checkbox" name="sandwich_policy" value="1"
        <?php echo e(($edit && ($policy?->sandwich_policy)) ? 'checked' : ''); ?>

        style="width:16px;height:16px">
      Sandwich Policy
      <span style="font-size:10px;color:var(--t3)">(weekends between leave counted)</span>
    </label>

    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
      <input type="checkbox" name="is_active" value="1"
        <?php echo e($edit ? (($policy?->is_active) ? 'checked' : '') : 'checked'); ?>

        style="width:16px;height:16px">
      Active
    </label>
  </div>

</div>
<?php /**PATH D:\hrms-payroll\resources\views/leave/_policy-form.blade.php ENDPATH**/ ?>