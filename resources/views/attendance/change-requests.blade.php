@extends('layouts.app')
@section('title', 'Attendance Change Requests')

@section('page_actions')
  <a href="{{ route('attendance.change_requests.export', ['company_id' => $companyId, 'search' => $search, 'month' => $month]) }}" class="btn">⬇ Export</a>
@endsection

@section('content')

@if($companies->count() > 1)
<form method="GET" action="{{ route('attendance.change_requests') }}" class="card"
  style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
  <input type="hidden" name="search" value="{{ $search }}">
  <input type="hidden" name="month" value="{{ $month }}">
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

<form method="GET" action="{{ route('attendance.change_requests') }}" class="card"
  style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
  @if($companyId)
    <input type="hidden" name="company_id" value="{{ $companyId }}">
  @endif
  <div class="fld" style="min-width:220px">
    <label>Search by Name / Emp ID</label>
    <input type="text" name="search" value="{{ $search }}" placeholder="e.g. EMP001 or John Doe">
  </div>
  <div class="fld" style="min-width:160px">
    <label>Month / Year</label>
    <input type="month" name="month" value="{{ $month }}">
  </div>
  <button type="submit" class="btn acc">Filter</button>
  @if($search !== '' || $month !== '')
    <a href="{{ route('attendance.change_requests', $companyId ? ['company_id' => $companyId] : []) }}" class="btn">✕ Clear</a>
  @endif
</form>

<div class="card">
  <div class="ch"><div class="ct">Pending & Recent Change Requests</div></div>
  @if($requests->isNotEmpty())
  <table>
    <thead><tr><th>Employee</th><th>Type</th><th>Date</th><th>Check In</th><th>Check Out</th><th>Remark</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      @foreach($requests as $req)
      <tr>
        <td>{{ $req->employee?->full_name ?? '—' }} <span style="color:var(--t3);font-size:11px">({{ $req->employee?->employee_id }})</span></td>
        <td><span class="bx gr">{{ $req->reason === 'weekly_hours_shortfall' ? 'Week' : 'Day' }}</span></td>
        <td>{{ $req->attendance_date->format('d M Y') }}</td>
        @if($req->reason === 'daily_hours_shortfall')
          @php $actual = $actualPunches->get($req->employee_id . '_' . $req->attendance_date->format('Y-m-d')); @endphp
          <td style="{{ $req->requested_check_in ? 'background:var(--yel2);font-weight:600' : '' }}">
            {{ $actual['check_in'] ?? null ? substr($actual['check_in'], 0, 5) : '—' }}
            @if($req->requested_check_in)
              <div style="font-size:11px;color:var(--red);font-weight:400">→ requested {{ substr($req->requested_check_in, 0, 5) }}</div>
            @endif
          </td>
          <td style="{{ $req->requested_check_out ? 'background:var(--yel2);font-weight:600' : '' }}">
            {{ $actual['check_out'] ?? null ? substr($actual['check_out'], 0, 5) : '—' }}
            @if($req->requested_check_out)
              <div style="font-size:11px;color:var(--red);font-weight:400">→ requested {{ substr($req->requested_check_out, 0, 5) }}</div>
            @endif
          </td>
        @else
          <td colspan="2" style="color:var(--t3);text-align:center">—</td>
        @endif
        <td>{{ $req->remarks }}</td>
        <td>
          <span class="bx {{ $req->status === 'approved' ? 'g' : ($req->status === 'rejected' ? 'r' : 'y') }}">
            {{ ucfirst($req->status) }}
          </span>
        </td>
        <td>
          @if($req->status === 'pending')
            <button class="btn sm" onclick="om('resolve-{{ $req->id }}')">Review</button>
          @else
            <span style="color:var(--t3);font-size:11px">{{ $req->approval_remarks ?: '—' }}</span>
          @endif
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
  <div style="margin-top:14px">{{ $requests->links() }}</div>
  @else
  <div class="empty"><div class="ico">📋</div><p>No change requests yet.</p></div>
  @endif
</div>

@foreach($requests as $req)
  @if($req->status === 'pending')
    <div class="modal" id="resolve-{{ $req->id }}">
      <div class="mb">
        <div class="mh">
          <span class="mt">Review — {{ $req->employee?->full_name }} ({{ $req->attendance_date->format('d M Y') }})</span>
          <button class="mc" onclick="cm('resolve-{{ $req->id }}')">✕</button>
        </div>
        <p style="font-size:13px;color:var(--t2);margin-bottom:12px"><strong>Employee's remark:</strong> {{ $req->remarks }}</p>
        <form method="POST" action="{{ route('attendance.change_requests.resolve', $req) }}">
          @csrf
          <div class="fld full">
            <label>HR Remark (optional)</label>
            <textarea name="approval_remarks" rows="3"></textarea>
          </div>
          <div class="mf">
            <button type="submit" name="action" value="rejected" class="btn" style="color:var(--red)">Reject</button>
            <button type="submit" name="action" value="approved" class="btn acc">Approve</button>
          </div>
        </form>
      </div>
    </div>
  @endif
@endforeach

@endsection
