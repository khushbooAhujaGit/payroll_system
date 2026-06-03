<?php $__env->startSection('title', 'Leave Tracker Records'); ?>

<?php $__env->startSection('page_actions'); ?>
  <a href="<?php echo e(route('leave')); ?>" class="btn">← Leave Management</a>
  <button class="btn" onclick="om('tracker-add-modal')">+ Add Manually</button>
  <a href="<?php echo e(route('leave.tracker.export') . '?' . http_build_query(request()->only('emp_id','leave_type','month','date_from','date_to'))); ?>" class="btn">⬇ Export</a>
  <button class="btn acc" onclick="om('tracker-import-modal')">📤 Import / Re-import</button>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<?php $isDaily = ($view ?? 'spells') === 'daily'; ?>

<div class="g4" style="margin-bottom:16px">
  <div class="sc">
    <div class="lbl">Total Leave Days (all)</div>
    <div class="val"><?php echo e(number_format($totalRecords)); ?></div>
  </div>
  <div class="sc">
    <div class="lbl"><?php echo e($isDaily ? 'Filtered Day Records' : 'Leave Spells (filtered)'); ?></div>
    <div class="val"><?php echo e(number_format($isDaily ? $records->total() : $spells->total())); ?></div>
  </div>
  <div class="sc" style="grid-column:span 2">
    <div class="lbl">View</div>
    <div style="display:flex;gap:6px;margin-top:4px">
      <a href="<?php echo e(request()->fullUrlWithQuery(['view' => 'spells', 'page' => null])); ?>"
         class="btn sm <?php echo e(!$isDaily ? 'acc' : ''); ?>">Spell Groups</a>
      <a href="<?php echo e(request()->fullUrlWithQuery(['view' => 'daily', 'page' => null])); ?>"
         class="btn sm <?php echo e($isDaily ? 'acc' : ''); ?>">Day-wise</a>
    </div>
    <?php if(!$isDaily): ?>
    <div style="font-size:10px;color:var(--t3);margin-top:4px">
      Consecutive days (bridging weekends) shown as one row. Gaps = separate rows.
    </div>
    <?php endif; ?>
  </div>
</div>


<form method="GET" action="<?php echo e(route('leave.tracker')); ?>" class="card"
      style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
  <input type="hidden" name="view" value="<?php echo e($view ?? 'spells'); ?>">
  
  <?php if(request('date_from')): ?><input type="hidden" name="date_from" value="<?php echo e(request('date_from')); ?>"><?php endif; ?>
  <?php if(request('date_to')): ?><input type="hidden" name="date_to" value="<?php echo e(request('date_to')); ?>"><?php endif; ?>
  <div class="fld" style="min-width:160px">
    <label>Employee ID / Name</label>
    <input type="text" name="emp_id" value="<?php echo e(request('emp_id')); ?>" placeholder="EMP code or name">
  </div>
  <div class="fld" style="min-width:130px">
    <label>Leave Type</label>
    <select name="leave_type">
      <option value="">All Types</option>
      <?php $__currentLoopData = $leaveTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $lt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <option value="<?php echo e($lt); ?>" <?php echo e(request('leave_type') === $lt ? 'selected' : ''); ?>><?php echo e($lt); ?></option>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select>
  </div>
  <div class="fld" style="min-width:140px">
    <label>Month</label>
    <input type="month" name="month" value="<?php echo e(request('month')); ?>">
  </div>
  <?php if(request('date_from')): ?>
  <div style="font-size:11px;color:var(--acc);padding-bottom:4px">
    📅 <?php echo e(\Carbon\Carbon::parse(request('date_from'))->format('d M')); ?>

    <?php if(request('date_from') !== request('date_to')): ?> – <?php echo e(\Carbon\Carbon::parse(request('date_to'))->format('d M Y')); ?>

    <?php else: ?><?php echo e(\Carbon\Carbon::parse(request('date_from'))->format(' Y')); ?><?php endif; ?>
    <a href="<?php echo e(route('leave.tracker', ['view' => 'daily', 'emp_id' => request('emp_id'), 'leave_type' => request('leave_type')])); ?>"
       style="margin-left:8px;color:var(--t3)">✕ clear dates</a>
  </div>
  <?php endif; ?>
  <button type="submit" class="btn acc">Filter</button>
  <a href="<?php echo e(route('leave.tracker', ['view' => $view ?? 'spells'])); ?>" class="btn">Reset</a>
