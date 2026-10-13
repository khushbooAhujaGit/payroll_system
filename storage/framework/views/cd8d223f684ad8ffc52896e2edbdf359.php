<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — HRMS Payroll</title>
  <link rel="icon" href="<?php echo e(asset('favicon-32.png')); ?>" sizes="32x32" type="image/png">
  <link rel="icon" href="<?php echo e(asset('favicon-256.png')); ?>" sizes="256x256" type="image/png">
  <link rel="apple-touch-icon" href="<?php echo e(asset('favicon-256.png')); ?>">
  <link rel="stylesheet" href="/css/hrms.css">
</head>

<body>
  <div class="login-wrap">
    <div class="lb">
      <div class="ll">
        <div class="ico"><img src="<?php echo e(asset('favicon-256.png')); ?>" alt="" style="width:24px;height:24px;border-radius:4px"></div>
        <div>
          <h1>HRMS Payroll</h1>
          <div class="sub">Enterprise Platform</div>
        </div>
      </div>
      <h2>Sign In</h2>
      <p class="hint">Enter your credentials to continue</p>

      <?php if(session('success')): ?>
        <div style="background:var(--grn2,#dcfce7);color:var(--grn,#16a34a);border-radius:7px;padding:10px 12px;font-size:12px;margin-bottom:14px">
          <?php echo e(session('success')); ?>

        </div>
      <?php endif; ?>

      <?php if($errors->any()): ?>
        <div class="lerr" style="display:block;margin-bottom:12px">
          <?php echo e($errors->first()); ?>

        </div>
      <?php endif; ?>

      <form method="POST" action="/login">
        <?php echo csrf_field(); ?>
        <div class="fg">
          <label>Email</label>
          <input type="email" name="email" value="<?php echo e(old('email')); ?>" placeholder="name@company.com" required autofocus>
        </div>
        <div class="fg">
          <label>Password</label>
          <input type="password" name="password" placeholder="••••••••" required>
        </div>
        <button type="submit" class="lbtn">Sign In →</button>
      </form>

      <p style="text-align:center;margin-top:16px;font-size:12px">
        <a href="<?php echo e(route('password.request')); ?>" style="color:var(--acc);text-decoration:none">Forgot your password?</a>
      </p>
    </div>
  </div>
</body>

</html><?php /**PATH D:\hrms-payroll\resources\views/auth/login.blade.php ENDPATH**/ ?>