@extends('layouts.app')
@section('title', 'Loans & Advances')

@section('page_actions')
  <button class="btn acc" onclick="om('loan-modal')">+ Add Advance</button>
  <button class="btn" onclick="om('upload-modal')">📤 Bulk Upload</button>
@endsection

@section('content')

<div class="g4" style="margin-bottom:16px">
  <div class="sc"><div class="lbl">Total Loans</div><div class="val">{{ $stats['total'] }}</div></div>
  <div class="sc"><div class="lbl">Active</div><div class="val" style="color:var(--grn)">{{ $stats['active'] }}</div></div>
  <div class="sc"><div class="lbl">Pending Approval</div><div class="val" style="color:var(--yel)">{{ $stats['pending'] }}</div></div>
  <div class="sc"><div class="lbl">Outstanding</div><div class="val" style="font-size:14px">₹ {{ number_format($stats['outstanding'],0,'.',',') }}</div></div>
</div>

<form method="GET" action="{{ route('loans') }}" class="card" data-ajax-filter
      style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end">
  <div class="fld" style="min-width:140px">
    <label>Status</label>
    <select name="status">
      <option value="">All</option>
      <option value="pending"  {{ request('status') === 'pending'  ? 'selected' : '' }}>Pending</option>
      <option value="active"   {{ request('status') === 'active'   ? 'selected' : '' }}>Active</option>
      <option value="closed"   {{ request('status') === 'closed'   ? 'selected' : '' }}>Closed</option>
      <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
    </select>
  </div>
  <button type="submit" class="btn acc">Filter</button>
  <a href="{{ route('loans') }}" class="btn" data-ajax-reset>Reset</a>
</form>

