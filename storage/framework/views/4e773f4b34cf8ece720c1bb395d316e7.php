<?php $__env->startSection('title', $from ? 'Attendance Report — ' . $from->format('F Y') : 'Attendance Report'); ?>

<?php $__env->startSection('page_actions'); ?>
  <a href="<?php echo e(route('reports')); ?>" class="btn">← Reports</a>
  <?php if($from && $rows->total() > 0): ?>
  <button class="btn" onclick="om('att-reupload-modal')">📤 Re-upload Approvals</button>
  <a href="<?php echo e(route('reports.attendance.export', array_filter(['month' => $monthStr, 'emp_id' => $empFilter]))); ?>"
     class="btn acc">📥 Export Excel</a>
  <?php endif; ?>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>


<form method="GET" action="<?php echo e(route('reports.attendance')); ?>" class="card"
      style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
  <div class="fld" style="min-width:160px">
    <label>Month *</label>
    <input type="month" name="month" value="<?php echo e($monthStr ?? now()->format('Y-m')); ?>" required>
  </div>
  <div class="fld" style="min-width:220px">
    <label>Employee (optional)</label>
    <select name="emp_id">
      <option value="">All Employees</option>
      <?php $__currentLoopData = $allEmployees; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <option value="<?php echo e($e->employee_id); ?>" <?php echo e($empFilter == $e->employee_id ? 'selected' : ''); ?>>
        <?php echo e($e->full_name); ?> (<?php echo e($e->employee_id); ?>)
      </option>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select>
  </div>
  <button type="submit" class="btn acc">Generate</button>
</form>


<?php if($monthStr): ?>
<form method="GET" action="<?php echo e(route('reports.attendance')); ?>" data-ajax-filter class="card"
      style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
  <input type="hidden" name="month" value="<?php echo e($monthStr); ?>">
  <input type="hidden" name="emp_id" value="<?php echo e($empFilter); ?>">
  <div class="fld" style="min-width:200px">
    <label>Search (name / emp ID)</label>
    <input type="text" name="search" value="<?php echo e($search ?? ''); ?>" placeholder="e.g. Dhruv or 201818002">
  </div>
  <button type="submit" class="btn acc">Search</button>
  <a href="<?php echo e(route('reports.attendance')); ?>?month=<?php echo e($monthStr); ?>" class="btn" data-ajax-reset>Reset Filter</a>
</form>
<?php endif; ?>

<div id="ajax-results">
<?php if(!$monthStr): ?>

<div class="card">
  <div class="empty">
    <div class="ico">📊</div>
    <p>Select a month above to generate the attendance report.</p>
  </div>
</div>

<?php elseif($rows->total() === 0): ?>
<div class="card">
  <div class="empty">
    <div class="ico">🕐</div>
    <p><?php echo e($search ? 'No rows match "' . $search . '".' : 'No attendance data found for ' . $from->format('F Y') . '.'); ?></p>
    <p style="font-size:12px;color:var(--t3)">
      <?php echo e($search ? 'Try a different name or employee ID.' : 'Make sure attendance has been imported for this period.'); ?>

    </p>
  </div>
</div>

<?php else: ?>


<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;font-size:12px">
  <span class="bx" style="background:var(--s2)">Total Rows: <strong><?php echo e($summary['total']); ?></strong></span>
  <span class="bx" style="background:#f0fdf4;color:#16a34a">Present / On Time: <strong><?php echo e($summary['present'] - $summary['late']); ?></strong></span>
  <span class="bx" style="background:#fff7ed;color:#c05621">Late In: <strong><?php echo e($summary['late']); ?></strong></span>
  <span class="bx" style="background:#fef2f2;color:#dc2626">Punch Missing: <strong><?php echo e($summary['missing']); ?></strong></span>
  <span class="bx" style="background:#eff6ff;color:#2563eb">Leave Days: <strong><?php echo e($summary['leave']); ?></strong></span>
</div>


<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;font-size:11px;align-items:center">
  <span style="color:var(--t3)">Legend:</span>
  <?php $__currentLoopData = [
    ['On Time',       '#16a34a','#f0fdf4'],
    ['LateTimeIn',    '#c05621','#fff7ed'],
    ['Punch Missing', '#dc2626','#fef2f2'],
    ['Leave',         '#2563eb','#eff6ff'],
    ['Weekend',       '#6b7280','#f3f4f6'],
  ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label,$color,$bg]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:4px;background:<?php echo e($bg); ?>;color:<?php echo e($color); ?>;font-weight:600"><?php echo e($label); ?></span>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>


