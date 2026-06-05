@extends('layouts.app')
@section('title', $from ? 'Attendance Report — ' . $from->format('F Y') : 'Attendance Report')

@section('page_actions')
  <a href="{{ route('reports') }}" class="btn">← Reports</a>
  @if($from && count($rows))
  <button class="btn" onclick="om('att-reupload-modal')">📤 Re-upload Approvals</button>
  <a href="{{ route('reports.attendance.export', array_filter(['month' => $monthStr, 'emp_id' => $empFilter])) }}"
     class="btn acc">📥 Export Excel</a>
  @endif
@endsection

@section('content')

{{-- Filter Form --}}
<form method="GET" action="{{ route('reports.attendance') }}" class="card"
      style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
  <div class="fld" style="min-width:160px">
    <label>Month *</label>
    <input type="month" name="month" value="{{ $monthStr ?? now()->format('Y-m') }}" required>
  </div>
  <div class="fld" style="min-width:220px">
    <label>Employee (optional)</label>
    <select name="emp_id">
      <option value="">All Employees</option>
      @foreach($allEmployees as $e)
      <option value="{{ $e->employee_id }}" {{ $empFilter == $e->employee_id ? 'selected' : '' }}>
        {{ $e->full_name }} ({{ $e->employee_id }})
      </option>
      @endforeach
    </select>
  </div>
  <button type="submit" class="btn acc">Generate</button>
  @if($monthStr)
  <a href="{{ route('reports.attendance') }}?month={{ $monthStr }}" class="btn">Reset Filter</a>
  @endif
</form>

@if(!$monthStr)
{{-- No month selected --}}
<div class="card">
  <div class="empty">
    <div class="ico">📊</div>
    <p>Select a month above to generate the attendance report.</p>
  </div>
</div>

@elseif(empty($rows))
<div class="card">
  <div class="empty">
    <div class="ico">🕐</div>
    <p>No attendance data found for {{ $from->format('F Y') }}.</p>
    <p style="font-size:12px;color:var(--t3)">Make sure attendance has been imported for this period.</p>
  </div>
</div>

@else

{{-- Summary row --}}
@php
  $totalRows     = count($rows);
  $presentCount  = collect($rows)->filter(fn($r) => $r['punch_in_type'] === 'On Time' || $r['punch_in_type'] === 'LateTimeIn')->count();
  $lateCount     = collect($rows)->filter(fn($r) => $r['punch_in_type'] === 'LateTimeIn')->count();
  $missingIn     = collect($rows)->filter(fn($r) => $r['punch_in_type'] === 'Punch Missing' && !$r['has_leave'] && !$r['is_weekend'])->count();
  $leaveCount    = collect($rows)->filter(fn($r) => $r['has_leave'])->count();
@endphp
<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;font-size:12px">
  <span class="bx" style="background:var(--s2)">Total Rows: <strong>{{ $totalRows }}</strong></span>
  <span class="bx" style="background:#f0fdf4;color:#16a34a">Present / On Time: <strong>{{ $presentCount - $lateCount }}</strong></span>
  <span class="bx" style="background:#fff7ed;color:#c05621">Late In: <strong>{{ $lateCount }}</strong></span>
  <span class="bx" style="background:#fef2f2;color:#dc2626">Punch Missing: <strong>{{ $missingIn }}</strong></span>
  <span class="bx" style="background:#eff6ff;color:#2563eb">Leave Days: <strong>{{ $leaveCount }}</strong></span>
</div>

{{-- Legend --}}
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;font-size:11px;align-items:center">
  <span style="color:var(--t3)">Legend:</span>
  @foreach([
    ['On Time',       '#16a34a','#f0fdf4'],
    ['LateTimeIn',    '#c05621','#fff7ed'],
    ['Punch Missing', '#dc2626','#fef2f2'],
    ['Leave',         '#2563eb','#eff6ff'],
    ['Weekend',       '#6b7280','#f3f4f6'],
  ] as [$label,$color,$bg])
  <span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:4px;background:{{ $bg }};color:{{ $color }};font-weight:600">{{ $label }}</span>
  @endforeach
