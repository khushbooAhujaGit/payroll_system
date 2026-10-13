@extends('layouts.app')
@section('title', 'Form 16')

@section('content')

<div style="background:#fff7ed;border:1px solid #fb923c;color:#7c2d12;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:12px;line-height:1.5">
  <strong>Best-effort summary, not an official TRACES-issued Form 16.</strong>
  Reports gross salary, PF, professional tax and TDS as recorded in the HRMS each month — it does not
  independently compute HRA exemption, Chapter VI-A deductions, surcharge, or final tax liability.
  Verify against your TDS returns before using it for filing.
</div>

<form method="GET" action="{{ route('form16') }}" class="card"
  style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
  <div class="fld" style="min-width:160px">
    <label>Financial Year</label>
    <input type="text" name="fy" value="{{ $fy }}" placeholder="e.g. 2025-26" pattern="\d{4}-\d{2}" required>
  </div>
  <input type="hidden" name="company_id" value="{{ $companyId }}">
  <button type="submit" class="btn acc">View</button>
</form>

<div class="card">
  <div class="ch"><div class="ct">Employees with locked salary data — FY {{ $fy }}</div></div>
  @if($employees->isNotEmpty())
  <table>
    <thead><tr><th>Employee</th><th>Emp ID</th><th></th></tr></thead>
    <tbody>
      @foreach($employees as $emp)
      <tr>
        <td>{{ $emp->full_name }}</td>
        <td class="mono" style="font-size:11px">{{ $emp->employee_id }}</td>
        <td><a href="{{ route('form16.download', ['employee' => $emp->id, 'fy' => $fy]) }}" class="btn sm">Download</a></td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @else
  <div class="empty"><div class="ico">📄</div><p>No locked salary data for FY {{ $fy }} yet.</p></div>
  @endif
</div>

@endsection
