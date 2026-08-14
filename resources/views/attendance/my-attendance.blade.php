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

{{-- Weekly averages — flagged weeks get a Change Request button --}}
<div class="card" style="margin-bottom:16px">
  <div class="ch"><div class="ct">Weekly Average Hours</div></div>
  <table>
    <thead><tr><th>Week</th><th>Days With Attendance</th><th>Average Hours/Day</th><th>Status</th><th>Change Request</th></tr></thead>
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
            <button class="btn sm" onclick="om('change-request-{{ $week['week_start'] }}')">Raise Change Request</button>
          @endif
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>

{{-- Daily detail — shown first per spec ("all details to show to employees first") --}}
<div class="card" style="margin-bottom:16px">
  <div class="ch"><div class="ct">Daily Attendance</div></div>
  <table>
    <thead><tr><th>Date</th><th>Check In</th><th>Check Out</th><th>Hours</th></tr></thead>
    <tbody>
      @foreach($days as $day)
      <tr>
        <td>{{ \Carbon\Carbon::parse($day['date'])->format('d M Y (D)') }}</td>
        <td class="mono">{{ $day['check_in'] ?? '—' }}</td>
        <td class="mono">{{ $day['check_out'] ?? '—' }}</td>
        <td>{{ $day['hours'] !== null ? $day['hours'] . ' hrs' : '—' }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>

{{-- My past change requests --}}
<div class="card">
  <div class="ch"><div class="ct">My Change Requests</div></div>
  @if($myRequests->isNotEmpty())
  <table>
    <thead><tr><th>Week Of</th><th>Remark</th><th>Status</th><th>HR Remark</th></tr></thead>
    <tbody>
      @foreach($myRequests as $req)
      <tr>
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
  @else
  <div class="empty"><div class="ico">📋</div><p>No change requests yet.</p></div>
  @endif
</div>

{{-- One modal per flagged week --}}
@foreach($weeks as $week)
  @if($week['is_shortfall'] && !$week['existing_request'])
    <div class="modal" id="change-request-{{ $week['week_start'] }}">
      <div class="mb">
        <div class="mh">
          <span class="mt">Change Request — Week of {{ $week['label'] }}</span>
          <button class="mc" onclick="cm('change-request-{{ $week['week_start'] }}')">✕</button>
        </div>
        <form method="POST" action="{{ route('my-attendance.change_request') }}">
          @csrf
          <input type="hidden" name="week_start" value="{{ $week['week_start'] }}">
          <div class="fld full">
            <label class="req">Remark</label>
            <textarea name="remarks" rows="4" required placeholder="Explain why your hours were short this week…"></textarea>
          </div>
          <div class="mf">
            <button type="button" class="btn" onclick="cm('change-request-{{ $week['week_start'] }}')">Cancel</button>
            <button type="submit" class="btn acc">Submit</button>
          </div>
        </form>
      </div>
    </div>
  @endif
@endforeach

@endsection
