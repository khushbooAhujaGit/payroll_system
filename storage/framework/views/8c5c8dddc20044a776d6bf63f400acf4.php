<?php $monthLabel = \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y'); ?>

<?php $__env->startSection('title', 'Reimbursement Report'); ?>

<?php $__env->startSection('page_actions'); ?>
  <a href="<?php echo e(route('reimbursements.report.saved')); ?>" class="btn">🗂 Saved Reports</a>
  <a href="<?php echo e(route('reimbursements.tracker', ['month' => $month])); ?>" class="btn">← Tracker</a>
  <?php if($isLocked): ?>
    
    <form method="POST" action="<?php echo e(route('reimbursements.report.download')); ?>" style="display:inline">
      <?php echo csrf_field(); ?> <input type="hidden" name="month" value="<?php echo e($month); ?>">
      <button type="submit" class="btn">📥 Download Excel</button>
    </form>
    
    <form method="POST" action="<?php echo e(route('reimbursements.report.generate')); ?>" style="display:inline">
      <?php echo csrf_field(); ?> <input type="hidden" name="month" value="<?php echo e($month); ?>">
      <button type="button" class="btn acc"
        onclick="swConfirm(this.closest('form'), 'Re-save report?',
          'This will regenerate the snapshot for <?php echo e($monthLabel); ?> from current approved rows, replacing the existing saved report.',
          'question', 'Yes, Re-save', '#2563eb')">
        🔄 Re-save Report
      </button>
    </form>
    
    <form method="POST" action="<?php echo e(route('reimbursements.report.unlock')); ?>" style="display:inline">
      <?php echo csrf_field(); ?> <input type="hidden" name="month" value="<?php echo e($month); ?>">
      <button type="button" class="btn"
        onclick="swConfirm(this.closest('form'), 'Unlock report?',
          'The saved snapshot for <?php echo e($monthLabel); ?> will be removed.',
          'question', 'Unlock', '#d97706')">
        🔓 Unlock
      </button>
    </form>
  <?php else: ?>
    <form method="POST" action="<?php echo e(route('reimbursements.report.generate')); ?>" style="display:inline">
      <?php echo csrf_field(); ?> <input type="hidden" name="month" value="<?php echo e($month); ?>">
      <button type="submit" class="btn acc">🔒 Save Report</button>
    </form>
  <?php endif; ?>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>


<form method="GET" action="<?php echo e(route('reimbursements.report')); ?>"
      class="card" style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
  <div class="fld" style="min-width:160px">
    <label>Month</label>
    <input type="month" name="month" value="<?php echo e($month); ?>" required>
  </div>
  <div class="fld" style="min-width:220px">
    <label>Search</label>
    <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="Emp ID or Employee Name…">
  </div>
  <button type="submit" class="btn acc">View</button>
  <?php if($search !== ''): ?>
    <a href="<?php echo e(route('reimbursements.report', ['month' => $month])); ?>" class="btn">✕ Clear</a>
  <?php endif; ?>
  <?php if($isLocked): ?>
    <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--t2);padding-bottom:2px">
      🔒 <strong>Saved</strong>
      <?php if($lockedAt): ?>
        <span style="color:var(--t3)">on <?php echo e($lockedAt->format('d M Y, h:i A')); ?></span>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div style="display:flex;align-items:center;font-size:12px;color:var(--yel);padding-bottom:2px">
      ⚠ No approved data for this month
    </div>
  <?php endif; ?>
</form>


<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:16px">
  <div class="card" style="padding:14px 18px">
    <div style="font-size:11px;color:var(--t3);text-transform:uppercase;letter-spacing:.05em">Approved Rows</div>
    <div style="font-size:26px;font-weight:700;margin-top:4px"><?php echo e($rowCount); ?></div>
  </div>
  <div class="card" style="padding:14px 18px">
    <div style="font-size:11px;color:var(--t3);text-transform:uppercase;letter-spacing:.05em">Total Amount</div>
    <div style="font-size:26px;font-weight:700;color:var(--grn);margin-top:4px">₹<?php echo e(number_format($totalAmount, 2)); ?></div>
  </div>
  <div class="card" style="padding:14px 18px">
    <div style="font-size:11px;color:var(--t3);text-transform:uppercase;letter-spacing:.05em">Status</div>
    <div style="font-size:18px;font-weight:700;margin-top:6px">
      <?php if($isLocked): ?>
        <span style="color:var(--grn)">🔒 Saved</span>
      <?php else: ?>
        <span style="color:var(--t3)">— No data</span>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if($rowCount === 0 && $search === ''): ?>
