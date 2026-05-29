
<?php
  $uploadTitle = $uploadTitle ?? 'Bulk Upload';
  $uploadHint = $uploadHint ?? null;
  $uploadFields = $uploadFields ?? [];
  $uploadId = $uploadId ?? 'upload-modal';
?>

<div class="modal" id="<?php echo e($uploadId); ?>">
  <div class="mb">
    <div class="mh">
      <span class="mt"><?php echo e($uploadTitle); ?></span>
      <button class="mc" onclick="cm('<?php echo e($uploadId); ?>')">✕</button>
    </div>
    
    <?php if(!empty($templateRoute)): ?>
      <div
        style="background:var(--acc2);border:1px solid var(--acc);border-radius:8px;padding:10px 14px;margin-bottom:16px;display:flex;align-items:center;gap:10px">
        <span style="font-size:18px">📥</span>
        <div style="flex:1">
          <div style="font-weight:600;font-size:12px;margin-bottom:2px">Download Template</div>
          <div style="font-size:11px;color:var(--t3)">Fill in this Excel template and upload it below.</div>
        </div>
        <a href="<?php echo e(route($templateRoute)); ?>" class="btn acc sm" style="white-space:nowrap">Get Template</a>
      </div>
    <?php endif; ?>
    <?php if($uploadHint): ?>
      <div style="font-size:12px;color:var(--t3);margin-bottom:14px;line-height:1.6"><?php echo e($uploadHint); ?></div>
    <?php endif; ?>
    <form method="POST" action="<?php echo e(route($uploadRoute)); ?>" enctype="multipart/form-data">
      <?php echo csrf_field(); ?>
      <?php $__currentLoopData = $uploadFields; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $val): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <input type="hidden" name="<?php echo e($key); ?>" value="<?php echo e($val); ?>">
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

      <div class="fld" style="margin-bottom:14px">
        <label>Excel File (.xlsx / .xls) *</label>
        <input type="file" name="file" accept=".xlsx,.xls" required
          style="background:var(--s2);border:1.5px dashed var(--b2);border-radius:8px;padding:14px;cursor:pointer;width:100%">
      </div>

      <?php if(!empty($uploadExtraSlot)): ?>
        <?php echo $uploadExtraSlot; ?>

      <?php endif; ?>

      <div class="mf">
        <button type="button" class="btn" onclick="cm('<?php echo e($uploadId); ?>')">Cancel</button>
        <button type="submit" class="btn acc">📤 Upload &amp; Import</button>
      </div>
    </form>
  </div>
</div><?php /**PATH D:\hrms-payroll\resources\views/partials/upload-modal.blade.php ENDPATH**/ ?>