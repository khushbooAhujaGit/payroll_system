@extends('layouts.app')
@section('title', 'My Attendance')

@section('page_actions')
  <a href="{{ route('attendance.analytics') }}" class="btn">Analytics</a>
@endsection

@section('content')

@php
  $prevMonth = $monthStart->copy()->subMonth()->format('Y-m');
  $nextMonth = $monthStart->copy()->addMonth()->format('Y-m');
@endphp

<div class="card" style="margin-bottom:16px">
  <div class="ch">
    <div class="ct">{{ $monthStart->format('F Y') }}</div>
    <div style="display:flex;gap:6px">
      <a href="{{ route('my-attendance', ['month' => $prevMonth]) }}" class="btn sm">← Prev</a>
      <a href="{{ route('my-attendance') }}" class="btn sm">This Month</a>
      <a href="{{ route('my-attendance', ['month' => $nextMonth]) }}" class="btn sm">Next →</a>
    </div>
  </div>
</div>

{{-- Weekly averages — its own independent "Add Remark" trigger (a week
     whose average fell below 9h), separate from the per-day one below. --}}
<div class="card" style="margin-bottom:16px">
  <div class="ch"><div class="ct">Weekly Average Hours</div></div>
  <div class="tw">
  <table>
    <thead><tr><th>Week</th><th>Days With Attendance</th><th>Average Hours/Day</th><th>Status</th><th>Remark</th></tr></thead>
    <tbody>
      @foreach($weeks as $week)
      <tr>
        <td>{{ $week['label'] }}</td>
        <td>{{ $week['day_count'] }}</td>
        <td>{{ $week['average_hours'] !== null ? $week['average_hours'] . ' hrs' : '—' }}</td>
        <td>
          @if($week['is_shortfall'])
            <span class="bx r">Below 9h Average</span>
          @elseif($week['average_hours'] !== null)
            <span class="bx g">OK</span>
          @else
            <span class="bx gr">No Data</span>
          @endif
        </td>
        <td>
          @if($week['existing_request'])
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
              <span class="bx {{ $week['existing_request']->status === 'approved' ? 'g' : ($week['existing_request']->status === 'rejected' ? 'r' : 'y') }}">
                {{ ucfirst($week['existing_request']->status) }}
              </span>
              <span style="font-size:12px;color:var(--t2)">"{{ $week['existing_request']->remarks }}"</span>
            </div>
            @if($week['existing_request']->approval_remarks)
              <div style="font-size:11px;color:var(--t3);margin-top:3px">HR: {{ $week['existing_request']->approval_remarks }}</div>
            @endif
          @elseif($week['is_shortfall'])
            <button class="btn sm" style="font-size:15px;padding:4px 9px" title="Add a remark explaining this week's shortfall" onclick="om('change-request-week-{{ $week['week_start'] }}')">📝</button>
          @endif
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
  </div>
</div>

