@extends('layouts.app')
@section('title', 'My Payslips')

@section('content')

<div class="card" style="margin-bottom:16px">
  <div class="ch"><div class="ct">Payslips</div></div>
  @if($snapshots->isNotEmpty())
  <div class="tw">
  <table>
    <thead><tr><th>Month</th><th>Processed On</th><th>Payslip</th></tr></thead>
    <tbody>
      @foreach($snapshots as $s)
      <tr>
        <td>{{ \Carbon\Carbon::createFromFormat('Y-m', $s->year_month)->format('F Y') }}</td>
        <td style="color:var(--t3);font-size:12px">{{ $s->locked_at?->format('d M Y') ?? '—' }}</td>
        <td><a href="{{ route('my-payslips.download', $s->year_month) }}" class="btn sm">Download</a></td>
      </tr>
      @endforeach
    </tbody>
  </table>
  </div>
  @else
  <div class="empty"><div class="ico">💳</div><p>No processed salary months yet.</p></div>
  @endif
</div>

<div class="card">
  <div class="ch"><div class="ct">Form 16</div></div>
  @if($financialYears->isNotEmpty())
  <div class="tw">
  <table>
    <thead><tr><th>Financial Year</th><th></th></tr></thead>
    <tbody>
      @foreach($financialYears as $fy)
      <tr>
        <td>FY {{ $fy }}</td>
        <td><a href="{{ route('my-payslips.form16', $fy) }}" class="btn sm">Download</a></td>
      </tr>
      @endforeach
    </tbody>
  </table>
  </div>
  <div style="font-size:11px;color:var(--t3);margin-top:10px">
    This is a system-generated summary of amounts paid and tax deducted — see the document itself for details on what it does and doesn't cover.
  </div>
  @else
  <div class="empty"><div class="ico">📄</div><p>No financial year data available yet.</p></div>
  @endif
</div>

@endsection
