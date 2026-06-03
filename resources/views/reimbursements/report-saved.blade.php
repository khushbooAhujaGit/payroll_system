@extends('layouts.app')
@section('title', 'Saved Reimbursement Reports')

@section('page_actions')
  <a href="{{ route('reimbursements.report') }}" class="btn">← Generate Report</a>
@endsection

@section('content')

<div class="card" style="margin-bottom:16px;padding:14px 18px">
  <div style="font-size:13px;color:var(--t2);line-height:1.6">
    <strong>Saved / Locked Reports</strong> — these are frozen snapshots of approved reimbursement rows.
    Editing tracker data after locking will not change these figures.
    Use <strong>Unlock</strong> to revert to live computation, or re-lock after editing to save the updated version.
  </div>
</div>

@if($snapshots->isEmpty())
<div class="card">
  <div class="empty">
    <div class="ico">🔒</div>
    <p>No locked reports yet.</p>
    <p style="font-size:12px;color:var(--t3);margin-top:4px">
      Go to the Report page and click <strong>Lock Report</strong> to freeze a month's data.
    </p>
    <a href="{{ route('reimbursements.report') }}" class="btn acc" style="margin-top:10px">Go to Report →</a>
  </div>
</div>
@else

<div class="card" style="overflow-x:auto">
  <div class="ch">
    <div class="ct">Locked Reports — {{ $snapshots->count() }} month{{ $snapshots->count() !== 1 ? 's' : '' }}</div>
  </div>
  <table style="font-size:12px;min-width:760px">
    <thead>
      <tr>
        <th>Month</th>
        <th style="text-align:center">Rows</th>
        <th style="text-align:right">Total Amount</th>
        <th>Locked On</th>
        <th>Locked By</th>
        <th style="text-align:center">Actions</th>
      </tr>
    </thead>
    <tbody>
      @foreach($snapshots as $snap)
      @php $monthLabel = \Carbon\Carbon::createFromFormat('Y-m', $snap->year_month)->format('F Y'); @endphp
      <tr>
        <td style="font-weight:600;white-space:nowrap">🔒 {{ $monthLabel }}</td>
        <td style="text-align:center">{{ $snap->row_count }}</td>
        <td style="text-align:right;color:var(--grn);font-weight:600">₹{{ number_format($snap->total_amount, 2) }}</td>
        <td style="font-size:11px;color:var(--t2);white-space:nowrap">
          {{ $snap->locked_at ? $snap->locked_at->format('d M Y, h:i A') : '—' }}
        </td>
        <td style="font-size:11px;color:var(--t2)">
          {{ $users[$snap->locked_by] ?? '—' }}
        </td>
        <td style="text-align:center;white-space:nowrap">

          <a href="{{ route('reimbursements.report.view', $snap->year_month) }}"
             class="btn sm acc" style="font-size:10px">View</a>

          <form method="POST" action="{{ route('reimbursements.report.download') }}" style="display:inline">
            @csrf
            <input type="hidden" name="month" value="{{ $snap->year_month }}">
            <button type="submit" class="btn sm" style="font-size:10px">📥 Excel</button>
          </form>

          <form method="POST" action="{{ route('reimbursements.report.unlock') }}" style="display:inline">
            @csrf
            <input type="hidden" name="month" value="{{ $snap->year_month }}">
            <button type="button" class="btn sm" style="font-size:10px;color:var(--t2)"
              onclick="swConfirm(this.closest('form'),
                'Unlock report?',
                'The snapshot for {{ $monthLabel }} will be removed. Data will recompute live.',
                'question', 'Unlock & Edit', '#d97706')">
              🔓 Unlock
            </button>
          </form>

          <form method="POST" action="{{ route('reimbursements.report.delete') }}" style="display:inline">
            @csrf
            <input type="hidden" name="month" value="{{ $snap->year_month }}">
            <button type="button" class="btn sm" style="font-size:10px;color:var(--red);font-weight:600"
              onclick="swConfirm(this.closest('form'),
                'Delete snapshot?',
                'This permanently removes the saved {{ $monthLabel }} report. Cannot be recovered.',
                'warning', 'Yes, delete it', '#dc2626')">
              🗑 Delete
            </button>
          </form>

        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>

@endif

@endsection