<div id="ajax-results">
<div class="card">
  @if($loans->isNotEmpty())
  <table>
    <thead>
      <tr>
        <th>Loan #</th>
        <th>Employee</th>
        <th>Type</th>
        <th>Amount</th>
        <th>Deduction</th>
        <th>Progress</th>
        <th>Outstanding</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @foreach($loans as $loan)
      @php
        $paidCount    = $loan->installments->where('status', 'paid')->count();
        $pendCount    = $loan->installments->where('status', 'pending')->count();
        $hasSchedule  = $loan->installments->count() > 0;
        $paidAmount   = (float)$loan->loan_amount - (float)$loan->outstanding_amount;
        $progressPct  = $loan->loan_amount > 0
          ? min(100, round($paidAmount / (float)$loan->loan_amount * 100))
          : 0;
        $colors = ['pending'=>'var(--yel)','active'=>'var(--grn)','rejected'=>'var(--red)','closed'=>'var(--t3)'];

        // For percent-based loans: compute estimated EMIs from the employee's actual salary
        $estMonthlyDed = 0.0;
        $estTotalEmis  = $loan->total_emis;
        $estPaidEmis   = $paidCount;
        if ($loan->percent_deduction > 0 && $salaryMap->has($loan->employee_id)) {
            $gross         = $salaryMap->get($loan->employee_id)->getGrossDecrypted();
            $estMonthlyDed = round($gross * (float)$loan->percent_deduction / 100, 2);
            if ($estMonthlyDed > 0) {
                $estTotalEmis = (int) ceil((float)$loan->loan_amount / $estMonthlyDed);
                $estPaidEmis  = $estMonthlyDed > 0 ? (int) floor($paidAmount / $estMonthlyDed) : $paidCount;
            }
        }
      @endphp
      <tr>
        <td class="mono" style="font-size:12px">{{ $loan->loan_number }}</td>
        <td style="white-space:nowrap">{{ $loan->employee?->full_name }}
          <div style="font-size:10px;color:var(--t3)">{{ $loan->employee?->employee_id }}</div>
        </td>
        <td style="font-size:11px">{{ $loan->loanType?->name ?? 'Advance' }}</td>
        <td style="font-weight:600">₹ {{ number_format($loan->loan_amount,0,'.',',') }}</td>
        <td style="font-size:12px">
          @if($loan->percent_deduction > 0)
            <span class="bx" style="background:var(--pur2);color:var(--pur);font-size:11px">
              {{ $loan->percent_deduction }}% of salary
            </span>
          @else
            ₹ {{ number_format($loan->emi_amount,0,'.',',') }}/mo
          @endif
        </td>
        <td style="min-width:145px">
          @if($loan->percent_deduction > 0)
            {{-- Percent-based: show salary-derived estimated EMI count --}}
            <div style="font-size:10px;color:var(--t3);margin-bottom:3px">
              {{ $estPaidEmis }} / {{ $estTotalEmis }} EMIs paid
              @if($estMonthlyDed > 0)
                <span style="color:var(--t4)"> · ₹{{ number_format($estMonthlyDed,0) }}/mo</span>
              @endif
            </div>
            <div style="height:6px;background:var(--s3);border-radius:3px;overflow:hidden">
              <div style="height:100%;width:{{ $progressPct }}%;background:{{ $progressPct >= 100 ? 'var(--grn)' : 'var(--acc)' }};border-radius:3px;transition:width .3s"></div>
            </div>
          @elseif($hasSchedule)
            {{-- Fixed EMI with schedule --}}
            <div style="font-size:10px;color:var(--t3);margin-bottom:3px">
              {{ $paidCount }} / {{ $loan->total_emis }} EMIs paid
            </div>
            <div style="height:6px;background:var(--s3);border-radius:3px;overflow:hidden">
              <div style="height:100%;width:{{ $progressPct }}%;background:{{ $progressPct >= 100 ? 'var(--grn)' : 'var(--acc)' }};border-radius:3px;transition:width .3s"></div>
            </div>
          @elseif($loan->status === 'active')
            <div style="font-size:10px;color:var(--t3);margin-bottom:3px">
              ₹{{ number_format($paidAmount,0,'.',',') }} paid ({{ $progressPct }}%)
            </div>
            <div style="height:6px;background:var(--s3);border-radius:3px;overflow:hidden">
              <div style="height:100%;width:{{ $progressPct }}%;background:var(--acc);border-radius:3px"></div>
            </div>
          @else
            <span style="color:var(--t4);font-size:11px">—</span>
          @endif
        </td>
        <td>
          <span style="font-weight:600;color:{{ (float)$loan->outstanding_amount <= 0 ? 'var(--grn)' : 'var(--txt)' }}">
            ₹ {{ number_format($loan->outstanding_amount,0,'.',',') }}
          </span>
          @if($loan->outstanding_amount <= 0)
            <div style="font-size:10px;color:var(--grn)">Fully paid</div>
          @elseif($loan->status === 'active')
            <div style="font-size:10px;color:var(--t3)">
              ₹{{ number_format($paidAmount,0,'.',',') }} paid
            </div>
          @endif
        </td>
        <td>
          <span class="bx" style="color:{{ $colors[$loan->status] ?? 'var(--t3)' }}">{{ ucfirst($loan->status) }}</span>
        </td>
        <td style="white-space:nowrap">
          {{-- Approve/Reject (only for pending) --}}
          @if($loan->status === 'pending')
          <button class="btn sm" style="background:var(--grn2);color:var(--grn)" onclick="openApprove({{ $loan->id }})">Approve</button>
          <form method="POST" action="{{ route('loans.approve', $loan) }}" style="display:inline">
            @csrf
            <input type="hidden" name="action" value="rejected">
            <input type="hidden" name="disbursement_date" value="{{ now()->toDateString() }}">
            <button type="button" class="btn sm" style="background:var(--red2);color:var(--red)"
              onclick="swConfirm(this.closest('form'),'Reject Loan?','This loan application will be rejected.','warning','Yes, reject','#dc2626')">
              Reject
            </button>
          </form>
          @endif

          {{-- EMI Schedule tracker --}}
          @if($loan->status === 'active' || $loan->status === 'closed')
          <button class="btn sm" style="font-size:10px" onclick="openSchedule({{ $loan->id }})">📋 EMIs</button>
          @endif

          {{-- Edit --}}
          <button class="btn sm" style="font-size:10px"
            onclick="openLoanEdit(
              {{ $loan->id }},
              '{{ $loan->status }}',
              {{ (float)$loan->loan_amount }},
              {{ (int)$loan->total_emis }},
              '{{ $loan->first_emi_month?->format('Y-m') }}',
              {{ $loan->percent_deduction ?? 'null' }},
              @js(addslashes($loan->purpose ?? '')),
              {{ $paidCount > 0 ? 'true' : 'false' }}
            )">Edit</button>

          {{-- Delete --}}
          <form method="POST" action="{{ route('loans.destroy', $loan) }}" style="display:inline">
            @csrf @method('DELETE')
            @if($loan->status === 'active')
            <button type="button" class="btn sm" style="font-size:10px;color:var(--red)"
              onclick="swConfirm(this.closest('form'),'Delete Active Loan?','⚠ This loan is ACTIVE. Deleting it will remove all EMI records and cannot be undone. Any locked salary reports that already deducted EMIs will NOT be reversed automatically.','warning','Yes, delete permanently','#dc2626')">
              Del
            </button>
            @else
            <button type="button" class="btn sm" style="font-size:10px;color:var(--red)"
              onclick="swConfirm(this.closest('form'),'Delete Loan?','Loan {{ $loan->loan_number }} will be permanently removed.','warning','Yes, delete','#dc2626')">
              Del
            </button>
            @endif
          </form>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
  <div style="margin-top:14px">{{ $loans->links() }}</div>
  @else
  <div class="empty"><div class="ico">🏦</div><p>No loans found.</p></div>
  @endif
