<?php $__env->startSection('title', 'Reimbursement Tracker'); ?>

<?php $__env->startSection('page_actions'); ?>
  <a href="<?php echo e(route('reimbursements.report.saved')); ?>" class="btn">📊 Reports</a>
  <button class="btn" onclick="om('img-upload-modal')">📸 Upload Images</button>
  <a href="<?php echo e(route('reimbursements.tracker.template')); ?>" class="btn">📄 Template</a>
  <button class="btn" onclick="om('import-modal')">📤 Import</button>
  <button class="btn acc" onclick="om('add-modal')">+ Add Reimbursement</button>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>


<form method="GET" action="<?php echo e(route('reimbursements.tracker')); ?>"
      class="card" style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
  <div class="fld" style="min-width:160px">
    <label>Month</label>
    <input type="month" name="month" value="<?php echo e($month); ?>" required>
  </div>
  <button type="submit" class="btn acc">View</button>
  <?php if($totalRows > 0): ?>
    <a href="<?php echo e(route('reimbursements.tracker.export', ['month' => $month])); ?>" class="btn">📥 Export</a>
  <?php endif; ?>
  <?php if($isLocked): ?>
    <span style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--t2)">
      🔒 Report saved
      <?php if($lockedAt): ?>
        <span style="color:var(--t3)">on <?php echo e($lockedAt->format('d M Y, h:i A')); ?></span>
      <?php endif; ?>
    </span>
  <?php endif; ?>
</form>


<form method="GET" action="<?php echo e(route('reimbursements.tracker')); ?>" data-ajax-filter
      class="card" style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
  <input type="hidden" name="month" value="<?php echo e($month); ?>">
  <div class="fld" style="min-width:240px">
    <label>Search by Emp ID / Name</label>
    <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="e.g. EMP001 or John Doe">
  </div>
  <button type="submit" class="btn acc">Search</button>
  <?php if($search !== ''): ?>
    <a href="<?php echo e(route('reimbursements.tracker', ['month' => $month])); ?>" class="btn" data-ajax-reset>✕ Clear</a>
  <?php endif; ?>
</form>

<div id="ajax-results">

<?php if($totalRows > 0): ?>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:16px">
  <div class="card" style="padding:14px 18px">
    <div style="font-size:11px;color:var(--t3);text-transform:uppercase;letter-spacing:.05em">Total Rows</div>
    <div style="font-size:22px;font-weight:700;margin-top:4px"><?php echo e(number_format($totalRows)); ?></div>
  </div>
  <div class="card" style="padding:14px 18px">
    <div style="font-size:11px;color:var(--t3);text-transform:uppercase;letter-spacing:.05em">Approved Amount</div>
    <div style="font-size:22px;font-weight:700;color:var(--grn);margin-top:4px">₹<?php echo e(number_format($totalApproved, 2)); ?></div>
  </div>
  <div class="card" style="padding:14px 18px">
    <div style="font-size:11px;color:var(--t3);text-transform:uppercase;letter-spacing:.05em">Total Amount</div>
    <div style="font-size:22px;font-weight:700;margin-top:4px">₹<?php echo e(number_format($totalAll, 2)); ?></div>
  </div>
  <div class="card" style="padding:14px 18px;display:flex;flex-direction:column;justify-content:center">
    <a href="<?php echo e(route('reimbursements.report', ['month' => $month])); ?>" class="btn acc" style="text-align:center">
      <?php echo e($isLocked ? '📊 View Report' : '📊 Generate Report'); ?>

    </a>
  </div>
</div>
<?php endif; ?>


