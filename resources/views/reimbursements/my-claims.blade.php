@extends('layouts.app')
@section('title', 'My Reimbursements')

@section('page_actions')
  <button class="btn acc" onclick="om('submit-claim-modal')">+ Submit Claim</button>
@endsection

@section('content')

<div class="card">
  <div class="ch"><div class="ct">My Claims</div></div>
  @if($claims->isNotEmpty())
  <div class="tw">
  <table>
    <thead>
      <tr>
        <th>Date</th>
        <th>Amount</th>
        <th>Category</th>
        <th>Remarks</th>
        <th>Document</th>
        <th>Approval Status</th>
        <th>Payment Status</th>
      </tr>
    </thead>
    <tbody>
      @foreach($claims as $claim)
      <tr>
        <td>{{ $claim->expense_date ? \Carbon\Carbon::parse($claim->expense_date)->format('d M Y') : '—' }}</td>
        <td style="font-weight:600">₹{{ number_format($claim->amount, 2) }}</td>
        <td style="color:var(--t2);font-size:12px" title="{{ $claim->category === 'Other' ? $claim->other_category_text : '' }}">
          {{ $claim->category ?? '—' }}{{ $claim->category === 'Other' && $claim->other_category_text ? ' — ' . $claim->other_category_text : '' }}
        </td>
        <td style="color:var(--t2);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $claim->remarks }}">
          {{ $claim->remarks ?? '—' }}
        </td>
        <td>
          @if($claim->document_path)
            <a href="{{ route('reimbursements.tracker.document', $claim) }}" target="_blank" class="btn sm">View</a>
          @else
            <span style="color:var(--t3)">—</span>
          @endif
        </td>
        <td>
          <span class="bx {{ $claim->status === 'approved' ? 'g' : ($claim->status === 'rejected' ? 'r' : 'y') }}">
            {{ ucfirst($claim->status) }}
          </span>
          @if($claim->status !== 'pending' && $claim->approval_remarks)
            <div style="font-size:11px;color:var(--t3);margin-top:3px">{{ $claim->approval_remarks }}</div>
          @endif
        </td>
        <td>
          @if($claim->status === 'approved')
            <span class="bx {{ $claim->payment_status === 'paid' ? 'g' : 'gr' }}">{{ ucfirst($claim->payment_status) }}</span>
            @if($claim->payment_status === 'paid' && $claim->payment_date)
              <div style="font-size:11px;color:var(--t3);margin-top:3px">
                {{ \Carbon\Carbon::parse($claim->payment_date)->format('d M Y') }}
                @if($claim->payment_reference) · {{ $claim->payment_reference }} @endif
              </div>
            @endif
          @else
            <span style="color:var(--t3)">—</span>
          @endif
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
  </div>
  <div style="margin-top:14px">{{ $claims->links() }}</div>
  @else
  <div class="empty"><div class="ico">💳</div><p>No claims submitted yet.</p></div>
  @endif
</div>

{{-- Submit Claim Modal --}}
<div class="modal" id="submit-claim-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Submit Reimbursement Claim</span>
      <button class="mc" onclick="cm('submit-claim-modal')">✕</button>
    </div>
    <form method="POST" action="{{ route('my-reimbursements.submit') }}" enctype="multipart/form-data">
      @csrf
      <div class="fg2">
        <div class="fld">
          <label class="req">Expense Date</label>
          <input type="date" name="expense_date" required max="{{ now()->toDateString() }}" value="{{ old('expense_date', now()->toDateString()) }}">
        </div>
        <div class="fld">
          <label class="req">Amount (₹)</label>
          <input type="number" name="amount" required min="0.01" step="0.01" value="{{ old('amount') }}">
        </div>
        <div class="fld full">
          <label class="req">Category</label>
          <select name="category" required onchange="toggleOtherCategory(this)" @error('category') class="inp-err" @enderror>
            <option value="">Select category</option>
            @foreach(\App\Models\ReimbursementTrackerRow::CATEGORIES as $cat)
            <option value="{{ $cat }}" {{ old('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
            @endforeach
          </select>
          @error('category') <div class="ferr">{{ $message }}</div> @enderror
        </div>
        <div class="fld full" id="other-category-wrap" style="{{ old('category') === 'Other' ? '' : 'display:none' }}">
          <label class="req">Please specify</label>
          <input type="text" name="other_category_text" maxlength="150" placeholder="What is this expense for?"
            value="{{ old('other_category_text') }}" @error('other_category_text') class="inp-err" @enderror>
          @error('other_category_text') <div class="ferr">{{ $message }}</div> @enderror
        </div>
        <div class="fld full">
          <label>Remarks</label>
          <input type="text" name="remarks" placeholder="e.g. Client visit travel" value="{{ old('remarks') }}">
        </div>
        <div class="fld full">
          <label class="req">Supporting Document</label>
          <input type="file" name="document" accept=".jpg,.jpeg,.png,.pdf" required>
          <div style="font-size:11px;color:var(--t3);margin-top:4px">JPG, PNG, or PDF — max 5 MB.</div>
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('submit-claim-modal')">Cancel</button>
        <button type="submit" class="btn acc">Submit</button>
      </div>
    </form>
  </div>
</div>

@if($errors->any())
<script>document.addEventListener('DOMContentLoaded', () => om('submit-claim-modal'))</script>
@endif

@push('scripts')
<script>
function toggleOtherCategory(select) {
  const wrap  = document.getElementById('other-category-wrap');
  const input = wrap.querySelector('input[name="other_category_text"]');
  const show  = select.value === 'Other';
  wrap.style.display = show ? '' : 'none';
  if (!show) input.value = '';
}
</script>
@endpush

@endsection
