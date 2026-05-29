@extends('layouts.app')
@section('title', 'Reimbursements')

@section('page_actions')
  <button class="btn acc" onclick="om('reimb-modal')">+ Submit Claim</button>
  <button class="btn" onclick="om('upload-modal')">📤 Bulk Upload</button>
@endsection

@section('content')

<form method="GET" action="{{ route('reimbursements') }}" class="card" style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end">
  <div class="fld" style="min-width:140px">
    <label>Status</label>
    <select name="status">
      <option value="">All</option>
      <option value="submitted" {{ request('status') === 'submitted' ? 'selected' : '' }}>Submitted</option>
      <option value="approved"  {{ request('status') === 'approved'  ? 'selected' : '' }}>Approved</option>
      <option value="rejected"  {{ request('status') === 'rejected'  ? 'selected' : '' }}>Rejected</option>
      <option value="paid"      {{ request('status') === 'paid'      ? 'selected' : '' }}>Paid</option>
    </select>
  </div>
  <button type="submit" class="btn acc">Filter</button>
  <a href="{{ route('reimbursements') }}" class="btn">Reset</a>
</form>

<div class="card">
  @if($claims->isNotEmpty())
  <table>
    <thead>
      <tr><th>Claim #</th><th>Employee</th><th>Month</th><th>Total Claimed</th><th>Status</th><th>Actions</th></tr>
    </thead>
    <tbody>
      @foreach($claims as $claim)
      <tr>
        <td class="mono">{{ $claim->claim_number }}</td>
        <td>{{ $claim->employee?->first_name }} {{ $claim->employee?->last_name }}<br>
            <span style="font-size:10px;color:var(--t3)">{{ $claim->employee?->department?->name }}</span></td>
        <td>{{ \Carbon\Carbon::createFromDate($claim->year, $claim->month, 1)->format('M Y') }}</td>
        <td>₹ {{ number_format($claim->total_claimed, 2) }}</td>
        <td>
          @php $colors = ['submitted'=>'var(--yel)','approved'=>'var(--grn)','rejected'=>'var(--red)','paid'=>'var(--pur)']; @endphp
          <span class="bx" style="color:{{ $colors[$claim->status] ?? 'var(--t3)' }}">{{ ucfirst($claim->status) }}</span>
        </td>
        <td>
          @if($claim->status === 'submitted')
          <form method="POST" action="{{ route('reimbursements.approve', $claim) }}" style="display:inline">
            @csrf
            <input type="hidden" name="action" value="approved">
            <button type="submit" class="btn sm" style="background:var(--grn2);color:var(--grn)">Approve</button>
          </form>
          <form method="POST" action="{{ route('reimbursements.approve', $claim) }}" style="display:inline">
            @csrf
            <input type="hidden" name="action" value="rejected">
            <button type="submit" class="btn sm" style="background:var(--red2);color:var(--red)">Reject</button>
          </form>
          @endif
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
  <div style="margin-top:14px">{{ $claims->links() }}</div>
  @else
  <div class="empty"><div class="ico">💳</div><p>No reimbursement claims found.</p></div>
  @endif
</div>

