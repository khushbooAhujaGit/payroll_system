<?php $__env->startSection('title', 'Reimbursements'); ?>

<?php $__env->startSection('page_actions'); ?>
  <button class="btn acc" onclick="om('reimb-modal')">+ Submit Claim</button>
  <button class="btn" onclick="om('upload-modal')">📤 Bulk Upload</button>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<form method="GET" action="<?php echo e(route('reimbursements')); ?>" class="card" style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end">
  <div class="fld" style="min-width:140px">
    <label>Status</label>
    <select name="status">
      <option value="">All</option>
      <option value="submitted" <?php echo e(request('status') === 'submitted' ? 'selected' : ''); ?>>Submitted</option>
      <option value="approved"  <?php echo e(request('status') === 'approved'  ? 'selected' : ''); ?>>Approved</option>
      <option value="rejected"  <?php echo e(request('status') === 'rejected'  ? 'selected' : ''); ?>>Rejected</option>
      <option value="paid"      <?php echo e(request('status') === 'paid'      ? 'selected' : ''); ?>>Paid</option>
    </select>
  </div>
  <button type="submit" class="btn acc">Filter</button>
  <a href="<?php echo e(route('reimbursements')); ?>" class="btn">Reset</a>
</form>

<div class="card">
  <?php if($claims->isNotEmpty()): ?>
  <table>
    <thead>
      <tr><th>Claim #</th><th>Employee</th><th>Month</th><th>Total Claimed</th><th>Status</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php $__currentLoopData = $claims; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $claim): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <tr>
        <td class="mono"><?php echo e($claim->claim_number); ?></td>
        <td><?php echo e($claim->employee?->full_name); ?><br>
            <span style="font-size:10px;color:var(--t3)"><?php echo e($claim->employee?->department?->name); ?></span></td>
        <td><?php echo e(\Carbon\Carbon::createFromDate($claim->year, $claim->month, 1)->format('M Y')); ?></td>
        <td>₹ <?php echo e(number_format($claim->total_claimed, 2)); ?></td>
        <td>
          <?php $colors = ['submitted'=>'var(--yel)','approved'=>'var(--grn)','rejected'=>'var(--red)','paid'=>'var(--pur)']; ?>
          <span class="bx" style="color:<?php echo e($colors[$claim->status] ?? 'var(--t3)'); ?>"><?php echo e(ucfirst($claim->status)); ?></span>
        </td>
        <td>
          <?php if($claim->status === 'submitted'): ?>
          <form method="POST" action="<?php echo e(route('reimbursements.approve', $claim)); ?>" style="display:inline">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="approved">
            <button type="submit" class="btn sm" style="background:var(--grn2);color:var(--grn)">Approve</button>
          </form>
          <form method="POST" action="<?php echo e(route('reimbursements.approve', $claim)); ?>" style="display:inline">
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
  <div style="margin-top:14px"><?php echo e($claims->links()); ?></div>
  <?php else: ?>
  <div class="empty"><div class="ico">💳</div><p>No reimbursement claims found.</p></div>
  <?php endif; ?>
</div>


