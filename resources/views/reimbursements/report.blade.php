@php $monthLabel = \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y'); @endphp
@extends('layouts.app')
@section('title', 'Reimbursement Report')

@section('page_actions')
  <a href="{{ route('reimbursements.report.saved') }}" class="btn">🗂 Saved Reports</a>
  <a href="{{ route('reimbursements.tracker', ['month' => $month]) }}" class="btn">← Tracker</a>
  @if($isLocked)
    {{-- Download --}}
    <form method="POST" action="{{ route('reimbursements.report.download') }}" style="display:inline">
      @csrf <input type="hidden" name="month" value="{{ $month }}">
      <button type="submit" class="btn">📥 Download Excel</button>
    </form>
    {{-- Re-lock (regenerate snapshot from current approved rows) --}}
    <form method="POST" action="{{ route('reimbursements.report.generate') }}" style="display:inline">
      @csrf <input type="hidden" name="month" value="{{ $month }}">
      <button type="button" class="btn acc"
        onclick="swConfirm(this.closest('form'), 'Re-save report?',
          'This will regenerate the snapshot for {{ $monthLabel }} from current approved rows, replacing the existing saved report.',
          'question', 'Yes, Re-save', '#2563eb')">
        🔄 Re-save Report
      </button>
    </form>
    {{-- Unlock --}}
    <form method="POST" action="{{ route('reimbursements.report.unlock') }}" style="display:inline">
      @csrf <input type="hidden" name="month" value="{{ $month }}">
      <button type="button" class="btn"
        onclick="swConfirm(this.closest('form'), 'Unlock report?',
          'The saved snapshot for {{ $monthLabel }} will be removed.',
          'question', 'Unlock', '#d97706')">
        🔓 Unlock
      </button>
    </form>
  @else
    <form method="POST" action="{{ route('reimbursements.report.generate') }}" style="display:inline">
      @csrf <input type="hidden" name="month" value="{{ $month }}">
      <button type="submit" class="btn acc">🔒 Save Report</button>
    </form>
  @endif
@endsection

@section('content')

{{-- Month — a real navigation, since it changes the Save/Re-save/Unlock
     header actions and the locked-status banner too. --}}
<form method="GET" action="{{ route('reimbursements.report') }}"
      class="card" style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
  <div class="fld" style="min-width:160px">
    <label>Month</label>
    <input type="month" name="month" value="{{ $month }}" required>
  </div>
  <button type="submit" class="btn acc">View</button>
  @if($isLocked)
    <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--t2);padding-bottom:2px">
      🔒 <strong>Saved</strong>
      @if($lockedAt)
        <span style="color:var(--t3)">on {{ $lockedAt->format('d M Y, h:i A') }}</span>
      @endif
    </div>
  @else
    <div style="display:flex;align-items:center;font-size:12px;color:var(--yel);padding-bottom:2px">
      ⚠ No approved data for this month
    </div>
  @endif
</form>

{{-- Search — narrows the already-loaded month, so this one is AJAX --}}
<form method="GET" action="{{ route('reimbursements.report') }}" data-ajax-filter
      class="card" style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
  <input type="hidden" name="month" value="{{ $month }}">
  <div class="fld" style="min-width:220px">
    <label>Search</label>
    <input type="text" name="search" value="{{ $search }}" placeholder="Emp ID or Employee Name…">
  </div>
  <button type="submit" class="btn acc">Search</button>
  @if($search !== '')
    <a href="{{ route('reimbursements.report', ['month' => $month]) }}" class="btn" data-ajax-reset>✕ Clear</a>
  @endif
</form>

