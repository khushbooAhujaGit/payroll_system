<?php $__env->startSection('title', 'Leave Management'); ?>

<?php $__env->startSection('page_actions'); ?>
  <button class="btn acc" onclick="om('leave-modal')">+ Apply Leave</button>
  <button class="btn" onclick="om('tracker-import-modal')">📤 Import Tracker</button>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<div class="g4" style="margin-bottom:16px">
  <div class="sc"><div class="lbl">Pending Applications</div><div class="val" style="color:var(--yel)"><?php echo e($pending); ?></div></div>
  <div class="sc"><div class="lbl">Total Applications</div><div class="val"><?php echo e($applications->total()); ?></div></div>
  <div class="sc">
    <div class="lbl">Tracker Records (entity)</div>
    <div class="val"><?php echo e(number_format($trackerTotal)); ?></div>
  </div>
  <div class="sc">
    <a href="<?php echo e(route('leave.tracker')); ?>" style="text-decoration:none;display:block">
      <div class="lbl">All Tracker Records →</div>
      <div class="val" style="font-size:13px;color:var(--acc)">View Full Tracker</div>
    </a>
  </div>
</div>


<form method="GET" action="<?php echo e(route('leave')); ?>" class="card" style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end">
  <div class="fld" style="min-width:140px">
    <label>Status</label>
    <select name="status">
      <option value="">All</option>
      <option value="pending"  <?php echo e(request('status') === 'pending'  ? 'selected' : ''); ?>>Pending</option>
      <option value="approved" <?php echo e(request('status') === 'approved' ? 'selected' : ''); ?>>Approved</option>
      <option value="rejected" <?php echo e(request('status') === 'rejected' ? 'selected' : ''); ?>>Rejected</option>
    </select>
  </div>
  <button type="submit" class="btn acc">Filter</button>
  <a href="<?php echo e(route('leave')); ?>" class="btn">Reset</a>
</form>

<div class="card">
  <?php if($applications->isNotEmpty()): ?>
  <table>
    <thead>
      <tr><th>App #</th><th>Employee</th><th>Leave Type</th><th>From</th><th>To</th><th>Days</th><th>Status</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php $__currentLoopData = $applications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $app): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <tr>
        <td class="mono"><?php echo e($app->application_number); ?></td>
        <td><?php echo e($app->employee?->first_name); ?> <?php echo e($app->employee?->last_name); ?></td>
        <td><?php echo e($app->leaveType?->name ?? '—'); ?></td>
        <td><?php echo e(\Carbon\Carbon::parse($app->from_date)->format('d M Y')); ?></td>
        <td><?php echo e(\Carbon\Carbon::parse($app->to_date)->format('d M Y')); ?></td>
        <td><?php echo e($app->days); ?></td>
        <td>
          <?php $color = ['pending'=>'var(--yel)','approved'=>'var(--grn)','rejected'=>'var(--red)'][$app->status] ?? 'var(--t3)'; ?>
          <span class="bx" style="color:<?php echo e($color); ?>"><?php echo e(ucfirst($app->status)); ?></span>
        </td>
        <td>
          <?php if($app->status === 'pending'): ?>
          <form method="POST" action="<?php echo e(route('leave.approve', $app)); ?>" style="display:inline">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="approved">
            <button type="submit" class="btn sm" style="background:var(--grn2);color:var(--grn)">Approve</button>
          </form>
          <form method="POST" action="<?php echo e(route('leave.approve', $app)); ?>" style="display:inline">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="rejected">
            <button type="submit" class="btn sm" style="background:var(--red2);color:var(--red)">Reject</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
  </table>
  <div style="margin-top:14px"><?php echo e($applications->links()); ?></div>
  <?php else: ?>
  <div class="empty"><div class="ico">🏖️</div><p>No leave applications found.</p></div>
  <?php endif; ?>
</div>


