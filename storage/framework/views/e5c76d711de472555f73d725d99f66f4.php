<div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap">
  <a class="btn sm rbac-tab-btn <?php echo e(request()->routeIs('rbac.roles') ? 'on' : ''); ?>" href="<?php echo e(route('rbac.roles')); ?>">Roles</a>
  <a class="btn sm rbac-tab-btn <?php echo e(request()->routeIs('rbac.permission_groups') ? 'on' : ''); ?>" href="<?php echo e(route('rbac.permission_groups')); ?>">Permission Groups</a>
  <a class="btn sm rbac-tab-btn <?php echo e(request()->routeIs('rbac.permissions') ? 'on' : ''); ?>" href="<?php echo e(route('rbac.permissions')); ?>">Permissions</a>
</div>
<?php /**PATH D:\hrms-payroll\resources\views/rbac/_nav.blade.php ENDPATH**/ ?>