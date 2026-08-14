@extends('layouts.app')
@section('title', 'Leave Management')

@section('page_actions')
  <button class="btn acc" onclick="om('leave-modal')">+ Apply Leave</button>
  <a href="{{ route('leave.calendar') }}" class="btn">Calendar</a>
  @if($isLeaveAdmin)
  <button class="btn" onclick="om('tracker-import-modal')">📤 Import Tracker</button>
  @endif
  <a href="{{ route('leave.export') . '?' . http_build_query(request()->only('status')) }}" class="btn">⬇ Export</a>
  @if($isLeaveAdmin)
  <a href="{{ route('leave.tracker') }}" class="btn">View Full Tracker</a>
  @endif
@endsection

@section('content')

@if($myLeaveBalances->isNotEmpty())
{{-- My Leave Balances — the viewer's own CL/SL/CO/Maternity/Paternity etc.
     balances for the current year. --}}
<div class="card" style="margin-bottom:16px">
  <div class="ch"><div class="ct">My Leave Balances ({{ now()->year }})</div></div>
  <div style="padding:14px 18px;display:flex;flex-wrap:wrap;gap:12px">
    @foreach($myLeaveBalances as $balance)
      <div style="min-width:120px;padding:10px 14px;border:1px solid var(--b1);border-radius:8px">
        <div style="font-size:11px;color:var(--t3);text-transform:uppercase;letter-spacing:.04em;margin-bottom:3px">
          {{ $balance->leaveType?->name ?? '—' }}
        </div>
        <div style="font-size:18px;font-weight:700">{{ rtrim(rtrim(number_format($balance->available, 2), '0'), '.') }}</div>
      </div>
    @endforeach
  </div>
</div>
@endif

{{-- Status + date-range filter, plus (for a manager) the Self/Team tab
     switcher, combined into one card. Tabs use the same link-toggle
     pattern as the Spell Groups / Day-wise view on the tracker page —
     each tab keeps its own search/pagination state via the query string. --}}
<div class="card" style="margin-bottom:16px">
  @if($isManager && !$isLeaveAdmin)
  <div style="display:flex;gap:6px;margin-bottom:14px">
    <a href="{{ request()->fullUrlWithQuery(['applications_tab' => 'self']) }}"
       class="btn sm {{ $activeTab === 'self' ? 'acc' : '' }}">Self Application</a>
    <a href="{{ request()->fullUrlWithQuery(['applications_tab' => 'team']) }}"
       class="btn sm {{ $activeTab === 'team' ? 'acc' : '' }}">Team Application</a>
  </div>
  @endif
  <form method="GET" action="{{ route('leave') }}"
    style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end">
    @if($isManager && !$isLeaveAdmin)
      <input type="hidden" name="applications_tab" value="{{ $activeTab }}">
    @endif
    <div class="fld" style="min-width:140px">
      <label style="font-size:11px">Status</label>
      <select name="status" style="font-size:12px">
        <option value="">All</option>
        <option value="pending"  {{ request('status') === 'pending'  ? 'selected' : '' }}>Pending</option>
        <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
        <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
      </select>
    </div>
    <div class="fld" style="min-width:140px">
      <label style="font-size:11px">From</label>
      <input type="date" name="date_from" value="{{ request('date_from') }}" style="font-size:12px">
    </div>
    <div class="fld" style="min-width:140px">
      <label style="font-size:11px">To</label>
      <input type="date" name="date_to" value="{{ request('date_to') }}" style="font-size:12px">
    </div>
    <button type="submit" class="btn sm acc">Filter</button>
    <a href="{{ route('leave', ['applications_tab' => $activeTab]) }}" class="btn sm">Reset</a>
  </form>
</div>

@if($isManager && !$isLeaveAdmin)
  @if($activeTab === 'team')
    {{-- Combined list: formal applications + tracker-only rows (bulk
         imports/manual entries) for the team, one table. --}}
    @include('leave._applications_table', [
      'rows'         => $teamApplications,
      'title'        => 'Team Application',
      'searchParam'  => 'team_search',
      'searchValue'  => request('team_search'),
      'showBulk'     => true,
      'isAdmin'      => false,
      'viewerEmployeeId' => $viewerEmployeeId,
      'emptyText'    => 'No leave records found for your team.',
      'formId'       => 'team-applications-form',
      'tabValue'     => 'team',
    ])
  @else
    @include('leave._applications_table', [
      'rows'         => $selfApplications,
      'title'        => 'Self Application',
      'searchParam'  => 'self_search',
      'searchValue'  => request('self_search'),
      'showBulk'     => false,
      'isAdmin'      => false,
      'viewerEmployeeId' => $viewerEmployeeId,
      'emptyText'    => 'No leave records found.',
      'formId'       => 'self-applications-form',
      'tabValue'     => 'self',
    ])
  @endif
