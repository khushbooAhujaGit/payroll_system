<?php $__env->startSection('title', 'Monthly TDS'); ?>

<?php $__env->startSection('page_actions'); ?>
  <a href="<?php echo e(route('tds.monthly.template')); ?>" class="btn">📥 Template</a>
  <button class="btn" onclick="om('tds-import-modal')">📤 Import</button>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

  
  <form method="GET" action="<?php echo e(route('tds.monthly')); ?>" class="card"
    style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end">
    <div class="fld" style="min-width:180px">
      <label>Month *</label>
      <input type="month" name="month" value="<?php echo e($month); ?>" required>
    </div>
    <button type="submit" class="btn acc">View</button>
  </form>

  
  <div class="g4" style="margin-bottom:16px">
    <div class="sc">
      <div class="lbl">Employees</div>
      <div class="val"><?php echo e($employees->count()); ?></div>
    </div>
    <div class="sc">
      <div class="lbl">With TDS</div>
      <div class="val" style="color:var(--acc)"><?php echo e($tdsRecords->count()); ?></div>
    </div>
    <div class="sc">
      <div class="lbl">Total TDS (₹)</div>
      <div class="val"><?php echo e(number_format($totalTds, 0)); ?></div>
    </div>
    <div class="sc">
      <div class="lbl">Month</div>
      <div class="val" style="font-size:14px"><?php echo e(\Carbon\Carbon::createFromFormat('Y-m', $month)->format('M Y')); ?></div>
    </div>
  </div>

  <div class="card">
    <div class="ch">
      <div class="ct">Monthly TDS Deductions — <?php echo e(\Carbon\Carbon::createFromFormat('Y-m', $month)->format('M Y')); ?></div>
      <div style="font-size:11px;color:var(--t3)">Click <strong>Add</strong> next to an employee to enter their TDS
        deduction for this month</div>
    </div>
    <table>
      <thead>
        <tr>
          <th>Emp ID</th>
          <th>Employee</th>
          <th style="text-align:right">TDS (₹)</th>
          <th>Remarks</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php $__currentLoopData = $employees; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $emp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php $tds = $tdsRecords[$emp->id] ?? null; ?>
          <tr>
            <td class="mono"><?php echo e($emp->employee_id); ?></td>
            <td><?php echo e($emp->first_name); ?> <?php echo e($emp->last_name); ?></td>
            <td
              style="text-align:right;font-weight:<?php echo e($tds ? '600' : '400'); ?>;color:<?php echo e($tds ? 'var(--acc)' : 'var(--t3)'); ?>">
              <?php echo e($tds ? '₹ ' . number_format($tds->amount, 0) : '—'); ?>

            </td>
            <td style="font-size:11px;color:var(--t2)"><?php echo e($tds?->remarks ?? '—'); ?></td>
            <td style="white-space:nowrap">
              <button class="btn sm"
                onclick="openTdsModal(<?php echo e($emp->id); ?>, '<?php echo e(addslashes($emp->first_name)); ?> <?php echo e(addslashes($emp->last_name)); ?>', <?php echo e($tds?->amount ?? 0); ?>, '<?php echo e(addslashes($tds?->remarks ?? '')); ?>', '<?php echo e($tds?->month ?? $month); ?>')">
                <?php echo e($tds ? 'Edit' : 'Add'); ?>

              </button>
              <?php if($tds): ?>
                <form method="POST" action="<?php echo e(route('tds.monthly.destroy', $tds)); ?>" style="display:inline">
                  <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                  <button type="button" class="btn sm" style="color:var(--red)"
                    onclick="swConfirm(this.closest('form'), 'Remove TDS?', 'Monthly TDS for <?php echo e($emp->first_name); ?> will be permanently removed.', 'warning', 'Yes, remove', '#dc2626')">Del</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </tbody>
    </table>
  </div>

  
  <div class="modal" id="tds-modal">
    <div class="mb">
      <div class="mh">
        <span class="mt" id="tds-modal-title">Set Monthly TDS</span>
        <button class="mc" onclick="cm('tds-modal')">✕</button>
      </div>
      <form method="POST" action="<?php echo e(route('tds.monthly.upsert')); ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="employee_id" id="tds-emp-id">
        <div class="fg2">
          <div class="fld">
            <label>Month *</label>
            <input type="month" name="month" id="tds-month" required value="<?php echo e($month); ?>">
          </div>
          <div class="fld">
            <label>TDS Amount (₹) *</label>
            <input type="number" name="amount" id="tds-amount" required min="0" step="0.01">
          </div>
          <div class="fld full">
            <label>Remarks</label>
            <input type="text" name="remarks" id="tds-remarks" maxlength="200" placeholder="e.g. Computed from ITR">
          </div>
        </div>
        <div class="mf">
          <button type="button" class="btn" onclick="cm('tds-modal')">Cancel</button>
          <button type="submit" class="btn acc">Save</button>
        </div>
      </form>
    </div>
  </div>

  
  <div class="modal" id="tds-import-modal">
    <div class="mb">
      <div class="mh">
        <span class="mt">Import Monthly TDS</span>
        <button class="mc" onclick="cm('tds-import-modal')">✕</button>
      </div>
      <div style="background:var(--acc2);border:1px solid var(--acc);border-radius:8px;
                  padding:12px 14px;margin-bottom:14px;font-size:12px;line-height:1.6;color:var(--t2)">
        Template columns: <span class="mono">Emp ID · TDS Amount · Remarks</span>
      </div>
      <form method="POST" action="<?php echo e(route('tds.monthly.import')); ?>" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <div class="fg2" style="margin-bottom:14px">
          <div class="fld">
            <label>Month *</label>
            <input type="month" name="import_month" required value="<?php echo e($month); ?>">
          </div>
          <div class="fld">
            <label>Excel File (.xlsx / .xls) *</label>
            <input type="file" name="file" accept=".xlsx,.xls" required
              style="background:var(--s2);border:1.5px dashed var(--b2);border-radius:8px;padding:14px;cursor:pointer;width:100%">
          </div>
        </div>
        <div class="mf">
          <button type="button" class="btn" onclick="cm('tds-import-modal')">Cancel</button>
          <button type="submit" class="btn acc">📤 Import</button>
        </div>
      </form>
    </div>
  </div>

  <?php $__env->startPush('scripts'); ?>
    <script>
      function openTdsModal(empId, name, amount, remarks, month) {
        document.getElementById('tds-modal-title').textContent = 'Monthly TDS — ' + name;
        document.getElementById('tds-emp-id').value = empId;
        document.getElementById('tds-month').value = month || '<?php echo e($month); ?>';
        document.getElementById('tds-amount').value = amount || '';
        document.getElementById('tds-remarks').value = remarks || '';
        om('tds-modal');
      }
    </script>
  <?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/monthly-tds/index.blade.php ENDPATH**/ ?>