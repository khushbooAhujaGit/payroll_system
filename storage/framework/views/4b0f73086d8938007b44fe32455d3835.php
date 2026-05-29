<?php $__env->startSection('title', 'Reports'); ?>

<?php $__env->startSection('page_actions'); ?>
  <button class="btn" onclick="om('upload-modal')">📤 Bulk Upload</button>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<div class="g4" style="margin-bottom:16px">
  <?php $__currentLoopData = ['active'=>['label'=>'Active','color'=>'var(--grn)'],
            'inactive'=>['label'=>'Inactive','color'=>'var(--t3)'],
            'on_notice'=>['label'=>'On Notice','color'=>'var(--yel)'],
            'terminated'=>['label'=>'Terminated','color'=>'var(--red)']]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status => $info): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <div class="sc">
    <div class="lbl"><?php echo e($info['label']); ?></div>
    <div class="val" style="color:<?php echo e($info['color']); ?>"><?php echo e($headcount[$status] ?? 0); ?></div>
  </div>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>

<div class="g2" style="margin-bottom:16px">
  <div class="card">
    <div class="ch"><div class="ct">Department Headcount</div></div>
    <?php if($deptBreakdown->isNotEmpty()): ?>
    <table>
      <thead><tr><th>Department</th><th>Employees</th></tr></thead>
      <tbody>
        <?php $__currentLoopData = $deptBreakdown; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
          <td><?php echo e($row->department?->name ?? 'Unknown'); ?></td>
          <td><?php echo e($row->count); ?></td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="empty"><div class="ico">🏗️</div><p>No department data.</p></div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="ch"><div class="ct">Last Payroll Summary</div></div>
    <?php if($lastPayroll && $lastRunRecords): ?>
    <div style="padding:8px 0">
      <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--b1)">
        <span style="color:var(--t3)">Period</span>
        <span><?php echo e($lastPayroll->period?->year); ?>/<?php echo e(str_pad($lastPayroll->period?->month,2,'0',STR_PAD_LEFT)); ?></span>
      </div>
      <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--b1)">
        <span style="color:var(--t3)">Employees</span>
        <span><?php echo e($lastRunRecords->count); ?></span>
      </div>
      <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--b1)">
        <span style="color:var(--t3)">Total Gross</span>
        <span>₹ <?php echo e(number_format($lastRunRecords->total_gross,0,'.',',')); ?></span>
      </div>
      <div style="display:flex;justify-content:space-between;padding:7px 0">
        <span style="color:var(--t3)">Total Net</span>
        <span style="font-weight:600">₹ <?php echo e(number_format($lastRunRecords->total_net,0,'.',',')); ?></span>
      </div>
    </div>
    <?php else: ?>
    <div class="empty"><div class="ico">💰</div><p>No payroll runs yet.</p></div>
    <?php endif; ?>
  </div>
</div>

<div class="card" style="margin-bottom:16px">
  <div class="ch"><div class="ct">Attendance Report</div></div>
  <form method="GET" action="<?php echo e(route('reports.attendance')); ?>"
        style="display:flex;gap:10px;align-items:flex-end;padding:4px 0 8px;flex-wrap:wrap">
    <div class="fld" style="min-width:160px">
      <label>Month *</label>
      <input type="month" name="month" value="<?php echo e(now()->format('Y-m')); ?>" required>
    </div>
    <button type="submit" class="btn acc">Generate Report →</button>
  </form>
</div>

<div class="card">
  <div class="ch"><div class="ct">Download Reports</div></div>
  <div class="g3">
    <a href="<?php echo e(route('employees', ['export' => 1])); ?>" class="card" style="display:block;text-decoration:none;cursor:pointer;transition:box-shadow .15s" onmouseover="this.style.boxShadow='0 4px 14px rgba(0,0,0,.12)'" onmouseout="this.style.boxShadow=''">
      <div style="font-size:22px;margin-bottom:8px">👥</div>
      <div style="font-weight:600;margin-bottom:4px">Employee Master</div>
      <div style="font-size:11px;color:var(--t3)">All employee details and statutory info</div>
    </a>
    <a href="<?php echo e(route('attendance', ['export' => 1])); ?>" class="card" style="display:block;text-decoration:none;cursor:pointer">
      <div style="font-size:22px;margin-bottom:8px">🕐</div>
      <div style="font-weight:600;margin-bottom:4px">Attendance Register</div>
      <div style="font-size:11px;color:var(--t3)">Monthly attendance data</div>
    </a>
    <div class="card" style="cursor:pointer" onclick="toast('Payroll register — run a payroll first.','er')">
      <div style="font-size:22px;margin-bottom:8px">💰</div>
      <div style="font-weight:600;margin-bottom:4px">Payroll Register</div>
      <div style="font-size:11px;color:var(--t3)">Salary computation sheet</div>
    </div>
    <div class="card" style="cursor:pointer" onclick="toast('Leave summary export — coming soon.','er')">
      <div style="font-size:22px;margin-bottom:8px">🏖️</div>
      <div style="font-weight:600;margin-bottom:4px">Leave Summary</div>
      <div style="font-size:11px;color:var(--t3)">Leave balances and utilisation</div>
    </div>
    <div class="card" style="cursor:pointer" onclick="toast('Loan report — coming soon.','er')">
      <div style="font-size:22px;margin-bottom:8px">🏦</div>
      <div style="font-weight:600;margin-bottom:4px">Loan Register</div>
      <div style="font-size:11px;color:var(--t3)">Outstanding loans and EMI schedule</div>
    </div>
    <div class="card" style="cursor:pointer" onclick="toast('Bank transfer file — finalize payroll first.','er')">
      <div style="font-size:22px;margin-bottom:8px">🏛️</div>
      <div style="font-weight:600;margin-bottom:4px">Bank Transfer File</div>
      <div style="font-size:11px;color:var(--t3)">NEFT/RTGS payment file</div>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('partials.upload-modal', [
    'uploadId'    => 'upload-modal',
    'uploadTitle' => 'Bulk Data Import',
    'uploadRoute' => 'employees.import',
    'templateRoute' => 'employees.template',
    'uploadHint'  => 'Use this to bulk-import employee master data. Template columns: First Name, Last Name, Gender, Date of Birth, Mobile, Official Email, Date of Joining, Employment Type, Work Type, Department, Designation, PAN, UAN, Bank Name, Account Number, IFSC Code, CTC Annual.',
], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/reports/index.blade.php ENDPATH**/ ?>