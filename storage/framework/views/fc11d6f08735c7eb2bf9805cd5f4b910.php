<?php $__env->startSection('title', 'Holidays'); ?>

<?php $__env->startSection('page_actions'); ?>
  <button class="btn" onclick="om('weekoff-modal')">📅 Configure Week Offs</button>
  <button class="btn acc" onclick="om('holiday-modal')">+ Add Holiday</button>
  <button class="btn" onclick="om('upload-modal')">📤 Bulk Upload</button>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

  
  <form method="GET" action="<?php echo e(route('holidays')); ?>" class="card"
    style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">

    <div class="fld" style="min-width:160px">
      <label>Company</label>
      <select name="company_id">
        <option value="">All Companies</option>
        <?php $__currentLoopData = $companies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $co): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($co->id); ?>" <?php echo e($companyFilter == $co->id ? 'selected' : ''); ?>><?php echo e($co->name); ?></option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </select>
    </div>

    <div class="fld" style="min-width:90px">
      <label>Year</label>
      <select name="year">
        <?php for($y = now()->year + 1; $y >= now()->year - 2; $y--): ?>
          <option value="<?php echo e($y); ?>" <?php echo e($year == $y ? 'selected' : ''); ?>><?php echo e($y); ?></option>
        <?php endfor; ?>
      </select>
    </div>

    <div class="fld" style="min-width:200px">
      <label>Search by Name</label>
      <input type="text" name="search_name" value="<?php echo e($searchName); ?>" placeholder="e.g. Diwali, Sunday…">
    </div>

    <div class="fld" style="min-width:160px">
      <label>Search by Date</label>
      <input type="date" name="search_date" value="<?php echo e($searchDate); ?>">
    </div>

    <button type="submit" class="btn acc">Search</button>
    <a href="<?php echo e(route('holidays', ['year' => $year])); ?>" class="btn">Reset</a>
  </form>

  
  <div class="card">
    <div class="ch" style="display:flex;justify-content:space-between;align-items:center">
      <div class="ct">Holidays — <?php echo e($year); ?></div>
      <div style="font-size:12px;color:var(--t3)">
        <?php echo e(number_format($totalRecords)); ?> record(s)
        <?php if($searchName || $searchDate): ?>
          <span style="color:var(--acc)"> — filtered</span>
        <?php endif; ?>
        <?php if($holidays->lastPage() > 1): ?> · Page <?php echo e($holidays->currentPage()); ?>/<?php echo e($holidays->lastPage()); ?> <?php endif; ?>
      </div>
    </div>

    <?php if($holidays->isNotEmpty()): ?>
      <div style="overflow-x:auto">
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Day</th>
              <th>Holiday Name</th>
              <th>Type</th>
              <th>Company</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php $__currentLoopData = $holidays; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $h): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <?php
                $carbon    = \Carbon\Carbon::parse($h->date);
                $isWeekend = in_array($carbon->dayOfWeek, [0, 6]);
                $typeColors = [
                  'national'   => ['bg'=>'#dbeafe','color'=>'#1d4ed8'],
                  'regional'   => ['bg'=>'#dcfce7','color'=>'#15803d'],
                  'optional'   => ['bg'=>'#fef9c3','color'=>'#a16207'],
                  'company'    => ['bg'=>'#f3e8ff','color'=>'#7e22ce'],
                  'weekly_off' => ['bg'=>'#f0fdf4','color'=>'#166534'],
                ];
                $tc = $typeColors[$h->type] ?? ['bg'=>'#f3f4f6','color'=>'#374151'];
              ?>
              <tr>
                <td style="white-space:nowrap;font-weight:<?php echo e($isWeekend ? '600' : '400'); ?>">
                  <?php echo e($carbon->format('d M Y')); ?>

                </td>
                <td style="color:var(--t3);white-space:nowrap"><?php echo e($carbon->format('l')); ?></td>
                <td><?php echo e($h->name); ?></td>
                <td>
                  <span style="display:inline-block;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:700;
                               background:<?php echo e($tc['bg']); ?>;color:<?php echo e($tc['color']); ?>">
                    <?php echo e(str_replace('_', ' ', ucfirst($h->type))); ?>

                  </span>
                </td>
                <td style="font-size:12px;color:var(--t2)">
                  <?php echo e($h->calendar?->company?->name ?? ($h->calendar?->name ?? '—')); ?>

                </td>
                <td>
                  <form method="POST" action="<?php echo e(route('holidays.destroy', $h)); ?>" style="display:inline">
                    <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                    <button type="button" class="btn sm" style="color:var(--red)"
                      onclick="swConfirm(this.closest('form'), 'Delete Holiday?',
                        'Remove <?php echo e(addslashes($h->name)); ?>?',
                        'warning', 'Yes, delete', '#dc2626')">Del</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </tbody>
        </table>
      </div>
      <?php if($holidays->hasPages()): ?>
        <div style="padding:14px 18px;border-top:1px solid var(--b1)"><?php echo e($holidays->links()); ?></div>
      <?php endif; ?>

      <?php if(($searchName || $searchDate) && $holidays->total() === 0): ?>
        <div class="empty" style="padding:24px">
          <div class="ico">🔍</div>
          <p>No holidays matching your search.</p>
          <a href="<?php echo e(route('holidays', ['year' => $year])); ?>" class="btn acc" style="margin-top:10px">Clear Search</a>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="empty">
        <div class="ico">🗓️</div>
        <p>No holidays found for <?php echo e($year); ?><?php echo e($companyFilter ? ' for this company' : ''); ?>.</p>
        <p style="font-size:12px;color:var(--t3);margin-top:4px">Add a holiday, configure week offs, or bulk upload.</p>
      </div>
    <?php endif; ?>
  </div>

  
  <div class="modal" id="weekoff-modal">
    <div class="mb wide">
      <div class="mh">
        <span class="mt">📅 Configure Week Offs</span>
        <button class="mc" onclick="cm('weekoff-modal')">✕</button>
      </div>
      <div style="font-size:12px;color:var(--t2);background:var(--acc2);border-radius:8px;padding:10px 14px;margin-bottom:14px;line-height:1.6">
        Auto-generates Sunday / Saturday off entries in the holidays table for the selected companies and period.
        These replace the static week-off calculation in the salary report.
      </div>
      <form method="POST" action="<?php echo e(route('holidays.weekoffs')); ?>">
        <?php echo csrf_field(); ?>
        <div class="fg2">

          
          <div class="fld full">
            <label class="req">Companies / Firms</label>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:4px">
              <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;
                            background:var(--s2);border:1px solid var(--b1);border-radius:6px;padding:6px 10px">
                <input type="checkbox" id="co-all" onchange="toggleAllCo(this)"> <strong>All Companies</strong>
              </label>
              <?php $__currentLoopData = $companies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $co): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <label style="display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer;
                            background:var(--s2);border:1px solid var(--b1);border-radius:6px;padding:6px 10px">
                <input type="checkbox" name="company_ids[]" value="<?php echo e($co->id); ?>" class="co-chk"
                  <?php echo e(old('company_ids') && in_array($co->id, old('company_ids')) ? 'checked' : ''); ?>>
                <?php echo e($co->name); ?>

              </label>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
            <?php $__errorArgs = ['company_ids'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
          </div>

          
          <div class="fld">
            <label class="req">From Date</label>
            <input type="date" name="start_date" required value="<?php echo e(old('start_date', now()->startOfYear()->toDateString())); ?>">
          </div>
          <div class="fld">
            <label class="req">To Date</label>
            <input type="date" name="end_date" required value="<?php echo e(old('end_date', now()->endOfYear()->toDateString())); ?>">
          </div>

          
          <div class="fld full">
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
              <input type="checkbox" name="include_sundays" value="1" checked
                style="width:15px;height:15px">
              <span>✅ All <strong>Sundays</strong> are weekly off (recommended, checked by default)</span>
            </label>
          </div>

          
          <div class="fld full">
            <label class="req" style="margin-bottom:6px;display:block">Saturday Off Configuration</label>
            <div style="display:flex;flex-wrap:wrap;gap:8px">
              <?php $__currentLoopData = [
                'none'    => 'No Saturdays off',
                'all'     => 'All Saturdays off',
                '1st'     => '1st Saturday only',
                '2nd'     => '2nd Saturday only',
                '3rd'     => '3rd Saturday only',
                '4th'     => '4th Saturday only',
                '1st_3rd' => '1st & 3rd Saturday',
                '2nd_4th' => '2nd & 4th Saturday',
              ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <label style="display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer;
                            background:var(--s2);border:1px solid var(--b1);border-radius:6px;padding:6px 10px">
                <input type="radio" name="saturday_config" value="<?php echo e($val); ?>"
                  <?php echo e((old('saturday_config', '2nd_4th') === $val) ? 'checked' : ''); ?>>
                <?php echo e($label); ?>

              </label>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
          </div>

        </div>
        <div class="mf">
          <button type="button" class="btn" onclick="cm('weekoff-modal')">Cancel</button>
          <button type="submit" class="btn acc">Generate Week Offs</button>
        </div>
      </form>
    </div>
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
            <label class="req">Apply to Companies</label>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:4px">
              <label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer;
                            background:var(--s2);border:1px solid var(--b1);border-radius:6px;padding:5px 8px">
                <input type="checkbox" id="hco-all" onchange="toggleAllHco(this)"> <strong>All</strong>
              </label>
              <?php $__currentLoopData = $companies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $co): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer;
                            background:var(--s2);border:1px solid var(--b1);border-radius:6px;padding:5px 8px">
                <input type="checkbox" name="company_ids[]" value="<?php echo e($co->id); ?>" class="hco-chk"
                  <?php echo e(old('company_ids') && in_array($co->id, old('company_ids')) ? 'checked' : ''); ?>>
                <?php echo e($co->name); ?>

              </label>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
            <?php $__errorArgs = ['company_ids'];
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

<?php $__env->startPush('scripts'); ?>
<script>
function toggleAllCo(master) {
  document.querySelectorAll('.co-chk').forEach(c => c.checked = master.checked);
}
function toggleAllHco(master) {
  document.querySelectorAll('.hco-chk').forEach(c => c.checked = master.checked);
}
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/holidays/index.blade.php ENDPATH**/ ?>