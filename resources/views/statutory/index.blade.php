@extends('layouts.app')
@section('title', 'Statutory Compliance')

@section('page_actions')
  <button class="btn acc" onclick="om('challan-modal')">+ Add Challan</button>
  <button class="btn" onclick="om('upload-modal')">📤 Bulk Upload</button>
@endsection

@section('content')

{{-- Summary cards --}}
<div class="g4" style="margin-bottom:16px">
  @foreach(['pf'=>'PF','esic'=>'ESIC','pt'=>'PT','tds'=>'TDS'] as $key => $label)
  @php $row = $annualTotals->firstWhere('challan_type', $key); @endphp
  <div class="sc">
    <div class="lbl">{{ $label }} (FY {{ now()->year }})</div>
    <div class="val" style="font-size:15px">₹ {{ number_format($row?->total_amount ?? 0, 0, '.', ',') }}</div>
    <div class="sub">{{ $row?->paid_count ?? 0 }}/{{ $row?->count ?? 0 }} paid</div>
  </div>
  @endforeach
</div>

@if($overdue->isNotEmpty())
<div class="card" style="margin-bottom:16px;border:1px solid var(--red);background:var(--red2)">
  <div class="ch"><div class="ct" style="color:var(--red)">⚠ Overdue Challans ({{ $overdue->count() }})</div></div>
  @foreach($overdue as $ch)
  <div style="padding:6px 0;border-bottom:1px solid rgba(220,38,38,.2);display:flex;justify-content:space-between;align-items:center">
    <span>{{ strtoupper($ch->challan_type) }} — {{ $ch->year }}/{{ str_pad($ch->month,2,'0',STR_PAD_LEFT) }}</span>
    <span>₹ {{ number_format($ch->amount,0,'.',',') }}</span>
    <span style="color:var(--red);font-size:11px">Due: {{ \Carbon\Carbon::parse($ch->due_date)->format('d M Y') }}</span>
    <button class="btn sm" onclick="openPaid({{ $ch->id }})">Mark Paid</button>
  </div>
  @endforeach
</div>
@endif

@if($dueThisMonth->isNotEmpty())
<div class="card" style="margin-bottom:16px;border:1px solid var(--yel)">
  <div class="ch"><div class="ct" style="color:var(--yel)">Due This Month ({{ $dueThisMonth->count() }})</div></div>
  @foreach($dueThisMonth as $ch)
  <div style="padding:6px 0;border-bottom:1px solid var(--b1);display:flex;justify-content:space-between;align-items:center">
    <span>{{ strtoupper($ch->challan_type) }} — {{ $ch->year }}/{{ str_pad($ch->month,2,'0',STR_PAD_LEFT) }}</span>
    <span>₹ {{ number_format($ch->amount,0,'.',',') }}</span>
    <span style="font-size:11px;color:var(--t3)">Due: {{ \Carbon\Carbon::parse($ch->due_date)->format('d M Y') }}</span>
    <button class="btn sm" onclick="openPaid({{ $ch->id }})">Mark Paid</button>
  </div>
  @endforeach
</div>
@endif

<div class="card">
  <div class="ch"><div class="ct">All Challans</div></div>
  @if($challans->isNotEmpty())
  <table>
    <thead>
      <tr><th>Type</th><th>Period</th><th>Amount</th><th>Due Date</th><th>Status</th><th>Actions</th></tr>
    </thead>
    <tbody>
      @foreach($challans as $ch)
      <tr>
        <td><span class="bx">{{ strtoupper($ch->challan_type) }}</span></td>
        <td>{{ $ch->year }}/{{ str_pad($ch->month,2,'0',STR_PAD_LEFT) }}</td>
        <td>₹ {{ number_format($ch->amount,0,'.',',') }}</td>
        <td>{{ \Carbon\Carbon::parse($ch->due_date)->format('d M Y') }}</td>
        <td>
          @php $colors = ['pending'=>'var(--yel)','paid'=>'var(--grn)','overdue'=>'var(--red)']; @endphp
          <span class="bx" style="color:{{ $colors[$ch->status] ?? 'var(--t3)' }}">{{ ucfirst($ch->status) }}</span>
        </td>
        <td>
          @if($ch->status === 'pending')
          <button class="btn sm" onclick="openPaid({{ $ch->id }})">Mark Paid</button>
          @endif
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
  <div style="margin-top:14px">{{ $challans->links() }}</div>
  @else
  <div class="empty"><div class="ico">📋</div><p>No challans recorded yet.</p></div>
  @endif
</div>

{{-- Add Challan Modal --}}
<div class="modal" id="challan-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Add Challan</span>
      <button class="mc" onclick="cm('challan-modal')">✕</button>
    </div>
    <form method="POST" action="{{ route('statutory.challan') }}">
      @csrf
      <div class="fg2">
        <div class="fld">
          <label>Challan Type *</label>
          <select name="challan_type" required>
            <option value="pf">PF</option>
            <option value="esic">ESIC</option>
            <option value="pt">PT</option>
            <option value="tds">TDS</option>
            <option value="lwf">LWF</option>
          </select>
        </div>
        <div class="fld">
          <label>Amount (₹) *</label>
          <input type="number" name="amount" required min="0" step="0.01">
        </div>
        <div class="fld">
          <label>Year *</label>
          <input type="number" name="year" required value="{{ now()->year }}">
        </div>
        <div class="fld">
          <label>Month *</label>
          <select name="month" required>
            @foreach(['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'] as $i => $m)
            <option value="{{ $i+1 }}" {{ now()->month == $i+1 ? 'selected' : '' }}>{{ $m }}</option>
            @endforeach
          </select>
        </div>
        <div class="fld full">
          <label>Due Date *</label>
          <input type="date" name="due_date" required>
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('challan-modal')">Cancel</button>
        <button type="submit" class="btn acc">Add</button>
      </div>
    </form>
  </div>
</div>

{{-- Mark Paid Modal --}}
<div class="modal" id="paid-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Mark Challan Paid</span>
      <button class="mc" onclick="cm('paid-modal')">✕</button>
    </div>
    <form method="POST" id="paid-form">
      @csrf
      <div class="fg2">
        <div class="fld">
          <label>Challan Number *</label>
          <input type="text" name="challan_number" required>
        </div>
        <div class="fld">
          <label>Payment Date *</label>
          <input type="date" name="payment_date" required value="{{ now()->toDateString() }}">
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('paid-modal')">Cancel</button>
        <button type="submit" class="btn acc">Confirm Paid</button>
      </div>
    </form>
  </div>
</div>
@endsection

@include('partials.upload-modal', [
    'uploadId'    => 'upload-modal',
    'uploadTitle' => 'Bulk Upload Challans',
    'uploadRoute' => 'statutory.challan',
    'uploadHint'  => 'Template columns: Challan Type (pf/esic/pt/tds/lwf), Year, Month, Amount, Due Date (YYYY-MM-DD). One row per challan.',
])

@push('scripts')
<script>
function openPaid(id) {
  document.getElementById('paid-form').action = '/statutory/challan/' + id + '/paid';
  om('paid-modal');
}
</script>
@endpush
