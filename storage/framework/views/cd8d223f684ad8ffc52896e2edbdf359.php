<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — HRMS Payroll</title>
  <link rel="stylesheet" href="/css/hrms.css">
</head>

<body>
  <div class="login-wrap">
    <div class="lb">
      <div class="ll">
        <div class="ico">💼</div>
        <div>
          <h1>HRMS Payroll</h1>
          <div class="sub">Enterprise Platform</div>
        </div>
      </div>
      <h2>Sign In</h2>
      <p class="hint">Enter your credentials to continue</p>

      <?php if($errors->any()): ?>
        <div class="lerr" style="display:block;margin-bottom:12px">
          <?php echo e($errors->first()); ?>

        </div>
      <?php endif; ?>

      <form method="POST" action="/login">
        <?php echo csrf_field(); ?>
        <div class="fg">
          <label>Email</label>
          <input type="email" name="email" value="<?php echo e(old('email')); ?>" placeholder="admin@hrms.local" required autofocus>
        </div>
        <div class="fg">
          <label>Password</label>
          <input type="password" name="password" placeholder="••••••••" required>
        </div>
        <button type="submit" class="lbtn">Sign In →</button>
      </form>
    </div>
  </div>
</body>

</html><?php /**PATH D:\hrms-payroll\resources\views/auth/login.blade.php ENDPATH**/ ?>