<div class="card">
  <div class="empty">
    <div class="ico">📊</div>
    <p>No approved reimbursement rows for <?php echo e(\Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y')); ?>.</p>
    <p style="font-size:12px;color:var(--t3);margin-top:4px">Upload data and approve rows in the tracker first.</p>
    <a href="<?php echo e(route('reimbursements.tracker', ['month' => $month])); ?>" class="btn acc" style="margin-top:10px">Go to Tracker →</a>
  </div>
</div>
<?php else: ?>


<div class="card" style="margin-bottom:14px;padding:12px 16px;font-size:12px;color:var(--t2);line-height:1.6">
  <?php if($isLocked): ?>
    🔒 <strong>Report saved</strong> on <?php echo e($lockedAt?->format('d M Y, h:i A')); ?>.
    This page always shows <strong>live approved rows</strong>.
    <a href="<?php echo e(route('reimbursements.report.view', $month)); ?>" style="color:var(--acc)">View frozen snapshot →</a>
  <?php else: ?>
    ⚡ <strong>Live view</strong> — showing current approved rows. No saved snapshot yet for this month.
  <?php endif; ?>
</div>


<div class="card" style="overflow-x:auto">
  <div class="ch" style="display:flex;justify-content:space-between;align-items:center">
    <div class="ct">
      <?php echo e(\Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y')); ?> — Live Approved Rows
      (<?php echo e(number_format($rows->total())); ?><?php echo e($search !== '' ? ', filtered: "' . e($search) . '"' : ''); ?>)
    </div>
    <?php if($rows->lastPage() > 1): ?>
      <div style="font-size:12px;color:var(--t3)">Page <?php echo e($rows->currentPage()); ?> of <?php echo e($rows->lastPage()); ?></div>
    <?php endif; ?>
  </div>
  <table style="font-size:12px;min-width:600px">
    <thead>
      <tr>
        <th style="width:40px">#</th>
        <th>Date</th>
        <th>Emp ID</th>
        <th>Employee Name</th>
        <th style="text-align:right">Amount (₹)</th>
        <th>Remarks</th>
        <th style="text-align:center">Image</th>
      </tr>
    </thead>
    <tbody>
      <?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <tr>
        <td style="color:var(--t3)"><?php echo e(($rows->currentPage() - 1) * $rows->perPage() + $loop->iteration); ?></td>
        <td style="color:var(--t2);white-space:nowrap"><?php echo e($row->expense_date ? \Carbon\Carbon::parse($row->expense_date)->format('d M Y') : '—'); ?></td>
        <td class="mono" style="font-size:11px"><?php echo e($row->emp_id ?? '—'); ?></td>
        <td style="font-weight:500"><?php echo e($row->employee_name); ?></td>
        <td style="text-align:right;font-weight:600">₹<?php echo e(number_format($row->amount, 2)); ?></td>
        <td style="color:var(--t2)"><?php echo e($row->remarks ?? '—'); ?></td>
        <td style="text-align:center">
          <?php if($row->image_filename): ?>
            <a href="<?php echo e(route('reimbursements.tracker.image.view', ['month' => $month, 'filename' => $row->image_filename])); ?>"
               target="_blank" class="btn sm" style="font-size:10px">🖼 View</a>
          <?php else: ?>
            <span style="color:var(--t3);font-size:11px">—</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
    <tfoot>
      <tr style="background:var(--s2);font-weight:700">
        <td colspan="4" style="text-align:right;padding:8px 12px">Total Approved:</td>
        <td style="text-align:right;padding:8px 12px;color:var(--grn)">₹<?php echo e(number_format($totalAmount, 2)); ?></td>
        <td colspan="2"></td>
      </tr>
    </tfoot>
  </table>
  <?php if($rows->hasPages()): ?>
    <div style="padding:14px 18px;border-top:1px solid var(--b1)"><?php echo e($rows->links()); ?></div>
  <?php endif; ?>
  <?php if($search !== '' && $rows->total() === 0): ?>
    <div class="empty" style="padding:24px">
      <div class="ico">🔍</div>
      <p>No results for "<?php echo e(e($search)); ?>".</p>
      <a href="<?php echo e(route('reimbursements.report', ['month' => $month])); ?>" class="btn acc" style="margin-top:10px">Clear Search</a>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/reimbursements/report.blade.php ENDPATH**/ ?>