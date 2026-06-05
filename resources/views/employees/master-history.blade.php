@extends('layouts.app')
@section('title', 'Employee Master History')

@section('page_actions')
  <a href="{{ route('employees') }}" class="btn">← Employees</a>
@endsection

@section('content')

<form method="GET" action="{{ route('employees.master_history') }}"
      class="card" style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
  <div class="fld" style="min-width:220px">
    <label>Search Emp ID / Name</label>
    <input type="text" name="search" value="{{ $search }}" placeholder="e.g. EMP001 or John…">
  </div>
  <div class="fld" style="min-width:160px">
    <label>Field Type</label>
    <select name="field_type">
      <option value="" {{ $fieldType==='' ? 'selected' : '' }}>All Types</option>
      <option value="bank_details" {{ $fieldType==='bank_details' ? 'selected' : '' }}>Bank Details</option>
      <option value="pan"          {{ $fieldType==='pan'          ? 'selected' : '' }}>PAN</option>
      <option value="email"        {{ $fieldType==='email'        ? 'selected' : '' }}>Email</option>
    </select>
  </div>
  <div class="fld" style="min-width:160px">
    <label>Changed Via</label>
    <select name="changed_via">
      <option value="" {{ $changedVia==='' ? 'selected' : '' }}>All Sources</option>
      <option value="salary_reupload" {{ $changedVia==='salary_reupload' ? 'selected' : '' }}>Salary Re-upload</option>
      <option value="manual"          {{ $changedVia==='manual'          ? 'selected' : '' }}>Manual</option>
      <option value="import"          {{ $changedVia==='import'          ? 'selected' : '' }}>Import</option>
    </select>
  </div>
  <button type="submit" class="btn acc">Search</button>
  @if($search || $fieldType || $changedVia)
    <a href="{{ route('employees.master_history') }}" class="btn">✕ Clear</a>
  @endif
</form>

<div class="card">
  <div class="ch" style="display:flex;justify-content:space-between;align-items:center">
    <div class="ct">Employee Master Change History</div>
    <div style="font-size:12px;color:var(--t3)">
      {{ number_format($history->total()) }} record(s)
      @if($history->lastPage() > 1) · Page {{ $history->currentPage() }}/{{ $history->lastPage() }} @endif
    </div>
  </div>

  @if($history->isNotEmpty())
  <div style="overflow-x:auto">
  <table style="font-size:12px">
    <thead>
      <tr>
        <th>Date &amp; Time</th>
        <th>Employee</th>
        <th>Field Type</th>
        <th>Old Value</th>
        <th>New Value</th>
        <th>Changed Via</th>
        <th>Changed By</th>
      </tr>
    </thead>
    <tbody>
      @foreach($history as $h)
      @php
        $typeBadge = [
          'bank_details' => ['bg'=>'#dbeafe','color'=>'#1d4ed8','label'=>'Bank Details'],
          'pan'          => ['bg'=>'#f3e8ff','color'=>'#7e22ce','label'=>'PAN'],
          'email'        => ['bg'=>'#fef9c3','color'=>'#a16207','label'=>'Email'],
        ][$h->field_type] ?? ['bg'=>'#f3f4f6','color'=>'#374151','label'=>ucfirst($h->field_type)];

        $viaBadge = [
          'salary_reupload' => ['bg'=>'#fef3c7','color'=>'#92400e','label'=>'Salary Re-upload'],
          'manual'          => ['bg'=>'#f0fdf4','color'=>'#166534','label'=>'Manual'],
          'import'          => ['bg'=>'#eff6ff','color'=>'#1e40af','label'=>'Import'],
        ][$h->changed_via] ?? ['bg'=>'#f3f4f6','color'=>'#374151','label'=>ucfirst($h->changed_via)];
      @endphp
      <tr>
        <td style="white-space:nowrap;color:var(--t2)">
          {{ $h->created_at->format('d M Y') }}<br>
          <span style="font-size:10px">{{ $h->created_at->format('h:i A') }}</span>
        </td>
        <td>
          <span style="font-weight:600">{{ $h->employee?->full_name ?? '—' }}</span><br>
          <span class="mono" style="font-size:10px;color:var(--t3)">{{ $h->employee?->employee_id }}</span>
        </td>
        <td>
          <span style="display:inline-block;padding:2px 8px;border-radius:99px;font-size:10px;font-weight:700;
                       background:{{ $typeBadge['bg'] }};color:{{ $typeBadge['color'] }}">
            {{ $typeBadge['label'] }}
          </span>
        </td>
        <td style="color:var(--red);font-size:11px;max-width:220px">
          @if($h->old_value)
            @foreach($h->old_value as $k => $v)
              <div><span style="color:var(--t3)">{{ $k }}:</span> {{ $v ?: '—' }}</div>
            @endforeach
          @else
            <span style="color:var(--t3)">— (new entry)</span>
          @endif
        </td>
        <td style="color:var(--grn);font-size:11px;max-width:220px">
          @if($h->new_value)
            @foreach($h->new_value as $k => $v)
              <div><span style="color:var(--t3)">{{ $k }}:</span> <strong>{{ $v ?: '—' }}</strong></div>
            @endforeach
          @else
            <span style="color:var(--t3)">—</span>
          @endif
        </td>
        <td>
          <span style="display:inline-block;padding:2px 8px;border-radius:99px;font-size:10px;font-weight:700;
                       background:{{ $viaBadge['bg'] }};color:{{ $viaBadge['color'] }}">
            {{ $viaBadge['label'] }}
          </span>
        </td>
        <td style="font-size:11px;color:var(--t2)">{{ $h->changedBy?->name ?? '—' }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  </div>
  @if($history->hasPages())
    <div style="padding:14px 18px;border-top:1px solid var(--b1)">{{ $history->links() }}</div>
  @endif

  @else
  <div class="empty">
    <div class="ico">📋</div>
    <p>No master field change history found.</p>
    <p style="font-size:12px;color:var(--t3);margin-top:4px">
      Changes to bank details, PAN, and email via salary re-upload will appear here.
    </p>
  </div>
  @endif
</div>

@endsection
