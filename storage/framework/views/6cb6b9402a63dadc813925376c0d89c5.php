<?php $__env->startSection('title', 'Employees'); ?>

<?php $__env->startSection('page_actions'); ?>
  <button class="btn acc" onclick="om('emp-modal')">+ Add Employee</button>
  <button class="btn" onclick="om('upload-modal')">📤 Bulk Upload</button>
  <button class="btn" onclick="om('bioid-upload-modal')">🔖 Upload Bio IDs</button>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

  
  <form method="GET" action="<?php echo e(route('employees')); ?>" class="card"
    style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
    <div class="fld" style="flex:1;min-width:160px">
      <label>Search</label>
      <input type="text" name="search" value="<?php echo e(request('search')); ?>" placeholder="Name, ID, email…">
    </div>
    <div class="fld" style="min-width:160px">
      <label>Department</label>
      <select name="department_id">
        <option value="">All Departments</option>
        <?php $__currentLoopData = $departments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dept): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($dept->id); ?>" <?php echo e(request('department_id') == $dept->id ? 'selected' : ''); ?>><?php echo e($dept->name); ?></option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </select>
    </div>
    <div class="fld" style="min-width:130px">
      <label>Status</label>
      <select name="status">
        <option value="">All</option>
        <option value="active" <?php echo e(request('status') === 'active' ? 'selected' : ''); ?>>Active</option>
        <option value="inactive" <?php echo e(request('status') === 'inactive' ? 'selected' : ''); ?>>Inactive</option>
        <option value="on_notice" <?php echo e(request('status') === 'on_notice' ? 'selected' : ''); ?>>On Notice</option>
      </select>
    </div>
    <button type="submit" class="btn acc">Filter</button>
    <a href="<?php echo e(route('employees')); ?>" class="btn">Reset</a>
  </form>

  <div class="card">
    <?php if($employees->isNotEmpty()): ?>
      <table id="emp-tbl">
        <thead>
          <tr>
            <th>Emp ID</th>
            <th>Name</th>
            <th>Department</th>
            <th>Designation</th>
            <th>Status</th>
            <th>Joined</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php $__currentLoopData = $employees; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $emp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
              <td class="mono"><?php echo e($emp->employee_id); ?></td>
              <td><?php echo e($emp->first_name); ?> <?php echo e($emp->last_name); ?></td>
              <td><?php echo e($emp->department?->name ?? '—'); ?></td>
              <td><?php echo e($emp->designation?->name ?? '—'); ?></td>
              <td><span class="bx <?php echo e($emp->employee_status === 'active' ? 'g' : 'gr'); ?>"><?php echo e($emp->employee_status); ?></span></td>
              <td><?php echo e($emp->date_of_joining?->format('d M Y') ?? '—'); ?></td>
              <td>
                <button class="btn sm" onclick="openEditEmp(<?php echo e($emp->id); ?>)">Edit</button>
                <form method="POST" action="<?php echo e(route('employees.destroy', $emp)); ?>" style="display:inline">
                  <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                  <button type="button" class="btn sm" style="color:var(--red)"
                    onclick="swConfirm(this.closest('form'), 'Delete Employee?', 'This employee record will be permanently deleted. This cannot be undone.', 'warning', 'Yes, delete', '#dc2626')">Del</button>
                </form>
              </td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
      </table>
      <div style="margin-top:14px"><?php echo e($employees->links()); ?></div>
    <?php else: ?>
      <div class="empty">
        <div class="ico">👥</div>
        <p>No employees found. <a href="#" onclick="om('emp-modal')">Add one now.</a></p>
      </div>
    <?php endif; ?>
  </div>

  
  <div class="modal" id="emp-modal">
    <div class="mb wide" style="max-height:90vh;overflow-y:auto">
      <div class="mh" style="position:sticky;top:0;z-index:10;background:var(--s1)">
        <span class="mt">Add Employee</span>
        <button class="mc" onclick="cm('emp-modal')">✕</button>
      </div>
      <form method="POST" action="<?php echo e(route('employees.store')); ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="entity_id" value="<?php echo e($entityId); ?>">
        <input type="hidden" name="employee_status" value="active">

        
        <div style="margin:14px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
          Personal Information
        </div>
        <div class="fg2">
          <div class="fld">
            <label class="req">First Name</label>
            <input type="text" name="first_name" required value="<?php echo e(old('first_name')); ?>"
              <?php $__errorArgs = ['first_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
            <?php $__errorArgs = ['first_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
          </div>
          <div class="fld">
            <label>Middle Name</label>
            <input type="text" name="middle_name" value="<?php echo e(old('middle_name')); ?>">
          </div>
          <div class="fld">
            <label class="req">Last Name</label>
            <input type="text" name="last_name" required value="<?php echo e(old('last_name')); ?>"
              <?php $__errorArgs = ['last_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
            <?php $__errorArgs = ['last_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
          </div>
          <div class="fld">
            <label class="req">Gender</label>
            <select name="gender" required <?php $__errorArgs = ['gender'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
              <option value="Male"   <?php echo e(old('gender', 'Male') === 'Male'   ? 'selected' : ''); ?>>Male</option>
              <option value="Female" <?php echo e(old('gender') === 'Female'         ? 'selected' : ''); ?>>Female</option>
              <option value="Other"  <?php echo e(old('gender') === 'Other'          ? 'selected' : ''); ?>>Other</option>
            </select>
            <?php $__errorArgs = ['gender'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
          </div>
          <div class="fld">
            <label>Date of Birth</label>
            <input type="date" name="date_of_birth" value="<?php echo e(old('date_of_birth')); ?>">
          </div>
          <div class="fld">
            <label>Blood Group</label>
            <select name="blood_group">
              <option value="">Select</option>
              <?php $__currentLoopData = ['A+','A-','B+','B-','AB+','AB-','O+','O-']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($bg); ?>" <?php echo e(old('blood_group') === $bg ? 'selected' : ''); ?>><?php echo e($bg); ?></option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
          </div>
          <div class="fld">
            <label>Marital Status</label>
            <select name="marital_status">
              <option value="">Select</option>
              <option value="single"   <?php echo e(old('marital_status') === 'single'   ? 'selected' : ''); ?>>Single</option>
              <option value="married"  <?php echo e(old('marital_status') === 'married'  ? 'selected' : ''); ?>>Married</option>
              <option value="divorced" <?php echo e(old('marital_status') === 'divorced' ? 'selected' : ''); ?>>Divorced</option>
              <option value="widowed"  <?php echo e(old('marital_status') === 'widowed'  ? 'selected' : ''); ?>>Widowed</option>
            </select>
          </div>
          <div class="fld">
            <label>Nationality</label>
            <input type="text" name="nationality" value="<?php echo e(old('nationality', 'Indian')); ?>">
          </div>
        </div>

        
        <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
          Contact Details
        </div>
        <div class="fg2">
          <div class="fld">
            <label>Official Email</label>
            <input type="email" name="official_email" value="<?php echo e(old('official_email')); ?>">
          </div>
          <div class="fld">
            <label class="req">Mobile (10 digits)</label>
            <input type="text" name="mobile" required pattern="\d{10}" maxlength="10"
              value="<?php echo e(old('mobile')); ?>" placeholder="9876543210"
              <?php $__errorArgs = ['mobile'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
            <?php $__errorArgs = ['mobile'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
          </div>
          <div class="fld">
            <label>Personal Email</label>
            <input type="email" name="personal_email" value="<?php echo e(old('personal_email')); ?>">
          </div>
          <div class="fld">
            <label>Alternate Phone</label>
            <input type="text" name="alternate_phone" pattern="\d{10}" maxlength="10"
              value="<?php echo e(old('alternate_phone')); ?>" placeholder="10 digits">
          </div>
        </div>

        
        <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
          Employment Details
        </div>
        <div class="fg2">
          <div class="fld">
            <label class="req">Date of Joining</label>
            <input type="date" name="date_of_joining" required value="<?php echo e(old('date_of_joining')); ?>"
              <?php $__errorArgs = ['date_of_joining'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
            <?php $__errorArgs = ['date_of_joining'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
          </div>
          <div class="fld">
            <label class="req">Employment Type</label>
            <select name="employment_type" required <?php $__errorArgs = ['employment_type'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
              <option value="full_time"   <?php echo e(old('employment_type', 'full_time') === 'full_time'   ? 'selected' : ''); ?>>Full Time</option>
              <option value="part_time"   <?php echo e(old('employment_type') === 'part_time'   ? 'selected' : ''); ?>>Part Time</option>
              <option value="contract"    <?php echo e(old('employment_type') === 'contract'    ? 'selected' : ''); ?>>Contract</option>
              <option value="intern"      <?php echo e(old('employment_type') === 'intern'      ? 'selected' : ''); ?>>Intern</option>
              <option value="consultant"  <?php echo e(old('employment_type') === 'consultant'  ? 'selected' : ''); ?>>Consultant</option>
              <option value="retainer"    <?php echo e(old('employment_type') === 'retainer'    ? 'selected' : ''); ?>>Retainer</option>
            </select>
          </div>
          <div class="fld">
            <label>Work Type *</label>
            <select name="work_type" required>
              <option value="wfo"            <?php echo e(old('work_type', 'wfo') === 'wfo'            ? 'selected' : ''); ?>>Work from Office</option>
              <option value="wfh"            <?php echo e(old('work_type') === 'wfh'            ? 'selected' : ''); ?>>Work from Home</option>
              <option value="hybrid"         <?php echo e(old('work_type') === 'hybrid'         ? 'selected' : ''); ?>>Hybrid</option>
              <option value="client_location"<?php echo e(old('work_type') === 'client_location'? 'selected' : ''); ?>>Client Location</option>
            </select>
          </div>
          <div class="fld">
            <label>Department</label>
            <select name="department_id">
              <option value="">Select Department</option>
              <?php $__currentLoopData = $departments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dept): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($dept->id); ?>" <?php echo e(old('department_id') == $dept->id ? 'selected' : ''); ?>><?php echo e($dept->name); ?></option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
          </div>
          <div class="fld">
            <label>Designation</label>
            <select name="designation_id">
              <option value="">Select Designation</option>
              <?php $__currentLoopData = $designations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($d->id); ?>" <?php echo e(old('designation_id') == $d->id ? 'selected' : ''); ?>><?php echo e($d->name); ?></option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
          </div>
          <div class="fld">
            <label>Branch / Location</label>
            <select name="branch_id">
              <option value="">Select Branch</option>
              <?php $__currentLoopData = $branches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($b->id); ?>" <?php echo e(old('branch_id') == $b->id ? 'selected' : ''); ?>><?php echo e($b->name); ?></option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
          </div>
          <div class="fld">
            <label>Reporting Manager</label>
            <select name="reporting_manager_id">
              <option value="">Select Manager</option>
              <?php $__currentLoopData = $managers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $mgr): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($mgr->id); ?>" <?php echo e(old('reporting_manager_id') == $mgr->id ? 'selected' : ''); ?>>
                  <?php echo e($mgr->employee_id); ?> — <?php echo e($mgr->first_name); ?> <?php echo e($mgr->last_name); ?>

                </option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
          </div>
          <div class="fld">
            <label>Probation Period (months)</label>
            <input type="number" name="probation_period_months" min="0" max="24"
              value="<?php echo e(old('probation_period_months', 6)); ?>" placeholder="6">
          </div>
        </div>

        
        <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
          Payroll Applicability
        </div>
        <div class="fg2">
          <div class="fld">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
              <input type="checkbox" name="is_pf_applicable" value="1"
                <?php echo e(old('is_pf_applicable', '1') ? 'checked' : ''); ?> style="width:16px;height:16px">
              PF Applicable
            </label>
          </div>
          <div class="fld">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
              <input type="checkbox" name="is_esic_applicable" value="1"
                <?php echo e(old('is_esic_applicable') ? 'checked' : ''); ?> style="width:16px;height:16px">
              ESIC Applicable
            </label>
          </div>
          <div class="fld">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
              <input type="checkbox" name="is_pt_applicable" value="1"
                <?php echo e(old('is_pt_applicable', '1') ? 'checked' : ''); ?> style="width:16px;height:16px">
              PT Applicable
            </label>
          </div>
          <div class="fld">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
              <input type="checkbox" name="is_tds_applicable" value="1"
                <?php echo e(old('is_tds_applicable') ? 'checked' : ''); ?> style="width:16px;height:16px">
              TDS Applicable
            </label>
          </div>
          <div class="fld">
            <label>TDS Regime</label>
            <select name="tds_regime">
              <option value="new" <?php echo e(old('tds_regime', 'new') === 'new' ? 'selected' : ''); ?>>New Regime</option>
              <option value="old" <?php echo e(old('tds_regime') === 'old'        ? 'selected' : ''); ?>>Old Regime</option>
            </select>
          </div>
        </div>

        
        <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
          Statutory Details
        </div>
        <div class="fg2">
          <div class="fld">
            <label>PAN Number</label>
            <input type="text" name="pan_number" value="<?php echo e(old('pan_number')); ?>"
              placeholder="ABCDE1234F" maxlength="10" style="text-transform:uppercase"
              oninput="this.value=this.value.toUpperCase()">
          </div>
          <div class="fld">
            <label>Aadhaar Number</label>
            <input type="text" name="aadhaar_number" value="<?php echo e(old('aadhaar_number')); ?>"
              placeholder="12-digit number" maxlength="12" pattern="\d{12}">
          </div>
          <div class="fld">
            <label>UAN Number</label>
            <input type="text" name="uan_number" value="<?php echo e(old('uan_number')); ?>"
              placeholder="12-digit UAN" maxlength="12">
          </div>
        </div>

        
        <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
          Bank Details
        </div>
        <div class="fg2">
          <div class="fld">
            <label>Bank Name</label>
            <input type="text" name="bank_name" value="<?php echo e(old('bank_name')); ?>" placeholder="e.g. HDFC Bank">
          </div>
          <div class="fld">
            <label>Account Number</label>
            <input type="text" name="account_number" value="<?php echo e(old('account_number')); ?>">
          </div>
          <div class="fld">
            <label>IFSC Code</label>
            <input type="text" name="ifsc_code" value="<?php echo e(old('ifsc_code')); ?>"
              placeholder="HDFC0001234" maxlength="11" style="text-transform:uppercase"
              oninput="this.value=this.value.toUpperCase()">
          </div>
          <div class="fld">
            <label>Account Holder Name</label>
            <input type="text" name="account_holder_name" value="<?php echo e(old('account_holder_name')); ?>"
              placeholder="As per bank records">
          </div>
        </div>

        
        <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
          Emergency Contact
        </div>
        <div class="fg2">
          <div class="fld">
            <label>Contact Name</label>
            <input type="text" name="emergency_contact_name" value="<?php echo e(old('emergency_contact_name')); ?>">
          </div>
          <div class="fld">
            <label>Contact Phone (10 digits)</label>
            <input type="text" name="emergency_contact_phone" value="<?php echo e(old('emergency_contact_phone')); ?>"
              pattern="\d{10}" maxlength="10" placeholder="9876543210">
          </div>
        </div>

        <div class="mf" style="position:sticky;bottom:0;background:var(--s1);padding-top:12px;border-top:1px solid var(--b1)">
          <button type="button" class="btn" onclick="cm('emp-modal')">Cancel</button>
          <button type="submit" class="btn acc">Save Employee</button>
        </div>
      </form>
    </div>
  </div>

  
  <div class="modal" id="emp-edit-modal">
    <div class="mb wide" style="max-height:90vh;overflow-y:auto">
      <div class="mh" style="position:sticky;top:0;z-index:10;background:var(--s1)">
        <span class="mt">Edit Employee</span>
        <button class="mc" onclick="cm('emp-edit-modal')">✕</button>
      </div>
      <form method="POST" id="emp-edit-form">
        <?php echo csrf_field(); ?>
        <div id="edit-loading" style="text-align:center;padding:40px;color:var(--t3);display:none">
          Loading employee data…
        </div>
        <div id="edit-body">

          
          <div style="margin:14px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
            Personal Information
          </div>
          <div class="fg2">
            <div class="fld">
              <label>First Name *</label>
              <input type="text" name="first_name" id="e-first-name" required>
            </div>
            <div class="fld">
              <label>Middle Name</label>
              <input type="text" name="middle_name" id="e-middle-name">
            </div>
            <div class="fld">
              <label>Last Name *</label>
              <input type="text" name="last_name" id="e-last-name" required>
            </div>
            <div class="fld">
              <label>Gender</label>
              <select name="gender" id="e-gender">
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div class="fld">
              <label>Date of Birth</label>
              <input type="date" name="date_of_birth" id="e-dob">
            </div>
            <div class="fld">
              <label>Blood Group</label>
              <select name="blood_group" id="e-blood-group">
                <option value="">Select</option>
                <?php $__currentLoopData = ['A+','A-','B+','B-','AB+','AB-','O+','O-']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <option value="<?php echo e($bg); ?>"><?php echo e($bg); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
              </select>
            </div>
            <div class="fld">
              <label>Marital Status</label>
              <select name="marital_status" id="e-marital">
                <option value="">Select</option>
                <option value="single">Single</option>
                <option value="married">Married</option>
                <option value="divorced">Divorced</option>
                <option value="widowed">Widowed</option>
              </select>
            </div>
          </div>

          
          <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
            Contact Details
          </div>
          <div class="fg2">
            <div class="fld">
              <label>Official Email</label>
              <input type="email" name="official_email" id="e-email">
            </div>
            <div class="fld">
              <label>Mobile</label>
              <input type="text" name="mobile" id="e-mobile" maxlength="15">
            </div>
            <div class="fld">
              <label>Personal Email</label>
              <input type="email" name="personal_email" id="e-personal-email">
            </div>
            <div class="fld">
              <label>Alternate Phone</label>
              <input type="text" name="alternate_phone" id="e-alt-phone" maxlength="10">
            </div>
          </div>

          
          <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
            Employment Details
          </div>
          <div class="fg2">
            <div class="fld">
              <label>Status *</label>
              <select name="employee_status" id="e-status" required>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="on_notice">On Notice</option>
                <option value="terminated">Terminated</option>
              </select>
            </div>
            <div class="fld">
              <label>Employment Type</label>
              <select name="employment_type" id="e-emp-type">
                <option value="full_time">Full Time</option>
                <option value="part_time">Part Time</option>
                <option value="contract">Contract</option>
                <option value="intern">Intern</option>
                <option value="consultant">Consultant</option>
                <option value="retainer">Retainer</option>
              </select>
            </div>
            <div class="fld">
              <label>Work Type</label>
              <select name="work_type" id="e-work-type">
                <option value="wfo">Work from Office</option>
                <option value="wfh">Work from Home</option>
                <option value="hybrid">Hybrid</option>
                <option value="client_location">Client Location</option>
              </select>
            </div>
            <div class="fld">
              <label>Department</label>
              <select name="department_id" id="e-dept">
                <option value="">Select Department</option>
                <?php $__currentLoopData = $departments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dept): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <option value="<?php echo e($dept->id); ?>"><?php echo e($dept->name); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
              </select>
            </div>
            <div class="fld">
              <label>Designation</label>
              <select name="designation_id" id="e-desig">
                <option value="">Select Designation</option>
                <?php $__currentLoopData = $designations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <option value="<?php echo e($d->id); ?>"><?php echo e($d->name); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
              </select>
            </div>
            <div class="fld">
              <label>Branch / Location</label>
              <select name="branch_id" id="e-branch">
                <option value="">Select Branch</option>
                <?php $__currentLoopData = $branches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <option value="<?php echo e($b->id); ?>"><?php echo e($b->name); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
              </select>
            </div>
            <div class="fld">
              <label>Reporting Manager</label>
              <select name="reporting_manager_id" id="e-manager">
                <option value="">Select Manager</option>
                <?php $__currentLoopData = $managers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $mgr): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <option value="<?php echo e($mgr->id); ?>"><?php echo e($mgr->employee_id); ?> — <?php echo e($mgr->first_name); ?> <?php echo e($mgr->last_name); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
              </select>
            </div>
            <div class="fld">
              <label>Date of Leaving</label>
              <input type="date" name="date_of_leaving" id="e-dol">
            </div>
          </div>

          
          <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
            Payroll Applicability
          </div>
          <div class="fg2">
            <div class="fld">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="is_pf_applicable" value="1" id="e-pf" style="width:16px;height:16px">
                PF Applicable
              </label>
            </div>
            <div class="fld">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="is_esic_applicable" value="1" id="e-esic" style="width:16px;height:16px">
                ESIC Applicable
              </label>
            </div>
            <div class="fld">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="is_pt_applicable" value="1" id="e-pt" style="width:16px;height:16px">
                PT Applicable
              </label>
            </div>
            <div class="fld">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                <input type="checkbox" name="is_tds_applicable" value="1" id="e-tds" style="width:16px;height:16px">
                TDS Applicable
              </label>
            </div>
            <div class="fld">
              <label>TDS Regime</label>
              <select name="tds_regime" id="e-tds-regime">
                <option value="new">New Regime</option>
                <option value="old">Old Regime</option>
              </select>
            </div>
          </div>

          
          <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
            Statutory Details
          </div>
          <div class="fg2">
            <div class="fld">
              <label>PAN Number</label>
              <input type="text" name="pan_number" id="e-pan"
                placeholder="ABCDE1234F" maxlength="10" style="text-transform:uppercase"
                oninput="this.value=this.value.toUpperCase()">
            </div>
            <div class="fld">
              <label>Aadhaar Number</label>
              <input type="text" name="aadhaar_number" id="e-aadhaar"
                placeholder="12-digit number" maxlength="12">
            </div>
            <div class="fld">
              <label>UAN Number</label>
              <input type="text" name="uan_number" id="e-uan" maxlength="12">
            </div>
          </div>

          
          <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
            Bank Details
          </div>
          <div class="fg2">
            <div class="fld">
              <label>Bank Name</label>
              <input type="text" name="bank_name" id="e-bank-name" placeholder="e.g. HDFC Bank">
            </div>
            <div class="fld">
              <label>Account Number</label>
              <input type="text" name="account_number" id="e-acc-no">
            </div>
            <div class="fld">
              <label>IFSC Code</label>
              <input type="text" name="ifsc_code" id="e-ifsc"
                placeholder="HDFC0001234" maxlength="11" style="text-transform:uppercase"
                oninput="this.value=this.value.toUpperCase()">
            </div>
            <div class="fld">
              <label>Account Holder Name</label>
              <input type="text" name="account_holder_name" id="e-acc-holder">
            </div>
          </div>

          
          <div style="margin:18px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
            Emergency Contact
          </div>
          <div class="fg2">
            <div class="fld">
              <label>Contact Name</label>
              <input type="text" name="emergency_contact_name" id="e-emg-name">
            </div>
            <div class="fld">
              <label>Contact Phone</label>
              <input type="text" name="emergency_contact_phone" id="e-emg-phone" maxlength="10">
            </div>
          </div>

        </div>

        <div class="mf" style="position:sticky;bottom:0;background:var(--s1);padding-top:12px;border-top:1px solid var(--b1)">
          <button type="button" class="btn" onclick="cm('emp-edit-modal')">Cancel</button>
          <button type="submit" class="btn acc">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <?php echo $__env->make('partials.upload-modal', [
    'uploadId'      => 'upload-modal',
    'uploadTitle'   => 'Bulk Upload Employees',
    'uploadRoute'   => 'employees.import',
    'templateRoute' => 'employees.template',
    'uploadFields'  => [],
    'uploadHint'    => 'Download the template, fill employee data (one row per employee), then upload. Existing records matched by mobile number will be updated.',
  ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

  <?php echo $__env->make('partials.upload-modal', [
    'uploadId'      => 'bioid-upload-modal',
    'uploadTitle'   => 'Upload Bio ID Mappings',
    'uploadRoute'   => 'bio_ids.import',
    'templateRoute' => 'bio_ids.template',
    'uploadFields'  => [],
    'uploadHint'    => 'Columns: Employee Name, New Emp ID, BioID. BioID can be multiple values separated by commas (e.g. I-20,II-6,III-8). Types: I=Machine 10, II=Machine 12, III=Machine K.',
  ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

  <?php if($errors->any()): ?>
    <script>document.addEventListener('DOMContentLoaded',()=>om('emp-modal'))</script>
  <?php endif; ?>

  <?php $__env->startPush('scripts'); ?>
  <script>
  async function openEditEmp(id) {
    // Show modal with loading state first
    document.getElementById('edit-loading').style.display = 'block';
    document.getElementById('edit-body').style.display    = 'none';
    document.getElementById('emp-edit-form').action       = '/employees/' + id;
    om('emp-edit-modal');

    try {
      const res  = await fetch('/employees/' + id + '/edit-data');
      const d    = await res.json();

      // Personal
      setVal('e-first-name',    d.first_name);
      setVal('e-middle-name',   d.middle_name);
      setVal('e-last-name',     d.last_name);
      setVal('e-gender',        d.gender);
      setVal('e-dob',           d.date_of_birth);
      setVal('e-blood-group',   d.blood_group);
      setVal('e-marital',       d.marital_status);

      // Contact
      setVal('e-email',         d.official_email);
      setVal('e-mobile',        d.mobile);
      setVal('e-personal-email',d.personal_email);
      setVal('e-alt-phone',     d.alternate_phone);

      // Employment
      setVal('e-status',        d.employee_status);
      setVal('e-emp-type',      d.employment_type);
      setVal('e-work-type',     d.work_type);
      setVal('e-dept',          d.department_id);
      setVal('e-desig',         d.designation_id);
      setVal('e-branch',        d.branch_id);
      setVal('e-manager',       d.reporting_manager_id);
      setVal('e-dol',           d.date_of_leaving);

      // Payroll flags
      setCheck('e-pf',          d.is_pf_applicable);
      setCheck('e-esic',        d.is_esic_applicable);
      setCheck('e-pt',          d.is_pt_applicable);
      setCheck('e-tds',         d.is_tds_applicable);
      setVal('e-tds-regime',    d.tds_regime);

      // Statutory
      setVal('e-pan',           d.pan_number);
      setVal('e-aadhaar',       d.aadhaar_number);
      setVal('e-uan',           d.uan_number);

      // Bank
      setVal('e-bank-name',     d.bank_name);
      setVal('e-acc-no',        d.account_number);
      setVal('e-ifsc',          d.ifsc_code);
      setVal('e-acc-holder',    d.account_holder_name);

      // Emergency
      setVal('e-emg-name',      d.emergency_contact_name);
      setVal('e-emg-phone',     d.emergency_contact_phone);

    } catch(e) {
      console.error('Failed to load employee data', e);
    } finally {
      document.getElementById('edit-loading').style.display = 'none';
      document.getElementById('edit-body').style.display    = '';
    }
  }

  function setVal(id, val) {
    const el = document.getElementById(id);
    if (!el) return;
    el.value = val ?? '';
    window._ssSync?.(el); // sync searchable-select display if enhanced
  }
  function setCheck(id, val) {
    const el = document.getElementById(id);
    if (el) el.checked = !!val;
  }
  </script>
  <?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/employees/index.blade.php ENDPATH**/ ?>