</form>

<div class="card">

<?php if($isDaily): ?>
  
  <?php if($records->isNotEmpty()): ?>
  <table style="font-size:12px">
    <thead>
      <tr>
        <th>Ref ID</th>
        <th>EMP Code</th>
        <th>Employee</th>
        <th>Date</th>
        <th>Leave Type</th>
        <th>Team Leader</th>
        <th>Reason Type</th>
        <th>Reason</th>
        <th style="text-align:center">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php $__currentLoopData = $records; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rec): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <tr>
        <td class="mono" style="font-size:10px;color:var(--t3)"><?php echo e($rec->leave_ref_id); ?></td>
        <td class="mono" style="font-size:11px"><?php echo e($rec->sheet_emp_id ?? '—'); ?></td>
        <td>
          <?php if($rec->employee): ?>
            <?php echo e($rec->employee->full_name); ?>

            <span style="font-size:10px;color:var(--t3)">(<?php echo e($rec->employee->employee_id); ?>)</span>
          <?php elseif($rec->employee_name): ?>
            <?php echo e($rec->employee_name); ?>

            <span class="bx" style="font-size:9px;color:var(--yel);margin-left:4px">unmatched</span>
          <?php else: ?>
            <span style="color:var(--t3)">—</span>
          <?php endif; ?>
        </td>
        <td class="mono" style="white-space:nowrap"><?php echo e(\Carbon\Carbon::parse($rec->leave_date)->format('d M Y')); ?></td>
        <td>
          <span class="bx" style="background:<?php echo e(strtoupper($rec->leave_type) === 'LOP' ? 'var(--red2)' : 'var(--acc2)'); ?>;
                                   color:<?php echo e(strtoupper($rec->leave_type) === 'LOP' ? 'var(--red)' : 'var(--acc)'); ?>">
            <?php echo e($rec->leave_type); ?>

          </span>
        </td>
        <td style="font-size:11px;color:var(--t2)"><?php echo e($rec->team_leader_name ?? '—'); ?></td>
        <td style="font-size:11px;color:var(--t2);text-transform:capitalize"><?php echo e(str_replace('_', ' ', $rec->reason_type ?? '—')); ?></td>
        <td style="font-size:11px;color:var(--t2);max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
            title="<?php echo e($rec->reason); ?>"><?php echo e($rec->reason ?? '—'); ?></td>
        <td style="white-space:nowrap;text-align:center">
          
          <button class="btn sm" style="font-size:10px;padding:3px 8px"
            onclick="openTrackerEdit(
              <?php echo e($rec->id); ?>,
              '<?php echo e($rec->leave_date?->format('Y-m-d')); ?>',
              '<?php echo e(strtoupper($rec->leave_type)); ?>',
              '<?php echo e(addslashes($rec->team_leader_name ?? '')); ?>',
              '<?php echo e(addslashes($rec->reason_type ?? '')); ?>',
              '<?php echo e(addslashes($rec->reason ?? '')); ?>'
            )">Edit</button>
          
          <form method="POST" action="<?php echo e(route('leave.tracker.destroy', $rec->id)); ?>" style="display:inline"
                onsubmit="return confirm('Delete this single day record?')">
            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
            <button type="submit" class="btn sm" style="font-size:10px;padding:3px 8px;color:var(--red)">Del</button>
          </form>
        </td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
  </table>
  <div style="margin-top:14px"><?php echo e($records->links()); ?></div>
  <?php else: ?>
  <div class="empty"><div class="ico">📋</div><p>No records found for this filter.</p></div>
  <?php endif; ?>

