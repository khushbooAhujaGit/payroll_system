<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password — HRMS Payroll</title>
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
      <h2>Reset Password</h2>
      <p class="hint">Choose a new password for your account.</p>

      @if ($errors->any())
        <div class="lerr" style="display:block;margin-bottom:12px">
          {{ $errors->first() }}
        </div>
      @endif

      <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="fg">
          <label>Email</label>
          <input type="email" name="email" value="{{ old('email', $email) }}" required autofocus>
        </div>
        <div class="fg">
          <label>New Password</label>
          <input type="password" name="password" placeholder="••••••••" required minlength="8" autocomplete="new-password">
        </div>
        <div class="fg">
          <label>Confirm New Password</label>
          <input type="password" name="password_confirmation" placeholder="••••••••" required minlength="8" autocomplete="new-password">
        </div>
        <button type="submit" class="lbtn">Reset Password →</button>
      </form>

      <p style="text-align:center;margin-top:16px;font-size:12px">
        <a href="{{ route('login') }}" style="color:var(--acc);text-decoration:none">← Back to Sign In</a>
      </p>
    </div>
  </div>
</body>

</html>
