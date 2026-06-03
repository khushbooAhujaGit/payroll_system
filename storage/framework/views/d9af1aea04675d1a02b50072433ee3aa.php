<?php $__env->startSection('title', 'Reimbursement Report — ' . \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y')); ?>

<?php $__env->startSection('page_actions'); ?>
  <a href="<?php echo e(route('reimbursements.report.saved')); ?>" class="btn">← Saved Reports</a>
  <form method="POST" action="<?php echo e(route('reimbursements.report.download')); ?>" style="display:inline">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="month" value="<?php echo e($month); ?>">
    <button type="submit" class="btn">📥 Download Excel</button>
  </form>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>


<div class="card" style="margin-bottom:16px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
  <div>
    <div style="font-size:16px;font-weight:700">
      🔒 <?php echo e(\Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y')); ?> — Reimbursement Report
    </div>
    <div style="font-size:12px;color:var(--t3);margin-top:3px">
      Locked on <?php echo e($lockedAt ? $lockedAt->format('d M Y, h:i A') : '—'); ?>

    </div>
  </div>
  <div style="display:flex;gap:24px">
    <div style="text-align:right">
      <div style="font-size:11px;color:var(--t3)">Approved Rows</div>
      <div style="font-size:20px;font-weight:700"><?php echo e($rowCount); ?></div>
    </div>
    <div style="text-align:right">
      <div style="font-size:11px;color:var(--t3)">Total Amount</div>
      <div style="font-size:20px;font-weight:700;color:var(--grn)">₹<?php echo e(number_format($totalAmount, 2)); ?></div>
    </div>
  </div>
</div>


<form method="GET" action="<?php echo e(route('reimbursements.report.view', $month)); ?>"
      class="card" style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
  <div class="fld" style="min-width:260px">
    <label>Search</label>
    <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="Emp ID or Employee Name…">
  </div>
  <button type="submit" class="btn acc">Search</button>
  <?php if($search !== ''): ?>
    <a href="<?php echo e(route('reimbursements.report.view', $month)); ?>" class="btn">✕ Clear</a>
    <span style="font-size:12px;color:var(--t2);padding-bottom:2px">
      <?php echo e($rows->total()); ?> result(s) for "<?php echo e(e($search)); ?>"
    </span>
  <?php endif; ?>
</form>


<div class="card" style="overflow-x:auto">
  <?php if($rows->total() > 0): ?>
  <div class="ch" style="display:flex;justify-content:space-between;align-items:center">
    <div class="ct">
      <?php echo e(number_format($rows->total())); ?> row(s)
      <?php if($search !== ''): ?>
        <span style="color:var(--acc);font-size:11px"> matching "<?php echo e(e($search)); ?>"</span>
      <?php endif; ?>
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
      <?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <tr>
        <td style="color:var(--t3)"><?php echo e(($rows->currentPage() - 1) * $rows->perPage() + $loop->iteration); ?></td>
        <td style="color:var(--t2);white-space:nowrap"><?php echo e(!empty($row['expense_date']) ? \Carbon\Carbon::parse($row['expense_date'])->format('d M Y') : '—'); ?></td>
        <td class="mono" style="font-size:11px"><?php echo e($row['emp_id'] ?? '—'); ?></td>
        <td style="font-weight:500"><?php echo e($row['employee_name']); ?></td>
        <td style="text-align:right;font-weight:600">₹<?php echo e(number_format($row['amount'], 2)); ?></td>
        <td style="color:var(--t2)"><?php echo e($row['remarks'] ?? '—'); ?></td>
        <td style="text-align:center">
          <?php if(!empty($row['image_filename'])): ?>
            <a href="<?php echo e(route('reimbursements.tracker.image.view', ['month' => $month, 'filename' => $row['image_filename']])); ?>"
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
        <td colspan="4" style="text-align:right;padding:10px 12px">Total:</td>
        <td style="text-align:right;padding:10px 12px;color:var(--grn);font-size:14px">₹<?php echo e(number_format($totalAmount, 2)); ?></td>
        <td colspan="2"></td>
      </tr>
    </tfoot>
  </table>
  <?php if($rows->hasPages()): ?>
    <div style="padding:14px 18px;border-top:1px solid var(--b1)"><?php echo e($rows->links()); ?></div>
  <?php endif; ?>
  <?php elseif($search !== ''): ?>
  <div class="empty" style="padding:24px">
    <div class="ico">🔍</div>
    <p>No results for "<?php echo e(e($search)); ?>".</p>
    <a href="<?php echo e(route('reimbursements.report.view', $month)); ?>" class="btn acc" style="margin-top:10px">Clear Search</a>
  </div>
  <?php else: ?>
  <div class="empty">
    <div class="ico">📊</div>
    <p>No rows in this report.</p>
  </div>
  <?php endif; ?>
</div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/reimbursements/report-view.blade.php ENDPATH**/ ?>