<?php if($paginator->hasPages()): ?>
<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;font-size:12px;margin-top:4px">

  
  <?php if($paginator->onFirstPage()): ?>
    <span class="btn sm" style="opacity:.4;cursor:default;padding:4px 9px">&lsaquo;</span>
  <?php else: ?>
    <a class="btn sm" style="padding:4px 9px" href="<?php echo e($paginator->previousPageUrl()); ?>" rel="prev">&lsaquo;</a>
  <?php endif; ?>

  
  <?php $__currentLoopData = $elements; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $element): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <?php if(is_string($element)): ?>
      <span style="color:var(--t3);padding:0 2px">…</span>
    <?php endif; ?>

    <?php if(is_array($element)): ?>
      <?php $__currentLoopData = $element; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $page => $url): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php if($page == $paginator->currentPage()): ?>
          <span class="btn sm" style="background:var(--acc);color:#fff;border-color:var(--acc);padding:4px 9px;min-width:30px;text-align:center;cursor:default"><?php echo e($page); ?></span>
        <?php else: ?>
          <a class="btn sm" style="padding:4px 9px;min-width:30px;text-align:center" href="<?php echo e($url); ?>"><?php echo e($page); ?></a>
        <?php endif; ?>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php endif; ?>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

  
  <?php if($paginator->hasMorePages()): ?>
    <a class="btn sm" style="padding:4px 9px" href="<?php echo e($paginator->nextPageUrl()); ?>" rel="next">&rsaquo;</a>
  <?php else: ?>
    <span class="btn sm" style="opacity:.4;cursor:default;padding:4px 9px">&rsaquo;</span>
  <?php endif; ?>

  <span style="color:var(--t3);margin-left:6px">
    <?php echo e(number_format($paginator->firstItem())); ?>–<?php echo e(number_format($paginator->lastItem())); ?>

    of <?php echo e(number_format($paginator->total())); ?>

  </span>

</div>
<?php endif; ?>
<?php /**PATH D:\hrms-payroll\resources\views/vendor/pagination/hrms.blade.php ENDPATH**/ ?>