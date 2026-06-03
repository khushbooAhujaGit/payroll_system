@extends('layouts.app')
@section('title', 'Locked Salary Report — ' . \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y'))

@section('page_actions')
  <a href="{{ route('payroll') }}" class="btn">← Payroll</a>
  <form method="POST" action="{{ route('salary.report.download') }}" style="display:inline">
    @csrf
    <input type="hidden" name="month" value="{{ $month }}">
    <input type="hidden" name="company_id" value="{{ $companyId }}">
    <button type="submit" class="btn acc">📥 Download 🔒 Excel</button>
  </form>
  @php $deleteLabel = \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y'); @endphp
  <form method="POST" action="{{ route('salary.report.delete') }}" style="display:inline">
    @csrf
    <input type="hidden" name="month" value="{{ $month }}">
    <button type="button" class="btn" style="color:var(--red)"
      onclick="swConfirm(this.closest('form'),
        'Delete this snapshot?',
        'This permanently removes the saved {{ $deleteLabel }} report. The data cannot be recovered.',
        'warning', 'Yes, delete it', '#dc2626')">
      🗑 Delete Snapshot
    </button>
  </form>
@endsection

@section('content')

{{-- Locked banner --}}
<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
  <span style="font-size:20px">🔒</span>
  <div style="flex:1;min-width:200px">
    <div style="font-weight:600;color:#166534;font-size:14px">
      Locked Report — {{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}
    </div>
    <div style="font-size:11px;color:#15803d;margin-top:2px">
      Frozen snapshot · {{ $snapshotCount }} employees · Locked on {{ $lockedAt?->format('d M Y, h:i A') }}
    </div>
  </div>
  <div style="font-size:11px;color:var(--t3);font-style:italic">
    Read-only view — to edit rows, open the report from <a href="{{ route('salary.report', ['month' => $month]) }}" style="color:var(--acc)">Salary Report →</a>
  </div>
</div>

{{-- Summary Cards --}}
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;margin-bottom:16px">
  @php
    $cards = [
      ['Headcount',       $summary['headcount'],                                          null,         '👥'],
      ['Gross Payable',   '₹'.number_format($summary['payable_gross'],0),     'var(--acc)', '💰'],
      ['Total Incentive', '₹'.number_format($summary['incentive'],0),         'var(--grn)', '🎁'],
      ['Reimbursement',   '₹'.number_format($summary['reimbursement'],0),     'var(--grn)', '🧾'],
      ['Employee PF',     '₹'.number_format($summary['employee_pf'],0),        null,        '🏦'],
      ['Prof. Tax',       '₹'.number_format($summary['professional_tax'],0),   null,        '📋'],
      ['Advance/EMI',     '₹'.number_format($summary['advance_salary'],0),     null,        '💳'],
      ['TDS',             '₹'.number_format($summary['tds'],0),                null,        '📑'],
      ['Total Deduction', '₹'.number_format($summary['total_deduction'],0),   'var(--red)', '➖'],
      ['Net Pay',         '₹'.number_format($summary['net_pay'],0),           'var(--grn)', '✅'],
      ['CTC (Total)',     '₹'.number_format($summary['ctc'],0),                null,        '📊'],
    ];
  @endphp
  @foreach($cards as [$label,$val,$color,$ico])
  <div class="sc">
    <div class="lbl">{{ $ico }} {{ $label }}</div>
    <div class="val" style="font-size:13px;{{ $color ? 'color:'.$color : '' }}">{{ $val }}</div>
  </div>
  @endforeach
</div>

{{-- Table --}}
<div class="card" style="overflow-x:auto">
  <div class="ch">
    <div class="ct">
      Salary Sheet — {{ \Carbon\Carbon::createFromFormat('Y-m',$month)->format('F Y') }} · All Firms
      <span style="background:#dcfce7;color:#166534;border-radius:4px;padding:2px 6px;font-size:10px;margin-left:8px">🔒 LOCKED</span>
    </div>
    <div style="font-size:11px;color:var(--t3)">{{ count($rows) }} employees · Scroll right for full details</div>
  </div>
  <table style="font-size:11px;min-width:2800px">
    <thead>
      <tr>
        <th>Emp ID</th>
        <th>Employee</th>
        <th>Designation</th>
        <th style="text-align:right">Gross</th>
        <th>Month</th>
        <th style="text-align:center">Mo Days</th>
        <th style="text-align:center">W/Off+H</th>
        <th style="text-align:center">Work Days</th>
        <th style="text-align:center">LOP</th>
        <th style="text-align:center">Net Paid</th>
        <th style="text-align:right">Payable Gross</th>
        <th style="text-align:right">Basic</th>
        <th style="text-align:right">HRA</th>
        <th style="text-align:right">Spl Allow</th>
        <th style="text-align:right">Incentive</th>
        <th style="text-align:right">Reimbursement</th>
        <th style="text-align:right">Emp PF</th>
        <th style="text-align:right">PT</th>
        <th style="text-align:right">Advance</th>
        <th style="text-align:right">TDS</th>
        <th style="text-align:right;background:var(--red2);color:var(--red)">Total Ded.</th>
        <th style="text-align:right;background:var(--grn2);color:var(--grn);font-weight:700">Net Pay</th>
        <th style="text-align:right">Empl PF</th>
        <th style="text-align:right">CTC</th>
        <th style="text-align:right">Diwali Bonus</th>
        <th style="text-align:right">CTC+Bonus</th>
        <th>Firm</th>
        <th style="text-align:center">PF App.</th>
        <th>UAN No.</th>
        <th style="text-align:center">CL Bal.</th>
        <th style="text-align:center">SL Bal.</th>
        <th style="text-align:center">CP Used</th>
        <th style="text-align:center">LOP (Lv)</th>
        <th style="text-align:center">LOP (Att)</th>
        <th>Account No.</th>
        <th>IFSC</th>
        <th>PAN</th>
        <th>Email</th>
      </tr>
    </thead>
    <tbody>
      @foreach($rows as $r)
      <tr>
        <td class="mono">{{ $r['emp_id'] }}</td>
        <td>{{ $r['name'] }}</td>
        <td style="color:var(--t2)">{{ $r['designation'] }}</td>
        <td style="text-align:right">{{ number_format($r['gross_salary'],0) }}</td>
        <td>{{ $r['month'] }}</td>
        <td style="text-align:center">{{ $r['month_days'] }}</td>
        <td style="text-align:center">{{ $r['weekoff_holidays'] }}</td>
        <td style="text-align:center">{{ $r['working_days'] }}</td>
        <td style="text-align:center;color:{{ $r['total_lop'] > 0 ? 'var(--red)' : 'var(--t3)' }};font-weight:{{ $r['total_lop'] > 0 ? '600' : '400' }}">
          {{ $r['total_lop'] ?: '—' }}
        </td>
        <td style="text-align:center">{{ $r['net_paid_days'] }}</td>
        <td style="text-align:right">{{ number_format($r['payable_gross'],0) }}</td>
        <td style="text-align:right">{{ number_format($r['basic_pay'],0) }}</td>
        <td style="text-align:right">{{ number_format($r['hra'],0) }}</td>
        <td style="text-align:right">{{ number_format($r['special_allowance'],0) }}</td>
        <td style="text-align:right;color:{{ $r['incentive'] > 0 ? 'var(--grn)' : 'var(--t3)' }}">
          {{ $r['incentive'] > 0 ? number_format($r['incentive'],0) : '—' }}
        </td>
        <td style="text-align:right;color:{{ ($r['reimbursement'] ?? 0) > 0 ? 'var(--grn)' : 'var(--t3)' }}">
          {{ ($r['reimbursement'] ?? 0) > 0 ? number_format($r['reimbursement'],0) : '—' }}
        </td>
        <td style="text-align:right">{{ $r['employee_pf'] > 0 ? number_format($r['employee_pf'],0) : '—' }}</td>
        <td style="text-align:right">{{ $r['professional_tax'] > 0 ? number_format($r['professional_tax'],0) : '—' }}</td>
        <td style="text-align:right;color:{{ $r['advance_salary'] > 0 ? 'var(--yel)' : 'var(--t3)' }}">
          {{ $r['advance_salary'] > 0 ? number_format($r['advance_salary'],0) : '—' }}
        </td>
        <td style="text-align:right;color:{{ $r['tds'] > 0 ? 'var(--acc)' : 'var(--t3)' }}">
          {{ $r['tds'] > 0 ? number_format($r['tds'],0) : '—' }}
        </td>
        <td style="text-align:right;background:var(--red2);color:var(--red);font-weight:600">
          {{ number_format($r['total_deduction'],0) }}
        </td>
        <td style="text-align:right;background:var(--grn2);color:var(--grn);font-weight:700">
          {{ number_format($r['net_pay'],0) }}
        </td>
        <td style="text-align:right;color:var(--t2)">{{ $r['employer_pf'] > 0 ? number_format($r['employer_pf'],0) : '—' }}</td>
        <td style="text-align:right">{{ number_format($r['ctc'],0) }}</td>
        <td style="text-align:right;color:{{ $r['bonus_diwali'] > 0 ? 'var(--yel)' : 'var(--t3)' }}">
          {{ $r['bonus_diwali'] > 0 ? number_format($r['bonus_diwali'],0) : '—' }}
        </td>
        <td style="text-align:right">{{ number_format($r['ctc_with_diwali'],0) }}</td>
        <td style="font-size:10px;color:var(--t2)">{{ Str::limit($r['firm'],16) }}</td>
        <td style="text-align:center">
          <span class="bx" style="font-size:9px;color:{{ $r['pf_applicability']==='Yes'?'var(--grn)':'var(--t3)' }}">
            {{ $r['pf_applicability'] }}
          </span>
        </td>
        <td class="mono" style="font-size:10px;color:var(--t2)">{{ $r['uan_number'] ?: '—' }}</td>
        <td style="text-align:center;color:{{ ($r['closing_cl'] ?? 0) > 0 ? 'var(--grn)' : 'var(--t3)' }}">
          {{ number_format($r['closing_cl'] ?? 0, 1) }}
        </td>
        <td style="text-align:center;color:{{ ($r['closing_sl'] ?? 0) > 0 ? 'var(--grn)' : 'var(--t3)' }}">
          {{ number_format($r['closing_sl'] ?? 0, 1) }}
        </td>
        <td style="text-align:center;color:var(--t2)">{{ number_format($r['current_cp'] ?? 0, 1) }}</td>
        <td style="text-align:center;color:{{ ($r['current_lop'] ?? 0) > 0 ? 'var(--red)' : 'var(--t3)' }}">
          {{ ($r['current_lop'] ?? 0) ?: '—' }}
        </td>
        <td style="text-align:center;color:{{ ($r['att_lop'] ?? 0) > 0 ? 'var(--red)' : 'var(--t3)' }}">
          {{ ($r['att_lop'] ?? 0) ?: '—' }}
        </td>
        <td class="mono" style="font-size:10px;color:var(--t2)">{{ $r['account_number'] ?: '—' }}</td>
        <td class="mono" style="font-size:10px;color:var(--t2)">{{ $r['ifsc_code'] ?: '—' }}</td>
        <td class="mono" style="font-size:10px;color:var(--t2)">{{ $r['pan_number'] ?: '—' }}</td>
        <td style="font-size:10px;color:var(--t2)">{{ $r['rsc_mail_id'] ?: '—' }}</td>
      </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr style="background:var(--s2);font-weight:700;font-size:11px">
        <td colspan="3" style="text-align:right;color:var(--t2)">TOTAL ({{ count($rows) }} employees)</td>
        <td style="text-align:right">{{ number_format($summary['gross_salary'],0) }}</td>
        <td colspan="6"></td>
        <td style="text-align:right">{{ number_format($summary['payable_gross'],0) }}</td>
        <td style="text-align:right">{{ number_format($summary['basic_pay'],0) }}</td>
        <td style="text-align:right">{{ number_format($summary['hra'],0) }}</td>
        <td style="text-align:right">{{ number_format($summary['special_allowance'],0) }}</td>
        <td style="text-align:right;color:var(--grn)">{{ number_format($summary['incentive'],0) }}</td>
        <td style="text-align:right;color:var(--grn)">{{ number_format($summary['reimbursement'] ?? 0, 0) }}</td>
        <td style="text-align:right">{{ number_format($summary['employee_pf'],0) }}</td>
        <td style="text-align:right">{{ number_format($summary['professional_tax'],0) }}</td>
        <td style="text-align:right">{{ number_format($summary['advance_salary'],0) }}</td>
        <td style="text-align:right">{{ number_format($summary['tds'],0) }}</td>
        <td style="text-align:right;color:var(--red)">{{ number_format($summary['total_deduction'],0) }}</td>
        <td style="text-align:right;color:var(--grn)">{{ number_format($summary['net_pay'],0) }}</td>
        <td style="text-align:right">{{ number_format($summary['employer_pf'],0) }}</td>
        <td style="text-align:right">{{ number_format($summary['ctc'],0) }}</td>
        <td style="text-align:right">{{ number_format($summary['bonus_diwali'],0) }}</td>
        <td style="text-align:right">{{ number_format($summary['ctc_with_diwali'],0) }}</td>
        <td colspan="12"></td>
      </tr>
    </tfoot>
  </table>
</div>

@endsection