</div>
</div>

{{-- ════════════ ADD ADVANCE MODAL ════════════ --}}
<div class="modal" id="loan-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Add Advance / Loan</span>
      <button class="mc" onclick="cm('loan-modal')">✕</button>
    </div>
    <form method="POST" action="{{ route('loans.store') }}">
      @csrf
      <div class="fg2">
        <div class="fld full">
          <label class="req">Employee</label>
          <select name="employee_id" required @error('employee_id') class="inp-err" @enderror>
            <option value="">Select employee</option>
            @foreach($employees as $emp)
            <option value="{{ $emp->id }}" {{ old('employee_id') == $emp->id ? 'selected' : '' }}>{{ $emp->employee_id }} — {{ $emp->full_name }}</option>
            @endforeach
          </select>
          @error('employee_id') <div class="ferr">{{ $message }}</div> @enderror
        </div>
        <div class="fld">
          <label class="req">Start Month</label>
          <input type="month" name="first_emi_month" required value="{{ old('first_emi_month', now()->addMonth()->format('Y-m')) }}"
            @error('first_emi_month') class="inp-err" @enderror>
          @error('first_emi_month') <div class="ferr">{{ $message }}</div> @enderror
        </div>
        <div class="fld">
          <label class="req">Advance Amount (₹)</label>
          <input type="number" name="loan_amount" required min="1" step="1" value="{{ old('loan_amount') }}"
            @error('loan_amount') class="inp-err" @enderror>
          @error('loan_amount') <div class="ferr">{{ $message }}</div> @enderror
        </div>
        <div class="fld">
          <label>% Deduction from Salary</label>
          <input type="number" name="percent_deduction" id="add-pct" min="0" max="100" step="0.01"
            value="{{ old('percent_deduction') }}" placeholder="e.g. 10 for 10%" oninput="toggleEmiRequired()">
          <span style="font-size:10px;color:var(--t3)">Fill this → No. of EMIs auto-calculated from salary</span>
        </div>
        <div class="fld">
          <label id="add-emis-label">No. of EMI <span id="add-emis-req">*</span></label>
          <input type="number" name="total_emis" id="add-emis" min="1" max="360" value="{{ old('total_emis') }}"
            placeholder="Auto-calc when % is set"
            @error('total_emis') class="inp-err" @enderror>
          @error('total_emis') <div class="ferr">{{ $message }}</div> @enderror
        </div>
        <div class="fld full">
          <label>Remark / Purpose</label>
          <textarea name="purpose" rows="2" style="resize:vertical"
            placeholder="e.g. Medical emergency, festival advance">{{ old('purpose') }}</textarea>
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('loan-modal')">Cancel</button>
        <button type="submit" class="btn acc">Submit</button>
      </div>
    </form>
  </div>
</div>

