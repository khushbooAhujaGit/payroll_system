@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<div class="g4">
  <div class="sc">
    <div class="lbl">Active Employees</div>
    <div class="val">{{ $totalActive }}</div>
  </div>
  <div class="sc">
    <div class="lbl">New Joiners</div>
    <div class="val">{{ $newJoiners }}</div>
    <div class="sub">This month</div>
  </div>
  <div class="sc">
    <div class="lbl">Pending Leaves</div>
    <div class="val" style="color:var(--yel)">{{ $pendingLeaves }}</div>
  </div>
  <div class="sc">
    <div class="lbl">Open Tickets</div>
    <div class="val" style="color:var(--red)">{{ $openTickets }}</div>
  </div>
</div>

<div class="g2" style="margin-top:18px">
  <div class="card">
    <div class="ch">
      <div class="ct">Recent Employees</div>
      <a href="{{ route('employees') }}" class="btn sm">View All →</a>
    </div>
    @if($recentEmployees->isNotEmpty())
    <table>
      <thead><tr><th>Emp ID</th><th>Name</th><th>Department</th><th>Status</th></tr></thead>
      <tbody>
        @foreach($recentEmployees as $emp)
        <tr>
          <td class="mono">{{ $emp->employee_id }}</td>
          <td>{{ $emp->full_name }}</td>
          <td>{{ $emp->department?->name ?? '—' }}</td>
          <td><span class="bx {{ $emp->employee_status === 'active' ? 'g' : 'gr' }}">{{ $emp->employee_status }}</span></td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @else
    <div class="empty">
      <div class="ico">👥</div>
      <p>No employees yet</p>
      <a href="{{ route('employees') }}" class="btn acc" style="margin-top:10px">+ Add Employee</a>
    </div>
    @endif
  </div>

  <div class="card">
    <div class="ch"><div class="ct">Quick Actions</div></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
      <a href="{{ route('employees') }}" class="btn" style="justify-content:flex-start">+ Add Employee</a>
      <a href="{{ route('attendance') }}" class="btn" style="justify-content:flex-start">✔ Attendance</a>
      <a href="{{ route('leave') }}" class="btn" style="justify-content:flex-start">Apply Leave</a>
      <a href="{{ route('payroll') }}" class="btn" style="justify-content:flex-start">Run Payroll</a>
      <a href="{{ route('reports') }}" class="btn" style="justify-content:flex-start">📊 Reports</a>
      <a href="{{ route('statutory') }}" class="btn" style="justify-content:flex-start">📋 Statutory</a>
    </div>

    @if($deptStrength->isNotEmpty())
    <div style="margin-top:14px">
      <div style="font-size:11px;font-weight:600;color:var(--t3);margin-bottom:8px">DEPARTMENT STRENGTH</div>
      @foreach($deptStrength->take(5) as $dept)
      @php $pct = $totalActive ? round($dept->count / $totalActive * 100) : 0; @endphp
      <div style="margin-bottom:7px">
        <div style="display:flex;justify-content:space-between;font-size:11px;margin-bottom:2px">
          <span>{{ $dept->department?->name ?? 'Unknown' }}</span>
          <span style="color:var(--t3)">{{ $dept->count }}</span>
        </div>
        <div style="background:var(--bg);border-radius:3px;height:4px">
          <div style="background:var(--acc);width:{{ $pct }}%;height:100%;border-radius:3px"></div>
        </div>
      </div>
      @endforeach
    </div>
    @endif

    @if($lastRun)
    <div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--b1)">
      <div style="font-size:11px;font-weight:600;color:var(--t3);margin-bottom:6px">LAST PAYROLL RUN</div>
      <div style="font-size:12px">
        <span class="bx g">{{ strtoupper($lastRun->status) }}</span>
        &nbsp;{{ $lastRun->run_name }}
        &mdash; {{ $lastRun->period?->year }}/{{ str_pad($lastRun->period?->month, 2, '0', STR_PAD_LEFT) }}
      </div>
    </div>
    @endif
  </div>
</div>

<div class="card" style="margin-top:18px">
  <div class="ch"><div class="ct">Today's Attendance</div></div>
  <div class="g4">
    @php
      $statuses = ['present' => ['label'=>'Present','color'=>'var(--grn)'],
                   'absent'  => ['label'=>'Absent', 'color'=>'var(--red)'],
                   'leave'   => ['label'=>'On Leave','color'=>'var(--yel)'],
                   'wfh'     => ['label'=>'WFH',    'color'=>'var(--pur)']];
    @endphp
    @foreach($statuses as $key => $info)
    <div class="sc">
      <div class="lbl">{{ $info['label'] }}</div>
      <div class="val" style="color:{{ $info['color'] }}">{{ $todayAttendance[$key] ?? 0 }}</div>
    </div>
    @endforeach
  </div>
</div>
@endsection
