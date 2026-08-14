<?php $__env->startSection('title', 'Leave Calendar'); ?>

<?php $__env->startSection('page_actions'); ?>
  <a href="<?php echo e(route('leave')); ?>" class="btn">← Leave Management</a>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<?php
  $prevMonth = $monthStart->copy()->subMonth()->format('Y-m');
  $nextMonth = $monthStart->copy()->addMonth()->format('Y-m');
  $weekdays  = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
?>

<div class="card" style="margin-bottom:16px">
  <div class="ch">
    <div class="ct"><?php echo e($monthStart->format('F Y')); ?></div>
    <div style="display:flex;gap:6px">
      <a href="<?php echo e(route('leave.calendar', ['month' => $prevMonth])); ?>" class="btn sm">← Prev</a>
      <a href="<?php echo e(route('leave.calendar')); ?>" class="btn sm">Today</a>
      <a href="<?php echo e(route('leave.calendar', ['month' => $nextMonth])); ?>" class="btn sm">Next →</a>
    </div>
  </div>

  <div style="padding:14px 18px">
    <div class="cal-grid" style="margin-bottom:10px">
      <?php $__currentLoopData = $weekdays; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $wd): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="cal-weekday"><?php echo e($wd); ?></div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>

    <div class="cal-grid">
      <?php $__currentLoopData = $days; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $day): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php if(is_null($day)): ?>
          <div class="cal-day empty"></div>
        <?php else: ?>
          <div class="cal-day <?php echo e($day['is_weekend'] ? 'weekend' : ''); ?> <?php echo e($day['is_today'] ? 'today' : ''); ?> <?php echo e($day['count'] > 0 ? 'has-leave' : ''); ?>"
            <?php if($day['count'] > 0): ?> onclick="om('cal-day-<?php echo e($day['date']); ?>')" <?php endif; ?>>
            <div class="cal-day-num"><?php echo e($day['day']); ?></div>
            <?php if($day['count'] > 0): ?>
              <div class="cal-day-count"><?php echo e($day['count']); ?></div>
              <div class="cal-dots">
                <?php $__currentLoopData = $day['by_type']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <span class="cal-dot" style="background:<?php echo e($t['color']); ?>" title="<?php echo e($t['label']); ?>"></span>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
  </div>
</div>

<?php if($legend->isNotEmpty()): ?>
<div class="card">
  <div class="ch"><div class="ct">Leave Types</div></div>
  <div style="padding:14px 18px">
    <div class="cal-legend">
      <?php $__currentLoopData = $legend; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $lt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="cal-legend-item">
          <span class="cal-legend-dot" style="background:<?php echo e($lt->color); ?>"></span>
          <?php echo e($lt->name); ?>

        </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
  </div>
</div>
<?php endif; ?>


<?php $__currentLoopData = $days; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $day): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <?php if(!is_null($day) && $day['count'] > 0): ?>
    <div class="modal" id="cal-day-<?php echo e($day['date']); ?>">
      <div class="mb">
        <div class="mh">
          <span class="mt"><?php echo e(\Carbon\Carbon::parse($day['date'])->format('d M Y (D)')); ?></span>
          <button class="mc" onclick="cm('cal-day-<?php echo e($day['date']); ?>')">✕</button>
        </div>

        <?php if($canSeeNames): ?>
          
          <?php $__currentLoopData = $day['names']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $n): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="cal-detail-row">
              <span class="cal-legend-dot" style="background:<?php echo e($n['color']); ?>"></span>
              <span style="flex:1"><?php echo e($n['name']); ?></span>
              <span style="color:var(--t3);font-size:11px"><?php echo e($n['label']); ?></span>
            </div>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php else: ?>
          
          <?php $__currentLoopData = $day['by_type']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="cal-detail-row">
              <span class="cal-legend-dot" style="background:<?php echo e($t['color']); ?>"></span>
              <span style="flex:1"><?php echo e($t['label']); ?></span>
              <span style="color:var(--t3);font-size:11px"><?php echo e($t['count']); ?> <?php echo e($t['count'] === 1 ? 'person' : 'people'); ?></span>
            </div>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php endif; ?>

        <div class="mf">
          <button type="button" class="btn" onclick="cm('cal-day-<?php echo e($day['date']); ?>')">Close</button>
        </div>
      </div>
    </div>
  <?php endif; ?>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/leave/calendar.blade.php ENDPATH**/ ?>