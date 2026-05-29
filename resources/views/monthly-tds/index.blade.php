@extends('layouts.app')
@section('title', 'Monthly TDS')

@section('page_actions')
  <a href="{{ route('tds.monthly.template') }}" class="btn">📥 Template</a>
  <button class="btn" onclick="om('tds-import-modal')">📤 Import</button>
@endsection

@section('content')

  {{-- Month selector --}}
  <form method="GET" action="{{ route('tds.monthly') }}" class="card"
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
      <div class="lbl">With TDS</div>
      <div class="val" style="color:var(--acc)">{{ $tdsRecords->count() }}</div>
    </div>
    <div class="sc">
      <div class="lbl">Total TDS (₹)</div>
      <div class="val">{{ number_format($totalTds, 0) }}</div>
    </div>
    <div class="sc">
      <div class="lbl">Month</div>
      <div class="val" style="font-size:14px">{{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('M Y') }}</div>
    </div>
  </div>

  <div class="card">
    <div class="ch">
      <div class="ct">Monthly TDS Deductions — {{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('M Y') }}</div>
      <div style="font-size:11px;color:var(--t3)">Click <strong>Add</strong> next to an employee to enter their TDS
        deduction for this month</div>
    </div>
    <table>
      <thead>
        <tr>
          <th>Emp ID</th>
          <th>Employee</th>
          <th style="text-align:right">TDS (₹)</th>
          <th>Remarks</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @foreach($employees as $emp)
          @php $tds = $tdsRecords[$emp->id] ?? null; @endphp
          <tr>
            <td class="mono">{{ $emp->employee_id }}</td>
            <td>{{ $emp->first_name }} {{ $emp->last_name }}</td>
            <td
              style="text-align:right;font-weight:{{ $tds ? '600' : '400' }};color:{{ $tds ? 'var(--acc)' : 'var(--t3)' }}">
              {{ $tds ? '₹ ' . number_format($tds->amount, 0) : '—' }}
            </td>
            <td style="font-size:11px;color:var(--t2)">{{ $tds?->remarks ?? '—' }}</td>
            <td style="white-space:nowrap">
              <button class="btn sm"
                onclick="openTdsModal({{ $emp->id }}, '{{ addslashes($emp->first_name) }} {{ addslashes($emp->last_name) }}', {{ $tds?->amount ?? 0 }}, '{{ addslashes($tds?->remarks ?? '') }}', '{{ $tds?->month ?? $month }}')">
                {{ $tds ? 'Edit' : 'Add' }}
              </button>
              @if($tds)
                <form method="POST" action="{{ route('tds.monthly.destroy', $tds) }}" style="display:inline">
                  @csrf @method('DELETE')
                  <button type="button" class="btn sm" style="color:var(--red)"
                    onclick="swConfirm(this.closest('form'), 'Remove TDS?', 'Monthly TDS for {{ $emp->first_name }} will be permanently removed.', 'warning', 'Yes, remove', '#dc2626')">Del</button>
                </form>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  {{-- Set / Edit Modal --}}
  <div class="modal" id="tds-modal">
    <div class="mb">
      <div class="mh">
        <span class="mt" id="tds-modal-title">Set Monthly TDS</span>
        <button class="mc" onclick="cm('tds-modal')">✕</button>
      </div>
      <form method="POST" action="{{ route('tds.monthly.upsert') }}">
        @csrf
        <input type="hidden" name="employee_id" id="tds-emp-id">
        <div class="fg2">
          <div class="fld">
            <label class="req">Month</label>
            <input type="month" name="month" id="tds-month" required value="{{ $month }}"
              @error('month') class="inp-err" @enderror>
            @error('month') <div class="ferr">{{ $message }}</div> @enderror
          </div>
          <div class="fld">
            <label class="req">TDS Amount (₹)</label>
            <input type="number" name="amount" id="tds-amount" required min="0" step="0.01"
              @error('amount') class="inp-err" @enderror>
            @error('amount') <div class="ferr">{{ $message }}</div> @enderror
          </div>
          <div class="fld full">
            <label>Remarks</label>
            <input type="text" name="remarks" id="tds-remarks" maxlength="200" placeholder="e.g. Computed from ITR">
          </div>
        </div>
        <div class="mf">
          <button type="button" class="btn" onclick="cm('tds-modal')">Cancel</button>
          <button type="submit" class="btn acc">Save</button>
        </div>
      </form>
    </div>
  </div>

  {{-- Import Modal --}}
  <div class="modal" id="tds-import-modal">
    <div class="mb">
      <div class="mh">
        <span class="mt">Import Monthly TDS</span>
        <button class="mc" onclick="cm('tds-import-modal')">✕</button>
      </div>
      <div style="background:var(--acc2);border:1px solid var(--acc);border-radius:8px;
                  padding:12px 14px;margin-bottom:14px;font-size:12px;line-height:1.6;color:var(--t2)">
        Template columns: <span class="mono">Emp ID · TDS Amount · Remarks</span>
      </div>
      <form method="POST" action="{{ route('tds.monthly.import') }}" enctype="multipart/form-data">
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
          <button type="button" class="btn" onclick="cm('tds-import-modal')">Cancel</button>
          <button type="submit" class="btn acc">📤 Import</button>
        </div>
      </form>
    </div>
  </div>

  @push('scripts')
    <script>
      function openTdsModal(empId, name, amount, remarks, month) {
        document.getElementById('tds-modal-title').textContent = 'Monthly TDS — ' + name;
        document.getElementById('tds-emp-id').value = empId;
        document.getElementById('tds-month').value = month || '{{ $month }}';
        document.getElementById('tds-amount').value = amount || '';
        document.getElementById('tds-remarks').value = remarks || '';
        om('tds-modal');
      }
    </script>
  @endpush
@endsection