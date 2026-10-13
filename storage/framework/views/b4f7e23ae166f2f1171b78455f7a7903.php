<?php $__env->startSection('title', 'Locked Salary Report — ' . \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y')); ?>

<?php $__env->startSection('page_actions'); ?>
  <a href="<?php echo e(route('payroll')); ?>" class="btn">← Payroll</a>
  <form method="POST" action="<?php echo e(route('salary.report.download')); ?>" style="display:inline">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="month" value="<?php echo e($month); ?>">
    <input type="hidden" name="company_id" value="<?php echo e($companyId); ?>">
    <button type="submit" class="btn acc">📥 Download 🔒 Excel</button>
  </form>
  <button class="btn" onclick="om('reupload-modal')" style="border-color:var(--acc)">📤 Re-upload Edited Report</button>
  <?php $deleteLabel = \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y'); ?>
  <form method="POST" action="<?php echo e(route('salary.report.delete')); ?>" style="display:inline">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="month" value="<?php echo e($month); ?>">
    <button type="button" class="btn" style="color:var(--red)"
      onclick="swConfirm(this.closest('form'),
        'Delete this snapshot?',
        'This permanently removes the saved <?php echo e($deleteLabel); ?> report. The data cannot be recovered.',
        'warning', 'Yes, delete it', '#dc2626')">
      🗑 Delete Snapshot
    </button>
  </form>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>


<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
  <span style="font-size:20px">🔒</span>
  <div style="flex:1;min-width:200px">
    <div style="font-weight:600;color:#166534;font-size:14px">
      Locked Report — <?php echo e(\Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y')); ?>

    </div>
    <div style="font-size:11px;color:#15803d;margin-top:2px">
      Frozen snapshot · <?php echo e($snapshotCount); ?> employees · Locked on <?php echo e($lockedAt?->format('d M Y, h:i A')); ?>

    </div>
  </div>
  <div style="font-size:11px;color:var(--t3);font-style:italic">
    Read-only view — to edit rows, open the report from <a href="<?php echo e(route('salary.report', ['month' => $month])); ?>" style="color:var(--acc)">Salary Report →</a>
  </div>
</div>


<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;margin-bottom:16px">
  <?php
    $cards = [
      ['Headcount',       $summary['headcount'],                                          null,         '👥'],
      ['Gross Payable',   '₹'.number_format($summary['payable_gross'],0),     'var(--acc)', '💰'],
      ['Total Incentive', '₹'.number_format($summary['incentive'],0),         'var(--grn)', '🎁'],
      ['Reimbursement',   '₹'.number_format($summary['reimbursement'],0),     'var(--grn)', '🧾'],
      ['Employee PF',     '₹'.number_format($summary['employee_pf'],0),        null,        '🏦'],
      ['Prof. Tax',       '₹'.number_format($summary['professional_tax'],0),   null,        '📋'],
      ['Advance/EMI',     '₹'.number_format($summary['advance_salary'],0),     null,        '💳'],
      ['TDS',             '₹'.number_format($summary['tds'],0),                null,        '📑'],
      ['Total Deduction', '₹'.number_format($summary['total_deduction'],0),   'var(--red)', '➖'],
      ['Net Pay',         '₹'.number_format($summary['net_pay'],0),           'var(--grn)', '✅'],
      ['CTC (Total)',     '₹'.number_format($summary['ctc'],0),                null,        '📊'],
    ];
  ?>
  <?php $__currentLoopData = $cards; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label,$val,$color,$ico]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <div class="sc">
    <div class="lbl"><?php echo e($ico); ?> <?php echo e($label); ?></div>
    <div class="val" style="font-size:13px;<?php echo e($color ? 'color:'.$color : ''); ?>"><?php echo e($val); ?></div>
  </div>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>


