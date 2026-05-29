<?php $__env->startSection('title', 'Salary Details'); ?>

<?php $__env->startSection('page_actions'); ?>
  <?php if(!$isHistoric): ?>
    <a href="<?php echo e(route('salary.template')); ?>" class="btn">📥 Template</a>
    <button class="btn" onclick="om('sal-import-modal')">📤 Import</button>
  <?php endif; ?>
  <a href="<?php echo e(route('salary.report')); ?>" class="btn acc">📊 Salary Report</a>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

  
  <form method="GET" action="<?php echo e(route('salary')); ?>" class="card"
    style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
    <div class="fld" style="flex:1;min-width:160px">
      <label>Search</label>
      <input type="text" name="search" value="<?php echo e(request('search')); ?>" placeholder="Name or employee ID…">
    </div>
    <div class="fld">
      <label>Month</label>
      <input type="month" name="month" value="<?php echo e($monthParam ?? ''); ?>"
        style="padding:8px 12px;border:1.5px solid var(--b1);border-radius:8px;background:#f9fafb;font-family:inherit;font-size:13px;color:var(--txt);outline:none"
        onfocus="this.style.borderColor='var(--acc)'" onblur="this.style.borderColor='var(--b1)'">
    </div>
    <button type="submit" class="btn acc">Filter</button>
    <a href="<?php echo e(route('salary')); ?>" class="btn">Reset</a>
  </form>

  
  <?php if($isHistoric): ?>
    <div class="ibox" style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
      <span style="font-size:15px">📅</span>
      <span>Showing salaries effective during <strong><?php echo e($monthLabel); ?></strong>.
        Edits are disabled for historical view.
        <a href="<?php echo e(route('salary')); ?>" style="color:var(--acc);font-weight:600;margin-left:6px">View current →</a>
      </span>
    </div>
  <?php endif; ?>

  <div class="card" style="overflow-x:auto">
    <?php if($employees->isNotEmpty()): ?>
      <table>
        <thead>
          <tr>
            <th>Emp ID</th>
            <th>Name</th>
            <th>CTC Annual</th>
            <th>Gross Monthly</th>
            <th>Effective From</th>
            <?php if(!$isHistoric): ?>
            <th>Actions</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php $__currentLoopData = $employees; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $emp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php $salary = $emp->salaryForMonth; ?>
            <tr>
              <td class="mono"><?php echo e($emp->employee_id); ?></td>
              <td><?php echo e($emp->first_name); ?> <?php echo e($emp->last_name); ?></td>
              
              
              <td>
                <?php if($salary): ?>
                  <?php if($isAdmin): ?>
                    <?php $ctcDec = $salary->getCtcAnnualDecrypted(); ?>
                    <?php if($ctcDec > 0): ?>
                      ₹ <?php echo e(number_format($ctcDec, 0, '.', ',')); ?>

                      <span style="font-size:9px;color:var(--grn)" title="Encrypted in database">🔓</span>
                    <?php else: ?>
                      <span style="color:var(--t3)">—</span>
                    <?php endif; ?>
                  <?php else: ?>
                    <span title="Salary is confidential" style="color:var(--t3);letter-spacing:2px;font-size:11px">🔒
                      Confidential</span>
                  <?php endif; ?>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>

              
              <td>
                <?php if($salary): ?>
                  <?php if($isAdmin): ?>
                    <?php $grossDec = $salary->getGrossDecrypted(); ?>
                    <?php if($grossDec > 0): ?>
                      ₹ <?php echo e(number_format($grossDec, 0, '.', ',')); ?>

                      <span style="font-size:9px;color:var(--grn)" title="Encrypted in database">🔓</span>
                    <?php else: ?>
                      <span style="color:var(--t3)">—</span>
                    <?php endif; ?>
                  <?php else: ?>
                    <span title="Salary is confidential" style="color:var(--t3);letter-spacing:2px;font-size:11px">🔒
                      Confidential</span>
                  <?php endif; ?>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>

              
              <td>
                <?php echo e($salary?->effective_from
            ? \Carbon\Carbon::parse($salary->effective_from)->format('d M Y')
            : '—'); ?>

              </td>

              
              <?php if(!$isHistoric): ?>
                <td style="white-space:nowrap">
                  <button class="btn sm" onclick="openSalModal(
                      <?php echo e($emp->id); ?>,
                      '<?php echo e(addslashes($emp->first_name . ' ' . $emp->last_name)); ?>',
                      <?php echo e($salary ? $salary->getCtcAnnualDecrypted() : 0); ?>,
                      <?php echo e($salary ? $salary->getGrossDecrypted() : 0); ?>

                    )">
                    <?php echo e($salary ? 'Edit' : 'Set'); ?>

                  </button>
                  <?php if($salary): ?>
                    <form method="POST" action="<?php echo e(route('salary.destroy', $salary)); ?>" style="display:inline">
                      <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                      <button type="button" class="btn sm" style="color:var(--red)"
                        onclick="swConfirm(this.closest('form'), 'Delete Salary Record?', 'This salary record will be permanently deleted.', 'warning', 'Yes, delete', '#dc2626')">Del</button>
                    </form>
                  <?php endif; ?>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
      </table>
      <div style="margin-top:14px"><?php echo e($employees->links()); ?></div>
    <?php else: ?>
      <div class="empty">
        <div class="ico">🧾</div>
        <p><?php echo e($isHistoric ? 'No salary records found for ' . $monthLabel . '.' : 'No employees found.'); ?></p>
      </div>
    <?php endif; ?>
  </div>

  
  <?php if(!$isHistoric): ?>
    <div class="modal" id="sal-modal">
      <div class="mb">
        <div class="mh">
          <span class="mt" id="sal-modal-title">Set Salary</span>
          <button class="mc" onclick="cm('sal-modal')">✕</button>
        </div>
        <form method="POST" id="sal-form">
          <?php echo csrf_field(); ?>
          <div class="fg2">
            <div class="fld">
              <label class="req">CTC Annual (₹)</label>
              <input type="number" name="ctc_annual" id="sal-ctc" required min="1" oninput="calcGross()"
                <?php $__errorArgs = ['ctc_annual'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
              <?php $__errorArgs = ['ctc_annual'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
            <div class="fld">
              <label class="req">Gross Monthly (₹)</label>
              <input type="number" name="gross_monthly" id="sal-gross" required min="1"
                <?php $__errorArgs = ['gross_monthly'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
              <?php $__errorArgs = ['gross_monthly'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
            <div class="fld full">
              <label class="req">Effective From</label>
              <input type="date" name="effective_from" required value="<?php echo e(now()->toDateString()); ?>"
                <?php $__errorArgs = ['effective_from'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
              <?php $__errorArgs = ['effective_from'];
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
            <button type="button" class="btn" onclick="cm('sal-modal')">Cancel</button>
            <button type="submit" class="btn acc">Save</button>
          </div>
        </form>
      </div>
    </div>

    
    <div class="modal" id="sal-import-modal">
      <div class="mb">
        <div class="mh">
          <span class="mt">Import Salary Data</span>
          <button class="mc" onclick="cm('sal-import-modal')">✕</button>
        </div>
        <div style="background:var(--acc2);border:1px solid var(--acc);border-radius:8px;
                    padding:12px 14px;margin-bottom:14px;font-size:12px;line-height:1.6;color:var(--t2)">
          Template columns: <span class="mono" style="font-size:11px">Employee Name · Emp ID · Gross Salary</span><br>
          Gross Salary is stored encrypted. Visible to admin only.
        </div>
        <form method="POST" action="<?php echo e(route('salary.import')); ?>" enctype="multipart/form-data">
          <?php echo csrf_field(); ?>
          <div class="fg2" style="margin-bottom:14px">
            <div class="fld">
              <label>Effective Month *</label>
              <input type="month" name="import_month" required value="<?php echo e(now()->format('Y-m')); ?>"
                style="padding:9px 12px;border:1.5px solid var(--b1);border-radius:8px;background:#f9fafb;font-family:inherit;font-size:13px;color:var(--txt);outline:none;width:100%">
              <span style="font-size:10px;color:var(--t3)">Salaries will be effective from the 1st of this month</span>
            </div>
            <div class="fld" style="display:flex;flex-direction:column;gap:5px">
              <label>Salary File (.xlsx / .xls) *</label>
              <input type="file" name="file" accept=".xlsx,.xls" required
                style="background:var(--s2);border:1.5px dashed var(--b2);border-radius:8px;padding:10px;cursor:pointer;width:100%;flex:1">
            </div>
          </div>
          <div class="mf">
            <button type="button" class="btn" onclick="cm('sal-import-modal')">Cancel</button>
            <button type="submit" class="btn acc">📤 Import</button>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <?php $__env->startPush('scripts'); ?>
    <script>
      function openSalModal(empId, name, ctc, gross) {
        document.getElementById('sal-modal-title').textContent = 'Salary — ' + name;
        document.getElementById('sal-form').action = '/salary/' + empId + '/set';
        document.getElementById('sal-ctc').value = ctc || '';
        document.getElementById('sal-gross').value = gross || '';
        om('sal-modal');
      }
      function calcGross() {
        const ctc = parseFloat(document.getElementById('sal-ctc').value) || 0;
        document.getElementById('sal-gross').value = Math.round(ctc * 0.9 / 12);
      }
    </script>
  <?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/salary/index.blade.php ENDPATH**/ ?>