<?php $__env->startSection('title', 'Payroll & Salary Reports'); ?>

<?php $__env->startSection('page_actions'); ?>
  <button class="btn acc" onclick="om('gen-modal')">📊 Generate Report</button>
  <button class="btn" onclick="om('period-modal')" style="display:none !important;">+ New Period</button>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>


<div class="g4" style="margin-bottom:16px">
  <div class="sc">
    <div class="lbl">Saved Reports</div>
    <div class="val"><?php echo e($savedReports->count()); ?></div>
  </div>
  <div class="sc">
    <div class="lbl">Latest Report</div>
    <div class="val" style="font-size:13px">
      <?php if($savedReports->isNotEmpty()): ?>
        <?php echo e(\Carbon\Carbon::createFromFormat('Y-m', $savedReports->first()['year_month'])->format('M Y')); ?>

      <?php else: ?> —
      <?php endif; ?>
    </div>
  </div>
  <div class="sc">
    <div class="lbl">Payroll Periods</div>
    <div class="val"><?php echo e($periods->total()); ?></div>
  </div>
  <?php if($lastRun): ?>
  <div class="sc">
    <div class="lbl">Last Run</div>
    <div class="val" style="font-size:12px"><?php echo e($lastRun->run_name); ?> — <?php echo e($lastRun->period?->year); ?>/<?php echo e(str_pad($lastRun->period?->month,2,'0',STR_PAD_LEFT)); ?></div>
  </div>
  <?php else: ?>
  <div class="sc"><div class="lbl">Last Run</div><div class="val" style="color:var(--t3)">None</div></div>
  <?php endif; ?>
</div>


<div class="card" style="margin-bottom:16px">
  <div class="ch" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <div class="ct">🗂 Salary Reports</div>
    <button class="btn sm acc" onclick="om('gen-modal')">📊 Generate / Preview Report</button>
  </div>

  <?php if($savedReports->isNotEmpty()): ?>
  <table>
    <thead>
      <tr>
        <th>Month</th>
        <th style="text-align:center">Employees</th>
        <th style="text-align:right">Total Payable</th>
        <th style="text-align:right">Total Net Pay</th>
        <th>Locked</th>
        <th>By</th>
        <th style="text-align:center">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php $__currentLoopData = $savedReports; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rep): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <?php
        $repLabel  = \Carbon\Carbon::createFromFormat('Y-m', $rep['year_month'])->format('F Y');
        $lockerName = $lockers[$rep['locked_by']] ?? '—';
      ?>
      <tr>
        <td>
          <span class="mono" style="font-weight:600"><?php echo e($rep['year_month']); ?></span>
          <div style="font-size:10px;color:var(--t3)"><?php echo e($repLabel); ?></div>
        </td>
        <td style="text-align:center"><?php echo e($rep['employee_count']); ?></td>
        <td style="text-align:right;color:var(--acc);font-weight:600">
          ₹ <?php echo e(number_format($rep['total_gross'], 0)); ?>

        </td>
        <td style="text-align:right;color:var(--grn);font-weight:700">
          ₹ <?php echo e(number_format($rep['total_net_pay'], 0)); ?>

        </td>
        <td style="font-size:11px;color:var(--t3)">
          <?php echo e(\Carbon\Carbon::parse($rep['locked_at'])->format('d M Y, h:i A')); ?>

        </td>
        <td style="font-size:11px"><?php echo e($lockerName); ?></td>
        <td style="white-space:nowrap;text-align:center">
          
          <a href="<?php echo e(route('salary.report.view', $rep['year_month'])); ?>" class="btn sm">
            🔒 View
          </a>
          
          <form method="POST" action="<?php echo e(route('salary.report.download')); ?>" style="display:inline">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="month" value="<?php echo e($rep['year_month']); ?>">
            <input type="hidden" name="company_id" value="0">
            <button type="submit" class="btn sm" style="background:var(--grn2);color:var(--grn)">
              📥 Excel
            </button>
          </form>
          
          <form method="POST" action="<?php echo e(route('salary.report.unlock')); ?>" style="display:inline">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="month" value="<?php echo e($rep['year_month']); ?>">
            <input type="hidden" name="company_id" value="0">
            <button type="button" class="btn sm" style="background:#fff7ed;color:#c2410c"
              onclick="swConfirm(this.closest('form'),
                'Unlock <?php echo e($repLabel); ?>?',
                'This removes the frozen snapshot and reverses any loan EMI deductions recorded for this month. The report will recalculate live again.',
                'warning','Yes, unlock','#c2410c')">
              🔓 Unlock
            </button>
          </form>
          
          <form method="POST" action="<?php echo e(route('salary.report.delete')); ?>" style="display:inline">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="month" value="<?php echo e($rep['year_month']); ?>">
            <button type="button" class="btn sm" style="color:var(--red)"
              onclick="swConfirm(this.closest('form'),
                'Delete <?php echo e($repLabel); ?> snapshot?',
                'This permanently removes the saved report. The data cannot be recovered.',
                'warning','Yes, delete','#dc2626')">
              🗑
            </button>
          </form>
        </td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
  </table>
  <?php else: ?>
  <div class="empty">
    <div class="ico">📊</div>
    <p>No saved salary reports yet.</p>
    <p style="font-size:12px;color:var(--t3)">Click "Generate / Preview Report" to create and lock a salary report for any month.</p>
  </div>
  <?php endif; ?>
