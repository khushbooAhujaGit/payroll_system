<?php $__env->startSection('title', 'Helpdesk'); ?>

<?php $__env->startSection('page_actions'); ?>
  <button class="btn acc" onclick="om('ticket-modal')">+ Raise Ticket</button>
  <button class="btn" onclick="om('upload-modal')">📤 Bulk Upload</button>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

  <div class="g4" style="margin-bottom:16px">
    <div class="sc">
      <div class="lbl">Open</div>
      <div class="val" style="color:var(--red)"><?php echo e($stats['open']); ?></div>
    </div>
    <div class="sc">
      <div class="lbl">In Progress</div>
      <div class="val" style="color:var(--yel)"><?php echo e($stats['in_progress']); ?></div>
    </div>
    <div class="sc">
      <div class="lbl">Resolved</div>
      <div class="val" style="color:var(--grn)"><?php echo e($stats['resolved']); ?></div>
    </div>
  </div>

  <form method="GET" action="<?php echo e(route('helpdesk')); ?>" class="card" data-ajax-filter
    style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end">
    <div class="fld" style="min-width:140px">
      <label>Status</label>
      <select name="status">
        <option value="">All</option>
        <option value="open" <?php echo e(request('status') === 'open' ? 'selected' : ''); ?>>Open</option>
        <option value="in_progress" <?php echo e(request('status') === 'in_progress' ? 'selected' : ''); ?>>In Progress</option>
        <option value="resolved" <?php echo e(request('status') === 'resolved' ? 'selected' : ''); ?>>Resolved</option>
        <option value="closed" <?php echo e(request('status') === 'closed' ? 'selected' : ''); ?>>Closed</option>
      </select>
    </div>
    <div class="fld" style="min-width:120px">
      <label>Priority</label>
      <select name="priority">
        <option value="">All</option>
        <option value="urgent" <?php echo e(request('priority') === 'urgent' ? 'selected' : ''); ?>>Urgent</option>
        <option value="high" <?php echo e(request('priority') === 'high' ? 'selected' : ''); ?>>High</option>
        <option value="medium" <?php echo e(request('priority') === 'medium' ? 'selected' : ''); ?>>Medium</option>
        <option value="low" <?php echo e(request('priority') === 'low' ? 'selected' : ''); ?>>Low</option>
      </select>
    </div>
    <button type="submit" class="btn acc">Filter</button>
    <a href="<?php echo e(route('helpdesk')); ?>" class="btn" data-ajax-reset>Reset</a>
  </form>

  <div id="ajax-results">
  <div class="card">
    <?php if($tickets->isNotEmpty()): ?>
      <table>
        <thead>
          <tr>
            <th>Ticket #</th>
            <th>Subject</th>
            <th>Employee</th>
            <th>Category</th>
            <th>Priority</th>
            <th>Status</th>
            <th>Due By</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php $__currentLoopData = $tickets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ticket): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
              $priColors = ['urgent' => 'var(--red)', 'high' => 'var(--yel)', 'medium' => 'var(--acc)', 'low' => 'var(--t3)'];
              $stColors = ['open' => 'var(--red)', 'in_progress' => 'var(--yel)', 'resolved' => 'var(--grn)', 'closed' => 'var(--t3)'];
            ?>
            <tr>
              <td class="mono"><?php echo e($ticket->ticket_number); ?></td>
              <td><?php echo e(\Illuminate\Support\Str::limit($ticket->subject, 50)); ?></td>
              <td><?php echo e($ticket->employee?->full_name); ?></td>
              <td><?php echo e($ticket->category?->name ?? '—'); ?></td>
              <td><span class="bx"
                  style="color:<?php echo e($priColors[$ticket->priority] ?? 'var(--t3)'); ?>"><?php echo e(ucfirst($ticket->priority)); ?></span>
              </td>
              <td><span class="bx"
                  style="color:<?php echo e($stColors[$ticket->status] ?? 'var(--t3)'); ?>"><?php echo e(str_replace('_', ' ', ucwords($ticket->status, '_'))); ?></span>
              </td>
              <td style="font-size:11px;color:<?php echo e($ticket->due_by && $ticket->due_by < now() ? 'var(--red)' : 'var(--t3)'); ?>">
                <?php echo e($ticket->due_by ? \Carbon\Carbon::parse($ticket->due_by)->format('d M, H:i') : '—'); ?>

              </td>
              <td>
                <?php if(!in_array($ticket->status, ['resolved', 'closed'])): ?>
                  <button class="btn sm" onclick="openResolve(<?php echo e($ticket->id); ?>)">Resolve</button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
      </table>
      <div style="margin-top:14px"><?php echo e($tickets->links()); ?></div>
    <?php else: ?>
      <div class="empty">
        <div class="ico">🎫</div>
        <p>No tickets found.</p>
      </div>
    <?php endif; ?>
  </div>
  </div>

  
  <div class="modal" id="ticket-modal">
    <div class="mb">
      <div class="mh">
        <span class="mt">Raise Ticket</span>
        <button class="mc" onclick="cm('ticket-modal')">✕</button>
      </div>
      <form method="POST" action="<?php echo e(route('helpdesk.store')); ?>">
        <?php echo csrf_field(); ?>
        <div class="fg2">
          <div class="fld full">
            <label>Category *</label>
            <select name="category_id" required>
              <option value="">Select category</option>
              <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($cat->id); ?>"><?php echo e($cat->name); ?></option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
          </div>
          <div class="fld full">
            <label>Subject *</label>
            <input type="text" name="subject" required maxlength="200">
          </div>
          <div class="fld">
            <label>Priority *</label>
            <select name="priority" required>
              <option value="low">Low</option>
              <option value="medium" selected>Medium</option>
              <option value="high">High</option>
              <option value="urgent">Urgent</option>
            </select>
          </div>
          <div class="fld full">
            <label>Description *</label>
            <textarea name="description" required rows="4" style="resize:vertical"></textarea>
          </div>
        </div>
        <div class="mf">
          <button type="button" class="btn" onclick="cm('ticket-modal')">Cancel</button>
          <button type="submit" class="btn acc">Raise Ticket</button>
        </div>
      </form>
    </div>
  </div>

  
  <div class="modal" id="resolve-modal">
    <div class="mb">
      <div class="mh">
        <span class="mt">Resolve Ticket</span>
        <button class="mc" onclick="cm('resolve-modal')">✕</button>
      </div>
      <form method="POST" id="resolve-form">
        <?php echo csrf_field(); ?>
        <div class="fld" style="margin-bottom:14px">
          <label>Closure Remarks *</label>
          <textarea name="closure_remarks" required rows="4" style="resize:vertical"></textarea>
        </div>
        <div class="mf">
          <button type="button" class="btn" onclick="cm('resolve-modal')">Cancel</button>
          <button type="submit" class="btn acc">Resolve</button>
        </div>
      </form>
    </div>
  </div>

  <?php if($errors->any()): ?>
    <script>document.addEventListener('DOMContentLoaded', () => om('ticket-modal'))</script>
  <?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('partials.upload-modal', [
    'uploadId'      => 'upload-modal',
    'uploadTitle'   => 'Bulk Upload Tickets',
    'uploadRoute'   => 'helpdesk.import',
    'templateRoute' => 'helpdesk.template',
    'uploadHint'    => 'Template columns: Employee ID, Category, Subject, Priority (low/medium/high/urgent), Description. One row per ticket.',
], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<?php $__env->startPush('scripts'); ?>
  <script>
    function openResolve(id) {
      document.getElementById('resolve-form').action = '/helpdesk/' + id + '/resolve';
      om('resolve-modal');
    }
  </script>
<?php $__env->stopPush(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/helpdesk/index.blade.php ENDPATH**/ ?>