<form method="GET" action="<?php echo e(route('salary.report.view', $month)); ?>" data-ajax-filter
      class="card" style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
  <div class="fld" style="min-width:220px">
    <label>Search Emp ID / Name</label>
    <input type="text" name="search" value="<?php echo e($search ?? ''); ?>" placeholder="e.g. EMP001 or John…">
  </div>
  <div class="fld" style="min-width:160px">
    <label>Filter by</label>
    <select name="filter">
      <option value="" <?php echo e(($filter??'')==='' ? 'selected' : ''); ?>>— None —</option>
      <option value="tds"       <?php echo e(($filter??'')==='tds'       ? 'selected' : ''); ?>>Has TDS</option>
      <option value="incentive" <?php echo e(($filter??'')==='incentive' ? 'selected' : ''); ?>>Has Incentive</option>
      <option value="loan"      <?php echo e(($filter??'')==='loan'      ? 'selected' : ''); ?>>Has Loan / EMI</option>
      <option value="lop"       <?php echo e(($filter??'')==='lop'       ? 'selected' : ''); ?>>Has LOP</option>
    </select>
  </div>
  <button type="submit" class="btn acc">Search</button>
  <?php if(!empty($search) || !empty($filter)): ?>
    <a href="<?php echo e(route('salary.report.view', $month)); ?>" class="btn" data-ajax-reset>✕ Clear</a>
    <?php if(!empty($search) || !empty($filter)): ?>
      <span style="font-size:12px;color:var(--t2);padding-bottom:2px">
        <?php echo e($rows->total()); ?> result(s)
        <?php if(!empty($filter)): ?>
          <?php $filterLabels = ['tds'=>'TDS','incentive'=>'Incentive','loan'=>'Loan/EMI','lop'=>'LOP']; ?>
          <span style="background:var(--acc2);color:var(--acc);padding:2px 6px;border-radius:4px;font-size:10px;margin-left:4px">
            <?php echo e($filterLabels[$filter] ?? $filter); ?>

          </span>
        <?php endif; ?>
      </span>
    <?php endif; ?>
  <?php endif; ?>
</form>