<?php else: ?>
  
  <?php if($spells->isNotEmpty()): ?>
  <table style="font-size:12px">
    <thead>
      <tr>
        <th>Employee</th>
        <th>EMP Code</th>
        <th>Leave Type</th>
        <th style="text-align:center">Days</th>
        <th>Start Date</th>
        <th>End Date</th>
        <th>Team Leader</th>
        <th>Reason Type</th>
        <th style="text-align:center">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php $__currentLoopData = $spells; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <?php $isSingle = $sp['start_date']->eq($sp['end_date']); ?>
      <tr>
        <td>
          <?php if($sp['employee']): ?>
            <?php echo e($sp['employee']->full_name); ?>

          <?php else: ?>
            <span style="color:var(--t3);font-style:italic"><?php echo e($sp['employee_name'] ?? '—'); ?></span>
            <span style="font-size:9px;background:#fef3c7;color:#d97706;border-radius:3px;padding:1px 4px;margin-left:4px">unmatched</span>
          <?php endif; ?>
        </td>
        <td class="mono" style="font-size:11px"><?php echo e($sp['sheet_emp_id'] ?? '—'); ?></td>
        <td><span class="bx" style="background:var(--acc2);color:var(--acc)"><?php echo e($sp['leave_type']); ?></span></td>
        <td class="mono" style="text-align:center;font-weight:700"><?php echo e($sp['day_count']); ?></td>
        <td class="mono" style="white-space:nowrap"><?php echo e($sp['start_date']->format('d M Y')); ?></td>
        <td class="mono" style="white-space:nowrap;color:<?php echo e($isSingle ? 'var(--t3)' : 'inherit'); ?>">
          <?php echo e($isSingle ? '—' : $sp['end_date']->format('d M Y')); ?>

        </td>
        <td style="font-size:11px;color:var(--t2)"><?php echo e($sp['team_leader_name'] ?? '—'); ?></td>
        <td style="font-size:11px;color:var(--t2);text-transform:capitalize">
          <?php echo e(str_replace('_', ' ', $sp['reason_type'] ?? '—')); ?>

        </td>
        <td style="white-space:nowrap;text-align:center">
          
          <a href="<?php echo e(route('leave.tracker', ['view' => 'daily', 'emp_id' => $sp['sheet_emp_id'], 'leave_type' => $sp['leave_type'], 'date_from' => $sp['start_date']->format('Y-m-d'), 'date_to' => $sp['end_date']->format('Y-m-d')])); ?>"
             class="btn sm" style="font-size:10px;padding:3px 8px"><?php echo e($sp['day_count']); ?> Days →</a>
          
          <form method="POST" action="<?php echo e(route('leave.tracker.bulk_destroy')); ?>" style="display:inline"
                onsubmit="return confirm('Delete all <?php echo e($sp['day_count']); ?> day(s) of <?php echo e($sp['leave_type']); ?> for this employee?')">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="sheet_emp_id" value="<?php echo e($sp['sheet_emp_id']); ?>">
            <input type="hidden" name="leave_type"   value="<?php echo e($sp['leave_type']); ?>">
            <input type="hidden" name="date_from"    value="<?php echo e($sp['start_date']->format('Y-m-d')); ?>">
            <input type="hidden" name="date_to"      value="<?php echo e($sp['end_date']->format('Y-m-d')); ?>">
            <button type="submit" class="btn sm" style="font-size:10px;padding:3px 8px;color:var(--red)">Del Spell</button>
          </form>
        </td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
  </table>
  <div style="margin-top:14px"><?php echo e($spells->links()); ?></div>
  <?php else: ?>
  <div class="empty">
    <div class="ico">📋</div>
    <p>No tracker records found.</p>
    <p style="font-size:12px;color:var(--t3);margin-top:4px">Import the Leave Tracker xlsm file using the button above.</p>
  </div>
  <?php endif; ?>
<?php endif; ?>

</div>


