@extends('layouts.app')
@section('title', 'Incentives')

@section('page_actions')
  <a href="{{ route('incentives.template') }}" class="btn">📥 Template</a>
  <button class="btn" onclick="om('inc-import-modal')">📤 Import</button>
@endsection

@section('content')

{{-- Month selector --}}
<form method="GET" action="{{ route('incentives') }}" class="card"
  style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end">
  <div class="fld" style="min-width:180px">
    <label>Month *</label>
    <input type="month" name="month" value="{{ $month }}" required>
  </div>
  <button type="submit" class="btn acc">View</button>
</form>

{{-- Stat cards --}}
<div class="g4" style="margin-bottom:16px">
  <div class="sc">
    <div class="lbl">Employees</div>
    <div class="val">{{ $employees->count() }}</div>
  </div>
  <div class="sc">
    <div class="lbl">With Incentive</div>
    <div class="val" style="color:var(--grn)">{{ $incentives->count() }}</div>
  </div>
  <div class="sc">
    <div class="lbl">Total Incentive (₹)</div>
    <div class="val">{{ number_format($totalIncentive, 0) }}</div>
  </div>
  <div class="sc">
    <div class="lbl">Month</div>
    <div class="val" style="font-size:14px">{{ \Carbon\Carbon::createFromFormat('Y-m',$month)->format('M Y') }}</div>
  </div>
</div>

<div class="card">
  <div class="ch">
    <div class="ct">Incentives — {{ \Carbon\Carbon::createFromFormat('Y-m',$month)->format('M Y') }}</div>
    <div style="font-size:11px;color:var(--t3)">Click <strong>Add</strong> next to an employee to enter their incentive amount</div>
  </div>
  <table>
    <thead>
      <tr>
        <th>Emp ID</th>
        <th>Employee</th>
        <th style="text-align:right">Incentive (₹)</th>
        <th>Reason</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      @foreach($employees as $emp)
      @php $inc = $incentives[$emp->id] ?? null; @endphp
      <tr>
        <td class="mono">{{ $emp->employee_id }}</td>
        <td>{{ $emp->first_name }} {{ $emp->last_name }}</td>
        <td style="text-align:right;font-weight:{{ $inc ? '600' : '400' }};color:{{ $inc ? 'var(--grn)' : 'var(--t3)' }}">
          {{ $inc ? '₹ ' . number_format($inc->amount, 0) : '—' }}
        </td>
        <td style="font-size:11px;color:var(--t2)">{{ $inc?->reason ?? '—' }}</td>
        <td style="white-space:nowrap">
          <button class="btn sm"
            onclick="openIncModal({{ $emp->id }}, '{{ addslashes($emp->first_name) }} {{ addslashes($emp->last_name) }}', {{ $inc?->amount ?? 0 }}, '{{ addslashes($inc?->reason ?? '') }}', '{{ $inc?->month ?? $month }}')">
            {{ $inc ? 'Edit' : 'Add' }}
          </button>
          @if($inc)
          <form method="POST" action="{{ route('incentives.destroy', $inc) }}" style="display:inline">
            @csrf @method('DELETE')
            <button type="button" class="btn sm" style="color:var(--red)"
              onclick="swConfirm(this.closest('form'), 'Remove Incentive?', 'The incentive for {{ $emp->first_name }} will be permanently removed.', 'warning', 'Yes, remove', '#dc2626')">Del</button>
          </form>
          @endif
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>

{{-- Set / Edit Modal --}}
<div class="modal" id="inc-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt" id="inc-modal-title">Set Incentive</span>
      <button class="mc" onclick="cm('inc-modal')">✕</button>
    </div>
    <form method="POST" action="{{ route('incentives.upsert') }}">
      @csrf
      <input type="hidden" name="employee_id" id="inc-emp-id">
      <div class="fg2">
        <div class="fld">
          <label class="req">Month</label>
          <input type="month" name="month" id="inc-month" required value="{{ $month }}"
            @error('month') class="inp-err" @enderror>
          @error('month') <div class="ferr">{{ $message }}</div> @enderror
        </div>
        <div class="fld">
          <label class="req">Amount (₹)</label>
          <input type="number" name="amount" id="inc-amount" required min="0" step="0.01"
            @error('amount') class="inp-err" @enderror>
          @error('amount') <div class="ferr">{{ $message }}</div> @enderror
        </div>
        <div class="fld full">
          <label>Reason</label>
          <input type="text" name="reason" id="inc-reason" maxlength="200" placeholder="e.g. Performance bonus">
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('inc-modal')">Cancel</button>
        <button type="submit" class="btn acc">Save</button>
      </div>
    </form>
  </div>
</div>

{{-- Import Modal --}}
<div class="modal" id="inc-import-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Import Incentives</span>
      <button class="mc" onclick="cm('inc-import-modal')">✕</button>
    </div>
    <div style="background:var(--acc2);border:1px solid var(--acc);border-radius:8px;
                padding:12px 14px;margin-bottom:14px;font-size:12px;line-height:1.6;color:var(--t2)">
      Template columns: <span class="mono">Emp ID · Amount · Reason</span>
    </div>
    <form method="POST" action="{{ route('incentives.import') }}" enctype="multipart/form-data">
      @csrf
      <div class="fg2" style="margin-bottom:14px">
        <div class="fld">
          <label>Month *</label>
          <input type="month" name="import_month" required value="{{ $month }}">
        </div>
        <div class="fld">
          <label>Excel File (.xlsx / .xls) *</label>
          <input type="file" name="file" accept=".xlsx,.xls" required
            style="background:var(--s2);border:1.5px dashed var(--b2);border-radius:8px;padding:14px;cursor:pointer;width:100%">
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('inc-import-modal')">Cancel</button>
        <button type="submit" class="btn acc">📤 Import</button>
      </div>
    </form>
  </div>
</div>

@push('scripts')
<script>
function openIncModal(empId, name, amount, reason, month) {
  document.getElementById('inc-modal-title').textContent = 'Incentive — ' + name;
  document.getElementById('inc-emp-id').value   = empId;
  document.getElementById('inc-month').value    = month || '{{ $month }}';
  document.getElementById('inc-amount').value   = amount || '';
  document.getElementById('inc-reason').value   = reason || '';
  om('inc-modal');
}
</script>
@endpush
@endsection