<div class="card" style="padding:0;overflow:hidden">
  <div style="overflow-x:auto;max-height:75vh;overflow-y:auto">
    <table style="font-size:11px;min-width:1500px;border-collapse:collapse">
      
      <?php
        $thStyle = 'padding:8px 10px;white-space:nowrap;background:#3b7de8;color:#fff;border-right:1px solid rgba(255,255,255,.2);text-align:left';
        // Sortable column header: link toggles asc/desc on this column,
        // preserves month/emp_id/search, always resets to page 1.
        $sortLink = function (string $col, string $label) use ($sortBy, $sortDir) {
            $nextDir = ($sortBy === $col && $sortDir === 'asc') ? 'desc' : 'asc';
            $arrow = $sortBy === $col ? ($sortDir === 'asc' ? ' ▲' : ' ▼') : '';
            $url = request()->fullUrlWithQuery(['sort' => $col, 'dir' => $nextDir, 'page' => 1]);
            return "<a href=\"{$url}\" style=\"color:inherit;text-decoration:none\">{$label}{$arrow}</a>";
        };
      ?>
      <thead>
        <tr>
          <th style="<?php echo e($thStyle); ?>">Month</th>
          <th style="<?php echo e($thStyle); ?>"><?php echo $sortLink('date_ymd', 'Date'); ?></th>
          <th style="<?php echo e($thStyle); ?>">Day Name</th>
          <th style="<?php echo e($thStyle); ?>"><?php echo $sortLink('emp_id', 'New Emp ID'); ?></th>
          <th style="<?php echo e($thStyle); ?>"><?php echo $sortLink('emp_name', 'Employee Name'); ?></th>
          <th style="<?php echo e($thStyle); ?>;text-align:center">TimeIn</th>
          <th style="<?php echo e($thStyle); ?>;text-align:center">TimeOut</th>
          <th style="<?php echo e($thStyle); ?>"><?php echo $sortLink('punch_in_type', 'Punch In Type'); ?></th>
          <th style="<?php echo e($thStyle); ?>"><?php echo $sortLink('punch_out_type', 'Punch Out Type'); ?></th>
          <th style="<?php echo e($thStyle); ?>;text-align:center">Clocked Time</th>
          <th style="<?php echo e($thStyle); ?>">CT_Remark</th>
          <th style="<?php echo e($thStyle); ?>"><?php echo $sortLink('leave_type', 'Leave Type'); ?></th>
          <th style="<?php echo e($thStyle); ?>">Reason For Leave</th>
          <th style="<?php echo e($thStyle); ?>;text-align:center">Punch Deviation</th>
          <th style="<?php echo e($thStyle); ?>;text-align:center">Punch In Approval</th>
          <th style="<?php echo e($thStyle); ?>;text-align:center;border-right:none">Punch Out Approval</th>
        </tr>
      </thead>
      <tbody>
        <?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php
          if ($r['is_weekend']) {
              $rowBg = '#f3f4f6';
          } elseif ($r['has_leave']) {
              $rowBg = '#eff6ff';
          } elseif ($i % 2 === 0) {
              $rowBg = '#f4fce8';
          } else {
              $rowBg = '#ffffff';
          }

          $piColor = match($r['punch_in_type']) {
              'On Time'       => '#16a34a',
              'LateTimeIn'    => '#c05621',
              'Punch Missing' => '#dc2626',
              default         => 'inherit',
          };
          $poColor = $r['punch_out_type'] === 'Punch Missing' ? '#dc2626' : '#16a34a';
        ?>
        <tr style="background:<?php echo e($rowBg); ?>;border-bottom:1px solid #e5e7eb">
          <td style="padding:5px 10px;white-space:nowrap;color:var(--t3)"><?php echo e($r['month']); ?></td>
          <td style="padding:5px 10px;white-space:nowrap;font-weight:500"><?php echo e($r['date']); ?></td>
          <td style="padding:5px 10px;white-space:nowrap;color:var(--t2)"><?php echo e($r['day_name']); ?></td>
          <td style="padding:5px 10px;white-space:nowrap;font-family:monospace"><?php echo e($r['emp_id']); ?></td>
          <td style="padding:5px 10px;white-space:nowrap;font-weight:500"><?php echo e($r['emp_name']); ?></td>
          <td style="padding:5px 10px;text-align:center;white-space:nowrap;font-family:monospace">
            <?php echo e($r['time_in'] ?: '—'); ?>

          </td>
          <td style="padding:5px 10px;text-align:center;white-space:nowrap;font-family:monospace">
            <?php echo e($r['time_out'] ?: '—'); ?>

          </td>
          <td style="padding:5px 10px;white-space:nowrap;font-weight:600;color:<?php echo e($piColor); ?>">
            <?php echo e($r['punch_in_type']); ?>

          </td>
          <td style="padding:5px 10px;white-space:nowrap;font-weight:600;color:<?php echo e($poColor); ?>">
            <?php echo e($r['punch_out_type']); ?>

          </td>
          <td style="padding:5px 10px;text-align:center;white-space:nowrap;font-family:monospace;font-size:10px">
            <?php echo e($r['clocked_time'] ?: '—'); ?>

          </td>
          <td style="padding:5px 10px;white-space:nowrap;font-size:10px;color:<?php echo e($r['ct_remark'] === '>9:00 Hours' ? '#16a34a' : ($r['ct_remark'] === '< 9:00 Hours' ? '#c05621' : 'var(--t3)')); ?>">
            <?php echo e($r['ct_remark']); ?>

          </td>
          <td style="padding:5px 10px;white-space:nowrap">
            <?php if($r['leave_type']): ?>
            <span class="bx" style="font-size:10px;background:#dbeafe;color:#1d4ed8"><?php echo e($r['leave_type']); ?></span>
            <?php else: ?>
            <span style="color:var(--t3)">—</span>
            <?php endif; ?>
          </td>
          <td style="padding:5px 10px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--t2);font-size:10px"
              title="<?php echo e($r['reason_for_leave']); ?>">
            <?php echo e($r['reason_for_leave'] ?: '—'); ?>

          </td>
          <td style="padding:5px 10px;text-align:center;white-space:nowrap;font-size:10px;color:<?php echo e($r['punch_deviation'] ? '#c05621' : 'var(--t3)'); ?>">
            <?php echo e($r['punch_deviation'] ?: '—'); ?>

          </td>
          
          <td style="padding:3px 6px;text-align:center">
            <?php if($r['has_punch']): ?>
            <select class="appr-sel" data-emp="<?php echo e($r['internal_emp_id']); ?>" data-date="<?php echo e($r['date_ymd']); ?>" data-field="punch_in_approval"
                    style="font-size:10px;padding:2px 4px;border-radius:4px;border:1px solid #d1d5db;cursor:pointer;
                           background:<?php echo e($r['punch_in_approval']==='not_approved' ? '#fef2f2' : '#f0fdf4'); ?>;
                           color:<?php echo e($r['punch_in_approval']==='not_approved' ? '#dc2626' : '#16a34a'); ?>">
              <option value="approved"     <?php echo e($r['punch_in_approval'] !== 'not_approved' ? 'selected' : ''); ?>>Approved</option>
              <option value="not_approved" <?php echo e($r['punch_in_approval'] === 'not_approved' ? 'selected' : ''); ?>>Not Approved</option>
            </select>
            <?php else: ?>
            <span style="color:var(--t3);font-size:10px">—</span>
            <?php endif; ?>
          </td>
          
          <td style="padding:3px 6px;text-align:center">
            <?php if($r['has_punch']): ?>
            <select class="appr-sel" data-emp="<?php echo e($r['internal_emp_id']); ?>" data-date="<?php echo e($r['date_ymd']); ?>" data-field="punch_out_approval"
                    style="font-size:10px;padding:2px 4px;border-radius:4px;border:1px solid #d1d5db;cursor:pointer;
                           background:<?php echo e($r['punch_out_approval']==='not_approved' ? '#fef2f2' : '#f0fdf4'); ?>;
                           color:<?php echo e($r['punch_out_approval']==='not_approved' ? '#dc2626' : '#16a34a'); ?>">
              <option value="approved"     <?php echo e($r['punch_out_approval'] !== 'not_approved' ? 'selected' : ''); ?>>Approved</option>
              <option value="not_approved" <?php echo e($r['punch_out_approval'] === 'not_approved' ? 'selected' : ''); ?>>Not Approved</option>
            </select>
            <?php else: ?>
            <span style="color:var(--t3);font-size:10px">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </tbody>
    </table>
  </div>
  <div style="padding:10px 14px;font-size:11px;color:var(--t3);border-top:1px solid var(--b1)">
    Showing <?php echo e($rows->firstItem()); ?>–<?php echo e($rows->lastItem()); ?> of <?php echo e($rows->total()); ?> rows · <?php echo e($from->format('F Y')); ?> · Standard start time: 10:00 AM
    <?php if($empFilter): ?> · Filtered: <?php echo e($empFilter); ?> <?php endif; ?>
    <?php if($search): ?> · Search: "<?php echo e($search); ?>" <?php endif; ?>
  </div>
  <div style="padding:10px 14px;border-top:1px solid var(--b1)">
    <?php echo e($rows->links()); ?>

  </div>
