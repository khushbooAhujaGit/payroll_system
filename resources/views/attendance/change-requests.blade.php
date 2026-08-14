@extends('layouts.app')
@section('title', 'Attendance Change Requests')

@section('content')

@if($companies->count() > 1)
<form method="GET" action="{{ route('attendance.change_requests') }}" class="card"
  style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
  <div class="fld" style="min-width:200px">
    <label>Entity / Company</label>
    <select name="company_id" onchange="this.form.submit()">
      @foreach($companies as $ent)
        <option value="{{ $ent->id }}" {{ $companyId == $ent->id ? 'selected' : '' }}>{{ $ent->name }}</option>
      @endforeach
    </select>
  </div>
</form>
@endif

<div class="card">
  <div class="ch"><div class="ct">Pending & Recent Change Requests</div></div>
  @if($requests->isNotEmpty())
  <table>
    <thead><tr><th>Employee</th><th>Week Of</th><th>Remark</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      @foreach($requests as $req)
      <tr>
        <td>{{ $req->employee?->full_name ?? '—' }} <span style="color:var(--t3);font-size:11px">({{ $req->employee?->employee_id }})</span></td>
        <td>{{ $req->attendance_date->format('d M Y') }}</td>
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
