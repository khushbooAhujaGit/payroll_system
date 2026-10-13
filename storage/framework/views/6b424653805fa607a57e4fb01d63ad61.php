<?php $__env->startSection('title', 'Salary Revisions'); ?>

<?php $__env->startSection('page_actions'); ?>
  <button class="btn acc" onclick="om('rev-modal')">+ Raise Revision</button>
  <button class="btn" onclick="om('upload-modal')">📤 Bulk Upload</button>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<form method="GET" action="<?php echo e(route('revisions')); ?>" class="card" data-ajax-filter style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end">
  <div class="fld" style="min-width:140px">
    <label>Status</label>
    <select name="status">
      <option value="">All</option>
      <option value="pending_approval" <?php echo e(request('status') === 'pending_approval' ? 'selected' : ''); ?>>Pending</option>
      <option value="implemented"      <?php echo e(request('status') === 'implemented'      ? 'selected' : ''); ?>>Implemented</option>
      <option value="rejected"         <?php echo e(request('status') === 'rejected'         ? 'selected' : ''); ?>>Rejected</option>
    </select>
  </div>
  <button type="submit" class="btn acc">Filter</button>
  <a href="<?php echo e(route('revisions')); ?>" class="btn" data-ajax-reset>Reset</a>
</form>

<div id="ajax-results">
<div class="card">
  <?php if($revisions->isNotEmpty()): ?>
  <table>
    <thead>
      <tr><th>Employee</th><th>Old CTC</th><th>New CTC</th><th>Hike %</th><th>Effective</th><th>Status</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php $__currentLoopData = $revisions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rev): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <tr>
        <td><?php echo e($rev->employee?->full_name); ?><br>
            <span style="font-size:10px;color:var(--t3)"><?php echo e($rev->employee?->department?->name); ?></span></td>
        <td>₹ <?php echo e(number_format($rev->old_ctc,0,'.',',')); ?></td>
        <td>₹ <?php echo e(number_format($rev->new_ctc,0,'.',',')); ?></td>
        <td style="color:<?php echo e($rev->increment_percentage > 0 ? 'var(--grn)' : 'var(--red)'); ?>">
          <?php echo e($rev->increment_percentage); ?>%
        </td>
        <td><?php echo e(\Carbon\Carbon::parse($rev->effective_date)->format('d M Y')); ?></td>
        <td>
          <?php $colors = ['pending_approval'=>'var(--yel)','implemented'=>'var(--grn)','rejected'=>'var(--red)']; ?>
          <span class="bx" style="color:<?php echo e($colors[$rev->status] ?? 'var(--t3)'); ?>"><?php echo e(str_replace('_',' ',ucwords($rev->status,'_'))); ?></span>
        </td>
        <td>
          <?php if($rev->status === 'pending_approval'): ?>
          <form method="POST" action="<?php echo e(route('revisions.approve', $rev)); ?>" style="display:inline">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="approved">
            <button type="submit" class="btn sm" style="background:var(--grn2);color:var(--grn)">Approve</button>
          </form>
          <form method="POST" action="<?php echo e(route('revisions.approve', $rev)); ?>" style="display:inline">
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
  <div style="margin-top:14px"><?php echo e($revisions->links()); ?></div>
  <?php else: ?>
  <div class="empty"><div class="ico">📈</div><p>No salary revisions yet.</p></div>
  <?php endif; ?>
</div>
</div>


<div class="modal" id="rev-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Raise Salary Revision</span>
      <button class="mc" onclick="cm('rev-modal')">✕</button>
    </div>
    <form method="POST" action="<?php echo e(route('revisions.store')); ?>">
      <?php echo csrf_field(); ?>
      <div class="fg2">
        <div class="fld full">
          <label class="req">Employee</label>
          <select name="employee_id" required <?php $__errorArgs = ['employee_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
            <option value="">Select employee</option>
            <?php $__currentLoopData = $employees; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $emp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($emp->id); ?>" <?php echo e(old('employee_id') == $emp->id ? 'selected' : ''); ?>><?php echo e($emp->full_name); ?> (<?php echo e($emp->employee_id); ?>)</option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
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
        <div class="fld">
          <label class="req">New CTC Annual (₹)</label>
          <input type="number" name="new_ctc" required min="1" value="<?php echo e(old('new_ctc')); ?>"
            <?php $__errorArgs = ['new_ctc'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
          <?php $__errorArgs = ['new_ctc'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div class="fld">
          <label class="req">Effective Date</label>
          <input type="date" name="effective_date" required value="<?php echo e(old('effective_date', now()->toDateString())); ?>"
            <?php $__errorArgs = ['effective_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
          <?php $__errorArgs = ['effective_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div class="fld full">
          <label class="req">Reason</label>
          <textarea name="reason" required rows="3" style="resize:vertical"
            <?php $__errorArgs = ['reason'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>><?php echo e(old('reason')); ?></textarea>
          <?php $__errorArgs = ['reason'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('rev-modal')">Cancel</button>
        <button type="submit" class="btn acc">Submit</button>
      </div>
    </form>
  </div>
</div>

<?php echo $__env->make('partials.upload-modal', [
    'uploadId'      => 'upload-modal',
    'uploadTitle'   => 'Bulk Upload Salary Revisions',
    'uploadRoute'   => 'revisions.import',
    'templateRoute' => 'revisions.template',
    'uploadHint'    => 'Template columns: Employee ID, New CTC Annual, Effective Date (DD-MM-YYYY), Reason. Each row creates a pending salary revision.',
], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<?php if($errors->any()): ?>
<script>document.addEventListener('DOMContentLoaded',()=>om('rev-modal'))</script>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/revisions/index.blade.php ENDPATH**/ ?>