<div id="ajax-results">
{{-- Summary --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:16px">
  <div class="card" style="padding:14px 18px">
    <div style="font-size:11px;color:var(--t3);text-transform:uppercase;letter-spacing:.05em">Approved Rows</div>
    <div style="font-size:26px;font-weight:700;margin-top:4px">{{ $rowCount }}</div>
  </div>
  <div class="card" style="padding:14px 18px">
    <div style="font-size:11px;color:var(--t3);text-transform:uppercase;letter-spacing:.05em">Total Amount</div>
    <div style="font-size:26px;font-weight:700;color:var(--grn);margin-top:4px">₹{{ number_format($totalAmount, 2) }}</div>
  </div>
  <div class="card" style="padding:14px 18px">
    <div style="font-size:11px;color:var(--t3);text-transform:uppercase;letter-spacing:.05em">Status</div>
    <div style="font-size:18px;font-weight:700;margin-top:6px">
      @if($isLocked)
        <span style="color:var(--grn)">🔒 Saved</span>
      @else
        <span style="color:var(--t3)">— No data</span>
      @endif
    </div>
  </div>
</div>

@if($rowCount === 0 && $search === '')
<div class="card">
  <div class="empty">
    <div class="ico">📊</div>
    <p>No approved reimbursement rows for {{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}.</p>
    <p style="font-size:12px;color:var(--t3);margin-top:4px">Upload data and approve rows in the tracker first.</p>
    <a href="{{ route('reimbursements.tracker', ['month' => $month]) }}" class="btn acc" style="margin-top:10px">Go to Tracker →</a>
  </div>
</div>
@else {{-- #ajax-results: no-rows branch --}}

{{-- Info Banner --}}
<div class="card" style="margin-bottom:14px;padding:12px 16px;font-size:12px;color:var(--t2);line-height:1.6">
  @if($isLocked)
    🔒 <strong>Report saved</strong> on {{ $lockedAt?->format('d M Y, h:i A') }}.
    This page always shows <strong>live approved rows</strong>.
    <a href="{{ route('reimbursements.report.view', $month) }}" style="color:var(--acc)">View frozen snapshot →</a>
  @else
    ⚡ <strong>Live view</strong> — showing current approved rows. No saved snapshot yet for this month.
  @endif
</div>

{{-- Report Table --}}
<div class="card" style="overflow-x:auto">
  <div class="ch" style="display:flex;justify-content:space-between;align-items:center">
    <div class="ct">
      {{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y') }} — Live Approved Rows
      ({{ number_format($rows->total()) }}{{ $search !== '' ? ', filtered: "' . e($search) . '"' : '' }})
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
      @foreach($rows as $row)
      <tr>
        <td style="color:var(--t3)">{{ ($rows->currentPage() - 1) * $rows->perPage() + $loop->iteration }}</td>
        <td style="color:var(--t2);white-space:nowrap">{{ $row->expense_date ? \Carbon\Carbon::parse($row->expense_date)->format('d M Y') : '—' }}</td>
        <td class="mono" style="font-size:11px">{{ $row->emp_id ?? '—' }}</td>
        <td style="font-weight:500">{{ $row->employee_name }}</td>
        <td style="text-align:right;font-weight:600">₹{{ number_format($row->amount, 2) }}</td>
        <td style="color:var(--t2)">{{ $row->remarks ?? '—' }}</td>
        <td style="text-align:center">
          @if($row->image_filename)
            <a href="{{ route('reimbursements.tracker.image.view', ['month' => $month, 'filename' => $row->image_filename]) }}"
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
        <td colspan="4" style="text-align:right;padding:8px 12px">Total Approved:</td>
        <td style="text-align:right;padding:8px 12px;color:var(--grn)">₹{{ number_format($totalAmount, 2) }}</td>
        <td colspan="2"></td>
      </tr>
    </tfoot>
  </table>
  @if($rows->hasPages())
    <div style="padding:14px 18px;border-top:1px solid var(--b1)">{{ $rows->links() }}</div>
  @endif
  @if($search !== '' && $rows->total() === 0)
    <div class="empty" style="padding:24px">
      <div class="ico">🔍</div>
      <p>No results for "{{ e($search) }}".</p>
      <a href="{{ route('reimbursements.report', ['month' => $month]) }}" class="btn acc" style="margin-top:10px" data-ajax-reset>Clear Search</a>
    </div>
  @endif
</div>
@endif
</div> {{-- #ajax-results --}}

@endsection
