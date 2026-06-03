@extends('layouts.app')
@section('title', 'Reimbursement Report — ' . \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y'))

@section('page_actions')
  <a href="{{ route('reimbursements.report.saved') }}" class="btn">← Saved Reports</a>
  <form method="POST" action="{{ route('reimbursements.report.download') }}" style="display:inline">
    @csrf
    <input type="hidden" name="month" value="{{ $month }}">
    <button type="submit" class="btn">📥 Download Excel</button>
  </form>
@endsection

@section('content')

{{-- Header Info --}}
<div class="card" style="margin-bottom:16px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
  <div>
    <div style="font-size:16px;font-weight:700">
      🔒 {{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y') }} — Reimbursement Report
    </div>
    <div style="font-size:12px;color:var(--t3);margin-top:3px">
      Locked on {{ $lockedAt ? $lockedAt->format('d M Y, h:i A') : '—' }}
    </div>
  </div>
  <div style="display:flex;gap:24px">
    <div style="text-align:right">
      <div style="font-size:11px;color:var(--t3)">Approved Rows</div>
      <div style="font-size:20px;font-weight:700">{{ $rowCount }}</div>
    </div>
    <div style="text-align:right">
      <div style="font-size:11px;color:var(--t3)">Total Amount</div>
      <div style="font-size:20px;font-weight:700;color:var(--grn)">₹{{ number_format($totalAmount, 2) }}</div>
    </div>
  </div>
</div>

{{-- Search --}}
<form method="GET" action="{{ route('reimbursements.report.view', $month) }}"
      class="card" style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
  <div class="fld" style="min-width:260px">
    <label>Search</label>
    <input type="text" name="search" value="{{ $search }}" placeholder="Emp ID or Employee Name…">
  </div>
  <button type="submit" class="btn acc">Search</button>
  @if($search !== '')
    <a href="{{ route('reimbursements.report.view', $month) }}" class="btn">✕ Clear</a>
    <span style="font-size:12px;color:var(--t2);padding-bottom:2px">
      {{ $rows->total() }} result(s) for "{{ e($search) }}"
    </span>
  @endif
</form>

{{-- Report Table --}}
<div class="card" style="overflow-x:auto">
  @if($rows->total() > 0)
  <div class="ch" style="display:flex;justify-content:space-between;align-items:center">
    <div class="ct">
      {{ number_format($rows->total()) }} row(s)
      @if($search !== '')
        <span style="color:var(--acc);font-size:11px"> matching "{{ e($search) }}"</span>
      @endif
    </div>
    @if($rows->lastPage() > 1)
      <div style="font-size:12px;color:var(--t3)">Page {{ $rows->currentPage() }} of {{ $rows->lastPage() }}</div>
    @endif
  </div>
  <table style="font-size:12px;min-width:600px">
    <thead>
      <tr>
        <th style="width:40px">#</th>
        <th>Date</th>
        <th>Emp ID</th>
        <th>Employee Name</th>
        <th style="text-align:right">Amount (₹)</th>
        <th>Remarks</th>
        <th style="text-align:center">Image</th>
      </tr>
    </thead>
    <tbody>
      @foreach($rows as $i => $row)
      <tr>
        <td style="color:var(--t3)">{{ ($rows->currentPage() - 1) * $rows->perPage() + $loop->iteration }}</td>
        <td style="color:var(--t2);white-space:nowrap">{{ !empty($row['expense_date']) ? \Carbon\Carbon::parse($row['expense_date'])->format('d M Y') : '—' }}</td>
        <td class="mono" style="font-size:11px">{{ $row['emp_id'] ?? '—' }}</td>
        <td style="font-weight:500">{{ $row['employee_name'] }}</td>
        <td style="text-align:right;font-weight:600">₹{{ number_format($row['amount'], 2) }}</td>
        <td style="color:var(--t2)">{{ $row['remarks'] ?? '—' }}</td>
        <td style="text-align:center">
          @if(!empty($row['image_filename']))
            <a href="{{ route('reimbursements.tracker.image.view', ['month' => $month, 'filename' => $row['image_filename']]) }}"
               target="_blank" class="btn sm" style="font-size:10px">🖼 View</a>
          @else
            <span style="color:var(--t3);font-size:11px">—</span>
          @endif
        </td>
      </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr style="background:var(--s2);font-weight:700">
        <td colspan="4" style="text-align:right;padding:10px 12px">Total:</td>
        <td style="text-align:right;padding:10px 12px;color:var(--grn);font-size:14px">₹{{ number_format($totalAmount, 2) }}</td>
        <td colspan="2"></td>
      </tr>
    </tfoot>
  </table>
  @if($rows->hasPages())
    <div style="padding:14px 18px;border-top:1px solid var(--b1)">{{ $rows->links() }}</div>
  @endif
  @elseif($search !== '')
  <div class="empty" style="padding:24px">
    <div class="ico">🔍</div>
    <p>No results for "{{ e($search) }}".</p>
    <a href="{{ route('reimbursements.report.view', $month) }}" class="btn acc" style="margin-top:10px">Clear Search</a>
  </div>
  @else
  <div class="empty">
    <div class="ico">📊</div>
    <p>No rows in this report.</p>
  </div>
  @endif
</div>

@endsection