<div class="modal" id="tracker-add-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Add Leave Record Manually</span>
      <button class="mc" onclick="cm('tracker-add-modal')">✕</button>
    </div>
    <div style="background:#fef3c7;border:1px solid #d97706;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:12px;color:#92400e">
      <strong>LOP Note:</strong> If an employee took leave without approval, select <strong>LOP</strong> as leave type.
      If a record already exists for that date, it will be replaced with LOP.
    </div>
    <form method="POST" action="<?php echo e(route('leave.tracker.store')); ?>">
      <?php echo csrf_field(); ?>
      <div class="fg2">
        <div class="fld">
          <label>EMP ID (employee_id) *</label>
          <input type="text" name="sheet_emp_id" required placeholder="e.g. 202118007">
        </div>
        <div class="fld">
          <label>Leave Date *</label>
          <input type="date" name="leave_date" required>
        </div>
        <div class="fld">
          <label>Leave Type *</label>
          <select name="leave_type" required>
            <option value="">Select type</option>
            <?php $__currentLoopData = $leaveTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $lt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($lt); ?>"><?php echo e($lt); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <option value="LOP">LOP (Loss of Pay)</option>
            <option value="WFH">WFH</option>
          </select>
        </div>
        <div class="fld">
          <label>Employee Name</label>
          <input type="text" name="employee_name" placeholder="Optional — auto-filled if EMP matched">
        </div>
        <div class="fld">
          <label>Team Leader</label>
          <input type="text" name="team_leader_name">
        </div>
        <div class="fld">
          <label>Reason Type</label>
          <input type="text" name="reason_type" placeholder="e.g. Personal, Medical">
        </div>
        <div class="fld full">
          <label>Reason</label>
          <input type="text" name="reason" placeholder="Brief reason">
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('tracker-add-modal')">Cancel</button>
        <button type="submit" class="btn acc">Add Record</button>
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
      <strong>Upload the Leave Tracker .xlsm file</strong><br>
      Reads the <strong>"Mark Leave"</strong> sheet. If the file has a single sheet, any sheet name is accepted.<br>
      Each row = one day of leave. Re-importing is safe — existing records are updated, not duplicated.
    </div>
    <form method="POST" action="<?php echo e(route('leave.tracker.import')); ?>" enctype="multipart/form-data">
      <?php echo csrf_field(); ?>
      <div class="fg2" style="margin-bottom:14px">
        <div class="fld">
          <label>Leave Month *</label>
          <input type="month" name="import_month" required value="<?php echo e(now()->format('Y-m')); ?>"
                 style="padding:9px 12px;border:1.5px solid var(--b1);border-radius:8px;background:#f9fafb;font-family:inherit;font-size:13px;color:var(--txt);outline:none;width:100%">
          <span style="font-size:10px;color:var(--t3)">Month the leave data belongs to</span>
        </div>
        <div class="fld">
          <label>Leave Tracker File (.xlsm / .xlsx / .xls) *</label>
          <input type="file" name="file" accept=".xlsm,.xlsx,.xls" required
            style="background:var(--s2);border:1.5px dashed var(--b2);border-radius:8px;padding:10px;cursor:pointer;width:100%">
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('tracker-import-modal')">Cancel</button>
        <button type="submit" class="btn acc">📤 Import</button>
      </div>
    </form>
  </div>
</div>


<div class="modal" id="tracker-edit-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Edit Leave Record</span>
      <button class="mc" onclick="cm('tracker-edit-modal')">✕</button>
    </div>
    <form method="POST" id="tracker-edit-form" action="">
      <?php echo csrf_field(); ?>
      <div class="fg2">
        <div class="fld">
          <label>Leave Date *</label>
          <input type="date" name="leave_date" id="edit-leave-date" required>
        </div>
        <div class="fld">
          <label>Leave Type *</label>
          <select name="leave_type" id="edit-leave-type" required>
            <?php $__currentLoopData = $leaveTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $lt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($lt); ?>"><?php echo e($lt); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <option value="LOP">LOP (Loss of Pay)</option>
            <option value="WFH">WFH</option>
          </select>
        </div>
        <div class="fld">
          <label>Team Leader</label>
          <input type="text" name="team_leader_name" id="edit-team-leader">
        </div>
        <div class="fld">
          <label>Reason Type</label>
          <input type="text" name="reason_type" id="edit-reason-type" placeholder="e.g. Personal, Medical">
        </div>
        <div class="fld full">
          <label>Reason</label>
          <input type="text" name="reason" id="edit-reason">
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('tracker-edit-modal')">Cancel</button>
        <button type="submit" class="btn acc">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<?php $__env->startPush('scripts'); ?>
<script>
function openTrackerEdit(id, date, leaveType, teamLeader, reasonType, reason) {
  document.getElementById('tracker-edit-form').action = '/leave/tracker/' + id;
  document.getElementById('edit-leave-date').value    = date;
  document.getElementById('edit-team-leader').value   = teamLeader;
  document.getElementById('edit-reason-type').value   = reasonType;
  document.getElementById('edit-reason').value        = reason;
  om('tracker-edit-modal');
  // Set <select> AFTER the modal is in the render tree (display:none→flex resets <select>
  // to its first option in Chrome). Use requestAnimationFrame so the element is painted
  // before we write to it, and set option.selected directly to bypass the .value reset.
  requestAnimationFrame(function() {
    const sel    = document.getElementById('edit-leave-type');
    const target = (leaveType || '').toUpperCase();
    Array.from(sel.options).forEach(function(opt) {
      opt.selected = opt.value.toUpperCase() === target;
    });
    if (sel._ssSync) sel._ssSync(); // refresh the searchable-select display input
  });
}
</script>
<?php $__env->stopPush(); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/leave/tracker.blade.php ENDPATH**/ ?>