<div class="modal" id="reimb-modal">
  <div class="mb wide">
    <div class="mh">
      <span class="mt">Submit Reimbursement Claim</span>
      <button class="mc" onclick="cm('reimb-modal')">✕</button>
    </div>
    <form method="POST" action="<?php echo e(route('reimbursements.store')); ?>">
      <?php echo csrf_field(); ?>
      <div class="fg2">
        <div class="fld full">
          <label>Employee (leave blank for self)</label>
          <select name="employee_id">
            <option value="">— Self —</option>
            <?php $__currentLoopData = $employees; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $emp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($emp->id); ?>"><?php echo e($emp->full_name); ?> (<?php echo e($emp->employee_id); ?>)</option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
        </div>
        <div class="fld">
          <label class="req">Year</label>
          <input type="number" name="year" required value="<?php echo e(old('year', now()->year)); ?>"
            <?php $__errorArgs = ['year'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
          <?php $__errorArgs = ['year'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div class="fld">
          <label class="req">Month</label>
          <select name="month" required <?php $__errorArgs = ['month'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
            <?php $__currentLoopData = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($i+1); ?>" <?php echo e((old('month', now()->month) == $i+1) ? 'selected' : ''); ?>><?php echo e($m); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
          <?php $__errorArgs = ['month'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div class="fld full" style="border-top:1px solid var(--b1);padding-top:12px;margin-top:4px">
          <label>Expense Items</label>
          <div id="items-container">
            <div class="fg2 reimb-item" style="margin-bottom:8px;align-items:flex-start">
              <div class="fld">
                <select name="items[0][reimbursement_type_id]" required>
                  <option value="">Type</option>
                  <?php $__currentLoopData = $types; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <option value="<?php echo e($t->id); ?>"><?php echo e($t->name); ?></option>
                  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
              </div>
              <div class="fld"><input type="date" name="items[0][expense_date]" required value="<?php echo e(now()->toDateString()); ?>"></div>
              <div class="fld"><input type="text" name="items[0][description]" placeholder="Description" required></div>
              <div class="fld"><input type="number" name="items[0][claimed_amount]" placeholder="Amount ₹" required min="0.01" step="0.01"></div>
              <div class="remove-btn-wrap" style="padding-top:6px;visibility:hidden"><button type="button" class="btn sm" style="color:var(--red);padding:6px 10px" onclick="removeItem(this)" title="Remove item">✕</button></div>
            </div>
          </div>
          <button type="button" class="btn sm" onclick="addItem()" style="margin-top:6px">+ Add Item</button>
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('reimb-modal')">Cancel</button>
        <button type="submit" class="btn acc">Submit</button>
      </div>
    </form>
  </div>
</div>

<?php echo $__env->make('partials.upload-modal', [
    'uploadId'       => 'upload-modal',
    'uploadTitle'    => 'Bulk Upload Reimbursement Claims',
    'uploadRoute'    => 'reimbursements.store',
    'templateRoute'  => 'reimbursements.template',
    'uploadHint'     => 'Template columns: Employee ID, Year, Month, Expense Type Code, Expense Date (YYYY-MM-DD), Description, Amount. One row per expense item.',
], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<?php if($errors->any()): ?>
<script>document.addEventListener('DOMContentLoaded',()=>om('reimb-modal'))</script>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
let _itemIdx = 1;
const _types = <?php echo json_encode($types->map(fn($t) => ['id'=>$t->id, 'name'=>$t->name]), 512) ?>;

function addItem() {
  const i = _itemIdx++;
  const div = document.createElement('div');
  div.className = 'fg2 reimb-item';
  div.style.marginBottom = '8px';
  div.style.alignItems = 'flex-start';
  div.innerHTML = `
    <div class="fld"><select name="items[${i}][reimbursement_type_id]" required>
      <option value="">Type</option>
      ${_types.map(t=>`<option value="${t.id}">${t.name}</option>`).join('')}
    </select></div>
    <div class="fld"><input type="date" name="items[${i}][expense_date]" required value="<?php echo e(now()->toDateString()); ?>"></div>
    <div class="fld"><input type="text" name="items[${i}][description]" placeholder="Description" required></div>
    <div class="fld"><input type="number" name="items[${i}][claimed_amount]" placeholder="Amount ₹" required min="0.01" step="0.01"></div>
    <div class="remove-btn-wrap" style="padding-top:6px"><button type="button" class="btn sm" style="color:var(--red);padding:6px 10px" onclick="removeItem(this)" title="Remove item">✕</button></div>
  `;
  document.getElementById('items-container').appendChild(div);
  enforceMinItems();
}

function removeItem(btn) {
  const container = document.getElementById('items-container');
  const items = container.querySelectorAll('.reimb-item');
  if (items.length <= 1) return; // always keep at least 1 item
  btn.closest('.reimb-item').remove();
  enforceMinItems();
}

function enforceMinItems() {
  const items = document.getElementById('items-container').querySelectorAll('.reimb-item');
  const single = items.length <= 1;
  items.forEach(item => {
    const wrap = item.querySelector('.remove-btn-wrap');
    if (wrap) wrap.style.visibility = single ? 'hidden' : 'visible';
  });
}

document.addEventListener('DOMContentLoaded', enforceMinItems);
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/reimbursements/index.blade.php ENDPATH**/ ?>