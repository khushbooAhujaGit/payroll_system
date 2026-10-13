<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 11px; color: #1f2937; }
    .hdr { text-align: center; border-bottom: 2px solid #1e3a5f; padding-bottom: 10px; margin-bottom: 12px; }
    .hdr .cname { font-size: 16px; font-weight: bold; color: #1e3a5f; }
    .hdr .title { font-size: 13px; font-weight: bold; margin-top: 8px; }
    .disclaimer { background: #fff7ed; border: 1.5px solid #ea580c; border-radius: 4px; padding: 10px 12px; font-size: 9.5px; color: #7c2d12; margin-bottom: 14px; line-height: 1.5; }
    .disclaimer strong { display: block; margin-bottom: 3px; font-size: 10.5px; }
    table { width: 100%; border-collapse: collapse; }
    .infot td { padding: 3px 6px; font-size: 10.5px; }
    .infot .lbl { color: #6b7280; width: 130px; }
    .sect { background: #1e3a5f; color: #fff; font-weight: bold; padding: 5px 8px; font-size: 11px; margin-top: 12px; }
    .mtable { width: 100%; border: 1px solid #d1d5db; border-collapse: collapse; margin-top: 0; }
    .mtable th { background: #eef0f4; padding: 5px 7px; text-align: right; font-size: 9.5px; border: 1px solid #d1d5db; }
    .mtable th:first-child { text-align: left; }
    .mtable td { padding: 5px 7px; font-size: 9.5px; border: 1px solid #d1d5db; text-align: right; }
    .mtable td:first-child { text-align: left; }
    .mtable .unlocked { color: #9ca3af; font-style: italic; }
    .totrow td { font-weight: bold; background: #f5f7fa; }
    .footer { margin-top: 20px; font-size: 9px; color: #9ca3af; text-align: center; }
</style>
</head>
<body>

<div class="hdr">
    <div class="cname">{{ $company->name ?? 'Company' }}</div>
    <div class="title">Form 16 — Tax Deduction Summary — FY {{ $financialYear }}</div>
</div>

<div class="disclaimer">
    <strong>This is a system-generated summary, not an official TRACES-issued Form 16.</strong>
    It reports gross salary, PF, professional tax, and TDS exactly as recorded in the HRMS each month.
    It does <u>not</u> independently compute HRA exemption, Chapter VI-A deductions (80C etc.), surcharge,
    or final tax liability. Please verify against your company's TDS returns / TRACES portal and consult
    a tax advisor before using this for income tax filing.
</div>

<table class="infot">
    <tr>
        <td class="lbl">Employee Name</td><td>{{ $employee->full_name }}</td>
        <td class="lbl">Employee ID</td><td>{{ $employee->employee_id }}</td>
    </tr>
    <tr>
        <td class="lbl">PAN</td><td>{{ $panNumber ?? '—' }}</td>
        <td class="lbl">Employer TAN</td><td>{{ $company->tan ?? '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Employer PAN</td><td>{{ $company->pan ?? '—' }}</td>
        <td class="lbl">Financial Year</td><td>{{ $financialYear }}</td>
    </tr>
</table>

<div class="sect">Month-wise Summary</div>
<table class="mtable">
    <tr>
        <th>Month</th><th>Gross Paid (₹)</th><th>Employee PF (₹)</th><th>Professional Tax (₹)</th><th>TDS Deducted (₹)</th>
    </tr>
    @foreach($monthlyRows as $m)
    <tr class="{{ $m['locked'] ? '' : 'unlocked' }}">
        <td>{{ $m['label'] }}{{ $m['locked'] ? '' : ' (not processed)' }}</td>
        <td>{{ number_format($m['gross'], 2) }}</td>
        <td>{{ number_format($m['pf'], 2) }}</td>
        <td>{{ number_format($m['pt'], 2) }}</td>
        <td>{{ number_format($m['tds'], 2) }}</td>
    </tr>
    @endforeach
    <tr class="totrow">
        <td>Total</td>
        <td>{{ number_format($totals['gross'], 2) }}</td>
        <td>{{ number_format($totals['pf'], 2) }}</td>
        <td>{{ number_format($totals['pt'], 2) }}</td>
        <td>{{ number_format($totals['tds'], 2) }}</td>
    </tr>
</table>

<div class="footer">
    Generated {{ now()->format('d M Y, h:i A') }} — system-generated document, does not require a signature.
</div>

</body>
</html>
