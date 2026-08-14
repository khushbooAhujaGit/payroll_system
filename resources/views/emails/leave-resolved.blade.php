<div style="font-family:Arial,Helvetica,sans-serif;max-width:480px;margin:0 auto;padding:24px;color:#1f2937">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:24px">
        <span style="font-size:24px">💼</span>
        <div>
            <div style="font-size:16px;font-weight:700">HRMS Payroll (Test mail)</div>
            <div style="font-size:11px;color:#6b7280">Enterprise Platform</div>
        </div>
    </div>

    <h2 style="font-size:18px;margin:0 0 16px">
        Your Leave Has Been {{ ucfirst($action) }}
    </h2>

    <table style="width:100%;font-size:13px;border-collapse:collapse">
        <tr>
            <td style="padding:6px 0;color:#6b7280;width:140px">Employee</td>
            <td style="padding:6px 0;font-weight:600">{{ $employee->full_name }} ({{ $employee->employee_id }})</td>
        </tr>
        <tr>
            <td style="padding:6px 0;color:#6b7280">Leave Type</td>
            <td style="padding:6px 0">{{ $leaveTypeLabel }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0;color:#6b7280">Duration</td>
            <td style="padding:6px 0">
                {{ $fromDate }}@if($fromDate !== $toDate) to {{ $toDate }}@endif
            </td>
        </tr>
        <tr>
            <td style="padding:6px 0;color:#6b7280">Status</td>
            <td style="padding:6px 0;font-weight:600;color:{{ $action === 'approved' ? '#16a34a' : '#dc2626' }}">
                {{ ucfirst($action) }}
            </td>
        </tr>
    </table>

    <p style="font-size:12px;color:#6b7280;margin-top:24px;border-top:1px solid #e5e7eb;padding-top:12px">
        Sign in to HRMS Payroll for full details of this application.
    </p>
</div>
