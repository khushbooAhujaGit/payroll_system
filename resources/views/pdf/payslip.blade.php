<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 11px; color: #1f2937; }
    .hdr-table { border-bottom: 2px solid #1e3a5f; padding-bottom: 10px; margin-bottom: 14px; }
    .hdr-table td { border: none; padding: 0; }
    .hdr-logo-cell { width: 100px; vertical-align: middle; }
    .hdr-center-cell { text-align: center; vertical-align: middle; }
    .hdr .cname { font-size: 16px; font-weight: bold; color: #1e3a5f; }
    .hdr .caddr { font-size: 10px; color: #6b7280; margin-top: 3px; }
    .hdr .title { font-size: 13px; font-weight: bold; margin-top: 8px; }
    table { width: 100%; border-collapse: collapse; }
    .infot td { padding: 3px 6px; font-size: 10.5px; }
    .infot .lbl { color: #6b7280; width: 110px; }
    .sect { background: #1e3a5f; color: #fff; font-weight: bold; padding: 5px 8px; font-size: 11px; margin-top: 14px; }
    .earn-ded { width: 100%; border: 1px solid #d1d5db; border-collapse: collapse; margin-top: 0; }
    .earn-ded th { background: #eef0f4; padding: 6px 8px; text-align: left; font-size: 10.5px; border: 1px solid #d1d5db; }
    .earn-ded td { padding: 6px 8px; font-size: 10.5px; border: 1px solid #d1d5db; }
    .amt { text-align: right; }
    .totrow td { font-weight: bold; background: #f5f7fa; }
    .netpay-row td { font-weight: bold; font-size: 13px; color: #166534; background: #f0fdf4; border: 1px solid #16a34a; padding: 8px; }
    .footer { margin-top: 24px; font-size: 9px; color: #9ca3af; text-align: center; }
</style>
</head>
<body>

<table class="hdr-table">
    <tr>
        <td class="hdr-logo-cell">
            @if($logoPath ?? null)
            <img src="{{ $logoPath }}" style="max-height:44px;max-width:95px;">
            @endif
        </td>
        <td class="hdr-center-cell hdr">
            <div class="cname">{{ $company->name ?? 'Company' }}</div>
            <div class="caddr">{{ $company->address ?? '' }}{{ $company->city ? ', ' . $company->city : '' }}</div>
            <div class="title">Payslip — {{ $monthLabel }}</div>
        </td>
        <td class="hdr-logo-cell"></td>
    </tr>
</table>

<table class="infot">
    <tr>
        <td class="lbl">Employee Name</td><td>{{ $row['name'] ?? $employee->full_name }}</td>
        <td class="lbl">Employee ID</td><td>{{ $row['emp_id'] ?? $employee->employee_id }}</td>
    </tr>
    <tr>
        <td class="lbl">Designation</td><td>{{ $row['designation'] ?? '—' }}</td>
        <td class="lbl">PAN</td><td>{{ $row['pan_number'] ?? '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">UAN</td><td>{{ $row['uan_number'] ?? '—' }}</td>
        <td class="lbl">Bank Account</td><td>{{ $row['account_number'] ?? '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Working Days</td><td>{{ $row['working_days'] ?? '—' }}</td>
        <td class="lbl">Net Paid Days</td><td>{{ $row['net_paid_days'] ?? '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">LOP Days</td><td>{{ $row['total_lop'] ?? 0 }}</td>
        <td class="lbl">IFSC Code</td><td>{{ $row['ifsc_code'] ?? '—' }}</td>
    </tr>
</table>

<div class="sect">Earnings &amp; Deductions</div>
<table class="earn-ded">
    <tr>
        <th>Earnings</th><th class="amt">Amount (₹)</th>
        <th>Deductions</th><th class="amt">Amount (₹)</th>
    </tr>
    <tr>
        <td>Basic Pay</td><td class="amt">{{ number_format($row['basic_pay'] ?? 0, 2) }}</td>
        <td>Employee PF</td><td class="amt">{{ number_format($row['employee_pf'] ?? 0, 2) }}</td>
    </tr>
    <tr>
        <td>HRA</td><td class="amt">{{ number_format($row['hra'] ?? 0, 2) }}</td>
        <td>Professional Tax</td><td class="amt">{{ number_format($row['professional_tax'] ?? 0, 2) }}</td>
    </tr>
    <tr>
        <td>Special Allowance</td><td class="amt">{{ number_format($row['special_allowance'] ?? 0, 2) }}</td>
        <td>Advance / Loan EMI</td><td class="amt">{{ number_format($row['advance_salary'] ?? 0, 2) }}</td>
    </tr>
    <tr>
        <td>Incentive</td><td class="amt">{{ number_format($row['incentive'] ?? 0, 2) }}</td>
        <td>TDS</td><td class="amt">{{ number_format($row['tds'] ?? 0, 2) }}</td>
    </tr>
    <tr>
        <td>Reimbursement</td><td class="amt">{{ number_format($row['reimbursement'] ?? 0, 2) }}</td>
        <td></td><td></td>
    </tr>
    <tr class="totrow">
        <td>Gross Earnings</td>
        <td class="amt">{{ number_format(($row['payable_gross'] ?? 0) + ($row['incentive'] ?? 0) + ($row['reimbursement'] ?? 0), 2) }}</td>
        <td>Total Deductions</td>
        <td class="amt">{{ number_format($row['total_deduction'] ?? 0, 2) }}</td>
    </tr>
    <tr class="netpay-row">
        <td colspan="3">NET PAY</td>
        <td class="amt">₹{{ number_format($row['net_pay'] ?? 0, 2) }}</td>
    </tr>
</table>

<div class="footer">
    This is a system-generated payslip and does not require a signature.
</div>

</body>
</html>
