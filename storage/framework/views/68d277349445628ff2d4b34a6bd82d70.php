<?php $__env->startSection('title', 'Leave Management'); ?>

<?php $__env->startSection('page_actions'); ?>
  <button class="btn acc" onclick="om('leave-modal')">+ Apply Leave</button>
  <a href="<?php echo e(route('leave.calendar')); ?>" class="btn">Calendar</a>
  <?php if($isLeaveAdmin): ?>
  <button class="btn" onclick="om('tracker-import-modal')">📤 Import Tracker</button>
  <?php endif; ?>
  <a href="<?php echo e(route('leave.export') . '?' . http_build_query(request()->only('status'))); ?>" class="btn">⬇ Export</a>
  <?php if($isLeaveAdmin): ?>
  <a href="<?php echo e(route('leave.tracker')); ?>" class="btn">View Full Tracker</a>
  <?php endif; ?>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<?php if($myLeaveBalances->isNotEmpty()): ?>

<div class="card" style="margin-bottom:16px">
  <div class="ch"><div class="ct">My Leave Balances (<?php echo e(now()->year); ?>)</div></div>
  <div style="padding:14px 18px;display:flex;flex-wrap:wrap;gap:12px">
    <?php $__currentLoopData = $myLeaveBalances; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $balance): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <div style="min-width:120px;padding:10px 14px;border:1px solid var(--b1);border-radius:8px">
        <div style="font-size:11px;color:var(--t3);text-transform:uppercase;letter-spacing:.04em;margin-bottom:3px">
          <?php echo e($balance->leaveType?->name ?? '—'); ?>

        </div>
        <div style="font-size:18px;font-weight:700"><?php echo e(rtrim(rtrim(number_format($balance->available, 2), '0'), '.')); ?></div>
      </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>
</div>
<?php endif; ?>


<div class="card" style="margin-bottom:16px">
  <?php if($isManager && !$isLeaveAdmin): ?>
  <div style="display:flex;gap:6px;margin-bottom:14px">
    <a href="<?php echo e(request()->fullUrlWithQuery(['applications_tab' => 'self'])); ?>"
       class="btn sm <?php echo e($activeTab === 'self' ? 'acc' : ''); ?>">Self Application</a>
    <a href="<?php echo e(request()->fullUrlWithQuery(['applications_tab' => 'team'])); ?>"
       class="btn sm <?php echo e($activeTab === 'team' ? 'acc' : ''); ?>">Team Application</a>
  </div>
  <?php endif; ?>
  <form method="GET" action="<?php echo e(route('leave')); ?>"
    style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end">
    <?php if($isManager && !$isLeaveAdmin): ?>
      <input type="hidden" name="applications_tab" value="<?php echo e($activeTab); ?>">
    <?php endif; ?>
    <div class="fld" style="min-width:140px">
      <label style="font-size:11px">Status</label>
      <select name="status" style="font-size:12px">
        <option value="">All</option>
        <option value="pending"  <?php echo e(request('status') === 'pending'  ? 'selected' : ''); ?>>Pending</option>
        <option value="approved" <?php echo e(request('status') === 'approved' ? 'selected' : ''); ?>>Approved</option>
        <option value="rejected" <?php echo e(request('status') === 'rejected' ? 'selected' : ''); ?>>Rejected</option>
      </select>
    </div>
    <div class="fld" style="min-width:140px">
      <label style="font-size:11px">From</label>
      <input type="date" name="date_from" value="<?php echo e(request('date_from')); ?>" style="font-size:12px">
    </div>
    <div class="fld" style="min-width:140px">
      <label style="font-size:11px">To</label>
      <input type="date" name="date_to" value="<?php echo e(request('date_to')); ?>" style="font-size:12px">
    </div>
    <button type="submit" class="btn sm acc">Filter</button>
    <a href="<?php echo e(route('leave', ['applications_tab' => $activeTab])); ?>" class="btn sm">Reset</a>
  </form>
</div>