{{-- Daily detail — shown first per spec ("all details to show to employees
     first"); a day can have a Change Request raised against its Check In
     and/or Check Out independently — arriving before 10:00 needs no Check In
     correction, leaving after 19:00 needs no Check Out correction — separate
     from the week-level "Remark" action in the Weekly table above. --}}
<div class="card" style="margin-bottom:16px">
  <div class="ch"><div class="ct">Daily Attendance</div></div>
  <div class="tw">
  <table>
    <thead><tr><th>Date</th><th>Check In</th><th>Check Out</th><th>Hours</th><th>Change Request</th></tr></thead>
    <tbody>
      @foreach($days as $day)
      <tr>
        <td>{{ \Carbon\Carbon::parse($day['date'])->format('d M Y (D)') }}</td>
        <td class="mono">{{ $day['check_in'] ?? '—' }}</td>
        <td class="mono">{{ $day['check_out'] ?? '—' }}</td>
        <td>{{ $day['hours'] !== null ? $day['hours'] . ' hrs' : '—' }}</td>
        <td>
          @if($day['existing_request'])
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
              <span class="bx {{ $day['existing_request']->status === 'approved' ? 'g' : ($day['existing_request']->status === 'rejected' ? 'r' : 'y') }}">
                {{ ucfirst($day['existing_request']->status) }}
              </span>
              <span style="font-size:12px;color:var(--t2)">"{{ $day['existing_request']->remarks }}"</span>
            </div>
            @if($day['existing_request']->requested_check_in)
              <div style="font-size:11px;color:var(--t2);margin-top:3px">Check In → {{ substr($day['existing_request']->requested_check_in, 0, 5) }}</div>
            @endif
            @if($day['existing_request']->requested_check_out)
              <div style="font-size:11px;color:var(--t2);margin-top:3px">Check Out → {{ substr($day['existing_request']->requested_check_out, 0, 5) }}</div>
            @endif
            @if($day['existing_request']->approval_remarks)
              <div style="font-size:11px;color:var(--t3);margin-top:3px">HR: {{ $day['existing_request']->approval_remarks }}</div>
            @endif
          @elseif($day['can_request_day'])
            <button class="btn sm" style="font-size:15px;padding:4px 9px" title="Raise a change request for this day's Check In/Out" onclick="om('change-request-day-{{ $day['date'] }}')">📝</button>
          @endif
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
  </div>
</div>

{{-- My past change requests — Day vs Week distinguished by reason --}}
<div class="card">
  <div class="ch"><div class="ct">My Change Requests</div></div>
  @if($myRequests->isNotEmpty())
  <div class="tw">
  <table>
    <thead><tr><th>Type</th><th>Date</th><th>Remark</th><th>Status</th><th>HR Remark</th></tr></thead>
    <tbody>
      @foreach($myRequests as $req)
      <tr>
        <td><span class="bx gr">{{ $req->reason === 'weekly_hours_shortfall' ? 'Week' : 'Day' }}</span></td>
        <td>{{ $req->attendance_date->format('d M Y') }}</td>
        <td>{{ $req->remarks }}</td>
        <td>
          <span class="bx {{ $req->status === 'approved' ? 'g' : ($req->status === 'rejected' ? 'r' : 'y') }}">
            {{ ucfirst($req->status) }}
          </span>
        </td>
        <td>{{ $req->approval_remarks ?: '—' }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  </div>
  @else
  <div class="empty"><div class="ico">📋</div><p>No change requests yet.</p></div>
  @endif
</div>

{{-- One modal per eligible week (shortfall, no request yet) --}}
@foreach($weeks as $week)
  @if($week['is_shortfall'] && !$week['existing_request'])
    <div class="modal" id="change-request-week-{{ $week['week_start'] }}">
      <div class="mb">
        <div class="mh">
          <span class="mt">Change Request — Week of {{ $week['label'] }}</span>
          <button class="mc" onclick="cm('change-request-week-{{ $week['week_start'] }}')">✕</button>
        </div>
        <form method="POST" action="{{ route('my-attendance.change_request') }}">
          @csrf
          <input type="hidden" name="request_type" value="week">
          <input type="hidden" name="attendance_date" value="{{ $week['week_start'] }}">
          <div class="fld full">
            <label class="req">Remark</label>
            <textarea name="remarks" rows="4" required placeholder="Explain why your hours were short this week…"></textarea>
          </div>
          <div class="mf">
            <button type="button" class="btn" onclick="cm('change-request-week-{{ $week['week_start'] }}')">Cancel</button>
            <button type="submit" class="btn acc">Submit</button>
          </div>
        </form>
      </div>
    </div>
  @endif
@endforeach

{{-- One modal per eligible day (no request yet, and the day's off standard
     hours on at least one side) — both Check In and Check Out are always
     offered once eligible, so the employee can flag either or both. --}}
@foreach($days as $day)
  @if($day['can_request_day'] && !$day['existing_request'])
    <div class="modal" id="change-request-day-{{ $day['date'] }}">
      <div class="mb">
        <div class="mh">
          <span class="mt">Change Request — {{ \Carbon\Carbon::parse($day['date'])->format('d M Y (D)') }}</span>
          <button class="mc" onclick="cm('change-request-day-{{ $day['date'] }}')">✕</button>
        </div>
        <form method="POST" action="{{ route('my-attendance.change_request') }}" onsubmit="return validateDayRequest(this)">
          @csrf
          <input type="hidden" name="request_type" value="day">
          <input type="hidden" name="attendance_date" value="{{ $day['date'] }}">
          <div class="fld full">
            <label class="req">What would you like to correct?</label>
            <div style="display:flex;flex-direction:column;gap:10px;margin-top:6px">
              <div>
                <label style="display:flex;align-items:center;gap:8px;font-weight:400;cursor:pointer">
                  <input type="checkbox" name="request_check_in" value="1"
                    onchange="toggleDayTimeField(this, 'ci-{{ $day['date'] }}')">
                  Check In <span style="color:var(--t3)">(currently {{ substr($day['check_in'], 0, 5) }})</span>
                </label>
                <input type="time" id="ci-{{ $day['date'] }}" name="requested_check_in"
                  style="display:none;margin-top:4px" disabled>
              </div>
              <div>
                <label style="display:flex;align-items:center;gap:8px;font-weight:400;cursor:pointer">
                  <input type="checkbox" name="request_check_out" value="1"
                    onchange="toggleDayTimeField(this, 'co-{{ $day['date'] }}')">
                  Check Out <span style="color:var(--t3)">(currently {{ substr($day['check_out'], 0, 5) }})</span>
                </label>
                <input type="time" id="co-{{ $day['date'] }}" name="requested_check_out"
                  style="display:none;margin-top:4px" disabled>
              </div>
            </div>
          </div>
          <div class="fld full">
            <label class="req">Reason</label>
            <textarea name="remarks" rows="4" required placeholder="Explain why this time needs correcting…"></textarea>
          </div>
          <div class="mf">
            <button type="button" class="btn" onclick="cm('change-request-day-{{ $day['date'] }}')">Cancel</button>
            <button type="submit" class="btn acc">Submit</button>
          </div>
        </form>
      </div>
    </div>
  @endif
@endforeach

@push('scripts')
<script>
function toggleDayTimeField(checkbox, inputId) {
  const el = document.getElementById(inputId);
  if (checkbox.checked) {
    el.style.display = '';
    el.disabled = false;
    el.required = true;
  } else {
    el.style.display = 'none';
    el.disabled = true;
    el.required = false;
    el.value = '';
  }
}

function validateDayRequest(form) {
  const ciChecked = form.querySelector('input[name="request_check_in"]')?.checked;
  const coChecked = form.querySelector('input[name="request_check_out"]')?.checked;
  if (!ciChecked && !coChecked) {
    Swal.fire('Select at least one', 'Tick Check In, Check Out, or both to say what you want corrected.', 'warning');
    return false;
  }
  return true;
}
</script>
@endpush

@endsection
