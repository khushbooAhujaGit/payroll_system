@extends('layouts.app')
@section('title', 'Attendance Analytics')

@section('content')

@php
  $prevMonth = $monthStart->copy()->subMonth()->format('Y-m');
  $nextMonth = $monthStart->copy()->addMonth()->format('Y-m');
@endphp

@if($isLeaveAdmin && $companies->count() > 1)
<form method="GET" action="{{ route('attendance.analytics') }}" class="card"
  style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
  <input type="hidden" name="month" value="{{ $month }}">
  <input type="hidden" name="search" value="{{ $search }}">
  <div class="fld" style="min-width:200px">
    <label>Entity / Company</label>
    <select name="company_id" onchange="this.form.submit()">
      <option value="all" {{ $companyId === 'all' ? 'selected' : '' }}>All Companies</option>
      @foreach($companies as $ent)
        <option value="{{ $ent->id }}" {{ $companyId == $ent->id ? 'selected' : '' }}>{{ $ent->name }}</option>
      @endforeach
    </select>
  </div>
</form>
@endif

{{-- Month/Year + employee search filter --}}
<form method="GET" action="{{ route('attendance.analytics') }}" class="card"
  style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
  @if($isLeaveAdmin)
    <input type="hidden" name="company_id" value="{{ $companyId }}">
  @endif
  <div class="fld" style="min-width:160px">
    <label>Month / Year</label>
    <input type="month" name="month" value="{{ $month }}">
  </div>
  <div class="fld" style="min-width:220px">
    <label>Search</label>
    <input type="text" name="search" value="{{ $search }}" placeholder="Name or Employee ID…">
  </div>
  <button type="submit" class="btn acc">Go</button>
  @if($search)
    <a href="{{ route('attendance.analytics', array_filter(['month' => $month, 'company_id' => $isLeaveAdmin ? $companyId : null])) }}" class="btn sm">Clear Search</a>
  @endif
</form>

<div class="card" style="margin-bottom:16px">
  <div class="ch">
    <div class="ct">{{ $monthStart->format('F Y') }} — {{ $isLeaveAdmin ? 'All Employees' : ($isManager ? 'My Team' : 'My Attendance') }}</div>
    <div style="display:flex;gap:6px">
      <a href="{{ route('attendance.analytics', ['month' => $prevMonth]) }}" class="btn sm">← Prev</a>
      <a href="{{ route('attendance.analytics') }}" class="btn sm">This Month</a>
      <a href="{{ route('attendance.analytics', ['month' => $nextMonth]) }}" class="btn sm">Next →</a>
    </div>
  </div>

  @if($rows->isNotEmpty())
  <table>
    <thead>
      <tr>
        <th>Employee</th>
        <th>Average Monthly Attendance</th>
        <th>Days With Attendance</th>
        <th>CL</th>
        <th>SL</th>
        <th>CO</th>
        <th>Other Leave Types</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      @foreach($rows as $row)
      <tr>
        <td>{{ $row['employee']->full_name }} <span style="color:var(--t3);font-size:11px">({{ $row['employee']->employee_id }})</span></td>
        <td>{{ $row['monthly_avg_hours'] !== null ? $row['monthly_avg_hours'] . ' hrs/day' : '—' }}</td>
        <td>{{ $row['monthly_day_count'] }}</td>
        <td>{{ $row['leave_cl'] ?: '—' }}</td>
        <td>{{ $row['leave_sl'] ?: '—' }}</td>
        <td>{{ $row['leave_co'] ?: '—' }}</td>
        <td>
          @if(!empty($row['leave_other_breakdown']))
            @foreach($row['leave_other_breakdown'] as $type => $count)
              <span class="bx gr" style="margin:1px 2px 1px 0;display:inline-block">{{ $type }}: {{ $count }}</span>
            @endforeach
          @else
            —
          @endif
        </td>
        <td>
          @if($row['is_shortfall'])
            <span class="bx r">Below 9h Average</span>
          @elseif($row['monthly_avg_hours'] !== null)
            <span class="bx g">OK</span>
          @elseif($row['leave_days_total'] > 0)
            <span class="bx y">On Leave</span>
          @else
            <span class="bx gr">No Data</span>
          @endif
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @else
  <div class="empty"><div class="ico">📊</div><p>No attendance data for this period.</p></div>
  @endif
</div>

@endsection
