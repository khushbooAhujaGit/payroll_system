<div style="font-family:Arial,Helvetica,sans-serif;max-width:480px;margin:0 auto;padding:24px;color:#1f2937">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:24px">
        <span style="font-size:24px">💼</span>
        <div>
            <div style="font-size:16px;font-weight:700">HRMS Payroll (Test mail)</div>
            <div style="font-size:11px;color:#6b7280">Enterprise Platform</div>
        </div>
    </div>

    <h2 style="font-size:18px;margin:0 0 12px">Welcome, {{ $employee->first_name }}!</h2>
    <p style="font-size:13px;line-height:1.6;color:#374151">
        An account has been created for you on HRMS Payroll. Use the details below to sign in,
        then change your password and complete your profile.
    </p>

    <table style="width:100%;font-size:13px;border-collapse:collapse;margin:20px 0;background:#f9fafb;border-radius:6px">
        <tr>
            <td style="padding:10px 14px;color:#6b7280;width:120px">Email</td>
            <td style="padding:10px 14px;font-weight:600">{{ $email }}</td>
        </tr>
        <tr>
            <td style="padding:10px 14px;color:#6b7280">Password</td>
            <td style="padding:10px 14px;font-weight:600">{{ $password }}</td>
        </tr>
    </table>

    <p style="text-align:center;margin:28px 0">
        <a href="{{ $loginUrl }}" style="background:#2563eb;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:6px;font-size:13px;font-weight:600;display:inline-block">
            Sign In →
        </a>
    </p>

    <p style="font-size:12px;color:#6b7280;line-height:1.6">
        For security, please change your password immediately after your first sign-in and complete your profile details.
    </p>
</div>