</div>

<?php endif; ?> 
</div> 


<div class="modal" id="att-reupload-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">📤 Re-upload Attendance Approvals</span>
      <button class="mc" onclick="cm('att-reupload-modal')">✕</button>
    </div>
    <div style="background:#fefce8;border:1px solid #fde047;border-radius:6px;padding:8px 12px;margin-bottom:12px;font-size:11px;color:#854d0e;line-height:1.6">
      <strong>Only these two columns are updated from the file — all others are ignored:</strong><br>
      ✏ <strong>Punch In Approval</strong> &nbsp;·&nbsp; ✏ <strong>Punch Out Approval</strong><br>
      <span style="font-size:10px;color:#92400e">
        Accepted values: <strong>Approved</strong> or <strong>Not Approved</strong> (use the dropdown in the Excel file).
        Rows are matched by <strong>New Emp ID + Date</strong>. Month must match <?php echo e($monthStr ? \Carbon\Carbon::createFromFormat('Y-m',$monthStr)->format('F Y') : 'the selected month'); ?>.
      </span>
    </div>
    <form method="POST" action="<?php echo e(route('reports.attendance.reupload')); ?>" enctype="multipart/form-data">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="expected_month" value="<?php echo e($monthStr); ?>">
      <div class="fld" style="margin-bottom:14px">
        <label class="req">Edited Excel File (.xlsx)</label>
        <input type="file" name="file" accept=".xlsx,.xls" required
          style="background:var(--s2);border:1.5px dashed var(--b2);border-radius:8px;padding:14px;cursor:pointer;width:100%">
        <div style="font-size:11px;color:var(--t3);margin-top:5px">
          Upload the attendance Excel that was exported and edited. The <strong>New Emp ID</strong>, <strong>Date</strong>, and the two approval columns must be intact.
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('att-reupload-modal')">Cancel</button>
        <button type="submit" class="btn acc">📤 Update Approvals</button>
      </div>
    </form>
  </div>
