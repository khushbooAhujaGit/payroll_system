@extends('layouts.app')
@section('title', 'Leave Tracker Records')

@section('page_actions')
  <a href="{{ route('leave') }}" class="btn">← Leave Management</a>
  <button class="btn" onclick="om('tracker-add-modal')">+ Add Manually</button>
  <button class="btn acc" onclick="om('tracker-import-modal')">📤 Import / Re-import</button>
@endsection

@section('content')

@php $isDaily = ($view ?? 'spells') === 'daily'; @endphp

<div class="g4" style="margin-bottom:16px">
  <div class="sc">
    <div class="lbl">Total Leave Days (all)</div>
    <div class="val">{{ number_format($totalRecords) }}</div>
  </div>
  <div class="sc">
    <div class="lbl">{{ $isDaily ? 'Filtered Day Records' : 'Leave Spells (filtered)' }}</div>
    <div class="val">{{ number_format($isDaily ? $records->total() : $spells->total()) }}</div>
  </div>
  <div class="sc" style="grid-column:span 2">
    <div class="lbl">View</div>
    <div style="display:flex;gap:6px;margin-top:4px">
      <a href="{{ request()->fullUrlWithQuery(['view' => 'spells', 'page' => null]) }}"
         class="btn sm {{ !$isDaily ? 'acc' : '' }}">Spell Groups</a>
      <a href="{{ request()->fullUrlWithQuery(['view' => 'daily', 'page' => null]) }}"
         class="btn sm {{ $isDaily ? 'acc' : '' }}">Day-wise</a>
    </div>
    @if(!$isDaily)
    <div style="font-size:10px;color:var(--t3);margin-top:4px">
      Consecutive days (bridging weekends) shown as one row. Gaps = separate rows.
    </div>
    @endif
  </div>
</div>

{{-- Filters --}}
<form method="GET" action="{{ route('leave.tracker') }}" class="card"
      style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
  <input type="hidden" name="view" value="{{ $view ?? 'spells' }}">
  {{-- Preserve date range from spell drill-down links --}}
  @if(request('date_from'))<input type="hidden" name="date_from" value="{{ request('date_from') }}">@endif
  @if(request('date_to'))<input type="hidden" name="date_to" value="{{ request('date_to') }}">@endif
  <div class="fld" style="min-width:160px">
    <label>Employee ID / Name</label>
    <input type="text" name="emp_id" value="{{ request('emp_id') }}" placeholder="EMP code or name">
  </div>
  <div class="fld" style="min-width:130px">
    <label>Leave Type</label>
    <select name="leave_type">
      <option value="">All Types</option>
      @foreach($leaveTypes as $lt)
      <option value="{{ $lt }}" {{ request('leave_type') === $lt ? 'selected' : '' }}>{{ $lt }}</option>
      @endforeach
    </select>
  </div>
  <div class="fld" style="min-width:140px">
    <label>Month</label>
    <input type="month" name="month" value="{{ request('month') }}">
  </div>
  @if(request('date_from'))
  <div style="font-size:11px;color:var(--acc);padding-bottom:4px">
    📅 {{ \Carbon\Carbon::parse(request('date_from'))->format('d M') }}
    @if(request('date_from') !== request('date_to')) – {{ \Carbon\Carbon::parse(request('date_to'))->format('d M Y') }}
    @else{{ \Carbon\Carbon::parse(request('date_from'))->format(' Y') }}@endif
    <a href="{{ route('leave.tracker', ['view' => 'daily', 'emp_id' => request('emp_id'), 'leave_type' => request('leave_type')]) }}"
       style="margin-left:8px;color:var(--t3)">✕ clear dates</a>
  </div>
  @endif
  <button type="submit" class="btn acc">Filter</button>
  <a href="{{ route('leave.tracker', ['view' => $view ?? 'spells']) }}" class="btn">Reset</a>
</form>

<div class="card">