<div class="card">
  <?php if($rows->total() > 0): ?>
  <div class="ch" style="display:flex;justify-content:space-between;align-items:center">
    <div class="ct">
      <?php echo e(\Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y')); ?>

      — <?php echo e(number_format($rows->total())); ?> row(s)
      <?php if($search !== ''): ?>
        <span style="color:var(--acc);font-size:11px"> matching "<?php echo e(e($search)); ?>"</span>
      <?php endif; ?>
    </div>
    <div style="font-size:12px;color:var(--t3)">
      Page <?php echo e($rows->currentPage()); ?> of <?php echo e($rows->lastPage()); ?>

    </div>
  </div>
  <div style="overflow-x:auto">
  <table style="font-size:12px;min-width:800px">
    <thead>
      <tr>
        <th style="width:40px">#</th>
        <th>Date</th>
        <th>Emp ID</th>
        <th>Employee Name</th>
        <th style="text-align:right">Amount (₹)</th>
        <th>Category</th>
        <th>Remarks</th>
        <th style="text-align:center">Image</th>
        <th style="text-align:center">Status</th>
        <th style="text-align:center">Payment</th>
        <th style="text-align:center">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <?php $rowNum = ($rows->currentPage() - 1) * $rows->perPage() + $loop->iteration; ?>
      <tr>
        <td style="color:var(--t3)"><?php echo e($rowNum); ?></td>
        <td style="color:var(--t2);white-space:nowrap">
          <?php echo e($row->expense_date ? \Carbon\Carbon::parse($row->expense_date)->format('d M Y') : '—'); ?>

        </td>
        <td class="mono" style="font-size:11px"><?php echo e($row->emp_id ?? '—'); ?></td>
        <td style="font-weight:500"><?php echo e($row->employee_name); ?></td>
        <td style="text-align:right;font-weight:600">₹<?php echo e(number_format($row->amount, 2)); ?></td>
        <td style="color:var(--t2);font-size:11px" title="<?php echo e($row->category === 'Other' ? $row->other_category_text : ''); ?>">
          <?php echo e($row->category ?? '—'); ?><?php echo e($row->category === 'Other' && $row->other_category_text ? ' — ' . $row->other_category_text : ''); ?>

        </td>
        <td style="color:var(--t2);max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?php echo e($row->remarks); ?>">
          <?php echo e($row->remarks ?? '—'); ?>

        </td>
        <td style="text-align:center">
          <?php if($row->image_filename): ?>
            <a href="<?php echo e(route('reimbursements.tracker.image.view', ['month' => $row->year_month, 'filename' => $row->image_filename])); ?>"
               target="_blank" class="btn sm" style="font-size:10px">🖼 View</a>
          <?php elseif($row->document_path): ?>
            <a href="<?php echo e(route('reimbursements.tracker.document', $row)); ?>"
               target="_blank" class="btn sm" style="font-size:10px">🖼 View</a>
          <?php else: ?>
            <span style="color:var(--t3);font-size:11px">—</span>
          <?php endif; ?>
        </td>
        <td style="text-align:center">
          <?php $sc = ['approved' => 'var(--grn)', 'rejected' => 'var(--red)', 'pending' => 'var(--yel)'];
               $sb = ['approved' => 'var(--grn2)', 'rejected' => 'var(--red2)', 'pending' => 'var(--yel2)']; ?>
          <span class="bx" style="color:<?php echo e($sc[$row->status] ?? 'var(--t3)'); ?>;background:<?php echo e($sb[$row->status] ?? 'var(--s2)'); ?>">
            <?php echo e(ucfirst($row->status)); ?>

          </span>
        </td>
        <td style="text-align:center">
          <?php if($row->status === 'approved'): ?>
            <?php $pc = ['paid' => 'g', 'partial' => 'y', 'unpaid' => 'gr']; ?>
            <button class="btn sm" style="font-size:10px" onclick="om('payment-<?php echo e($row->id); ?>')">
              <span class="bx <?php echo e($pc[$row->payment_status] ?? 'gr'); ?>">
                <?php echo e($row->payment_status === 'partial' ? 'Partially Paid' : ucfirst($row->payment_status)); ?>

              </span>
            </button>
            <?php if($row->payment_status === 'partial'): ?>
              <div style="font-size:10px;color:var(--t3);margin-top:2px">
                ₹<?php echo e(number_format($row->paid_amount, 2)); ?> of ₹<?php echo e(number_format($row->amount, 2)); ?>

              </div>
            <?php endif; ?>
            <?php if($row->carried_forward_from_row_id): ?>
              <div style="font-size:10px;color:var(--t3);margin-top:2px">↳ carried from <?php echo e(\Carbon\Carbon::createFromFormat('Y-m', $row->carriedForwardFrom?->year_month ?? $row->year_month)->format('M Y')); ?></div>
            <?php endif; ?>
          <?php else: ?>
            <span style="color:var(--t3);font-size:11px">—</span>
          <?php endif; ?>
        </td>
        <td style="text-align:center;white-space:nowrap">
          <?php if($row->employee_id && $row->status === 'pending'): ?>
            
            <span class="bx y" style="font-size:10px">Awaiting Approver</span>
          <?php elseif($row->status === 'approved'): ?>
            <form method="POST" action="<?php echo e(route('reimbursements.tracker.status', $row)); ?>" style="display:inline"
                  data-has-report="<?php echo e($isLocked ? 'true' : 'false'); ?>"
                  data-month="<?php echo e(\Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y')); ?>">
              <?php echo csrf_field(); ?> <input type="hidden" name="status" value="rejected">
              <button type="button" class="btn sm" style="color:var(--red);font-size:10px"
                onclick="confirmStatusChange(this.closest('form'))">Reject</button>
            </form>
          <?php else: ?>
            <form method="POST" action="<?php echo e(route('reimbursements.tracker.status', $row)); ?>" style="display:inline"
                  data-has-report="<?php echo e($isLocked ? 'true' : 'false'); ?>"
                  data-month="<?php echo e(\Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y')); ?>">
              <?php echo csrf_field(); ?> <input type="hidden" name="status" value="approved">
              <button type="button" class="btn sm" style="color:var(--grn);font-size:10px"
                onclick="confirmStatusChange(this.closest('form'))">Approve</button>
            </form>
          <?php endif; ?>
          <form method="POST" action="<?php echo e(route('reimbursements.tracker.destroy', $row)); ?>" style="display:inline">
            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
            <button type="button" class="btn sm" style="color:var(--red);font-size:10px"
              onclick="swConfirm(this.closest('form'), 'Delete row?', 'This cannot be undone.', 'warning', 'Delete', '#dc2626')">
              🗑
            </button>
          </form>
        </td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
    <tfoot>
      <tr style="background:var(--s2);font-weight:700">
        <td colspan="4" style="text-align:right;padding:8px 12px">Total Approved:</td>
        <td style="text-align:right;padding:8px 12px;color:var(--grn)">₹<?php echo e(number_format($totalApproved, 2)); ?></td>
        <td colspan="6"></td>
      </tr>
    </tfoot>
  </table>
  </div>
  
  <?php if($rows->hasPages()): ?>
    <div style="padding:14px 18px;border-top:1px solid var(--b1)">
      <?php echo e($rows->links()); ?>

    </div>
  <?php endif; ?>

  <?php elseif($search !== ''): ?>
  <div class="empty">
    <div class="ico">🔍</div>
    <p>No results for "<?php echo e(e($search)); ?>" in <?php echo e(\Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y')); ?>.</p>
    <a href="<?php echo e(route('reimbursements.tracker', ['month' => $month])); ?>" class="btn acc" style="margin-top:10px" data-ajax-reset>Clear Search</a>
  </div>

  <?php else: ?>
  <div class="empty">
    <div class="ico">💳</div>
    <p>No reimbursement data for <?php echo e(\Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y')); ?>.</p>
    <p style="font-size:12px;color:var(--t3);margin-top:4px">Add rows manually or import from Excel.</p>
    <button class="btn acc" style="margin-top:10px" onclick="om('add-modal')">+ Add Reimbursement</button>
  </div>
  <?php endif; ?>
</div>
</div> 


<div class="modal" id="add-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Add Reimbursement Row</span>
      <button class="mc" onclick="cm('add-modal')">✕</button>
    </div>
    <form method="POST" action="<?php echo e(route('reimbursements.tracker.store')); ?>">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="year_month" value="<?php echo e($month); ?>">
      <div class="fg2">
        <div class="fld">
          <label>Date</label>
          <input type="date" name="expense_date" value="<?php echo e(old('expense_date', now()->toDateString())); ?>">
        </div>
        <div class="fld">
          <label>Emp ID</label>
          <input type="text" name="emp_id" placeholder="e.g. EMP001" value="<?php echo e(old('emp_id')); ?>">
        </div>
        <div class="fld full">
          <label class="req">Employee Name</label>
          <input type="text" name="employee_name" required placeholder="e.g. John Doe"
            value="<?php echo e(old('employee_name')); ?>" <?php $__errorArgs = ['employee_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
          <?php $__errorArgs = ['employee_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div class="fld">
          <label class="req">Amount (₹)</label>
          <input type="number" name="amount" required min="0.01" step="0.01" placeholder="1800"
            value="<?php echo e(old('amount')); ?>" <?php $__errorArgs = ['amount'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
          <?php $__errorArgs = ['amount'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div class="fld full">
          <label>Category</label>
          <select name="category" onchange="toggleOtherCategory(this)" <?php $__errorArgs = ['category'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
            <option value="">— None —</option>
            <?php $__currentLoopData = \App\Models\ReimbursementTrackerRow::CATEGORIES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($cat); ?>" <?php echo e(old('category') === $cat ? 'selected' : ''); ?>><?php echo e($cat); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
          <?php $__errorArgs = ['category'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div class="fld full" id="other-category-wrap" style="<?php echo e(old('category') === 'Other' ? '' : 'display:none'); ?>">
          <label class="req">Please specify</label>
          <input type="text" name="other_category_text" maxlength="150" placeholder="What is this expense for?"
            value="<?php echo e(old('other_category_text')); ?>" <?php $__errorArgs = ['other_category_text'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> class="inp-err" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>>
          <?php $__errorArgs = ['other_category_text'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="ferr"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div class="fld full">
          <label>Remarks</label>
          <input type="text" name="remarks" placeholder="e.g. Petrol & Recharge" value="<?php echo e(old('remarks')); ?>">
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('add-modal')">Cancel</button>
        <button type="submit" class="btn acc">Add</button>
      </div>
    </form>
  </div>
</div>


<div class="modal" id="import-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Import from Excel</span>
      <button class="mc" onclick="cm('import-modal')">✕</button>
    </div>
    <form method="POST" action="<?php echo e(route('reimbursements.tracker.import')); ?>" enctype="multipart/form-data">
      <?php echo csrf_field(); ?>
      <div class="fg2">
        <div class="fld full">
          <label class="req">Month</label>
          <input type="month" name="month" value="<?php echo e($month); ?>" required>
        </div>
        <div class="fld full">
          <label class="req">Excel File</label>
          <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
          <div style="font-size:11px;color:var(--t3);margin-top:4px">
            <strong>Adding new rows?</strong> Columns: <strong>Date, Emp ID, Employee Name, Amount, Category, Other Category, Remarks, Image</strong>
            (fill "Other Category" only when Category is "Other") —
            <a href="<?php echo e(route('reimbursements.tracker.template')); ?>" style="color:var(--acc)">Download blank Template</a><br>
            <strong>Editing existing rows in bulk?</strong> Use <a href="<?php echo e(route('reimbursements.tracker.export', ['month' => $month])); ?>" style="color:var(--acc)">Export</a> instead, edit the cells you need
            (Category, Remarks, Approval Status, Payment Status, Paid Amount, etc.) in Excel, then upload that same file here —
            rows with an <strong>ID</strong> are updated in place instead of added as new.<br>
            <strong>Image naming rules:</strong> No spaces · Must contain employee name · Must include date &amp; time · Any extension (.jpg .png .pdf etc.)<br>
            Example: <code style="background:var(--s2);padding:1px 5px;border-radius:3px">VADHER_NEHA_DIPAKBHAI_03-06-2026_10-30.png</code>
            <span style="color:var(--t3)">— underscore (_) as separator, any valid extension</span>
          </div>
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('import-modal')">Cancel</button>
        <button type="submit" class="btn acc">📤 Import</button>
      </div>
    </form>
  </div>
</div>


<div class="modal" id="img-upload-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Upload Images</span>
      <button class="mc" onclick="cm('img-upload-modal')">✕</button>
    </div>
    <form method="POST" action="<?php echo e(route('reimbursements.tracker.images')); ?>" enctype="multipart/form-data">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="month" value="<?php echo e($month); ?>">
      <div class="fg2">
        <div class="fld full">
          <label class="req">Image Files</label>
          <input type="file" name="images[]" accept="image/*,.pdf" multiple required>
          <div style="font-size:11px;color:var(--t3);margin-top:4px">
            File names must match the <strong>Image</strong> column in your data.
            Accepted: JPG, PNG, GIF, WEBP, PDF — max 5 MB each.
          </div>
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('img-upload-modal')">Cancel</button>
        <button type="submit" class="btn acc">Upload</button>
      </div>
    </form>
  </div>
</div>


<?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <?php if($row->status === 'approved'): ?>
    <div class="modal" id="payment-<?php echo e($row->id); ?>">
      <div class="mb">
        <div class="mh">
          <span class="mt">Payment Status — <?php echo e($row->employee_name); ?></span>
          <button class="mc" onclick="cm('payment-<?php echo e($row->id); ?>')">✕</button>
        </div>
        <form method="POST" action="<?php echo e(route('reimbursements.tracker.payment', $row)); ?>">
          <?php echo csrf_field(); ?>
          <div class="fg2">
            <div class="fld full">
              <label class="req">Status</label>
              <select name="payment_status" onchange="togglePaymentFields(this, '<?php echo e($row->id); ?>')" required>
                <option value="unpaid" <?php echo e($row->payment_status === 'unpaid' ? 'selected' : ''); ?>>Unpaid</option>
                <option value="paid" <?php echo e($row->payment_status === 'paid' ? 'selected' : ''); ?>>Paid</option>
                <option value="partial" <?php echo e($row->payment_status === 'partial' ? 'selected' : ''); ?>>Partially Paid</option>
              </select>
            </div>
            <div class="fld full" id="payment-partial-note-<?php echo e($row->id); ?>" style="display:none;font-size:11px;color:var(--t3)">
              The unpaid remainder will automatically be added as a new row in next month's tracker.
            </div>
            <div class="fld" id="payment-amount-wrap-<?php echo e($row->id); ?>" style="<?php echo e($row->payment_status === 'partial' ? '' : 'display:none'); ?>">
              <label class="req">Paid Amount (₹)</label>
              <input type="number" name="paid_amount" min="0.01" step="0.01" max="<?php echo e(max(0, $row->amount - 0.01)); ?>"
                value="<?php echo e($row->paid_amount); ?>" placeholder="Amount actually paid so far">
            </div>
            <div class="fld" id="payment-date-wrap-<?php echo e($row->id); ?>" style="<?php echo e(in_array($row->payment_status, ['paid', 'partial']) ? '' : 'display:none'); ?>">
              <label class="req">Payment Date</label>
              <input type="date" name="payment_date" value="<?php echo e($row->payment_date?->toDateString() ?? now()->toDateString()); ?>">
            </div>
            <div class="fld" id="payment-ref-wrap-<?php echo e($row->id); ?>" style="<?php echo e(in_array($row->payment_status, ['paid', 'partial']) ? '' : 'display:none'); ?>">
              <label>Reference / UTR</label>
              <input type="text" name="payment_reference" value="<?php echo e($row->payment_reference); ?>" placeholder="e.g. UTR12345">
            </div>
          </div>
          <div class="mf">
            <button type="button" class="btn" onclick="cm('payment-<?php echo e($row->id); ?>')">Cancel</button>
            <button type="submit" class="btn acc">Save</button>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

<?php if($errors->any()): ?>
<script>document.addEventListener('DOMContentLoaded', () => om('add-modal'))</script>
<?php endif; ?>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
function togglePaymentFields(select, rowId) {
  const isPaid    = select.value === 'paid';
  const isPartial = select.value === 'partial';
  document.getElementById('payment-date-wrap-' + rowId).style.display = (isPaid || isPartial) ? '' : 'none';
  document.getElementById('payment-ref-wrap-' + rowId).style.display = (isPaid || isPartial) ? '' : 'none';
  document.getElementById('payment-amount-wrap-' + rowId).style.display = isPartial ? '' : 'none';
  document.getElementById('payment-partial-note-' + rowId).style.display = isPartial ? '' : 'none';
}

function toggleOtherCategory(select) {
  const wrap  = document.getElementById('other-category-wrap');
  const input = wrap.querySelector('input[name="other_category_text"]');
  const show  = select.value === 'Other';
  wrap.style.display = show ? '' : 'none';
  if (!show) input.value = '';
}

function confirmStatusChange(form) {
  if (form.dataset.hasReport === 'true') {
    Swal.fire({
      title: 'Report already saved',
      html: `The saved report for <strong>${form.dataset.month}</strong> will <strong>not</strong> change automatically.<br>
             Go to <strong>Reimb. Report → Re-save Report</strong> after making all your changes.`,
      icon: 'info',
      showCancelButton: true,
      confirmButtonText: 'OK, change status',
      confirmButtonColor: '#2563eb',
      cancelButtonText: 'Cancel',
    }).then(r => { if (r.isConfirmed) form.submit(); });
  } else {
    form.submit();
  }
}
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/reimbursements/tracker.blade.php ENDPATH**/ ?>