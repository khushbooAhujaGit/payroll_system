<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password — HRMS Payroll</title>
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
      <h2>Forgot Password</h2>
      <p class="hint">Enter your email and we'll send you a link to reset your password.</p>

      @if (session('status'))
        <div style="background:var(--grn2,#dcfce7);color:var(--grn,#16a34a);border-radius:7px;padding:10px 12px;font-size:12px;margin-bottom:14px">
          {{ session('status') }}
        </div>
      @endif

      @if ($errors->any())
        <div class="lerr" style="display:block;margin-bottom:12px">
          {{ $errors->first() }}
        </div>
      @endif

      <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="fg">
          <label>Email</label>
          <input type="email" name="email" value="{{ old('email') }}" placeholder="you@company.com" required autofocus>
        </div>
        <button type="submit" class="lbtn">Send Reset Link →</button>
      </form>

      <p style="text-align:center;margin-top:16px;font-size:12px">
        <a href="{{ route('login') }}" style="color:var(--acc);text-decoration:none">← Back to Sign In</a>
      </p>
    </div>
  </div>
</body>

</html>