</div>

{{-- Table --}}
<div class="card" style="padding:0;overflow:hidden">
  <div style="overflow-x:auto;max-height:75vh;overflow-y:auto">
    <table style="font-size:11px;min-width:1500px;border-collapse:collapse">
      {{-- Explicit background on every th overrides the global sticky-th CSS background --}}
      @php $thStyle = 'padding:8px 10px;white-space:nowrap;background:#3b7de8;color:#fff;border-right:1px solid rgba(255,255,255,.2);text-align:left'; @endphp
      <thead>
        <tr>
          <th style="{{ $thStyle }}">Month</th>
          <th style="{{ $thStyle }}">Date</th>
          <th style="{{ $thStyle }}">Day Name</th>
          <th style="{{ $thStyle }}">New Emp ID</th>
          <th style="{{ $thStyle }}">Employee Name</th>
          <th style="{{ $thStyle }};text-align:center">TimeIn</th>
          <th style="{{ $thStyle }};text-align:center">TimeOut</th>
          <th style="{{ $thStyle }}">Punch In Type</th>
          <th style="{{ $thStyle }}">Punch Out Type</th>
          <th style="{{ $thStyle }};text-align:center">Clocked Time</th>
          <th style="{{ $thStyle }}">CT_Remark</th>
          <th style="{{ $thStyle }}">Leave Type</th>
          <th style="{{ $thStyle }}">Reason For Leave</th>
          <th style="{{ $thStyle }};text-align:center">Punch Deviation</th>
          <th style="{{ $thStyle }};text-align:center">Punch In Approval</th>
          <th style="{{ $thStyle }};text-align:center;border-right:none">Punch Out Approval</th>
        </tr>
      </thead>
      <tbody>
        @foreach($rows as $i => $r)
        @php
          if ($r['is_weekend']) {
              $rowBg = '#f3f4f6';
          } elseif ($r['has_leave']) {
              $rowBg = '#eff6ff';
          } elseif ($i % 2 === 0) {
              $rowBg = '#f4fce8';
          } else {
              $rowBg = '#ffffff';
          }

          $piColor = match($r['punch_in_type']) {
              'On Time'       => '#16a34a',
              'LateTimeIn'    => '#c05621',
              'Punch Missing' => '#dc2626',
              default         => 'inherit',
          };
          $poColor = $r['punch_out_type'] === 'Punch Missing' ? '#dc2626' : '#16a34a';
        @endphp
        <tr style="background:{{ $rowBg }};border-bottom:1px solid #e5e7eb">
          <td style="padding:5px 10px;white-space:nowrap;color:var(--t3)">{{ $r['month'] }}</td>
          <td style="padding:5px 10px;white-space:nowrap;font-weight:500">{{ $r['date'] }}</td>
          <td style="padding:5px 10px;white-space:nowrap;color:var(--t2)">{{ $r['day_name'] }}</td>
          <td style="padding:5px 10px;white-space:nowrap;font-family:monospace">{{ $r['emp_id'] }}</td>
          <td style="padding:5px 10px;white-space:nowrap;font-weight:500">{{ $r['emp_name'] }}</td>
          <td style="padding:5px 10px;text-align:center;white-space:nowrap;font-family:monospace">
            {{ $r['time_in'] ?: '—' }}
          </td>
          <td style="padding:5px 10px;text-align:center;white-space:nowrap;font-family:monospace">
            {{ $r['time_out'] ?: '—' }}
          </td>
          <td style="padding:5px 10px;white-space:nowrap;font-weight:600;color:{{ $piColor }}">
            {{ $r['punch_in_type'] }}
          </td>
          <td style="padding:5px 10px;white-space:nowrap;font-weight:600;color:{{ $poColor }}">
            {{ $r['punch_out_type'] }}
          </td>
          <td style="padding:5px 10px;text-align:center;white-space:nowrap;font-family:monospace;font-size:10px">
            {{ $r['clocked_time'] ?: '—' }}
          </td>
          <td style="padding:5px 10px;white-space:nowrap;font-size:10px;color:{{ $r['ct_remark'] === '>9:00 Hours' ? '#16a34a' : ($r['ct_remark'] === '< 9:00 Hours' ? '#c05621' : 'var(--t3)') }}">
            {{ $r['ct_remark'] }}
          </td>
          <td style="padding:5px 10px;white-space:nowrap">
            @if($r['leave_type'])
            <span class="bx" style="font-size:10px;background:#dbeafe;color:#1d4ed8">{{ $r['leave_type'] }}</span>
            @else
            <span style="color:var(--t3)">—</span>
            @endif
          </td>
          <td style="padding:5px 10px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--t2);font-size:10px"
              title="{{ $r['reason_for_leave'] }}">
            {{ $r['reason_for_leave'] ?: '—' }}
          </td>
          <td style="padding:5px 10px;text-align:center;white-space:nowrap;font-size:10px;color:{{ $r['punch_deviation'] ? '#c05621' : 'var(--t3)' }}">
            {{ $r['punch_deviation'] ?: '—' }}
          </td>
          {{-- Punch In Approval dropdown --}}
          <td style="padding:3px 6px;text-align:center">
            @if($r['has_punch'])
            <select class="appr-sel" data-emp="{{ $r['internal_emp_id'] }}" data-date="{{ $r['date_ymd'] }}" data-field="punch_in_approval"
                    style="font-size:10px;padding:2px 4px;border-radius:4px;border:1px solid #d1d5db;cursor:pointer;
                           background:{{ $r['punch_in_approval']==='not_approved' ? '#fef2f2' : '#f0fdf4' }};
                           color:{{ $r['punch_in_approval']==='not_approved' ? '#dc2626' : '#16a34a' }}">
              <option value="approved"     {{ $r['punch_in_approval'] !== 'not_approved' ? 'selected' : '' }}>Approved</option>
              <option value="not_approved" {{ $r['punch_in_approval'] === 'not_approved' ? 'selected' : '' }}>Not Approved</option>
            </select>
            @else
            <span style="color:var(--t3);font-size:10px">—</span>
            @endif
          </td>
          {{-- Punch Out Approval dropdown --}}
          <td style="padding:3px 6px;text-align:center">
            @if($r['has_punch'])
            <select class="appr-sel" data-emp="{{ $r['internal_emp_id'] }}" data-date="{{ $r['date_ymd'] }}" data-field="punch_out_approval"
                    style="font-size:10px;padding:2px 4px;border-radius:4px;border:1px solid #d1d5db;cursor:pointer;
                           background:{{ $r['punch_out_approval']==='not_approved' ? '#fef2f2' : '#f0fdf4' }};
                           color:{{ $r['punch_out_approval']==='not_approved' ? '#dc2626' : '#16a34a' }}">
              <option value="approved"     {{ $r['punch_out_approval'] !== 'not_approved' ? 'selected' : '' }}>Approved</option>
              <option value="not_approved" {{ $r['punch_out_approval'] === 'not_approved' ? 'selected' : '' }}>Not Approved</option>
            </select>
            @else
            <span style="color:var(--t3);font-size:10px">—</span>
            @endif
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  <div style="padding:10px 14px;font-size:11px;color:var(--t3);border-top:1px solid var(--b1)">
    {{ count($rows) }} rows · {{ $from->format('F Y') }} · Standard start time: 10:00 AM
    @if($empFilter) · Filtered: {{ $empFilter }} @endif
  </div>
