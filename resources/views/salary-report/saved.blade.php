@extends('layouts.app')
@section('title', 'Saved Salary Reports')

@section('page_actions')
  <a href="{{ route('salary.report') }}" class="btn">← Generate Report</a>
@endsection

@section('content')

<div class="card" style="margin-bottom:16px;padding:14px 18px">
  <div style="font-size:13px;color:var(--t2);line-height:1.6">
    <strong>Saved / Locked Reports</strong> — these reports are frozen snapshots. Editing employee salary, loans, or leave data after locking will not change these figures.
    Use <strong>Unlock</strong> to revert to live computation, or re-lock after editing to save the updated version.
  </div>
</div>

@if($byMonth->isEmpty())
<div class="card">
  <div class="empty">
    <div class="ico">🔒</div>
    <p>No locked reports yet.</p>
    <p style="font-size:12px;color:var(--t3);margin-top:4px">
      Generate a report and click <strong>Lock Report</strong> to freeze it here.
    </p>
    <a href="{{ route('salary.report') }}" class="btn acc" style="margin-top:10px">Go to Salary Report →</a>
  </div>
</div>
@else

<div class="card" style="overflow-x:auto">
  <div class="ch">
    <div class="ct">Locked Reports — {{ $byMonth->count() }} month{{ $byMonth->count() !== 1 ? 's' : '' }}</div>
  </div>
  <table style="font-size:12px;min-width:900px">
    <thead>
      <tr>
        <th>Month</th>
        <th style="text-align:center">Employees</th>
        <th style="text-align:right">Total Gross</th>
        <th style="text-align:right">Total Payable</th>
        <th style="text-align:right">Total Net Pay</th>
        <th style="text-align:right">Total Deductions</th>
        <th>Locked On</th>
        <th>Locked By</th>
        <th style="text-align:center">Actions</th>
      </tr>
    </thead>
    <tbody>
      @foreach($byMonth as $rep)
      @php
        $monthLabel = \Carbon\Carbon::createFromFormat('Y-m', $rep['year_month'])->format('F Y');
      @endphp
      <tr>
        <td style="font-weight:600;white-space:nowrap">
          🔒 {{ $monthLabel }}
        </td>
        <td style="text-align:center">{{ $rep['employee_count'] }}</td>
        <td style="text-align:right">₹{{ number_format($rep['total_gross'], 0) }}</td>
        <td style="text-align:right">₹{{ number_format($rep['total_payable'], 0) }}</td>
        <td style="text-align:right;color:var(--grn);font-weight:600">₹{{ number_format($rep['total_net_pay'], 0) }}</td>
        <td style="text-align:right;color:var(--red)">₹{{ number_format($rep['total_deduction'], 0) }}</td>
        <td style="font-size:11px;color:var(--t2);white-space:nowrap">
          {{ $rep['locked_at'] ? \Carbon\Carbon::parse($rep['locked_at'])->format('d M Y, h:i A') : '—' }}
        </td>
        <td style="font-size:11px;color:var(--t2)">
          {{ $users[$rep['locked_by']] ?? '—' }}
        </td>
        <td style="text-align:center;white-space:nowrap">
          <a href="{{ route('salary.report.view', $rep['year_month']) }}"
             class="btn sm acc" style="font-size:10px">View</a>

          <form method="POST" action="{{ route('salary.report.download') }}" style="display:inline">
            @csrf
            <input type="hidden" name="month" value="{{ $rep['year_month'] }}">
            <input type="hidden" name="company_id" value="0">
            <button type="submit" class="btn sm" style="font-size:10px">📥 Excel</button>
          </form>

          <form method="POST" action="{{ route('salary.report.delete') }}" style="display:inline">
            @csrf
            <input type="hidden" name="month" value="{{ $rep['year_month'] }}">
            <button type="button" class="btn sm" style="font-size:10px;color:var(--red);font-weight:600"
              onclick="swConfirm(this.closest('form'),
                'Delete Snapshot?',
                'This permanently removes the saved {{ $monthLabel }} report. The data cannot be recovered.',
                'warning', 'Yes, delete it', '#dc2626')">
              🗑 Delete
            </button>
          </form>

          <form method="POST" action="{{ route('salary.report.unlock') }}" style="display:inline">
            @csrf
            <input type="hidden" name="month" value="{{ $rep['year_month'] }}">
            <input type="hidden" name="company_id" value="0">
            <button type="button" class="btn sm" style="font-size:10px;color:var(--t2)"
              onclick="swConfirm(this.closest('form'),
                'Unlock report?',
                'The snapshot for {{ $monthLabel }} will be removed and you will be taken to the live working report.',
                'question', 'Unlock & Edit', '#d97706')">
              🔓 Unlock
            </button>
          </form>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>

<div class="card" style="margin-top:16px;padding:14px 18px">
  <div style="font-size:12px;color:var(--t3);line-height:1.8">
    <strong style="color:var(--t2)">How locking works:</strong><br>
    • <strong>Lock Report</strong> — freezes the current computed values for all employees into a snapshot. Future changes to salary, loans, or leave data will not change this report.<br>
    • <strong>Edit row (while locked)</strong> — saving an override automatically refreshes that employee's snapshot, keeping the locked report up to date with intentional edits.<br>
    • <strong>Unlock</strong> — deletes the snapshot. The report will recompute live from current source data. Re-lock after making changes to create a new frozen version.
  </div>
</div>

@endif

@endsection