{{-- ════════════ EDIT LOAN MODAL ════════════ --}}
<div class="modal" id="loan-edit-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Edit Loan / Advance</span>
      <button class="mc" onclick="cm('loan-edit-modal')">✕</button>
    </div>
    <form method="POST" id="loan-edit-form">
      @csrf
      {{-- Pending-only: loan amount + EMI count --}}
      <div id="le-pending-fields">
        <div class="fg2">
          <div class="fld">
            <label>Advance Amount (₹) *</label>
            <input type="number" name="loan_amount" id="le-amount" min="1" step="1">
          </div>
          <div class="fld">
            <label>No. of EMI *</label>
            <input type="number" name="total_emis" id="le-emis" min="1" max="360">
          </div>
        </div>
      </div>
      {{-- Start month: visible for pending OR active with no paid EMIs --}}
      <div id="le-month-field" style="margin-bottom:10px">
        <div class="fld">
          <label id="le-month-label">Start Month *</label>
          <input type="month" name="first_emi_month" id="le-month">
          <span id="le-month-hint" style="font-size:10px;color:var(--t3)"></span>
        </div>
      </div>
      <div class="fg2">
        <div class="fld full">
          <label>% Deduction from Salary</label>
          <input type="number" name="percent_deduction" id="le-pct" min="0" max="100" step="0.01"
            placeholder="e.g. 50 for 50% · leave blank for fixed EMI">
          <span style="font-size:10px;color:var(--t3)">
            Changing this will take effect in the next salary report.
          </span>
        </div>
        <div class="fld full">
          <label>Remark / Purpose</label>
          <textarea name="purpose" id="le-purpose" rows="2" style="resize:vertical"></textarea>
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('loan-edit-modal')">Cancel</button>
        <button type="submit" class="btn acc">Save Changes</button>
      </div>
    </form>
  </div>
</div>

{{-- ════════════ EMI SCHEDULE MODAL ════════════ --}}
<div class="modal" id="schedule-modal">
  <div class="mb" style="max-width:640px">
    <div class="mh">
      <span class="mt" id="sch-title">EMI Schedule</span>
      <button class="mc" onclick="cm('schedule-modal')">✕</button>
    </div>
    <div id="sch-summary" style="padding:10px 0 14px;border-bottom:1px solid var(--b1);margin-bottom:14px;display:flex;gap:20px;flex-wrap:wrap;font-size:13px"></div>
    <div id="sch-body" style="max-height:380px;overflow-y:auto">
      <div class="empty"><p>Loading…</p></div>
    </div>
    <div class="mf" style="margin-top:12px">
      <button type="button" class="btn" onclick="cm('schedule-modal')">Close</button>
    </div>
  </div>
</div>

{{-- ════════════ APPROVE MODAL ════════════ --}}
<div class="modal" id="approve-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Approve Loan</span>
      <button class="mc" onclick="cm('approve-modal')">✕</button>
    </div>
    <form method="POST" id="approve-form">
      @csrf
      <input type="hidden" name="action" value="approved">
      <div class="fld" style="margin-bottom:14px">
        <label>Disbursement Date *</label>
        <input type="date" name="disbursement_date" required value="{{ now()->toDateString() }}">
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('approve-modal')">Cancel</button>
        <button type="submit" class="btn acc">Approve &amp; Generate Schedule</button>
      </div>
    </form>
  </div>
</div>

@include('partials.upload-modal', [
    'uploadId'      => 'upload-modal',
    'uploadTitle'   => 'Bulk Upload Advances',
    'uploadRoute'   => 'loans.import',
    'templateRoute' => 'loans.template',
    'uploadHint'    => 'Template columns: Emp ID · Start Month (YYYY-MM) · Advance Amount · No. of EMI · % Deduction · Remark',
])

@if($errors->any())
<script>document.addEventListener('DOMContentLoaded',()=>om('loan-modal'))</script>
@endif

@endsection

@push('scripts')
<script>
function toggleEmiRequired() {
  var pct  = parseFloat(document.getElementById('add-pct').value) || 0;
  var emis = document.getElementById('add-emis');
  var req  = document.getElementById('add-emis-req');
  if (pct > 0) {
    emis.required = false;
    emis.placeholder = 'Auto-calculated from salary';
    req.textContent = '(auto)';
    req.style.color = 'var(--t3)';
  } else {
    emis.required = true;
    emis.placeholder = '';
    req.textContent = '*';
    req.style.color = '';
  }
}
// Initialise on page load
document.addEventListener('DOMContentLoaded', toggleEmiRequired);

function openApprove(id) {
  document.getElementById('approve-form').action = '/loans/' + id + '/approve';
  om('approve-modal');
}

const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