@else
  {{-- HR/Admin (unified, entity-wide, applications only) or a plain
       employee (self only, combined with their tracker-only rows). --}}
  @include('leave._applications_table', [
    'rows'         => $applications,
    'title'        => $isLeaveAdmin ? 'All Leave Applications' : 'My Applications',
    'searchParam'  => 'search',
    'searchValue'  => request('search'),
    'showBulk'     => $isLeaveAdmin,
    'isAdmin'      => $isAdmin ?? false,
    'viewerEmployeeId' => $viewerEmployeeId,
    'emptyText'    => 'No leave records found.',
    'formId'       => 'all-applications-form',
  ])
@endif

{{-- Apply Leave Modal --}}
<div class="modal" id="leave-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Apply Leave</span>
      <button class="mc" onclick="cm('leave-modal')">✕</button>
    </div>
    <form method="POST" action="{{ route('leave.apply') }}">
      @csrf
      @error('duplicate')
        <div class="ferr" style="margin-bottom:12px">{{ $message }}</div>
      @enderror
      <div class="fg2">
        <div class="fld full">
          <label>{{ $isLeaveAdmin ? 'Employee (leave blank for self)' : 'Applying For' }}</label>
          <select name="employee_id" @error('employee_id') class="inp-err" @enderror>
            @if($isLeaveAdmin)
              <option value="">— Self —</option>
              @foreach($employees as $emp)
              <option value="{{ $emp->id }}" {{ old('employee_id') == $emp->id ? 'selected' : '' }}>{{ $emp->full_name }} ({{ $emp->employee_id }})</option>
              @endforeach
            @else
              {{-- Own name + direct reports are already in the list — no
                   separate "Self" placeholder needed; default straight to
                   the logged-in employee. --}}
              @foreach($employees as $emp)
              <option value="{{ $emp->id }}" {{ old('employee_id', $viewerEmployeeId) == $emp->id ? 'selected' : '' }}>
                {{ $emp->full_name }} ({{ $emp->employee_id }}){{ $emp->id === $viewerEmployeeId ? ' — Me' : '' }}
              </option>
              @endforeach
            @endif
          </select>
          @error('employee_id') <div class="ferr">{{ $message }}</div> @enderror
        </div>
        <div class="fld full">
          <label class="req">Leave Type</label>
          <select name="leave_type_id" id="leave-type-select" required onchange="toggleHalfDayPeriod()"
            @error('leave_type_id') class="inp-err" @enderror>
            <option value="">Select type</option>
            @foreach($leaveTypes as $lt)
            <option value="{{ $lt->id }}" data-half-day="{{ str_contains($lt->name, 'Half-Day') ? '1' : '0' }}"
              {{ old('leave_type_id') == $lt->id ? 'selected' : '' }}>{{ $lt->name }} ({{ $lt->code }})</option>
            @endforeach
          </select>
          @error('leave_type_id') <div class="ferr">{{ $message }}</div> @enderror
        </div>
        <div class="fld full" id="leave-half-day-wrap" style="display:none">
          <label class="req">Which Half?</label>
          <select name="half_day_period" id="leave-half-day-period">
            <option value="first_half" {{ old('half_day_period') === 'first_half' ? 'selected' : '' }}>First Half</option>
            <option value="second_half" {{ old('half_day_period') === 'second_half' ? 'selected' : '' }}>Second Half</option>
          </select>
        </div>
        <div class="fld full">
          <label class="req">Duration</label>
          <select name="duration_type" id="leave-duration-type" onchange="toggleLeaveDuration()">
            <option value="single" {{ old('duration_type', 'single') === 'single' ? 'selected' : '' }}>Single Day</option>
            <option value="period" {{ old('duration_type') === 'period' ? 'selected' : '' }}>Period (date range)</option>
          </select>
        </div>
        <div class="fld full" id="leave-single-date-wrap">
          <label class="req">Date</label>
          <input type="date" name="single_date" id="leave-single-date" value="{{ old('single_date') }}" min="{{ $minApplyDate }}"
            @error('single_date') class="inp-err" @enderror>
          <span style="font-size:10px;color:var(--t3)">Cannot be more than 30 days in the past.</span>
          @error('single_date') <div class="ferr">{{ $message }}</div> @enderror
        </div>
        <div class="fld" id="leave-from-date-wrap" style="display:none">
          <label class="req">From Date</label>
          <input type="date" name="from_date" id="leave-from-date" value="{{ old('from_date') }}" min="{{ $minApplyDate }}"
            @error('from_date') class="inp-err" @enderror>
          @error('from_date') <div class="ferr">{{ $message }}</div> @enderror
        </div>
        <div class="fld" id="leave-to-date-wrap" style="display:none">
          <label class="req">To Date</label>
          <input type="date" name="to_date" id="leave-to-date" value="{{ old('to_date') }}"
            @error('to_date') class="inp-err" @enderror>
          @error('to_date') <div class="ferr">{{ $message }}</div> @enderror
        </div>
        <div class="fld full">
          <label>Reason</label>
          <textarea name="reason" rows="3" style="resize:vertical">{{ old('reason') }}</textarea>
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('leave-modal')">Cancel</button>
        <button type="submit" class="btn acc">Submit</button>
      </div>
    </form>
  </div>
