<?php $__env->startSection('title', 'Reimbursement Approvals'); ?>

<?php $__env->startSection('content'); ?>

<div class="card">
  <div class="ch"><div class="ct">Pending Claims</div></div>
  <?php if($rows->isNotEmpty()): ?>
  <table>
    <thead><tr><th>Employee</th><th>Date</th><th>Amount</th><th>Remarks</th><th>Document</th><th>Actions</th></tr></thead>
    <tbody>
      <?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <tr>
        <td><?php echo e($row->employee_name); ?> <span style="color:var(--t3);font-size:11px">(<?php echo e($row->emp_id); ?>)</span></td>
        <td><?php echo e($row->expense_date ? \Carbon\Carbon::parse($row->expense_date)->format('d M Y') : '—'); ?></td>
        <td style="font-weight:600">₹<?php echo e(number_format($row->amount, 2)); ?></td>
        <td style="color:var(--t2)"><?php echo e($row->remarks ?? '—'); ?></td>
        <td>
          <?php if($row->document_path): ?>
            <a href="<?php echo e(route('reimbursements.tracker.document', $row)); ?>" target="_blank" class="btn sm">View</a>
          <?php else: ?>
            <span style="color:var(--t3)">—</span>
          <?php endif; ?>
        </td>
        <td><button class="btn sm" onclick="om('resolve-<?php echo e($row->id); ?>')">Review</button></td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
  </table>
  <div style="margin-top:14px"><?php echo e($rows->links()); ?></div>
  <?php else: ?>
  <div class="empty"><div class="ico">💳</div><p>No pending claims.</p></div>
  <?php endif; ?>
</div>

<?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <div class="modal" id="resolve-<?php echo e($row->id); ?>">
    <div class="mb">
      <div class="mh">
        <span class="mt">Review — <?php echo e($row->employee_name); ?> (₹<?php echo e(number_format($row->amount, 2)); ?>)</span>
        <button class="mc" onclick="cm('resolve-<?php echo e($row->id); ?>')">✕</button>
      </div>
      <form method="POST" action="<?php echo e(route('reimbursements.approvals.resolve', $row)); ?>">
        <?php echo csrf_field(); ?>
        <div class="fld full">
          <label class="req">Remark</label>
          <select name="remark_choice" onchange="toggleOtherRemark(this, '<?php echo e($row->id); ?>')" required>
            <option value="">Select a remark…</option>
            <?php $__currentLoopData = $standardRemarks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sr): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($sr); ?>"><?php echo e($sr); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <option value="__other__">Other</option>
          </select>
          <textarea name="remark_other" id="remark-other-<?php echo e($row->id); ?>" rows="3" style="display:none;margin-top:8px"
            placeholder="Enter remark…"></textarea>
        </div>
        <input type="hidden" name="remark" id="remark-final-<?php echo e($row->id); ?>">
        <div class="mf">
          <button type="button" class="btn" style="color:var(--red)"
            onclick="submitResolve(this, '<?php echo e($row->id); ?>', 'rejected')">Reject</button>
          <button type="button" class="btn acc"
            onclick="submitResolve(this, '<?php echo e($row->id); ?>', 'approved')">Approve</button>
        </div>
      </form>
    </div>
  </div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
function toggleOtherRemark(select, rowId) {
  const other = document.getElementById('remark-other-' + rowId);
  other.style.display = select.value === '__other__' ? 'block' : 'none';
}

function submitResolve(button, rowId, action) {
  const form = button.closest('form');
  const select = form.querySelector('select[name="remark_choice"]');
  const other = document.getElementById('remark-other-' + rowId);
  const finalRemark = select.value === '__other__' ? other.value.trim() : select.value;

  if (!finalRemark) {
    toast('Please select or enter a remark.', 'err');
    return;
  }

  document.getElementById('remark-final-' + rowId).value = finalRemark;

  const actionInput = document.createElement('input');
  actionInput.type = 'hidden';
  actionInput.name = 'action';
  actionInput.value = action;
  form.appendChild(actionInput);

  form.submit();
}
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/reimbursements/approvals.blade.php ENDPATH**/ ?>