<?php if($isManager && !$isLeaveAdmin): ?>
  <?php if($activeTab === 'team'): ?>
    
    <?php echo $__env->make('leave._applications_table', [
      'rows'         => $teamApplications,
      'title'        => 'Team Application',
      'searchParam'  => 'team_search',
      'searchValue'  => request('team_search'),
      'showBulk'     => true,
      'isAdmin'      => false,
      'viewerEmployeeId' => $viewerEmployeeId,
      'emptyText'    => 'No leave records found for your team.',
      'formId'       => 'team-applications-form',
      'tabValue'     => 'team',
    ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php else: ?>
    <?php echo $__env->make('leave._applications_table', [
      'rows'         => $selfApplications,
      'title'        => 'Self Application',
      'searchParam'  => 'self_search',
      'searchValue'  => request('self_search'),
      'showBulk'     => false,
      'isAdmin'      => false,
      'viewerEmployeeId' => $viewerEmployeeId,
      'emptyText'    => 'No leave records found.',
      'formId'       => 'self-applications-form',
      'tabValue'     => 'self',
    ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php endif; ?>
<?php else: ?>
  
  <?php echo $__env->make('leave._applications_table', [
    'rows'         => $applications,
    'title'        => $isLeaveAdmin ? 'All Leave Applications' : 'My Applications',
    'searchParam'  => 'search',
    'searchValue'  => request('search'),
    'showBulk'     => $isLeaveAdmin,
    'isAdmin'      => $isAdmin ?? false,
    'viewerEmployeeId' => $viewerEmployeeId,
    'emptyText'    => 'No leave records found.',
    'formId'       => 'all-applications-form',
  ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php endif; ?>


<div class="modal" id="leave-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Apply Leave</span>
      <button class="mc" onclick="cm('leave-modal')">✕</button>
    </div>
    <form method="POST" action="<?php echo e(route('leave.apply')); ?>">
      <?php echo csrf_field(); ?>
      <?php $__errorArgs = ['duplicate'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
        <div class="ferr" style="margin-bottom:12px"><?php echo e($message); ?></div>
      <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
      <div class="fg2">
        <div class="fld full">
          <label><?php echo e($isLeaveAdmin ? 'Employee (leave blank for self)' : 'Applying For'); ?></label>
          <select name="employee_id" <?php $__errorArgs = ['employee_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
            <?php if($isLeaveAdmin): ?>
              <option value="">— Self —</option>
              <?php $__currentLoopData = $employees; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $emp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($emp->id); ?>" <?php echo e(old('employee_id') == $emp->id ? 'selected' : ''); ?>><?php echo e($emp->full_name); ?> (<?php echo e($emp->employee_id); ?>)</option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php else: ?>
              
              <?php $__currentLoopData = $employees; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $emp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($emp->id); ?>" <?php echo e(old('employee_id', $viewerEmployeeId) == $emp->id ? 'selected' : ''); ?>>
                <?php echo e($emp->full_name); ?> (<?php echo e($emp->employee_id); ?>)<?php echo e($emp->id === $viewerEmployeeId ? ' — Me' : ''); ?>

              </option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php endif; ?>
          </select>
          <?php $__errorArgs = ['employee_id'];
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
          <select name="leave_type_id" id="leave-type-select" required onchange="toggleHalfDayPeriod()"
            <?php $__errorArgs = ['leave_type_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
            <option value="">Select type</option>
            <?php $__currentLoopData = $leaveTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $lt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($lt->id); ?>" data-half-day="<?php echo e(str_contains($lt->name, 'Half-Day') ? '1' : '0'); ?>"
              <?php echo e(old('leave_type_id') == $lt->id ? 'selected' : ''); ?>><?php echo e($lt->name); ?> (<?php echo e($lt->code); ?>)</option>
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
        <div class="fld full" id="leave-half-day-wrap" style="display:none">
          <label class="req">Which Half?</label>
          <select name="half_day_period" id="leave-half-day-period">
            <option value="first_half" <?php echo e(old('half_day_period') === 'first_half' ? 'selected' : ''); ?>>First Half</option>
            <option value="second_half" <?php echo e(old('half_day_period') === 'second_half' ? 'selected' : ''); ?>>Second Half</option>
          </select>
        </div>
        <div class="fld full">
          <label class="req">Duration</label>
          <select name="duration_type" id="leave-duration-type" onchange="toggleLeaveDuration()">
            <option value="single" <?php echo e(old('duration_type', 'single') === 'single' ? 'selected' : ''); ?>>Single Day</option>
            <option value="period" <?php echo e(old('duration_type') === 'period' ? 'selected' : ''); ?>>Period (date range)</option>
          </select>
        </div>
        <div class="fld full" id="leave-single-date-wrap">
          <label class="req">Date</label>
          <input type="date" name="single_date" id="leave-single-date" value="<?php echo e(old('single_date')); ?>" min="<?php echo e($minApplyDate); ?>"
            <?php $__errorArgs = ['single_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
          <span style="font-size:10px;color:var(--t3)">Cannot be more than 30 days in the past.</span>
          <?php $__errorArgs = ['single_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div class="fld" id="leave-from-date-wrap" style="display:none">
          <label class="req">From Date</label>
          <input type="date" name="from_date" id="leave-from-date" value="<?php echo e(old('from_date')); ?>" min="<?php echo e($minApplyDate); ?>"
            <?php $__errorArgs = ['from_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
          <?php $__errorArgs = ['from_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div class="fld" id="leave-to-date-wrap" style="display:none">
          <label class="req">To Date</label>
          <input type="date" name="to_date" id="leave-to-date" value="<?php echo e(old('to_date')); ?>"
            <?php $__errorArgs = ['to_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
          <?php $__errorArgs = ['to_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div class="fld full">
          <label>Reason</label>
          <textarea name="reason" rows="3" style="resize:vertical"><?php echo e(old('reason')); ?></textarea>
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('leave-modal')">Cancel</button>
        <button type="submit" class="btn acc">Submit</button>
      </div>
    </form>
  </div>
</div>


<div class="modal" id="tracker-import-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Import Leave Tracker</span>
      <button class="mc" onclick="cm('tracker-import-modal')">✕</button>
    </div>
    <div style="background:var(--acc2);border:1px solid var(--acc);border-radius:8px;padding:10px 14px;margin-bottom:12px;display:flex;align-items:center;gap:10px">
      <span style="font-size:18px">📥</span>
      <div style="flex:1">
        <div style="font-weight:600;font-size:12px;margin-bottom:2px">Download Template</div>
        <div style="font-size:11px;color:var(--t3)">Fill in this Excel template and upload it below.</div>
      </div>
      <a href="<?php echo e(route('leave.tracker.template')); ?>" class="btn acc sm" style="white-space:nowrap">Get Template</a>
    </div>
    <div style="background:var(--acc2);border:1px solid var(--acc);border-radius:8px;
                padding:12px 14px;margin-bottom:16px;font-size:12px;line-height:1.6;color:var(--t2)">
      <strong>📁 Upload the Leave Tracker .xlsm file</strong><br>
      The importer reads the <strong>"Mark Leave"</strong> sheet automatically. If the file has a single sheet, any sheet name is accepted.<br>
      Columns used: <span class="mono" style="font-size:11px">ID · EMP ID · Name · Date · Leave Type</span><br>
      <span style="color:var(--t3)">Re-uploading is safe — existing records are updated, not duplicated.</span>
    </div>
    <form method="POST" action="<?php echo e(route('leave.tracker.import')); ?>" enctype="multipart/form-data">
      <?php echo csrf_field(); ?>
      <div class="fld" style="margin-bottom:14px">
        <label>Leave Tracker File (.xlsm / .xlsx / .xls) *</label>
        <input type="file" name="file" accept=".xlsm,.xlsx,.xls" required
          style="background:var(--s2);border:1.5px dashed var(--b2);border-radius:8px;padding:14px;cursor:pointer;width:100%">
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('tracker-import-modal')">Cancel</button>
        <button type="submit" class="btn acc">📤 Import</button>
      </div>
    </form>
  </div>
</div>


<div class="modal" id="reason-modal">
  <div class="mb" style="width:420px">
    <div class="mh">
      <span class="mt">Leave Reason</span>
      <button class="mc" onclick="cm('reason-modal')">✕</button>
    </div>
    <p id="reason-modal-text" style="font-size:13px;color:var(--t2);line-height:1.6;white-space:pre-wrap"></p>
    <div class="mf">
      <button type="button" class="btn" onclick="cm('reason-modal')">Close</button>
    </div>
  </div>
</div>

<?php if($errors->any()): ?>
<script>document.addEventListener('DOMContentLoaded',()=>om('leave-modal'))</script>
<?php endif; ?>

<script>
  function toggleLeaveDuration() {
    var mode = document.getElementById('leave-duration-type').value;
    var singleWrap = document.getElementById('leave-single-date-wrap');
    var fromWrap = document.getElementById('leave-from-date-wrap');
    var toWrap = document.getElementById('leave-to-date-wrap');
    var singleInput = document.getElementById('leave-single-date');
    var fromInput = document.getElementById('leave-from-date');
    var toInput = document.getElementById('leave-to-date');

    var isSingle = mode === 'single';
    singleWrap.style.display = isSingle ? '' : 'none';
    fromWrap.style.display   = isSingle ? 'none' : '';
    toWrap.style.display     = isSingle ? 'none' : '';
    singleInput.required = isSingle;
    fromInput.required   = !isSingle;
    toInput.required     = !isSingle;
  }
  document.addEventListener('DOMContentLoaded', toggleLeaveDuration);

  function toggleHalfDayPeriod() {
    var select = document.getElementById('leave-type-select');
    var wrap   = document.getElementById('leave-half-day-wrap');
    var period = document.getElementById('leave-half-day-period');
    var opt    = select.options[select.selectedIndex];
    var isHalfDay = opt && opt.dataset.halfDay === '1';
    wrap.style.display = isHalfDay ? '' : 'none';
    period.required = isHalfDay;
  }
  document.addEventListener('DOMContentLoaded', toggleHalfDayPeriod);

  function showLeaveReason(cell) {
    document.getElementById('reason-modal-text').textContent = cell.dataset.reason;
    om('reason-modal');
  }
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/leave/index.blade.php ENDPATH**/ ?>