</div>


<div class="card">
  <div class="ch" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <div class="ct">📅 Payroll Periods</div>
    <button class="btn sm" onclick="om('period-modal')">+ New Period</button>
  </div>

  <?php if($periods->isNotEmpty()): ?>
  <table>
    <thead>
      <tr>
        <th>Period</th>
        <th>From</th>
        <th>To</th>
        <th>Status</th>
        <th style="text-align:center">Runs</th>
        <th>Salary Report</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php $__currentLoopData = $periods; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $period): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <?php
        $ym        = $period->year . '-' . str_pad($period->month, 2, '0', STR_PAD_LEFT);
        $hasReport = $savedReports->firstWhere('year_month', $ym) !== null;
        $clr       = ['open' => 'var(--grn)', 'closed' => 'var(--t3)', 'locked' => 'var(--red)'][$period->status] ?? 'var(--t3)';
      ?>
      <tr>
        <td class="mono"><?php echo e($ym); ?></td>
        <td><?php echo e(\Carbon\Carbon::parse($period->period_from)->format('d M Y')); ?></td>
        <td><?php echo e(\Carbon\Carbon::parse($period->period_to)->format('d M Y')); ?></td>
        <td>
          <span class="bx" style="color:<?php echo e($clr); ?>"><?php echo e(ucfirst($period->status)); ?></span>
          <?php if($period->is_locked): ?><span class="bx" style="background:var(--red2);color:var(--red)">Locked</span><?php endif; ?>
        </td>
        <td style="text-align:center"><?php echo e($period->runs_count); ?></td>
        <td>
          <?php if($hasReport): ?>
            <a href="<?php echo e(route('salary.report.view', $ym)); ?>" class="btn sm" style="background:var(--grn2);color:var(--grn)">
              🔒 View Report
            </a>
          <?php else: ?>
            <a href="<?php echo e(route('salary.report')); ?>?month=<?php echo e($ym); ?>" class="btn sm">
              📊 Generate
            </a>
          <?php endif; ?>
        </td>
        <td>
          <?php if(!$period->is_locked): ?>
          <button class="btn sm" onclick="om('run-modal');document.getElementById('run-period-id').value='<?php echo e($period->id); ?>'">
            ▶ Run Payroll
          </button>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
  </table>
  <div style="margin-top:14px"><?php echo e($periods->links()); ?></div>
  <?php else: ?>
  <div class="empty"><div class="ico">💰</div><p>No payroll periods yet. Create one to get started.</p></div>
  <?php endif; ?>
