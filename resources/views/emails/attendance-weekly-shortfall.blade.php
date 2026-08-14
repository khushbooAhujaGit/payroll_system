<div style="font-family:Arial,Helvetica,sans-serif;max-width:480px;margin:0 auto;padding:24px;color:#1f2937">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:24px">
        <span style="font-size:24px">💼</span>
        <div>
            <div style="font-size:16px;font-weight:700">HRMS Payroll</div>
            <div style="font-size:11px;color:#6b7280">Enterprise Platform</div>
        </div>
    </div>

    <h2 style="font-size:18px;margin:0 0 12px">Attendance Notice</h2>
    <p style="font-size:13px;line-height:1.6;color:#374151">
        Hi {{ $employee->first_name }}, your average clocked hours for the week of
        {{ $weekStart->format('d M Y') }} to {{ $weekEnd->format('d M Y') }} were below the
        required 9 hours/day average.
    </p>

    <table style="width:100%;font-size:13px;border-collapse:collapse;margin:16px 0">
        <tr>
            <td style="padding:6px 0;color:#6b7280;width:160px">Week</td>
            <td style="padding:6px 0;font-weight:600">{{ $weekStart->format('d M Y') }} - {{ $weekEnd->format('d M Y') }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0;color:#6b7280">Average Hours/Day</td>
            <td style="padding:6px 0;font-weight:600;color:#dc2626">{{ $averageHours }} hrs</td>
        </tr>
        <tr>
            <td style="padding:6px 0;color:#6b7280">Days With Attendance</td>
            <td style="padding:6px 0">{{ $dayCount }}</td>
        </tr>
    </table>

    <p style="font-size:12px;color:#6b7280;line-height:1.6;border-top:1px solid #e5e7eb;padding-top:12px">
        If this was due to a valid reason, sign in to HRMS Payroll and raise a Change Request from your
        My Attendance page with a remark — your reporting manager/HR will review it.
    </p>
</div>