@if($isDaily)
  {{-- ── Day-wise individual records ─────────────────────────────────── --}}
  @if($records->isNotEmpty())
  <table style="font-size:12px">
    <thead>
      <tr>
        <th>Ref ID</th>
        <th>EMP Code</th>
        <th>Employee</th>
        <th>Date</th>
        <th>Leave Type</th>
        <th>Team Leader</th>
        <th>Reason Type</th>
        <th>Reason</th>
      </tr>
    </thead>
    <tbody>
      @foreach($records as $rec)
      <tr>
        <td class="mono" style="font-size:10px;color:var(--t3)">{{ $rec->leave_ref_id }}</td>
        <td class="mono" style="font-size:11px">{{ $rec->sheet_emp_id ?? '—' }}</td>
        <td>
          @if($rec->employee)
            {{ $rec->employee->first_name }} {{ $rec->employee->last_name }}
            <span style="font-size:10px;color:var(--t3)">({{ $rec->employee->employee_id }})</span>
          @elseif($rec->employee_name)
            {{ $rec->employee_name }}
            <span class="bx" style="font-size:9px;color:var(--yel);margin-left:4px">unmatched</span>
          @else
            <span style="color:var(--t3)">—</span>
          @endif
        </td>
        <td class="mono" style="white-space:nowrap">
          {{ \Carbon\Carbon::parse($rec->leave_date)->format('d M Y') }}
        </td>
        <td>
          <span class="bx" style="background:{{ strtoupper($rec->leave_type) === 'LOP' ? 'var(--red2)' : 'var(--acc2)' }};
                                   color:{{ strtoupper($rec->leave_type) === 'LOP' ? 'var(--red)' : 'var(--acc)' }}">
            {{ $rec->leave_type }}
          </span>
        </td>
        <td style="font-size:11px;color:var(--t2)">{{ $rec->team_leader_name ?? '—' }}</td>
        <td style="font-size:11px;color:var(--t2);text-transform:capitalize">
          {{ str_replace('_', ' ', $rec->reason_type ?? '—') }}
        </td>
        <td style="font-size:11px;color:var(--t2);max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
            title="{{ $rec->reason }}">{{ $rec->reason ?? '—' }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  <div style="margin-top:14px">{{ $records->links() }}</div>
  @else
  <div class="empty"><div class="ico">📋</div><p>No records found for this filter.</p></div>
  @endif

@else
  {{-- ── Spell Groups: consecutive days = one row ────────────────────── --}}
  @if($spells->isNotEmpty())
  <table style="font-size:12px">
    <thead>
      <tr>
        <th>Employee</th>
        <th>EMP Code</th>
        <th>Leave Type</th>
        <th style="text-align:center">Days</th>
        <th>Start Date</th>
        <th>End Date</th>
        <th>Team Leader</th>
        <th>Reason Type</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      @foreach($spells as $sp)
      @php
        $isSingle = $sp['start_date']->eq($sp['end_date']);
      @endphp
      <tr>
        <td>
          @if($sp['employee'])
            {{ $sp['employee']->first_name }} {{ $sp['employee']->last_name }}
          @else
            <span style="color:var(--t3);font-style:italic">{{ $sp['employee_name'] ?? '—' }}</span>
            <span style="font-size:9px;background:#fef3c7;color:#d97706;border-radius:3px;padding:1px 4px;margin-left:4px">unmatched</span>
          @endif
        </td>
        <td class="mono" style="font-size:11px">{{ $sp['sheet_emp_id'] ?? '—' }}</td>
        <td>
          <span class="bx" style="background:var(--acc2);color:var(--acc)">{{ $sp['leave_type'] }}</span>
        </td>
        <td class="mono" style="text-align:center;font-weight:700">
          {{ $sp['day_count'] }}
        </td>
        <td class="mono" style="white-space:nowrap">
          {{ $sp['start_date']->format('d M Y') }}
        </td>
        <td class="mono" style="white-space:nowrap;color:{{ $isSingle ? 'var(--t3)' : 'inherit' }}">
          {{ $isSingle ? '—' : $sp['end_date']->format('d M Y') }}
        </td>
        <td style="font-size:11px;color:var(--t2)">{{ $sp['team_leader_name'] ?? '—' }}</td>
        <td style="font-size:11px;color:var(--t2);text-transform:capitalize">
          {{ str_replace('_', ' ', $sp['reason_type'] ?? '—') }}
        </td>
        <td>
          <a href="{{ route('leave.tracker', ['view' => 'daily', 'emp_id' => $sp['sheet_emp_id'], 'leave_type' => $sp['leave_type'], 'date_from' => $sp['start_date']->format('Y-m-d'), 'date_to' => $sp['end_date']->format('Y-m-d')]) }}"
             class="btn sm" style="font-size:10px">{{ $sp['day_count'] }} Days →</a>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
  <div style="margin-top:14px">{{ $spells->links() }}</div>
  @else
  <div class="empty">
    <div class="ico">📋</div>
    <p>No tracker records found.</p>
    <p style="font-size:12px;color:var(--t3);margin-top:4px">Import the Leave Tracker xlsm file using the button above.</p>
  </div>
  @endif
@endif

</div>

{{-- Manual Add Modal --}}
<div class="modal" id="tracker-add-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Add Leave Record Manually</span>
      <button class="mc" onclick="cm('tracker-add-modal')">✕</button>
    </div>
    <div style="background:#fef3c7;border:1px solid #d97706;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:12px;color:#92400e">
      <strong>LOP Note:</strong> If an employee took leave without approval, select <strong>LOP</strong> as leave type.
      If a record already exists for that date, it will be replaced with LOP.
    </div>
    <form method="POST" action="{{ route('leave.tracker.store') }}">
      @csrf
      <div class="fg2">
        <div class="fld">
          <label>EMP ID (employee_id) *</label>
          <input type="text" name="sheet_emp_id" required placeholder="e.g. 202118007">
        </div>
        <div class="fld">
          <label>Leave Date *</label>
          <input type="date" name="leave_date" required>
        </div>
        <div class="fld">
          <label>Leave Type *</label>
          <select name="leave_type" required>
            <option value="">Select type</option>
            @foreach($leaveTypes as $lt)
            <option value="{{ $lt }}">{{ $lt }}</option>
            @endforeach
            <option value="LOP">LOP (Loss of Pay)</option>
            <option value="WFH">WFH</option>
          </select>
        </div>
        <div class="fld">
          <label>Employee Name</label>
          <input type="text" name="employee_name" placeholder="Optional — auto-filled if EMP matched">
        </div>
        <div class="fld">
          <label>Team Leader</label>
          <input type="text" name="team_leader_name">
        </div>
        <div class="fld">
          <label>Reason Type</label>
          <input type="text" name="reason_type" placeholder="e.g. Personal, Medical">
        </div>
        <div class="fld full">
          <label>Reason</label>
          <input type="text" name="reason" placeholder="Brief reason">
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('tracker-add-modal')">Cancel</button>
        <button type="submit" class="btn acc">Add Record</button>
      </div>
    </form>
  </div>
</div>

{{-- Import Modal --}}
<div class="modal" id="tracker-import-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Import Leave Tracker</span>
      <button class="mc" onclick="cm('tracker-import-modal')">✕</button>
    </div>
    <div style="background:var(--acc2);border:1px solid var(--acc);border-radius:8px;padding:10px 14px;margin-bottom:12px;display:flex;align-items:center;gap:10px">
      <span style="font-size:18px">📥</span>
      <div style="flex:1">
        <div style="font-weight:600;font-size:12px;margin-bottom:2px">Download Template</div>
        <div style="font-size:11px;color:var(--t3)">Fill in this Excel template and upload it below.</div>
      </div>
      <a href="{{ route('leave.tracker.template') }}" class="btn acc sm" style="white-space:nowrap">Get Template</a>
    </div>
    <div style="background:var(--acc2);border:1px solid var(--acc);border-radius:8px;
                padding:12px 14px;margin-bottom:16px;font-size:12px;line-height:1.6;color:var(--t2)">
      <strong>Upload the Leave Tracker .xlsm file</strong><br>
      Reads the <strong>"Mark Leave"</strong> sheet. If the file has a single sheet, any sheet name is accepted.<br>
      Each row = one day of leave. Re-importing is safe — existing records are updated, not duplicated.
    </div>
    <form method="POST" action="{{ route('leave.tracker.import') }}" enctype="multipart/form-data">
      @csrf
      <div class="fg2" style="margin-bottom:14px">
        <div class="fld">
          <label>Leave Month *</label>
          <input type="month" name="import_month" required value="{{ now()->format('Y-m') }}"
                 style="padding:9px 12px;border:1.5px solid var(--b1);border-radius:8px;background:#f9fafb;font-family:inherit;font-size:13px;color:var(--txt);outline:none;width:100%">
          <span style="font-size:10px;color:var(--t3)">Month the leave data belongs to</span>
        </div>
        <div class="fld">
          <label>Leave Tracker File (.xlsm / .xlsx / .xls) *</label>
          <input type="file" name="file" accept=".xlsm,.xlsx,.xls" required
            style="background:var(--s2);border:1.5px dashed var(--b2);border-radius:8px;padding:10px;cursor:pointer;width:100%">
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('tracker-import-modal')">Cancel</button>
        <button type="submit" class="btn acc">📤 Import</button>
      </div>
    </form>
  </div>
</div>

@endsection