<div class="card" style="margin-top:20px">
  <div class="ch" style="flex-wrap:wrap;gap:8px">
    <div class="ct">Leave Tracker Records</div>
    <div style="font-size:11px;color:var(--t3)"><?php echo e(number_format($trackerTotal)); ?> records for this entity</div>
    <div style="margin-left:auto;display:flex;gap:6px">
      <a href="<?php echo e(route('leave.tracker')); ?>" class="btn sm">View All</a>
      <button class="btn sm acc" onclick="om('tracker-import-modal')">📤 Import</button>
    </div>
  </div>

  
  <form method="GET" action="<?php echo e(route('leave')); ?>"
    style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;padding:10px 0 14px;border-bottom:1px solid var(--b2)">
    <div class="fld" style="min-width:120px">
      <label style="font-size:11px">Leave Type</label>
      <select name="tracker_type" style="font-size:12px">
        <option value="">All Types</option>
        <?php $__currentLoopData = $trackerLeaveTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tlt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($tlt); ?>" <?php echo e(request('tracker_type') == $tlt ? 'selected' : ''); ?>><?php echo e($tlt); ?></option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </select>
    </div>
    <div class="fld" style="min-width:140px">
      <label style="font-size:11px">Employee ID / Name</label>
      <input type="text" name="tracker_emp" value="<?php echo e(request('tracker_emp')); ?>"
        placeholder="EMP code or name" style="font-size:12px">
    </div>
    <div class="fld" style="min-width:130px">
      <label style="font-size:11px">Month</label>
      <input type="month" name="tracker_month" value="<?php echo e(request('tracker_month')); ?>" style="font-size:12px">
    </div>
    <button type="submit" class="btn sm acc">Filter</button>
    <a href="<?php echo e(route('leave')); ?>" class="btn sm">Reset</a>
  </form>

  <?php if($trackerGroups->isNotEmpty()): ?>
  <table style="font-size:12px">
    <thead>
      <tr>
        <th>Employee</th>
        <th>EMP Code</th>
        <th>Leave Type</th>
        <th style="text-align:center">Days</th>
        <th>From</th>
        <th>To</th>
        <th>Team Leader</th>
      </tr>
    </thead>
    <tbody>
      <?php $__currentLoopData = $trackerGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $grp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <tr>
        <td>
          <?php if($grp->employee_id): ?>
            <?php $emp = \App\Models\Employee::find($grp->employee_id); ?>
            <?php echo e($emp ? $emp->first_name . ' ' . $emp->last_name : $grp->employee_name); ?>

          <?php else: ?>
            <span style="color:var(--t3);font-style:italic"><?php echo e($grp->employee_name ?? '—'); ?></span>
            <span style="font-size:9px;background:#fef3c7;color:#d97706;border-radius:3px;padding:1px 4px;margin-left:4px">unmatched</span>
          <?php endif; ?>
        </td>
        <td class="mono" style="font-size:11px"><?php echo e($grp->sheet_emp_id ?? '—'); ?></td>
        <td>
          <span class="bx" style="background:var(--acc2);color:var(--acc);font-size:10px">
            <?php echo e($grp->leave_type); ?>

          </span>
        </td>
        <td class="mono" style="text-align:center;font-weight:600"><?php echo e($grp->total_days); ?></td>
        <td class="mono" style="white-space:nowrap;font-size:11px">
          <?php echo e(\Carbon\Carbon::parse($grp->first_date)->format('d M Y')); ?>

        </td>
        <td class="mono" style="white-space:nowrap;font-size:11px">
          <?php echo e(\Carbon\Carbon::parse($grp->last_date)->format('d M Y')); ?>

        </td>
        <td style="color:var(--t2);font-size:11px"><?php echo e($grp->team_leader_name ?? '—'); ?></td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
  </table>
  <div style="margin-top:12px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
    <?php echo e($trackerGroups->appends(request()->except('tracker_page'))->links()); ?>

    <a href="<?php echo e(route('leave.tracker')); ?>" class="btn sm" style="flex-shrink:0">
      View Day-wise Detail (<?php echo e(number_format($trackerTotal)); ?> entries) →
    </a>
  </div>
  <?php else: ?>
  <div class="empty" style="padding:24px">
    <div class="ico">📋</div>
    <p>No tracker records found<?php echo e(request()->hasAny(['tracker_type','tracker_emp','tracker_month']) ? ' for this filter' : ' yet'); ?>.</p>
    <?php if(!request()->hasAny(['tracker_type','tracker_emp','tracker_month'])): ?>
    <p style="font-size:12px;color:var(--t3);margin-top:4px">
      Click <strong>📤 Import</strong> to upload the Leave Tracker xlsm file.
    </p>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>


<div class="modal" id="leave-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Apply Leave</span>
      <button class="mc" onclick="cm('leave-modal')">✕</button>
    </div>
    <form method="POST" action="<?php echo e(route('leave.apply')); ?>">
      <?php echo csrf_field(); ?>
      <div class="fg2">
        <div class="fld full">
          <label>Employee (leave blank for self)</label>
          <select name="employee_id">
            <option value="">— Self —</option>
            <?php $__currentLoopData = $employees; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $emp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($emp->id); ?>"><?php echo e($emp->first_name); ?> <?php echo e($emp->last_name); ?> (<?php echo e($emp->employee_id); ?>)</option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
        </div>
        <div class="fld full">
          <label class="req">Leave Type</label>
          <select name="leave_type_id" required
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
            <option value="<?php echo e($lt->id); ?>" <?php echo e(old('leave_type_id') == $lt->id ? 'selected' : ''); ?>><?php echo e($lt->name); ?> (<?php echo e($lt->code); ?>)</option>
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
          <label class="req">From Date</label>
          <input type="date" name="from_date" required value="<?php echo e(old('from_date')); ?>"
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
        <div class="fld">
          <label class="req">To Date</label>
          <input type="date" name="to_date" required value="<?php echo e(old('to_date')); ?>"
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

<?php if($errors->any()): ?>
<script>document.addEventListener('DOMContentLoaded',()=>om('leave-modal'))</script>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/leave/index.blade.php ENDPATH**/ ?>