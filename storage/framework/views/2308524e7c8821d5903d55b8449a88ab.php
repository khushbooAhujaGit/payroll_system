<?php $__env->startSection('title', 'Employee Master History'); ?>

<?php $__env->startSection('page_actions'); ?>
  <a href="<?php echo e(route('employees')); ?>" class="btn">← Employees</a>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<form method="GET" action="<?php echo e(route('employees.master_history')); ?>"
      class="card" style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
  <div class="fld" style="min-width:220px">
    <label>Search Emp ID / Name</label>
    <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="e.g. EMP001 or John…">
  </div>
  <div class="fld" style="min-width:160px">
    <label>Field Type</label>
    <select name="field_type">
      <option value="" <?php echo e($fieldType==='' ? 'selected' : ''); ?>>All Types</option>
      <option value="bank_details" <?php echo e($fieldType==='bank_details' ? 'selected' : ''); ?>>Bank Details</option>
      <option value="pan"          <?php echo e($fieldType==='pan'          ? 'selected' : ''); ?>>PAN</option>
      <option value="email"        <?php echo e($fieldType==='email'        ? 'selected' : ''); ?>>Email</option>
    </select>
  </div>
  <div class="fld" style="min-width:160px">
    <label>Changed Via</label>
    <select name="changed_via">
      <option value="" <?php echo e($changedVia==='' ? 'selected' : ''); ?>>All Sources</option>
      <option value="salary_reupload" <?php echo e($changedVia==='salary_reupload' ? 'selected' : ''); ?>>Salary Re-upload</option>
      <option value="manual"          <?php echo e($changedVia==='manual'          ? 'selected' : ''); ?>>Manual</option>
      <option value="import"          <?php echo e($changedVia==='import'          ? 'selected' : ''); ?>>Import</option>
    </select>
  </div>
  <button type="submit" class="btn acc">Search</button>
  <?php if($search || $fieldType || $changedVia): ?>
    <a href="<?php echo e(route('employees.master_history')); ?>" class="btn">✕ Clear</a>
  <?php endif; ?>
</form>

<div class="card">
  <div class="ch" style="display:flex;justify-content:space-between;align-items:center">
    <div class="ct">Employee Master Change History</div>
    <div style="font-size:12px;color:var(--t3)">
      <?php echo e(number_format($history->total())); ?> record(s)
      <?php if($history->lastPage() > 1): ?> · Page <?php echo e($history->currentPage()); ?>/<?php echo e($history->lastPage()); ?> <?php endif; ?>
    </div>
  </div>

  <?php if($history->isNotEmpty()): ?>
  <div style="overflow-x:auto">
  <table style="font-size:12px">
    <thead>
      <tr>
        <th>Date &amp; Time</th>
        <th>Employee</th>
        <th>Field Type</th>
        <th>Old Value</th>
        <th>New Value</th>
        <th>Changed Via</th>
        <th>Changed By</th>
      </tr>
    </thead>
    <tbody>
      <?php $__currentLoopData = $history; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $h): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <?php
        $typeBadge = [
          'bank_details' => ['bg'=>'#dbeafe','color'=>'#1d4ed8','label'=>'Bank Details'],
          'pan'          => ['bg'=>'#f3e8ff','color'=>'#7e22ce','label'=>'PAN'],
          'email'        => ['bg'=>'#fef9c3','color'=>'#a16207','label'=>'Email'],
        ][$h->field_type] ?? ['bg'=>'#f3f4f6','color'=>'#374151','label'=>ucfirst($h->field_type)];

        $viaBadge = [
          'salary_reupload' => ['bg'=>'#fef3c7','color'=>'#92400e','label'=>'Salary Re-upload'],
          'manual'          => ['bg'=>'#f0fdf4','color'=>'#166534','label'=>'Manual'],
          'import'          => ['bg'=>'#eff6ff','color'=>'#1e40af','label'=>'Import'],
        ][$h->changed_via] ?? ['bg'=>'#f3f4f6','color'=>'#374151','label'=>ucfirst($h->changed_via)];
      ?>
      <tr>
        <td style="white-space:nowrap;color:var(--t2)">
          <?php echo e($h->created_at->format('d M Y')); ?><br>
          <span style="font-size:10px"><?php echo e($h->created_at->format('h:i A')); ?></span>
        </td>
        <td>
          <span style="font-weight:600"><?php echo e($h->employee?->full_name ?? '—'); ?></span><br>
          <span class="mono" style="font-size:10px;color:var(--t3)"><?php echo e($h->employee?->employee_id); ?></span>
        </td>
        <td>
          <span style="display:inline-block;padding:2px 8px;border-radius:99px;font-size:10px;font-weight:700;
                       background:<?php echo e($typeBadge['bg']); ?>;color:<?php echo e($typeBadge['color']); ?>">
            <?php echo e($typeBadge['label']); ?>

          </span>
        </td>
        <td style="color:var(--red);font-size:11px;max-width:220px">
          <?php if($h->old_value): ?>
            <?php $__currentLoopData = $h->old_value; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <div><span style="color:var(--t3)"><?php echo e($k); ?>:</span> <?php echo e($v ?: '—'); ?></div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          <?php else: ?>
            <span style="color:var(--t3)">— (new entry)</span>
          <?php endif; ?>
        </td>
        <td style="color:var(--grn);font-size:11px;max-width:220px">
          <?php if($h->new_value): ?>
            <?php $__currentLoopData = $h->new_value; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <div><span style="color:var(--t3)"><?php echo e($k); ?>:</span> <strong><?php echo e($v ?: '—'); ?></strong></div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          <?php else: ?>
            <span style="color:var(--t3)">—</span>
          <?php endif; ?>
        </td>
        <td>
          <span style="display:inline-block;padding:2px 8px;border-radius:99px;font-size:10px;font-weight:700;
                       background:<?php echo e($viaBadge['bg']); ?>;color:<?php echo e($viaBadge['color']); ?>">
            <?php echo e($viaBadge['label']); ?>

          </span>
        </td>
        <td style="font-size:11px;color:var(--t2)"><?php echo e($h->changedBy?->name ?? '—'); ?></td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
  </table>
  </div>
  <?php if($history->hasPages()): ?>
    <div style="padding:14px 18px;border-top:1px solid var(--b1)"><?php echo e($history->links()); ?></div>
  <?php endif; ?>

  <?php else: ?>
  <div class="empty">
    <div class="ico">📋</div>
    <p>No master field change history found.</p>
    <p style="font-size:12px;color:var(--t3);margin-top:4px">
      Changes to bank details, PAN, and email via salary re-upload will appear here.
    </p>
  </div>
  <?php endif; ?>
</div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/employees/master-history.blade.php ENDPATH**/ ?>