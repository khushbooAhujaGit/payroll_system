@extends('layouts.app')
@section('title', 'Incentives')

@section('page_actions')
  <a href="{{ route('incentives.template') }}" class="btn">📥 Template</a>
  <button class="btn" onclick="om('inc-import-modal')">📤 Import</button>
  <a href="{{ route('incentives.export') . '?' . http_build_query(request()->only('month')) }}" class="btn">⬇ Export</a>
  <button class="btn acc" onclick="openIncModal()">+ Add Incentive</button>
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
    <div class="lbl">Records This Month</div>
    <div class="val">{{ $records->total() }}</div>
  </div>
  <div class="sc">
    <div class="lbl">Total Incentive (₹)</div>
    <div class="val" style="color:var(--grn)">{{ number_format($totalIncentive, 0) }}</div>
  </div>
  <div class="sc">
    <div class="lbl">Month</div>
    <div class="val" style="font-size:14px">{{ \Carbon\Carbon::createFromFormat('Y-m',$month)->format('M Y') }}</div>
  </div>
  <div class="sc">
    <div class="lbl">Actions</div>
    <div class="val" style="font-size:12px">
      <button class="btn sm acc" onclick="openIncModal()">+ Add</button>
    </div>
  </div>
</div>

<div class="card">
  <div class="ch">
    <div class="ct">Incentives — {{ \Carbon\Carbon::createFromFormat('Y-m',$month)->format('M Y') }}</div>
    <div style="font-size:11px;color:var(--t3)">{{ $records->total() }} record(s)</div>
  </div>

  @if($records->isNotEmpty())
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
      @foreach($records as $inc)
      @php $emp = $inc->employee; @endphp
      <tr>
        <td class="mono">{{ $emp?->employee_id ?? '—' }}</td>
        <td>{{ $emp?->full_name }}</td>
        <td style="text-align:right;font-weight:600;color:var(--grn)">
          ₹ {{ number_format($inc->amount, 0) }}
        </td>
        <td style="font-size:11px;color:var(--t2)">{{ $inc->reason ?? '—' }}</td>
        <td style="white-space:nowrap">
          <button class="btn sm"
            onclick="openIncModal({{ $inc->employee_id }}, '{{ addslashes($emp?->full_name) }}', {{ $inc->amount }}, '{{ addslashes($inc->reason ?? '') }}', '{{ $inc->month }}')">
            Edit
          </button>
          <form method="POST" action="{{ route('incentives.destroy', $inc) }}" style="display:inline">
            @csrf @method('DELETE')
            <button type="button" class="btn sm" style="color:var(--red)"
              onclick="swConfirm(this.closest('form'), 'Remove Incentive?', 'The incentive for {{ $emp?->first_name }} will be permanently removed.', 'warning', 'Yes, remove', '#dc2626')">Del</button>
          </form>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
  <div style="margin-top:14px">{{ $records->withQueryString()->links() }}</div>
  @else
  <div class="empty">
    <div class="ico">💰</div>
    <p>No incentive records for {{ \Carbon\Carbon::createFromFormat('Y-m',$month)->format('M Y') }}.</p>
    <p style="font-size:12px;color:var(--t3);margin-top:4px">
      Click <strong>+ Add Incentive</strong> to enter one, or <strong>📤 Import</strong> to bulk-upload.
    </p>
  </div>
  @endif
</div>

{{-- Add / Edit Modal --}}
<div class="modal" id="inc-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt" id="inc-modal-title">Add Incentive</span>
      <button class="mc" onclick="cm('inc-modal')">✕</button>
    </div>
    <form method="POST" action="{{ route('incentives.upsert') }}">
      @csrf
      <input type="hidden" name="employee_id" id="inc-emp-id">
      <div class="fg2">
        <div class="fld full" id="inc-emp-select-wrap">
          <label class="req">Employee</label>
          <select name="_emp_select" id="inc-emp-select" onchange="incEmpChanged(this)"
            style="width:100%">
            <option value="">— Select employee —</option>
            @foreach($allEmployees as $emp)
            <option value="{{ $emp->id }}" data-name="{{ $emp->full_name }}">
              {{ $emp->employee_id }} — {{ $emp->full_name }}
            </option>
            @endforeach
          </select>
        </div>
        <div class="fld" id="inc-emp-display" style="display:none">
          <label>Employee</label>
          <div id="inc-emp-name-display" style="font-weight:600;padding:6px 0;color:var(--t1)"></div>
        </div>
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
// empId=null → Add mode (show employee select dropdown)
// empId set  → Edit mode (show employee name, hide select)
function openIncModal(empId, name, amount, reason, month) {
  const isEdit = !!empId;
  document.getElementById('inc-modal-title').textContent = isEdit ? ('Incentive — ' + name) : 'Add Incentive';
  document.getElementById('inc-emp-id').value   = empId || '';
  document.getElementById('inc-month').value    = month || '{{ $month }}';
  document.getElementById('inc-amount').value   = amount || '';
  document.getElementById('inc-reason').value   = reason || '';

  // Toggle employee selector vs display
  document.getElementById('inc-emp-select-wrap').style.display = isEdit ? 'none' : '';
  document.getElementById('inc-emp-display').style.display     = isEdit ? '' : 'none';
  if (isEdit) {
    document.getElementById('inc-emp-name-display').textContent = name || '';
    document.getElementById('inc-emp-select').value = '';
  } else {
    document.getElementById('inc-emp-select').value = '';
  }
  om('inc-modal');
}

function incEmpChanged(sel) {
  document.getElementById('inc-emp-id').value = sel.value;
}
</script>
@endpush
@endsection
