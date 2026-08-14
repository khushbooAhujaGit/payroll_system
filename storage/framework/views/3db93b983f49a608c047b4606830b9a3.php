<?php $__env->startSection('title', 'My Reimbursements'); ?>

<?php $__env->startSection('page_actions'); ?>
  <button class="btn acc" onclick="om('submit-claim-modal')">+ Submit Claim</button>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<div class="card">
  <div class="ch"><div class="ct">My Claims</div></div>
  <?php if($claims->isNotEmpty()): ?>
  <table>
    <thead>
      <tr>
        <th>Date</th>
        <th>Amount</th>
        <th>Remarks</th>
        <th>Document</th>
        <th>Approval Status</th>
        <th>Payment Status</th>
      </tr>
    </thead>
    <tbody>
      <?php $__currentLoopData = $claims; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $claim): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <tr>
        <td><?php echo e($claim->expense_date ? \Carbon\Carbon::parse($claim->expense_date)->format('d M Y') : '—'); ?></td>
        <td style="font-weight:600">₹<?php echo e(number_format($claim->amount, 2)); ?></td>
        <td style="color:var(--t2);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?php echo e($claim->remarks); ?>">
          <?php echo e($claim->remarks ?? '—'); ?>

        </td>
        <td>
          <?php if($claim->document_path): ?>
            <a href="<?php echo e(route('reimbursements.tracker.document', $claim)); ?>" target="_blank" class="btn sm">View</a>
          <?php else: ?>
            <span style="color:var(--t3)">—</span>
          <?php endif; ?>
        </td>
        <td>
          <span class="bx <?php echo e($claim->status === 'approved' ? 'g' : ($claim->status === 'rejected' ? 'r' : 'y')); ?>">
            <?php echo e(ucfirst($claim->status)); ?>

          </span>
          <?php if($claim->status !== 'pending' && $claim->approval_remarks): ?>
            <div style="font-size:11px;color:var(--t3);margin-top:3px"><?php echo e($claim->approval_remarks); ?></div>
          <?php endif; ?>
        </td>
        <td>
          <?php if($claim->status === 'approved'): ?>
            <span class="bx <?php echo e($claim->payment_status === 'paid' ? 'g' : 'gr'); ?>"><?php echo e(ucfirst($claim->payment_status)); ?></span>
            <?php if($claim->payment_status === 'paid' && $claim->payment_date): ?>
              <div style="font-size:11px;color:var(--t3);margin-top:3px">
                <?php echo e(\Carbon\Carbon::parse($claim->payment_date)->format('d M Y')); ?>

                <?php if($claim->payment_reference): ?> · <?php echo e($claim->payment_reference); ?> <?php endif; ?>
              </div>
            <?php endif; ?>
          <?php else: ?>
            <span style="color:var(--t3)">—</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
  </table>
  <div style="margin-top:14px"><?php echo e($claims->links()); ?></div>
  <?php else: ?>
  <div class="empty"><div class="ico">💳</div><p>No claims submitted yet.</p></div>
  <?php endif; ?>
</div>


<div class="modal" id="submit-claim-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Submit Reimbursement Claim</span>
      <button class="mc" onclick="cm('submit-claim-modal')">✕</button>
    </div>
    <form method="POST" action="<?php echo e(route('my-reimbursements.submit')); ?>" enctype="multipart/form-data">
      <?php echo csrf_field(); ?>
      <div class="fg2">
        <div class="fld">
          <label class="req">Expense Date</label>
          <input type="date" name="expense_date" required max="<?php echo e(now()->toDateString()); ?>" value="<?php echo e(old('expense_date', now()->toDateString())); ?>">
        </div>
        <div class="fld">
          <label class="req">Amount (₹)</label>
          <input type="number" name="amount" required min="0.01" step="0.01" value="<?php echo e(old('amount')); ?>">
        </div>
        <div class="fld full">
          <label>Remarks</label>
          <input type="text" name="remarks" placeholder="e.g. Client visit travel" value="<?php echo e(old('remarks')); ?>">
        </div>
        <div class="fld full">
          <label class="req">Supporting Document</label>
          <input type="file" name="document" accept=".jpg,.jpeg,.png,.pdf" required>
          <div style="font-size:11px;color:var(--t3);margin-top:4px">JPG, PNG, or PDF — max 5 MB.</div>
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('submit-claim-modal')">Cancel</button>
        <button type="submit" class="btn acc">Submit</button>
      </div>
    </form>
  </div>
</div>

<?php if($errors->any()): ?>
<script>document.addEventListener('DOMContentLoaded', () => om('submit-claim-modal'))</script>
<?php endif; ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/reimbursements/my-claims.blade.php ENDPATH**/ ?>