<?php $__env->startSection('title', 'Holidays'); ?>

<?php $__env->startSection('page_actions'); ?>
  <button class="btn acc" onclick="om('holiday-modal')">+ Add Holiday</button>
  <button class="btn" onclick="om('upload-modal')">📤 Bulk Upload</button>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

  
  <form method="GET" action="<?php echo e(route('holidays')); ?>" class="card"
    style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">

    <div class="fld" style="min-width:180px">
      <label>Company</label>
      <select name="company_id" onchange="this.form.submit()">
        <option value="">All Companies</option>
        <?php $__currentLoopData = $companies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $co): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($co->id); ?>" <?php echo e($companyFilter == $co->id ? 'selected' : ''); ?>><?php echo e($co->name); ?></option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </select>
    </div>

    <div class="fld" style="min-width:100px">
      <label>Year</label>
      <select name="year" onchange="this.form.submit()">
        <?php for($y = now()->year + 1; $y >= now()->year - 2; $y--): ?>
          <option value="<?php echo e($y); ?>" <?php echo e($year == $y ? 'selected' : ''); ?>><?php echo e($y); ?></option>
        <?php endfor; ?>
      </select>
    </div>

    <button type="submit" class="btn acc">Filter</button>
    <a href="<?php echo e(route('holidays', ['year' => $year])); ?>" class="btn">Reset</a>
  </form>

  
  <div class="card">
    <div class="ch">
      <div class="ct">Holidays — <?php echo e($year); ?></div>
      <span style="font-size:12px;color:var(--t3)"><?php echo e($allHolidays->count()); ?> total · <?php echo e($byDate->count()); ?> unique dates</span>
    </div>

    <?php if($byDate->isNotEmpty()): ?>
      <div style="overflow-x:auto">
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Day</th>
              <th>Holiday Name</th>
              <th>Type</th>
              <?php if($activeCompanies->count() > 1): ?>
                <th>Company</th>
              <?php endif; ?>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php $__currentLoopData = $byDate; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dateStr => $holidaysOnDate): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <?php
                $isFirst = true;
                $rowspan = $holidaysOnDate->count();
                $carbon  = \Carbon\Carbon::parse($dateStr);
                $isWeekend = in_array($carbon->dayOfWeek, [0, 6]);
              ?>
              <?php $__currentLoopData = $holidaysOnDate; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $h): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr style="<?php echo e($isWeekend ? 'background:rgba(var(--acc-rgb,66,114,196),.04)' : ''); ?>">
                  <?php if($isFirst): ?>
                    <td rowspan="<?php echo e($rowspan); ?>" style="white-space:nowrap;font-weight:600;vertical-align:top;padding-top:10px">
                      <?php echo e($carbon->format('d M Y')); ?>

                    </td>
                    <td rowspan="<?php echo e($rowspan); ?>" style="color:var(--t3);white-space:nowrap;vertical-align:top;padding-top:10px">
                      <?php echo e($carbon->format('l')); ?>

                    </td>
                  <?php endif; ?>

                  <td><?php echo e($h->name); ?></td>

                  <td>
                    <?php
                      $typeColors = [
                        'national' => ['bg'=>'#dbeafe','color'=>'#1d4ed8'],
                        'regional' => ['bg'=>'#dcfce7','color'=>'#15803d'],
                        'optional' => ['bg'=>'#fef9c3','color'=>'#a16207'],
                        'company'  => ['bg'=>'#f3e8ff','color'=>'#7e22ce'],
                      ];
                      $tc = $typeColors[$h->type] ?? ['bg'=>'#f3f4f6','color'=>'#374151'];
                    ?>
                    <span style="display:inline-block;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:700;
                                 background:<?php echo e($tc['bg']); ?>;color:<?php echo e($tc['color']); ?>">
                      <?php echo e(ucfirst($h->type)); ?>

                    </span>
                  </td>

                  <?php if($activeCompanies->count() > 1): ?>
                    <td style="font-size:12px;color:var(--t3)"><?php echo e($h->company_name); ?></td>
                  <?php endif; ?>

                  <td>
                    <form method="POST" action="<?php echo e(route('holidays.destroy', $h)); ?>" style="display:inline">
                      <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                      <button type="button" class="btn sm" style="color:var(--red)"
                        onclick="swConfirm(this.closest('form'), 'Delete Holiday?',
                          'Remove <?php echo e(addslashes($h->name)); ?> from <?php echo e(addslashes($h->company_name)); ?>?',
                          'warning', 'Yes, delete', '#dc2626')">Del</button>
                    </form>
                  </td>
                </tr>
                <?php $isFirst = false; ?>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty">
        <div class="ico">🗓️</div>
        <p>No holidays found for <?php echo e($year); ?><?php echo e($companyFilter ? ' for this company' : ''); ?>. Add one or bulk upload.</p>
      </div>
    <?php endif; ?>
  </div>

  
  <div class="modal" id="holiday-modal">
    <div class="mb">
      <div class="mh">
        <span class="mt">Add Holiday</span>
        <button class="mc" onclick="cm('holiday-modal')">✕</button>
      </div>
      <form method="POST" action="<?php echo e(route('holidays.store')); ?>">
        <?php echo csrf_field(); ?>
        <div class="fg2">
          <div class="fld full">
            <label class="req">Company</label>
            <select name="company_id" required <?php $__errorArgs = ['company_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
              <option value="">Select Company</option>
              <?php $__currentLoopData = $companies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $co): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($co->id); ?>" <?php echo e(old('company_id', $companyFilter) == $co->id ? 'selected' : ''); ?>>
                  <?php echo e($co->name); ?>

                </option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
            <?php $__errorArgs = ['company_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
          </div>
          <div class="fld">
            <label class="req">Date</label>
            <input type="date" name="date" required value="<?php echo e(old('date')); ?>"
              <?php $__errorArgs = ['date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
            <?php $__errorArgs = ['date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
          </div>
          <div class="fld">
            <label class="req">Type</label>
            <select name="type" required>
              <?php $__currentLoopData = ['national'=>'National','regional'=>'Regional','optional'=>'Optional','company'=>'Company']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($val); ?>" <?php echo e(old('type') === $val ? 'selected' : ''); ?>><?php echo e($label); ?></option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
          </div>
          <div class="fld full">
            <label class="req">Holiday Name</label>
            <input type="text" name="name" required placeholder="e.g. Republic Day"
              value="<?php echo e(old('name')); ?>" <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
            <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
          </div>
          <div class="fld full">
            <label>Description</label>
            <input type="text" name="description" placeholder="Optional" value="<?php echo e(old('description')); ?>">
          </div>
        </div>
        <div class="mf">
          <button type="button" class="btn" onclick="cm('holiday-modal')">Cancel</button>
          <button type="submit" class="btn acc">Add Holiday</button>
        </div>
      </form>
    </div>
  </div>

  <?php if($errors->any()): ?>
    <script>document.addEventListener('DOMContentLoaded', () => om('holiday-modal'))</script>
  <?php endif; ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('partials.upload-modal', [
    'uploadId'      => 'upload-modal',
    'uploadTitle'   => 'Bulk Upload Holidays',
    'uploadRoute'   => 'holidays.import',
    'templateRoute' => 'holidays.template',
    'uploadHint'    => 'Template columns: Company, Date (YYYY-MM-DD), Name, Type (national/regional/optional/company). One row per holiday per company.',
    'uploadFields'  => [],
], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/holidays/index.blade.php ENDPATH**/ ?>