{{-- Submit Claim Modal --}}
<div class="modal" id="reimb-modal">
  <div class="mb wide">
    <div class="mh">
      <span class="mt">Submit Reimbursement Claim</span>
      <button class="mc" onclick="cm('reimb-modal')">✕</button>
    </div>
    <form method="POST" action="{{ route('reimbursements.store') }}">
      @csrf
      <div class="fg2">
        <div class="fld full">
          <label>Employee (leave blank for self)</label>
          <select name="employee_id">
            <option value="">— Self —</option>
            @foreach($employees as $emp)
            <option value="{{ $emp->id }}">{{ $emp->first_name }} {{ $emp->last_name }} ({{ $emp->employee_id }})</option>
            @endforeach
          </select>
        </div>
        <div class="fld">
          <label class="req">Year</label>
          <input type="number" name="year" required value="{{ old('year', now()->year) }}"
            @error('year') class="inp-err" @enderror>
          @error('year') <div class="ferr">{{ $message }}</div> @enderror
        </div>
        <div class="fld">
          <label class="req">Month</label>
          <select name="month" required @error('month') class="inp-err" @enderror>
            @foreach(['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'] as $i => $m)
            <option value="{{ $i+1 }}" {{ (old('month', now()->month) == $i+1) ? 'selected' : '' }}>{{ $m }}</option>
            @endforeach
          </select>
          @error('month') <div class="ferr">{{ $message }}</div> @enderror
        </div>
        <div class="fld full" style="border-top:1px solid var(--b1);padding-top:12px;margin-top:4px">
          <label>Expense Items</label>
          <div id="items-container">
            <div class="fg2 reimb-item" style="margin-bottom:8px;align-items:flex-start">
              <div class="fld">
                <select name="items[0][reimbursement_type_id]" required>
                  <option value="">Type</option>
                  @foreach($types as $t)
                  <option value="{{ $t->id }}">{{ $t->name }}</option>
                  @endforeach
                </select>
              </div>
              <div class="fld"><input type="date" name="items[0][expense_date]" required value="{{ now()->toDateString() }}"></div>
              <div class="fld"><input type="text" name="items[0][description]" placeholder="Description" required></div>
              <div class="fld"><input type="number" name="items[0][claimed_amount]" placeholder="Amount ₹" required min="0.01" step="0.01"></div>
              <div class="remove-btn-wrap" style="padding-top:6px;visibility:hidden"><button type="button" class="btn sm" style="color:var(--red);padding:6px 10px" onclick="removeItem(this)" title="Remove item">✕</button></div>
            </div>
          </div>
          <button type="button" class="btn sm" onclick="addItem()" style="margin-top:6px">+ Add Item</button>
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('reimb-modal')">Cancel</button>
        <button type="submit" class="btn acc">Submit</button>
      </div>
    </form>
  </div>
</div>

@include('partials.upload-modal', [
    'uploadId'    => 'upload-modal',
    'uploadTitle' => 'Bulk Upload Reimbursement Claims',
    'uploadRoute' => 'reimbursements.store',
    'uploadHint'  => 'Template columns: Employee ID, Year, Month, Expense Type Code, Expense Date (YYYY-MM-DD), Description, Amount. One row per expense item.',
])

@if($errors->any())
<script>document.addEventListener('DOMContentLoaded',()=>om('reimb-modal'))</script>
@endif
@endsection

@push('scripts')
<script>
let _itemIdx = 1;
const _types = @json($types->map(fn($t) => ['id'=>$t->id,'name'=>$t->name]));

function addItem() {
  const i = _itemIdx++;
  const div = document.createElement('div');
  div.className = 'fg2 reimb-item';
  div.style.marginBottom = '8px';
  div.style.alignItems = 'flex-start';
  div.innerHTML = `
    <div class="fld"><select name="items[${i}][reimbursement_type_id]" required>
      <option value="">Type</option>
      ${_types.map(t=>`<option value="${t.id}">${t.name}</option>`).join('')}
    </select></div>
    <div class="fld"><input type="date" name="items[${i}][expense_date]" required value="{{ now()->toDateString() }}"></div>
    <div class="fld"><input type="text" name="items[${i}][description]" placeholder="Description" required></div>
    <div class="fld"><input type="number" name="items[${i}][claimed_amount]" placeholder="Amount ₹" required min="0.01" step="0.01"></div>
    <div class="remove-btn-wrap" style="padding-top:6px"><button type="button" class="btn sm" style="color:var(--red);padding:6px 10px" onclick="removeItem(this)" title="Remove item">✕</button></div>
  `;
  document.getElementById('items-container').appendChild(div);
  enforceMinItems();
}

function removeItem(btn) {
  const container = document.getElementById('items-container');
  const items = container.querySelectorAll('.reimb-item');
  if (items.length <= 1) return; // always keep at least 1 item
  btn.closest('.reimb-item').remove();
  enforceMinItems();
}

function enforceMinItems() {
  const items = document.getElementById('items-container').querySelectorAll('.reimb-item');
  const single = items.length <= 1;
  items.forEach(item => {
    const wrap = item.querySelector('.remove-btn-wrap');
    if (wrap) wrap.style.visibility = single ? 'hidden' : 'visible';
  });
}

document.addEventListener('DOMContentLoaded', enforceMinItems);
</script>
@endpush