<div id="ajax-results">
<div class="card" style="overflow-x:auto;will-change:transform;-webkit-overflow-scrolling:touch">
  <div class="ch">
    <div class="ct">
      Salary Sheet — <?php echo e(\Carbon\Carbon::createFromFormat('Y-m',$month)->format('F Y')); ?> · All Firms
      <span style="background:#dcfce7;color:#166534;border-radius:4px;padding:2px 6px;font-size:10px;margin-left:8px">🔒 LOCKED</span>
    </div>
    <div style="font-size:11px;color:var(--t3)">
      <?php echo e($rows->total()); ?><?php echo e(!empty($search) ? ' matching "'.e($search).'"' : ''); ?> of <?php echo e($snapshotCount); ?> employees
      <?php if($rows->lastPage() > 1): ?> · Page <?php echo e($rows->currentPage()); ?> / <?php echo e($rows->lastPage()); ?> <?php endif; ?>
      · Scroll right for full details
    </div>
  </div>
  <table style="font-size:11px;min-width:2800px">
    <thead>
      <tr>
        <th>Emp ID</th>
        <th>Employee</th>
        <th>Designation</th>
        <th style="text-align:right">Gross</th>
        <th>Month</th>
        <th style="text-align:center">Mo Days</th>
        <th style="text-align:center">W/Off+H</th>
        <th style="text-align:center">Work Days</th>
        <th style="text-align:center">LOP</th>
        <th style="text-align:center">Net Paid</th>
        <th style="text-align:right">Payable Gross</th>
        <th style="text-align:right">Basic</th>
        <th style="text-align:right">HRA</th>
        <th style="text-align:right">Spl Allow</th>
        <th style="text-align:right">Incentive</th>
        <th style="text-align:right">Reimbursement</th>
        <th style="text-align:right">Emp PF</th>
        <th style="text-align:right">PT</th>
        <th style="text-align:right">Advance</th>
        <th style="text-align:right">TDS</th>
        <th style="text-align:right;background:var(--red2);color:var(--red)">Total Ded.</th>
        <th style="text-align:right;background:var(--grn2);color:var(--grn);font-weight:700">Net Pay</th>
        <th style="text-align:right">Empl PF</th>
        <th style="text-align:right">CTC</th>
        <th style="text-align:right">Diwali Bonus</th>
        <th style="text-align:right">CTC+Bonus</th>
        <th>Firm</th>
        <th style="text-align:center">PF App.</th>
        <th>UAN No.</th>
        <th style="text-align:center">CL Bal.</th>
        <th style="text-align:center">SL Bal.</th>
        <th style="text-align:center">CP Used</th>
        <th style="text-align:center">LOP (Lv)</th>
        <th style="text-align:center">LOP (Att)</th>
        <th>Account No.</th>
        <th>IFSC</th>
        <th>PAN</th>
        <th>Email</th>
      </tr>
    </thead>
    <tbody>
      <?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <tr>
        <td class="mono"><?php echo e($r['emp_id']); ?></td>
        <td><?php echo e($r['name']); ?></td>
        <td style="color:var(--t2)"><?php echo e($r['designation']); ?></td>
        <td style="text-align:right"><?php echo e(number_format($r['gross_salary'],0)); ?></td>
        <td><?php echo e($r['month']); ?></td>
        <td style="text-align:center"><?php echo e($r['month_days']); ?></td>
        <td style="text-align:center"><?php echo e($r['weekoff_holidays']); ?></td>
        <td style="text-align:center"><?php echo e($r['working_days']); ?></td>
        <td style="text-align:center;color:<?php echo e($r['total_lop'] > 0 ? 'var(--red)' : 'var(--t3)'); ?>;font-weight:<?php echo e($r['total_lop'] > 0 ? '600' : '400'); ?>">
          <?php echo e($r['total_lop'] ?: '—'); ?>

        </td>
        <td style="text-align:center"><?php echo e($r['net_paid_days']); ?></td>
        <td style="text-align:right"><?php echo e(number_format($r['payable_gross'],0)); ?></td>
        <td style="text-align:right"><?php echo e(number_format($r['basic_pay'],0)); ?></td>
        <td style="text-align:right"><?php echo e(number_format($r['hra'],0)); ?></td>
        <td style="text-align:right"><?php echo e(number_format($r['special_allowance'],0)); ?></td>
        <td style="text-align:right;color:<?php echo e($r['incentive'] > 0 ? 'var(--grn)' : 'var(--t3)'); ?>">
          <?php echo e($r['incentive'] > 0 ? number_format($r['incentive'],0) : '—'); ?>

        </td>
        <td style="text-align:right;color:<?php echo e(($r['reimbursement'] ?? 0) > 0 ? 'var(--grn)' : 'var(--t3)'); ?>">
          <?php echo e(($r['reimbursement'] ?? 0) > 0 ? number_format($r['reimbursement'],0) : '—'); ?>

        </td>
        <td style="text-align:right"><?php echo e($r['employee_pf'] > 0 ? number_format($r['employee_pf'],0) : '—'); ?></td>
        <td style="text-align:right"><?php echo e($r['professional_tax'] > 0 ? number_format($r['professional_tax'],0) : '—'); ?></td>
        <td style="text-align:right;color:<?php echo e($r['advance_salary'] > 0 ? 'var(--yel)' : 'var(--t3)'); ?>">
          <?php echo e($r['advance_salary'] > 0 ? number_format($r['advance_salary'],0) : '—'); ?>

        </td>
        <td style="text-align:right;color:<?php echo e($r['tds'] > 0 ? 'var(--acc)' : 'var(--t3)'); ?>">
          <?php echo e($r['tds'] > 0 ? number_format($r['tds'],0) : '—'); ?>

        </td>
        <td style="text-align:right;background:var(--red2);color:var(--red);font-weight:600">
          <?php echo e(number_format($r['total_deduction'],0)); ?>

        </td>
        <td style="text-align:right;background:var(--grn2);color:var(--grn);font-weight:700">
          <?php echo e(number_format($r['net_pay'],0)); ?>

        </td>
        <td style="text-align:right;color:var(--t2)"><?php echo e($r['employer_pf'] > 0 ? number_format($r['employer_pf'],0) : '—'); ?></td>
        <td style="text-align:right"><?php echo e(number_format($r['ctc'],0)); ?></td>
        <td style="text-align:right;color:<?php echo e($r['bonus_diwali'] > 0 ? 'var(--yel)' : 'var(--t3)'); ?>">
          <?php echo e($r['bonus_diwali'] > 0 ? number_format($r['bonus_diwali'],0) : '—'); ?>

        </td>
        <td style="text-align:right"><?php echo e(number_format($r['ctc_with_diwali'],0)); ?></td>
        <td style="font-size:10px;color:var(--t2)"><?php echo e(Str::limit($r['firm'],16)); ?></td>
        <td style="text-align:center">
          <span class="bx" style="font-size:9px;color:<?php echo e($r['pf_applicability']==='Yes'?'var(--grn)':'var(--t3)'); ?>">
            <?php echo e($r['pf_applicability']); ?>

          </span>
        </td>
        <td class="mono" style="font-size:10px;color:var(--t2)"><?php echo e($r['uan_number'] ?: '—'); ?></td>
        <td style="text-align:center;color:<?php echo e(($r['closing_cl'] ?? 0) > 0 ? 'var(--grn)' : 'var(--t3)'); ?>">
          <?php echo e(number_format($r['closing_cl'] ?? 0, 1)); ?>

        </td>
        <td style="text-align:center;color:<?php echo e(($r['closing_sl'] ?? 0) > 0 ? 'var(--grn)' : 'var(--t3)'); ?>">
          <?php echo e(number_format($r['closing_sl'] ?? 0, 1)); ?>

        </td>
        <td style="text-align:center;color:var(--t2)"><?php echo e(number_format($r['current_cp'] ?? 0, 1)); ?></td>
        <td style="text-align:center;color:<?php echo e(($r['current_lop'] ?? 0) > 0 ? 'var(--red)' : 'var(--t3)'); ?>">
          <?php echo e(($r['current_lop'] ?? 0) ?: '—'); ?>

        </td>
        <td style="text-align:center;color:<?php echo e(($r['att_lop'] ?? 0) > 0 ? 'var(--red)' : 'var(--t3)'); ?>">
          <?php echo e(($r['att_lop'] ?? 0) ?: '—'); ?>

        </td>
        <td class="mono" style="font-size:10px;color:var(--t2)"><?php echo e($r['account_number'] ?: '—'); ?></td>
        <td class="mono" style="font-size:10px;color:var(--t2)"><?php echo e($r['ifsc_code'] ?: '—'); ?></td>
        <td class="mono" style="font-size:10px;color:var(--t2)"><?php echo e($r['pan_number'] ?: '—'); ?></td>
        <td style="font-size:10px;color:var(--t2)"><?php echo e($r['rsc_mail_id'] ?: '—'); ?></td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
    <tfoot>
      <tr style="background:var(--s2);font-weight:700;font-size:11px">
        <td colspan="3" style="text-align:right;color:var(--t2)">TOTAL (<?php echo e($snapshotCount); ?> employees)</td>
        <td style="text-align:right"><?php echo e(number_format($summary['gross_salary'],0)); ?></td>
        <td colspan="6"></td>
        <td style="text-align:right"><?php echo e(number_format($summary['payable_gross'],0)); ?></td>
        <td style="text-align:right"><?php echo e(number_format($summary['basic_pay'],0)); ?></td>
        <td style="text-align:right"><?php echo e(number_format($summary['hra'],0)); ?></td>
        <td style="text-align:right"><?php echo e(number_format($summary['special_allowance'],0)); ?></td>
        <td style="text-align:right;color:var(--grn)"><?php echo e(number_format($summary['incentive'],0)); ?></td>
        <td style="text-align:right;color:var(--grn)"><?php echo e(number_format($summary['reimbursement'] ?? 0, 0)); ?></td>
        <td style="text-align:right"><?php echo e(number_format($summary['employee_pf'],0)); ?></td>
        <td style="text-align:right"><?php echo e(number_format($summary['professional_tax'],0)); ?></td>
        <td style="text-align:right"><?php echo e(number_format($summary['advance_salary'],0)); ?></td>
        <td style="text-align:right"><?php echo e(number_format($summary['tds'],0)); ?></td>
        <td style="text-align:right;color:var(--red)"><?php echo e(number_format($summary['total_deduction'],0)); ?></td>
        <td style="text-align:right;color:var(--grn)"><?php echo e(number_format($summary['net_pay'],0)); ?></td>
        <td style="text-align:right"><?php echo e(number_format($summary['employer_pf'],0)); ?></td>
        <td style="text-align:right"><?php echo e(number_format($summary['ctc'],0)); ?></td>
        <td style="text-align:right"><?php echo e(number_format($summary['bonus_diwali'],0)); ?></td>
        <td style="text-align:right"><?php echo e(number_format($summary['ctc_with_diwali'],0)); ?></td>
        <td colspan="12"></td>
      </tr>
    </tfoot>
  </table>
  <?php if($rows->hasPages()): ?>
    <div style="padding:14px 18px;border-top:1px solid var(--b1)"><?php echo e($rows->links()); ?></div>
  <?php endif; ?>
  <?php if(!empty($search) && $rows->total() === 0): ?>
    <div class="empty" style="padding:24px">
      <div class="ico">🔍</div>
      <p>No employees matching "<?php echo e(e($search)); ?>".</p>
      <a href="<?php echo e(route('salary.report.view', $month)); ?>" class="btn acc" style="margin-top:10px" data-ajax-reset>Clear Search</a>
    </div>
  <?php endif; ?>
