<div style="font-family:Arial,Helvetica,sans-serif;max-width:480px;margin:0 auto;padding:24px;color:#1f2937">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:24px">
        <span style="font-size:24px">💼</span>
        <div>
            <div style="font-size:16px;font-weight:700">HRMS Payroll (Test mail)</div>
            <div style="font-size:11px;color:#6b7280">Enterprise Platform</div>
        </div>
    </div>

    <h2 style="font-size:18px;margin:0 0 12px">Reset Your Password</h2>
    <p style="font-size:13px;line-height:1.6;color:#374151">
        We received a request to reset the password for your account ({{ $email }}).
        Click the button below to choose a new password. This link expires in {{ $expiryMinutes }} minutes.
    </p>

    <p style="text-align:center;margin:28px 0">
        <a href="{{ $url }}" style="background:#2563eb;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:6px;font-size:13px;font-weight:600;display:inline-block">
            Reset Password →
        </a>
    </p>

    <p style="font-size:12px;color:#6b7280;line-height:1.6">
        If you didn't request a password reset, no action is needed — your password will remain unchanged.
    </p>

    <p style="font-size:11px;color:#9ca3af;margin-top:24px;border-top:1px solid #e5e7eb;padding-top:12px">
        If the button above doesn't work, copy and paste this link into your browser:<br>
        <span style="word-break:break-all">{{ $url }}</span>
    </p>
</div>