</div>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
// ── Attendance Approval dropdowns — AJAX save on change ─────────────────────
(function () {
  const APPROVE_URL = '<?php echo e(route("reports.attendance.approve")); ?>';
  const CSRF        = '<?php echo e(csrf_token()); ?>';

  const BG  = { approved: '#f0fdf4', not_approved: '#fef2f2', '': '#fffbeb' };
  const CLR = { approved: '#16a34a', not_approved: '#dc2626', '': '#92400e' };

  function styleSelect(sel) {
    const v = sel.value;
    sel.style.background = BG[v]  ?? '#fffbeb';
    sel.style.color      = CLR[v] ?? '#92400e';
  }

  const saving = new WeakMap();

  document.addEventListener('change', async function (e) {
    const sel = e.target;
    if (!sel.classList.contains('appr-sel')) return;
    if (saving.get(sel)) return;
    saving.set(sel, true);
    sel.style.opacity = '0.5';

    try {
      const res = await fetch(APPROVE_URL, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': CSRF,
          'Accept':       'application/json',
        },
        body: JSON.stringify({
          employee_id:     sel.dataset.emp,
          attendance_date: sel.dataset.date,
          [sel.dataset.field]: sel.value || null,
        }),
      });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      styleSelect(sel);
    } catch (err) {
      console.error('Approval save failed', err);
      sel.value = sel.dataset.prev ?? '';
      styleSelect(sel);
      Swal.fire({ icon: 'error', title: 'Save Failed', text: 'Failed to save approval. Please try again.', confirmButtonColor: '#dc2626' });
    } finally {
      sel.style.opacity = '1';
      saving.set(sel, false);
    }
  });

  // Cache previous value before the dropdown opens (for rollback on error)
  document.addEventListener('mousedown', function (e) {
    if (e.target.classList.contains('appr-sel')) {
      e.target.dataset.prev = e.target.value;
    }
  });

  // Style all dropdowns on page load
  document.querySelectorAll('.appr-sel').forEach(styleSelect);
})();
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/reports/attendance.blade.php ENDPATH**/ ?>