</div>

{{-- Leave Tracker Import Modal --}}
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
      <strong>📁 Upload the Leave Tracker .xlsm file</strong><br>
      The importer reads the <strong>"Mark Leave"</strong> sheet automatically. If the file has a single sheet, any sheet name is accepted.<br>
      Columns used: <span class="mono" style="font-size:11px">ID · EMP ID · Name · Date · Leave Type</span><br>
      <span style="color:var(--t3)">Re-uploading is safe — existing records are updated, not duplicated.</span>
    </div>
    <form method="POST" action="{{ route('leave.tracker.import') }}" enctype="multipart/form-data">
      @csrf
      <div class="fld" style="margin-bottom:14px">
        <label>Leave Tracker File (.xlsm / .xlsx / .xls) *</label>
        <input type="file" name="file" accept=".xlsm,.xlsx,.xls" required
          style="background:var(--s2);border:1.5px dashed var(--b2);border-radius:8px;padding:14px;cursor:pointer;width:100%">
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('tracker-import-modal')">Cancel</button>
        <button type="submit" class="btn acc">📤 Import</button>
      </div>
    </form>
  </div>
</div>

{{-- Leave Reason Modal (shared — filled in by JS on click) --}}
<div class="modal" id="reason-modal">
  <div class="mb" style="width:420px">
    <div class="mh">
      <span class="mt">Leave Reason</span>
      <button class="mc" onclick="cm('reason-modal')">✕</button>
    </div>
    <p id="reason-modal-text" style="font-size:13px;color:var(--t2);line-height:1.6;white-space:pre-wrap"></p>
    <div class="mf">
      <button type="button" class="btn" onclick="cm('reason-modal')">Close</button>
    </div>
  </div>
</div>

@if($errors->any())
<script>document.addEventListener('DOMContentLoaded',()=>om('leave-modal'))</script>
@endif

<script>
  function toggleLeaveDuration() {
    var mode = document.getElementById('leave-duration-type').value;
    var singleWrap = document.getElementById('leave-single-date-wrap');
    var fromWrap = document.getElementById('leave-from-date-wrap');
    var toWrap = document.getElementById('leave-to-date-wrap');
    var singleInput = document.getElementById('leave-single-date');
    var fromInput = document.getElementById('leave-from-date');
    var toInput = document.getElementById('leave-to-date');

    var isSingle = mode === 'single';
    singleWrap.style.display = isSingle ? '' : 'none';
    fromWrap.style.display   = isSingle ? 'none' : '';
    toWrap.style.display     = isSingle ? 'none' : '';
    singleInput.required = isSingle;
    fromInput.required   = !isSingle;
    toInput.required     = !isSingle;
  }
  document.addEventListener('DOMContentLoaded', toggleLeaveDuration);

  function toggleHalfDayPeriod() {
    var select = document.getElementById('leave-type-select');
    var wrap   = document.getElementById('leave-half-day-wrap');
    var period = document.getElementById('leave-half-day-period');
    var opt    = select.options[select.selectedIndex];
    var isHalfDay = opt && opt.dataset.halfDay === '1';
    wrap.style.display = isHalfDay ? '' : 'none';
    period.required = isHalfDay;
  }
  document.addEventListener('DOMContentLoaded', toggleHalfDayPeriod);

  function showLeaveReason(cell) {
    document.getElementById('reason-modal-text').textContent = cell.dataset.reason;
    om('reason-modal');
  }
</script>
@endsection