</div>
</div> 


<div class="modal" id="reupload-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">📤 Re-upload Edited Salary Report</span>
      <button class="mc" onclick="cm('reupload-modal')">✕</button>
    </div>
    <div style="background:#fefce8;border:1px solid #fde047;border-radius:6px;padding:8px 12px;margin-bottom:12px;font-size:11px;color:#854d0e;line-height:1.6">
      <strong style="font-size:11px">Only these columns are read from the file — all others are ignored:</strong>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:2px 12px;margin-top:4px">
        <span>✏ <strong>Payable Gross</strong> <span style="font-weight:400">(triggers recompute)</span></span>
        <span>✏ <strong>Incentive</strong></span>
        <span>✏ <strong>Advance Salary</strong> (Loan/EMI)</span>
        <span>✏ <strong>TDS</strong></span>
        <span>✏ <strong>Total LOP</strong></span>
        <span>✏ <strong>Account No · IFSC · PAN · Email</strong> <span style="font-weight:400">(also updates employee master)</span></span>
      </div>
      <div style="margin-top:5px;color:#92400e;font-size:10px">
        System auto-recomputes Basic, HRA, Spl Allow, PF, PT, Total Deduction, Net Pay, CTC after upload. · <strong>Do not change Emp ID or Month columns.</strong>
      </div>
    </div>
    <form method="POST" action="<?php echo e(route('salary.report.reupload')); ?>" enctype="multipart/form-data">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="expected_month" value="<?php echo e($month); ?>">
      <div class="fld" style="margin-bottom:14px">
        <label class="req">Edited Excel File (.xlsx)</label>
        <input type="file" name="file" id="reupload-file-view" accept=".xlsx,.xls" required
          style="background:var(--s2);border:1.5px dashed var(--b2);border-radius:8px;padding:14px;cursor:pointer;width:100%"
          onchange="verifyReuploadMonth(this, '<?php echo e($month); ?>')">
        <div id="reupload-month-ok-view" style="display:none;margin-top:6px;font-size:11px;color:var(--grn);font-weight:600">
          ✅ File month verified — matches <strong><?php echo e(\Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y')); ?></strong>
        </div>
        <div style="font-size:11px;color:var(--t3);margin-top:4px">
          Upload the salary report Excel previously downloaded and edited.
          Month-year is read automatically from the <strong>Month</strong> column.
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('reupload-modal')">Cancel</button>
        <button type="submit" class="btn acc">📤 Replace Snapshot Data</button>
      </div>
    </form>
  </div>
</div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/salary-report/view.blade.php ENDPATH**/ ?>