</div>

@endif {{-- end rows --}}

{{-- Re-upload Approvals Modal --}}
<div class="modal" id="att-reupload-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">📤 Re-upload Attendance Approvals</span>
      <button class="mc" onclick="cm('att-reupload-modal')">✕</button>
    </div>
    <div style="background:#fefce8;border:1px solid #fde047;border-radius:6px;padding:8px 12px;margin-bottom:12px;font-size:11px;color:#854d0e;line-height:1.6">
      <strong>Only these two columns are updated from the file — all others are ignored:</strong><br>
      ✏ <strong>Punch In Approval</strong> &nbsp;·&nbsp; ✏ <strong>Punch Out Approval</strong><br>
      <span style="font-size:10px;color:#92400e">
        Accepted values: <strong>Approved</strong> or <strong>Not Approved</strong> (use the dropdown in the Excel file).
        Rows are matched by <strong>New Emp ID + Date</strong>. Month must match {{ $monthStr ? \Carbon\Carbon::createFromFormat('Y-m',$monthStr)->format('F Y') : 'the selected month' }}.
      </span>
    </div>
    <form method="POST" action="{{ route('reports.attendance.reupload') }}" enctype="multipart/form-data">
      @csrf
      <input type="hidden" name="expected_month" value="{{ $monthStr }}">
      <div class="fld" style="margin-bottom:14px">
        <label class="req">Edited Excel File (.xlsx)</label>
        <input type="file" name="file" accept=".xlsx,.xls" required
          style="background:var(--s2);border:1.5px dashed var(--b2);border-radius:8px;padding:14px;cursor:pointer;width:100%">
        <div style="font-size:11px;color:var(--t3);margin-top:5px">
          Upload the attendance Excel that was exported and edited. The <strong>New Emp ID</strong>, <strong>Date</strong>, and the two approval columns must be intact.
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('att-reupload-modal')">Cancel</button>
        <button type="submit" class="btn acc">📤 Update Approvals</button>
      </div>
    </form>
  </div>
