<div style="font-family:Arial,Helvetica,sans-serif;max-width:480px;margin:0 auto;padding:24px;color:#1f2937">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:24px">
        <span style="font-size:24px">💼</span>
        <div>
            <div style="font-size:16px;font-weight:700">HRMS Payroll</div>
            <div style="font-size:11px;color:#6b7280">Enterprise Platform</div>
        </div>
    </div>

    <h2 style="font-size:18px;margin:0 0 16px">New Leave Application (Testing mail)</h2>

    <table style="width:100%;font-size:13px;border-collapse:collapse">
        <tr>
            <td style="padding:6px 0;color:#6b7280;width:140px">Employee</td>
            <td style="padding:6px 0;font-weight:600">{{ $employee->full_name }} ({{ $employee->employee_id }})</td>
        </tr>
        <tr>
            <td style="padding:6px 0;color:#6b7280">Leave Type</td>
            <td style="padding:6px 0">{{ $leaveType->name }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0;color:#6b7280">Duration</td>
            <td style="padding:6px 0">
                {{ $fromDate }}@if($fromDate !== $toDate) to {{ $toDate }}@endif
            </td>
        </tr>
        @if($reason)
        <tr>
            <td style="padding:6px 0;color:#6b7280;vertical-align:top">Reason</td>
            <td style="padding:6px 0">{{ $reason }}</td>
        </tr>
        @endif
    </table>

    <p style="font-size:12px;color:#6b7280;margin-top:24px;border-top:1px solid #e5e7eb;padding-top:12px">
        This application is pending for approval. Sign in to HRMS Payroll to review it.
    </p>
</div>
