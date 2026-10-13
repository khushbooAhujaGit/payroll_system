@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
@php
  $presentDays = $monthAttendance['present'] ?? 0;
  $reimbColor = $pendingReimbursements > 0 ? 'yel' : 'grn';
  $leaveIcons = ['Casual Leave' => '🌴', 'Sick Leave' => '🤒', 'Compensation Off' => '🔄'];
@endphp

<div class="card" style="margin-bottom:18px">
  <div style="font-size:15px;font-weight:700">Welcome back, {{ $employee->first_name }}</div>
  <div class="sub">{{ $employee->designation?->name ?? $employee->employee_id }} &middot; {{ now()->format('l, F j, Y') }}</div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px">
  @foreach($leaveBalance as $bal)
  <div class="sc">
    <div class="stat-ico" style="background:var(--acc2);color:var(--acc)">{{ $leaveIcons[$bal->leaveType?->name] ?? '🌴' }}</div>
    <div class="lbl">{{ $bal->leaveType?->name ?? 'Leave' }}</div>
    <div class="val">{{ rtrim(rtrim(number_format($bal->available, 1), '0'), '.') }}</div>
    <div class="sub">days available</div>
  </div>
  @endforeach
  <div class="sc">
    <div class="stat-ico" style="background:var(--grn2);color:var(--grn)">✅</div>
    <div class="lbl">Present This Month</div>
    <div class="val">{{ $presentDays }}</div>
    <div class="sub">{{ now()->format('F') }}</div>
  </div>
  <div class="sc">
    <div class="stat-ico" style="background:var(--{{ $reimbColor }}2);color:var(--{{ $reimbColor }})">🧾</div>
    <div class="lbl">Reimbursements</div>
    <div class="val" style="color:var(--{{ $reimbColor }})">{{ $pendingReimbursements }}</div>
    <div class="sub">{{ $pendingReimbursements > 0 ? 'Pending review' : 'All settled' }}</div>
  </div>
</div>

<div class="card" style="margin-top:18px">
  <div class="ch"><div class="ct">Quick Actions</div></div>
  <div class="qa-grid">
    <a href="{{ route('leave') }}" class="qa-item"><span class="ico">🌴</span> Apply Leave</a>
    <a href="{{ route('my-attendance') }}" class="qa-item"><span class="ico">✔️</span> My Attendance</a>
    @can('salary_self_view')
    <a href="{{ route('my-payslips') }}" class="qa-item"><span class="ico">💰</span> My Payslips</a>
    @endcan
    @can('reimbursment_self_view')
    <a href="{{ route('my-reimbursements') }}" class="qa-item"><span class="ico">🧾</span> My Reimbursements</a>
    @endcan
    @if($isManager)
    <a href="{{ route('my-team') }}" class="qa-item"><span class="ico">👥</span> My Team</a>
    <a href="{{ route('attendance.change_requests') }}" class="qa-item"><span class="ico">🗓️</span> Attendance Requests</a>
    @endif
  </div>
</div>

@if($activeLoan)
<div class="card" style="margin-top:18px">
  <div class="ch"><div class="ct">Active Loan / Advance</div></div>
  <div style="display:flex;justify-content:space-between;align-items:center">
    <div>
      <div style="font-size:12px;color:var(--t2)">{{ $activeLoan->loan_number }} &middot; {{ $activeLoan->loanType?->name ?? '' }}</div>
      <div class="sub">EMI ₹{{ number_format($activeLoan->emi_amount, 0) }}/month</div>
    </div>
    <div style="font-size:18px;font-weight:700;font-family:'JetBrains Mono',monospace">₹{{ number_format($activeLoan->outstanding_amount, 0) }}</div>
  </div>
</div>
@endif

@if($isManager && ($teamPendingLeaves > 0 || $teamPendingAttendance > 0))
<div class="card" style="margin-top:18px">
  <div class="ch"><div class="ct">Team Approvals</div></div>
  <div style="display:flex;flex-direction:column;gap:2px">
    @if($teamPendingLeaves > 0)
    <a href="{{ route('leave') }}" class="qa-item" style="justify-content:space-between">
      <span>🗓️ Leave Requests</span>
      <span class="bx y">{{ $teamPendingLeaves }}</span>
    </a>
    @endif
    @if($teamPendingAttendance > 0)
    <a href="{{ route('attendance.change_requests') }}" class="qa-item" style="justify-content:space-between">
      <span>✔️ Attendance Requests</span>
      <span class="bx y">{{ $teamPendingAttendance }}</span>
    </a>
    @endif
  </div>
</div>
@endif
@endsection
