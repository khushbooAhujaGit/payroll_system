@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<form method="GET" action="{{ route('dashboard') }}" style="display:flex;gap:14px;margin-bottom:14px;flex-wrap:wrap">
  <div class="fld" style="max-width:180px">
    <label>Month</label>
    <input type="month" name="month" value="{{ $month }}" onchange="this.form.submit()">
  </div>
  @if($companies->isNotEmpty())
  <div class="fld" style="max-width:220px">
    <label>Firm</label>
    <select name="company_id" onchange="this.form.submit()">
      <option value="0" {{ $companyId == 0 ? 'selected' : '' }}>All Firms</option>
      @foreach($companies as $c)
        <option value="{{ $c->id }}" {{ $c->id == $companyId ? 'selected' : '' }}>{{ $c->short_name ?? $c->name }}</option>
      @endforeach
    </select>
  </div>
  @endif
</form>

@php
  $leaveColor  = $pendingLeaves > 0 ? 'yel' : 'grn';
  $ticketColor = $openTickets   > 0 ? 'red' : 'grn';
  $monthLabel  = \Carbon\Carbon::createFromFormat('Y-m', $month)->format('M Y');
@endphp

<div class="g4">
  <div class="sc">
    <div class="stat-ico" style="background:var(--acc2);color:var(--acc)">👥</div>
    <div class="lbl">Active Employees</div>
    <div class="val">{{ $totalActive }}</div>
  </div>
  <div class="sc">
    <div class="stat-ico" style="background:var(--pur2);color:var(--pur)">✨</div>
    <div class="lbl">New Joiners</div>
    <div class="val">{{ $newJoiners }}</div>
    <div class="sub">{{ $monthLabel }}</div>
  </div>
  <div class="sc">
    <div class="stat-ico" style="background:var(--{{ $leaveColor }}2);color:var(--{{ $leaveColor }})">🗓️</div>
    <div class="lbl">Pending Leaves</div>
    <div class="val" style="color:var(--{{ $leaveColor }})">{{ $pendingLeaves }}</div>
    <div class="sub">{{ $pendingLeaves > 0 ? 'Awaiting approval' : 'All caught up' }}</div>
  </div>
  <div class="sc">
    <div class="stat-ico" style="background:var(--{{ $ticketColor }}2);color:var(--{{ $ticketColor }})">🎫</div>
    <div class="lbl">Open Tickets</div>
    <div class="val" style="color:var(--{{ $ticketColor }})">{{ $openTickets }}</div>
    <div class="sub">{{ $openTickets > 0 ? 'Needs attention' : 'No open tickets' }}</div>
  </div>
</div>

@php
  $approvalsTotal = $pendingLeaves + $pendingLoans + $pendingReimbursements;
  $showPayrollCard   = $monthPayroll !== null;
  $showApprovalsCard = $approvalsTotal > 0;
@endphp

@if($showPayrollCard || $showApprovalsCard)
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px;margin-top:18px">
  @if($showPayrollCard)
  <div class="card" style="margin-bottom:0">
    <div class="ch"><div class="ct">Payroll Summary</div></div>
    <div style="font-size:11px;color:var(--t3);margin-bottom:4px">
      {{ $monthLabel }} &nbsp;<span class="bx {{ $monthPayroll['status'] === 'mixed' ? 'y' : 'g' }}">{{ strtoupper($monthPayroll['status']) }}</span>
    </div>
    <div style="font-size:22px;font-weight:700;font-family:'JetBrains Mono',monospace">₹{{ number_format($monthPayroll['total_net'], 0) }}</div>
    <div class="sub" style="margin-bottom:10px">Net pay &middot; {{ $monthPayroll['total_employees'] }} employee{{ $monthPayroll['total_employees'] == 1 ? '' : 's' }}</div>
    @can('payroll_view')
    <a href="{{ route('payroll') }}" class="btn sm">View Payroll →</a>
    @endcan
  </div>
  @endif

  @if($showApprovalsCard)
  <div class="card" style="margin-bottom:0">
    <div class="ch"><div class="ct">Approvals Queue</div></div>
    <div style="display:flex;flex-direction:column;gap:2px">
      @if($pendingLeaves > 0)
      <a href="{{ route('leave') }}" class="qa-item" style="justify-content:space-between">
        <span>🗓️ Leave Requests</span>
        <span class="bx y">{{ $pendingLeaves }}</span>
      </a>
      @endif
      @can('loan_tab_view')
      @if($pendingLoans > 0)
      <a href="{{ route('loans') }}" class="qa-item" style="justify-content:space-between">
        <span>💳 Loan / Advance</span>
        <span class="bx y">{{ $pendingLoans }}</span>
      </a>
      @endif
      @endcan
      @can('reimbursment_approve')
      @if($pendingReimbursements > 0)
      <a href="{{ route('reimbursements.approvals') }}" class="qa-item" style="justify-content:space-between">
        <span>🧾 Reimbursements</span>
        <span class="bx y">{{ $pendingReimbursements }}</span>
      </a>
      @endif
      @endcan
    </div>
  </div>
  @endif
