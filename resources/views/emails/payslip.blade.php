<div style="font-family:Arial,Helvetica,sans-serif;max-width:480px;margin:0 auto;padding:24px;color:#1f2937">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:24px">
        <span style="font-size:24px">💼</span>
        <div>
            <div style="font-size:16px;font-weight:700">HRMS Payroll</div>
            <div style="font-size:11px;color:#6b7280">Enterprise Platform</div>
        </div>
    </div>

    <h2 style="font-size:18px;margin:0 0 12px">Your Payslip — {{ $monthLabel }}</h2>
    <p style="font-size:13px;line-height:1.6;color:#374151">
        Hi {{ $employee->first_name }}, your salary for {{ $monthLabel }} has been processed.
        The payslip PDF is attached to this email.
    </p>

    <table style="width:100%;font-size:13px;border-collapse:collapse;margin:16px 0">
        <tr>
            <td style="padding:6px 0;color:#6b7280;width:160px">Net Pay</td>
            <td style="padding:6px 0;font-weight:700;color:#166534">₹{{ number_format($row['net_pay'] ?? 0, 2) }}</td>
        </tr>
    </table>

    <p style="font-size:12px;color:#6b7280;line-height:1.6;border-top:1px solid #e5e7eb;padding-top:12px">
        You can also download this or any past month's payslip anytime from My Payslips in HRMS Payroll.
    </p>
</div>