</div>

@endsection

@push('scripts')
<script>
// ── Attendance Approval dropdowns — AJAX save on change ─────────────────────
(function () {
  const APPROVE_URL = '{{ route("reports.attendance.approve") }}';
  const CSRF        = '{{ csrf_token() }}';

  const BG  = { approved: '#f0fdf4', not_approved: '#fef2f2', '': '#fffbeb' };
  const CLR = { approved: '#16a34a', not_approved: '#dc2626', '': '#92400e' };

  function styleSelect(sel) {
    const v = sel.value;
    sel.style.background = BG[v]  ?? '#fffbeb';
    sel.style.color      = CLR[v] ?? '#92400e';
  }

  const saving = new WeakMap();

  document.addEventListener('change', async function (e) {
    const sel = e.target;
    if (!sel.classList.contains('appr-sel')) return;
    if (saving.get(sel)) return;
    saving.set(sel, true);
    sel.style.opacity = '0.5';

    try {
      const res = await fetch(APPROVE_URL, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': CSRF,
          'Accept':       'application/json',
        },
        body: JSON.stringify({
          employee_id:     sel.dataset.emp,
          attendance_date: sel.dataset.date,
          [sel.dataset.field]: sel.value || null,
        }),
      });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      styleSelect(sel);
    } catch (err) {
      console.error('Approval save failed', err);
      sel.value = sel.dataset.prev ?? '';
      styleSelect(sel);
      Swal.fire({ icon: 'error', title: 'Save Failed', text: 'Failed to save approval. Please try again.', confirmButtonColor: '#dc2626' });
    } finally {
      sel.style.opacity = '1';
      saving.set(sel, false);
    }
  });

  // Cache previous value before the dropdown opens (for rollback on error)
  document.addEventListener('mousedown', function (e) {
    if (e.target.classList.contains('appr-sel')) {
      e.target.dataset.prev = e.target.value;
    }
  });

  // Style all dropdowns on page load
  document.querySelectorAll('.appr-sel').forEach(styleSelect);
})();
</script>
@endpush