</div>


<div class="modal" id="gen-modal">
  <div class="mb" style="max-width:440px">
    <div class="mh">
      <span class="mt">Generate Salary Report</span>
      <button class="mc" onclick="cm('gen-modal')">✕</button>
    </div>
    <p style="font-size:12px;color:var(--t3);margin-bottom:16px">
      Select month and firm to preview the live salary sheet. From there you can review, edit overrides, and lock (save) the report.
    </p>
    <div class="fg2">
      <div class="fld">
        <label>Month *</label>
        <input type="month" id="gen-month" value="<?php echo e(now()->format('Y-m')); ?>" required>
      </div>
      <div class="fld">
        <label>Firm</label>
        <select id="gen-entity">
          <option value="0">All Firms</option>
          <?php $__currentLoopData = $companies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($ent->id); ?>"><?php echo e($ent->short_name ?? $ent->name); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
      </div>
    </div>
    <div class="mf" style="margin-top:16px">
      <button type="button" class="btn" onclick="cm('gen-modal')">Cancel</button>
      <button type="button" class="btn acc" onclick="goToReport()">Preview Report →</button>
    </div>
  </div>
</div>


<div class="modal" id="period-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Create Payroll Period</span>
      <button class="mc" onclick="cm('period-modal')">✕</button>
    </div>
    <form method="POST" action="<?php echo e(route('payroll.period')); ?>">
      <?php echo csrf_field(); ?>
      <div class="fg2">
        <div class="fld">
          <label>Year *</label>
          <input type="number" name="year" required value="<?php echo e(now()->year); ?>" min="2020" max="2100">
        </div>
        <div class="fld">
          <label>Month *</label>
          <select name="month" required>
            <?php $__currentLoopData = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($i+1); ?>" <?php echo e(now()->month == $i+1 ? 'selected' : ''); ?>><?php echo e($m); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
        </div>
        <div class="fld">
          <label>Period From *</label>
          <input type="date" name="period_from" required value="<?php echo e(now()->startOfMonth()->toDateString()); ?>">
        </div>
        <div class="fld">
          <label>Period To *</label>
          <input type="date" name="period_to" required value="<?php echo e(now()->endOfMonth()->toDateString()); ?>">
        </div>
        <div class="fld full">
          <label>Payment Date</label>
          <input type="date" name="payment_date" value="<?php echo e(now()->endOfMonth()->addDays(5)->toDateString()); ?>">
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('period-modal')">Cancel</button>
        <button type="submit" class="btn acc">Create Period</button>
      </div>
    </form>
  </div>
</div>


<div class="modal" id="run-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Initiate Payroll Run</span>
      <button class="mc" onclick="cm('run-modal')">✕</button>
    </div>
    <form method="POST" action="<?php echo e(route('payroll.run')); ?>">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="payroll_period_id" id="run-period-id">
      <div class="fld" style="margin-bottom:14px">
        <label>Run Type *</label>
        <select name="run_type" required>
          <option value="regular">Regular</option>
          <option value="supplementary">Supplementary</option>
          <option value="arrear">Arrear</option>
          <option value="bonus">Bonus</option>
        </select>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('run-modal')">Cancel</button>
        <button type="submit" class="btn acc">▶ Initiate</button>
      </div>
    </form>
  </div>
</div>

<?php if($errors->any()): ?>
<script>document.addEventListener('DOMContentLoaded',()=>om('period-modal'))</script>
<?php endif; ?>

<?php $__env->startPush('scripts'); ?>
<script>
function goToReport() {
  const month  = document.getElementById('gen-month').value;
  const entity = document.getElementById('gen-entity').value;
  if (!month) { alert('Please select a month.'); return; }
  window.location.href = '<?php echo e(route('salary.report')); ?>?month=' + month + '&company_id=' + entity;
}
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/payroll/index.blade.php ENDPATH**/ ?>