function openSchedule(id) {
  document.getElementById('sch-title').textContent = 'EMI Schedule';
  document.getElementById('sch-summary').innerHTML = '';
  document.getElementById('sch-body').innerHTML = '<div class="empty"><p>Loading…</p></div>';
  om('schedule-modal');

  fetch('/loans/' + id + '/schedule')
    .then(r => r.json())
    .then(data => {
      const loan = data.loan;
      const pct  = Math.round(((parseFloat(loan.loan_amount) - parseFloat(loan.outstanding_amount)) / parseFloat(loan.loan_amount)) * 100);

      document.getElementById('sch-title').textContent = 'EMI Schedule — ' + loan.loan_number;
      document.getElementById('sch-summary').innerHTML =
        '<div><span style="color:var(--t3);font-size:11px">Employee</span><br><b>' + loan.employee_name + '</b></div>' +
        '<div><span style="color:var(--t3);font-size:11px">Loan Amount</span><br><b>₹ ' + loanFmt(loan.loan_amount) + '</b></div>' +
        '<div><span style="color:var(--t3);font-size:11px">Outstanding</span><br><b style="color:' + (parseFloat(loan.outstanding_amount) <= 0 ? 'var(--grn)' : 'var(--txt)') + '">₹ ' + loanFmt(loan.outstanding_amount) + '</b></div>' +
        '<div><span style="color:var(--t3);font-size:11px">Progress</span><br>' +
          '<div style="height:6px;background:var(--s3);border-radius:3px;width:100px;overflow:hidden;margin-top:4px">' +
            '<div style="height:100%;width:' + pct + '%;background:' + (pct >= 100 ? 'var(--grn)' : 'var(--acc)') + ';border-radius:3px"></div>' +
          '</div>' +
          '<span style="font-size:10px;color:var(--t3)">' + pct + '% repaid</span>' +
        '</div>';

      if (!data.installments || data.installments.length === 0) {
        document.getElementById('sch-body').innerHTML = '<div class="empty"><p>No installment schedule found.</p></div>';
        return;
      }

      const statusColor = { paid: 'var(--grn)', pending: 'var(--yel)', due: 'var(--red)' };
      let rows = data.installments.map(i => {
        const mo  = MONTHS[(i.emi_month - 1)] + ' ' + i.emi_year;
        const amt = '₹ ' + loanFmt(i.total_emi_amount);
        const bal = '₹ ' + loanFmt(i.outstanding_after);
        const st  = i.status.charAt(0).toUpperCase() + i.status.slice(1);
        const col = statusColor[i.status] || 'var(--t3)';
        return '<tr>' +
          '<td class="mono" style="font-size:11px">#' + i.installment_number + '</td>' +
          '<td>' + mo + '</td>' +
          '<td style="font-weight:600">' + amt + '</td>' +
          '<td style="font-size:11px;color:var(--t3)">' + bal + '</td>' +
          '<td><span class="bx" style="color:' + col + '">' + st + '</span></td>' +
          '</tr>';
      }).join('');

      document.getElementById('sch-body').innerHTML =
        '<table><thead><tr><th>#</th><th>Month</th><th>Amount</th><th>Balance After</th><th>Status</th></tr></thead>' +
        '<tbody>' + rows + '</tbody></table>';
    })
    .catch(() => {
      document.getElementById('sch-body').innerHTML = '<div class="empty"><p>Failed to load schedule.</p></div>';
    });
}

function loanFmt(n) {
  return parseFloat(n).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

function openLoanEdit(id, status, amount, emis, month, pct, purpose, hasPaidEmis) {
  var form        = document.getElementById('loan-edit-form');
  var pendingFlds = document.getElementById('le-pending-fields');
  var monthFld    = document.getElementById('le-month-field');
  var monthLabel  = document.getElementById('le-month-label');
  var monthHint   = document.getElementById('le-month-hint');
  var isPending   = status === 'pending';
  var canEditMonth = isPending || (status === 'active' && !hasPaidEmis);

  form.action = '/loans/' + id;

  // Pending-only fields: loan amount and EMI count
  pendingFlds.style.display = isPending ? '' : 'none';
  document.getElementById('le-amount').required = isPending;
  document.getElementById('le-emis').required   = isPending;

  // Start month field: visible for pending OR active with no paid EMIs
  monthFld.style.display = canEditMonth ? '' : 'none';
  document.getElementById('le-month').required = isPending;

  if (isPending) {
    monthLabel.textContent = 'Start Month *';
    monthHint.textContent  = '';
    document.getElementById('le-amount').value = amount || '';
    document.getElementById('le-emis').value   = emis   || '';
  } else if (status === 'active' && !hasPaidEmis) {
    monthLabel.textContent = 'Start Month (reschedule)';
    monthHint.textContent  = 'Changing this will delete the existing schedule and regenerate it from the new start month.';
  }

  if (canEditMonth) {
    document.getElementById('le-month').value = month || '';
  }

  document.getElementById('le-pct').value     = pct != null ? pct : '';
  document.getElementById('le-purpose').value = purpose || '';

  om('loan-edit-modal');
}
</script>
@endpush
