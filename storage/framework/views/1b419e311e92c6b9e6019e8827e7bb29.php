<?php $__env->startSection('title', 'My Profile'); ?>

<?php $__env->startSection('content'); ?>

  <?php $emp = $user->employee; ?>

  
  <div class="card" style="margin-bottom:16px;max-width:800px">
    <div class="ch"><div class="ct">Master Details</div><span style="font-size:11px;color:var(--t3)">Set by HR — contact HR to change</span></div>
    <div style="padding:14px 18px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px 24px;font-size:13px">
      <?php $__currentLoopData = [
          'Employee Code'     => $emp?->employee_id,
          'Company'           => $emp?->company?->name,
          'First Name'        => $emp?->first_name,
          'Last Name'         => $emp?->last_name,
          'Gender'            => $emp?->gender,
          'Official Email'    => $emp?->official_email,
          'Mobile'            => $emp?->mobile,
          'Date of Joining'   => $emp?->date_of_joining?->format('d M Y'),
          'Employment Type'   => $emp ? ucwords(str_replace('_',' ',$emp->employment_type)) : null,
          'Work Type'         => $emp ? ucwords(str_replace('_',' ',$emp->work_type)) : null,
          'Department'        => $emp?->department?->name,
          'Designation'       => $emp?->designation?->name,
          'Reporting Manager' => $emp?->reportingManager?->full_name,
          'Branch / Location' => $emp?->branch?->name,
          'Probation Period'  => $emp?->probation_period_months !== null ? $emp->probation_period_months . ' months' : null,
          'PF Applicable'     => $emp ? ($emp->is_pf_applicable ? 'Yes' : 'No') : null,
        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $label => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div>
          <div style="font-size:11px;color:var(--t3);text-transform:uppercase;letter-spacing:.04em;margin-bottom:3px"><?php echo e($label); ?></div>
          <div><?php echo e($value ?: '—'); ?></div>
        </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      <?php if($emp?->work_type === 'client_location'): ?>
        <div>
          <div style="font-size:11px;color:var(--t3);text-transform:uppercase;letter-spacing:.04em;margin-bottom:3px">Client Name</div>
          <div><?php echo e($emp->client_name ?: '—'); ?></div>
        </div>
      <?php endif; ?>
      <div>
        <div style="font-size:11px;color:var(--t3);text-transform:uppercase;letter-spacing:.04em;margin-bottom:3px">Role</div>
        <div>
          <?php $__empty_1 = true; $__currentLoopData = $user->roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <span style="display:inline-block;background:var(--bg2,#eef1f5);border-radius:12px;padding:2px 8px;font-size:11px;margin:2px 4px 2px 0"><?php echo e($role->name); ?></span>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <span style="color:var(--t3)">No roles assigned</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <?php if($emp): ?>
  
  <div class="card" style="margin-bottom:16px;max-width:800px">
    <div class="ch"><div class="ct">Additional Details</div><span style="font-size:11px;color:var(--t3)">Fill in anything still missing</span></div>
    <form method="POST" action="<?php echo e(route('profile.employee_details.update')); ?>" enctype="multipart/form-data" style="padding:14px 18px">
      <?php echo csrf_field(); ?>

      <div style="margin:0 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">Personal</div>
      <div class="fg2">
        <div class="fld">
          <label>Middle Name</label>
          <input type="text" name="middle_name" value="<?php echo e(old('middle_name', $emp->middle_name)); ?>">
        </div>
        <div class="fld">
          <label>Date of Birth</label>
          <input type="date" name="date_of_birth" value="<?php echo e(old('date_of_birth', $emp->date_of_birth ? \Carbon\Carbon::parse($emp->date_of_birth)->format('Y-m-d') : null)); ?>">
        </div>
        <div class="fld">
          <label>Marital Status</label>
          <select name="marital_status">
            <option value="">Select</option>
            <?php $__currentLoopData = ['single'=>'Single','married'=>'Married','divorced'=>'Divorced','widowed'=>'Widowed']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $lbl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($val); ?>" <?php echo e(old('marital_status', $emp->marital_status) === $val ? 'selected' : ''); ?>><?php echo e($lbl); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
        </div>
        <div class="fld">
          <label>Nationality</label>
          <input type="text" name="nationality" value="<?php echo e(old('nationality', $emp->nationality)); ?>">
        </div>
        <div class="fld">
          <label>Personal Email</label>
          <input type="email" name="personal_email" value="<?php echo e(old('personal_email', $emp->personal_email)); ?>"
            <?php $__errorArgs = ['personal_email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
          <?php $__errorArgs = ['personal_email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div class="fld">
          <label>Alternate Phone</label>
          <input type="text" name="alternate_phone" maxlength="10" placeholder="10 digits"
            value="<?php echo e(old('alternate_phone', $emp->alternate_phone)); ?>" <?php $__errorArgs = ['alternate_phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
          <?php $__errorArgs = ['alternate_phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
      </div>

      <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">Current Address</div>
      <div class="fg2">
        <div class="fld full">
          <label>Address</label>
          <input type="text" name="current_address" value="<?php echo e(old('current_address', $emp->current_address)); ?>">
        </div>
        <div class="fld">
          <label>City</label>
          <input type="text" name="current_city" value="<?php echo e(old('current_city', $emp->current_city)); ?>">
        </div>
        <div class="fld">
          <label>State</label>
          <input type="text" name="current_state" value="<?php echo e(old('current_state', $emp->current_state)); ?>">
        </div>
        <div class="fld">
          <label>Pincode</label>
          <input type="text" name="current_pincode" maxlength="10" value="<?php echo e(old('current_pincode', $emp->current_pincode)); ?>">
        </div>
      </div>

      <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">Permanent Address</div>
      <div class="fg2">
        <div class="fld full">
          <label>Address</label>
          <input type="text" name="permanent_address" value="<?php echo e(old('permanent_address', $emp->permanent_address)); ?>">
        </div>
        <div class="fld">
          <label>City</label>
          <input type="text" name="permanent_city" value="<?php echo e(old('permanent_city', $emp->permanent_city)); ?>">
        </div>
        <div class="fld">
          <label>State</label>
          <input type="text" name="permanent_state" value="<?php echo e(old('permanent_state', $emp->permanent_state)); ?>">
        </div>
        <div class="fld">
          <label>Pincode</label>
          <input type="text" name="permanent_pincode" maxlength="10" value="<?php echo e(old('permanent_pincode', $emp->permanent_pincode)); ?>">
        </div>
      </div>

      <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">Emergency Contact</div>
      <div class="fg2">
        <div class="fld">
          <label>Contact Name</label>
          <input type="text" name="emergency_contact_name" value="<?php echo e(old('emergency_contact_name', $emp->emergency_contact_name)); ?>">
        </div>
        <div class="fld">
          <label>Contact Phone</label>
          <input type="text" name="emergency_contact_phone" maxlength="10"
            value="<?php echo e(old('emergency_contact_phone', $emp->emergency_contact_phone)); ?>" <?php $__errorArgs = ['emergency_contact_phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
          <?php $__errorArgs = ['emergency_contact_phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div class="fld">
          <label>Relation</label>
          <input type="text" name="emergency_contact_relation" value="<?php echo e(old('emergency_contact_relation', $emp->emergency_contact_relation)); ?>">
        </div>
      </div>

      <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">Statutory Details</div>
      <div class="fg2">
        <div class="fld">
          <label>PAN Number</label>
          <input type="text" name="pan_number" maxlength="10" style="text-transform:uppercase" oninput="this.value=this.value.toUpperCase()"
            value="<?php echo e(old('pan_number', $emp->statutoryDetails?->pan_number)); ?>" <?php $__errorArgs = ['pan_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
          <?php $__errorArgs = ['pan_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div class="fld">
          <label>Aadhaar Number</label>
          <input type="text" name="aadhaar_number" maxlength="12"
            value="<?php echo e(old('aadhaar_number', $emp->statutoryDetails?->aadhaar_number)); ?>" <?php $__errorArgs = ['aadhaar_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
          <?php $__errorArgs = ['aadhaar_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div class="fld">
          <label>UAN Number</label>
          <input type="text" name="uan_number" maxlength="12" value="<?php echo e(old('uan_number', $emp->statutoryDetails?->uan_number)); ?>">
        </div>
      </div>

      <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">Bank Details</div>
      <div class="fg2">
        <div class="fld">
          <label>Bank Name</label>
          <input type="text" name="bank_name" value="<?php echo e(old('bank_name', $emp->primaryBank?->bank_name)); ?>">
        </div>
        <div class="fld">
          <label>Account Number</label>
          <input type="text" name="account_number" value="<?php echo e(old('account_number', $emp->primaryBank?->account_number)); ?>">
        </div>
        <div class="fld">
          <label>IFSC Code</label>
          <input type="text" name="ifsc_code" maxlength="11" style="text-transform:uppercase" oninput="this.value=this.value.toUpperCase()"
            value="<?php echo e(old('ifsc_code', $emp->primaryBank?->ifsc_code)); ?>" <?php $__errorArgs = ['ifsc_code'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
          <?php $__errorArgs = ['ifsc_code'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div class="fld">
          <label>Account Holder Name</label>
          <input type="text" name="account_holder_name" value="<?php echo e(old('account_holder_name', $emp->primaryBank?->account_holder_name)); ?>">
        </div>
      </div>

      <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">Documents</div>
      <div class="fg2">
        <?php $__currentLoopData = \App\Models\EmployeeDocument::UPLOAD_TYPES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $docType => $docLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php $existingDoc = $emp->documents->firstWhere('document_type', $docType); ?>
          <div class="fld">
            <label><?php echo e($docLabel); ?>

              <?php if($existingDoc): ?>
                <a href="<?php echo e(route('employees.documents.download', $existingDoc)); ?>" target="_blank" style="font-weight:400;font-size:11px;margin-left:6px">(view: <?php echo e($existingDoc->file_name); ?>)</a>
              <?php endif; ?>
            </label>
            <input type="file" name="documents[<?php echo e($docType); ?>]" accept=".pdf,.jpg,.jpeg,.png">
          </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>

      <div class="mf" style="border-top:none;padding-top:12px;margin-top:4px">
        <button type="submit" class="btn acc">Save Details</button>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <div class="card" style="max-width:800px">
    <div class="ch"><div class="ct">Account Details</div></div>
    <form method="POST" action="<?php echo e(route('profile.update')); ?>" style="padding:14px 18px">
      <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
      <div class="fld" style="margin-bottom:16px">
        <label class="req">Name</label>
        <input type="text" name="name" required maxlength="150" value="<?php echo e(old('name', $user->name)); ?>"
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
      <div class="fld" style="margin-bottom:16px">
        <label class="req">Email</label>
        <input type="email" name="email" required maxlength="150" value="<?php echo e(old('email', $user->email)); ?>"
          <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
        <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
      </div>
      <div class="fld" style="margin-bottom:16px">
        <label>New Password <span style="font-weight:400;color:var(--t3)">(leave blank to keep current)</span></label>
        <input type="password" name="password" minlength="8" autocomplete="new-password"
          <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
        <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
      </div>
      <div class="fld" style="margin-bottom:16px">
        <label>Confirm New Password</label>
        <input type="password" name="password_confirmation" minlength="8" autocomplete="new-password">
      </div>
      <div class="mf" style="border-top:none;padding-top:0;margin-top:4px">
        <button type="submit" class="btn acc">Save Changes</button>
      </div>
    </form>
  </div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/profile/show.blade.php ENDPATH**/ ?>