<?php $__env->startSection('title', 'Company / Entity'); ?>

<?php $__env->startSection('page_actions'); ?>
  <button class="btn acc" onclick="om('company-modal')">+ Add Company</button>
  <button class="btn" onclick="om('entity-modal')">+ Add Entity</button>
  <button class="btn" onclick="om('upload-modal')">📤 Bulk Upload</button>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<div class="g2">
  
  <div class="card">
    <div class="ch"><div class="ct">Companies</div></div>
    <?php if($companies->isNotEmpty()): ?>
    <table>
      <thead><tr><th>Name</th><th>PAN</th><th>Entities</th></tr></thead>
      <tbody>
        <?php $__currentLoopData = $companies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $co): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
          <td>
            <div style="font-weight:600"><?php echo e($co->name); ?></div>
            <div style="font-size:11px;color:var(--t3)"><?php echo e($co->legal_name); ?></div>
          </td>
          <td class="mono"><?php echo e($co->pan ?? '—'); ?></td>
          <td><?php echo e($co->entities->count()); ?></td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="empty"><div class="ico">🏢</div><p>No companies yet.</p></div>
    <?php endif; ?>
  </div>

  
  <div class="card">
    <div class="ch"><div class="ct">Entities</div></div>
    <?php if($entities->isNotEmpty()): ?>
    <table>
      <thead><tr><th>Name</th><th>Code</th><th>State</th><th>PF #</th></tr></thead>
      <tbody>
        <?php $__currentLoopData = $entities; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
          <td>
            <div style="font-weight:600"><?php echo e($ent->name); ?></div>
            <div style="font-size:11px;color:var(--t3)"><?php echo e($ent->company?->name); ?></div>
          </td>
          <td class="mono"><?php echo e($ent->code); ?></td>
          <td><?php echo e($ent->state ?? '—'); ?></td>
          <td class="mono" style="font-size:11px"><?php echo e($ent->pf_number ?? '—'); ?></td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="empty"><div class="ico">🏗️</div><p>No entities yet.</p></div>
    <?php endif; ?>
  </div>
</div>


<div class="modal" id="company-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Add Company</span>
      <button class="mc" onclick="cm('company-modal')">✕</button>
    </div>
    <form method="POST" action="<?php echo e(route('masters.company')); ?>">
      <?php echo csrf_field(); ?>
      <div class="fg2">
        <div class="fld full">
          <label>Company Name *</label>
          <input type="text" name="name" required value="<?php echo e(old('name')); ?>">
        </div>
        <div class="fld full">
          <label>Legal Name</label>
          <input type="text" name="legal_name" value="<?php echo e(old('legal_name')); ?>">
        </div>
        <div class="fld">
          <label>PAN</label>
          <input type="text" name="pan" maxlength="10" value="<?php echo e(old('pan')); ?>">
        </div>
        <div class="fld">
          <label>CIN</label>
          <input type="text" name="cin" maxlength="21" value="<?php echo e(old('cin')); ?>">
        </div>
        <div class="fld">
          <label>GSTIN</label>
          <input type="text" name="gstin" maxlength="15" value="<?php echo e(old('gstin')); ?>">
        </div>
        <div class="fld full">
          <label>Registered Address</label>
          <textarea name="registered_address" rows="2"><?php echo e(old('registered_address')); ?></textarea>
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('company-modal')">Cancel</button>
        <button type="submit" class="btn acc">Create Company</button>
      </div>
    </form>
  </div>
</div>


<div class="modal" id="entity-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Add Entity</span>
      <button class="mc" onclick="cm('entity-modal')">✕</button>
    </div>
    <form method="POST" action="<?php echo e(route('masters.entity')); ?>">
      <?php echo csrf_field(); ?>
      <div class="fg2">
        <div class="fld full">
          <label>Company *</label>
          <select name="company_id" required>
            <option value="">Select Company</option>
            <?php $__currentLoopData = $companies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $co): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($co->id); ?>"><?php echo e($co->name); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
        </div>
        <div class="fld">
          <label>Entity Name *</label>
          <input type="text" name="name" required value="<?php echo e(old('name')); ?>">
        </div>
        <div class="fld">
          <label>Code *</label>
          <input type="text" name="code" required maxlength="20" value="<?php echo e(old('code')); ?>">
        </div>
        <div class="fld">
          <label>State</label>
          <input type="text" name="state" value="<?php echo e(old('state')); ?>">
        </div>
        <div class="fld">
          <label>City</label>
          <input type="text" name="city" value="<?php echo e(old('city')); ?>">
        </div>
        <div class="fld">
          <label>PF Number</label>
          <input type="text" name="pf_number" value="<?php echo e(old('pf_number')); ?>" maxlength="22">
        </div>
        <div class="fld">
          <label>ESIC Number</label>
          <input type="text" name="esic_number" value="<?php echo e(old('esic_number')); ?>" maxlength="17">
        </div>
        <div class="fld">
          <label>PT Number</label>
          <input type="text" name="pt_number" value="<?php echo e(old('pt_number')); ?>" maxlength="20">
        </div>
        <div class="fld">
          <label>TAN</label>
          <input type="text" name="tan" value="<?php echo e(old('tan')); ?>" maxlength="10">
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('entity-modal')">Cancel</button>
        <button type="submit" class="btn acc">Create Entity</button>
      </div>
    </form>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('partials.upload-modal', [
    'uploadId'      => 'upload-modal',
    'uploadTitle'   => 'Bulk Upload Entities',
    'uploadRoute'   => 'masters.import',
    'templateRoute' => 'masters.template',
    'uploadHint'    => 'Template columns: Company Name, Entity Name, Entity Code, State, City, PF Number, ESIC Number, PT Number, TAN. One row per entity.',
], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\hrms-payroll\resources\views/masters/index.blade.php ENDPATH**/ ?>