</div>
@endif

@if($payrollTrend->isNotEmpty())
<div class="card" style="margin-top:18px">
  <div class="ch"><div class="ct">Payroll Cost Trend</div></div>
  @php $maxNet = $payrollTrend->max('total_net') ?: 1; @endphp
  <div style="display:flex;align-items:flex-end;gap:14px;height:150px;padding-top:10px">
    @foreach($payrollTrend as $point)
    @php $barH = max(6, round($point['total_net'] / $maxNet * 100)); @endphp
    <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%">
      <div style="font-size:10px;color:var(--t3);margin-bottom:5px;font-family:'JetBrains Mono',monospace">₹{{ number_format($point['total_net'] / 1000, 0) }}k</div>
      <div style="width:100%;max-width:44px;background:var(--acc);border-radius:5px 5px 0 0;height:{{ $barH }}%"></div>
      <div style="font-size:10px;color:var(--t3);margin-top:6px">{{ $point['label'] }}</div>
    </div>
    @endforeach
  </div>
</div>
@endif

<div class="g2" style="margin-top:18px">
  <div class="card">
    <div class="ch">
      <div class="ct">Recent Employees</div>
      @can('employee_view')
      <a href="{{ route('employees') }}" class="btn sm">View All →</a>
      @endcan
    </div>
    @if($recentEmployees->isNotEmpty())
    <table>
      <thead><tr><th>Emp ID</th><th>Name</th><th>Department</th><th>Status</th></tr></thead>
      <tbody>
        @foreach($recentEmployees as $emp)
        <tr>
          <td class="mono">{{ $emp->employee_id }}</td>
          <td>
            <div class="name-cell">
              <span class="avatar-sm">{{ strtoupper(substr($emp->first_name ?? $emp->full_name, 0, 1)) }}</span>
              {{ $emp->full_name }}
            </div>
          </td>
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
      @can('employee_add')
      <a href="{{ route('employees') }}" class="btn acc" style="margin-top:10px">+ Add Employee</a>
      @endcan
    </div>
    @endif
  </div>

  <div class="card">
    <div class="ch"><div class="ct">Quick Actions</div></div>
    <div class="qa-grid">
      @can('employee_add')
      <a href="{{ route('employees') }}" class="qa-item"><span class="ico">➕</span> Add Employee</a>
      @endcan
      @can('attendance_view')
      <a href="{{ route('attendance') }}" class="qa-item"><span class="ico">✔️</span> Attendance</a>
      @endcan
      <a href="{{ route('leave') }}" class="qa-item"><span class="ico">🌴</span> Apply Leave</a>
      @can('payroll_view')
      <a href="{{ route('payroll') }}" class="qa-item"><span class="ico">💰</span> Run Payroll</a>
      @endcan
    </div>
  </div>
</div>

<div class="card" style="margin-top:18px">
  <div class="ch"><div class="ct">Today's Attendance</div></div>
  <div class="g4">
    @php
      $statuses = ['present' => ['label'=>'Present','ico'=>'✅','var'=>'grn'],
                   'absent'  => ['label'=>'Absent', 'ico'=>'❌','var'=>'red'],
                   'leave'   => ['label'=>'On Leave','ico'=>'🌴','var'=>'yel'],
                   'wfh'     => ['label'=>'WFH',    'ico'=>'🏠','var'=>'pur']];
    @endphp
    @foreach($statuses as $key => $info)
    <div class="sc">
      <div class="stat-ico" style="background:var(--{{ $info['var'] }}2);color:var(--{{ $info['var'] }})">{{ $info['ico'] }}</div>
      <div class="lbl">{{ $info['label'] }}</div>
      <div class="val" style="color:var(--{{ $info['var'] }})">{{ $todayAttendance[$key] ?? 0 }}</div>
    </div>
    @endforeach
  </div>
</div>
@endsection
