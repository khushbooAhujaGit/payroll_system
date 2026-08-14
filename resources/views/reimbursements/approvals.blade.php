@extends('layouts.app')
@section('title', 'Reimbursement Approvals')

@section('content')

<div class="card">
  <div class="ch"><div class="ct">Pending Claims</div></div>
  @if($rows->isNotEmpty())
  <table>
    <thead><tr><th>Employee</th><th>Date</th><th>Amount</th><th>Remarks</th><th>Document</th><th>Actions</th></tr></thead>
    <tbody>
      @foreach($rows as $row)
      <tr>
        <td>{{ $row->employee_name }} <span style="color:var(--t3);font-size:11px">({{ $row->emp_id }})</span></td>
        <td>{{ $row->expense_date ? \Carbon\Carbon::parse($row->expense_date)->format('d M Y') : '—' }}</td>
        <td style="font-weight:600">₹{{ number_format($row->amount, 2) }}</td>
        <td style="color:var(--t2)">{{ $row->remarks ?? '—' }}</td>
        <td>
          @if($row->document_path)
            <a href="{{ route('reimbursements.tracker.document', $row) }}" target="_blank" class="btn sm">View</a>
          @else
            <span style="color:var(--t3)">—</span>
          @endif
        </td>
        <td><button class="btn sm" onclick="om('resolve-{{ $row->id }}')">Review</button></td>
      </tr>
      @endforeach
    </tbody>
  </table>
  <div style="margin-top:14px">{{ $rows->links() }}</div>
  @else
  <div class="empty"><div class="ico">💳</div><p>No pending claims.</p></div>
  @endif
</div>

@foreach($rows as $row)
  <div class="modal" id="resolve-{{ $row->id }}">
    <div class="mb">
      <div class="mh">
        <span class="mt">Review — {{ $row->employee_name }} (₹{{ number_format($row->amount, 2) }})</span>
        <button class="mc" onclick="cm('resolve-{{ $row->id }}')">✕</button>
      </div>
      <form method="POST" action="{{ route('reimbursements.approvals.resolve', $row) }}">
        @csrf
        <div class="fld full">
          <label class="req">Remark</label>
          <select name="remark_choice" onchange="toggleOtherRemark(this, '{{ $row->id }}')" required>
            <option value="">Select a remark…</option>
            @foreach($standardRemarks as $sr)
              <option value="{{ $sr }}">{{ $sr }}</option>
            @endforeach
            <option value="__other__">Other</option>
          </select>
          <textarea name="remark_other" id="remark-other-{{ $row->id }}" rows="3" style="display:none;margin-top:8px"
            placeholder="Enter remark…"></textarea>
        </div>
        <input type="hidden" name="remark" id="remark-final-{{ $row->id }}">
        <div class="mf">
          <button type="button" class="btn" style="color:var(--red)"
            onclick="submitResolve(this, '{{ $row->id }}', 'rejected')">Reject</button>
          <button type="button" class="btn acc"
            onclick="submitResolve(this, '{{ $row->id }}', 'approved')">Approve</button>
        </div>
      </form>
    </div>
  </div>
@endforeach

@endsection

@push('scripts')
<script>
function toggleOtherRemark(select, rowId) {
  const other = document.getElementById('remark-other-' + rowId);
  other.style.display = select.value === '__other__' ? 'block' : 'none';
}

function submitResolve(button, rowId, action) {
  const form = button.closest('form');
  const select = form.querySelector('select[name="remark_choice"]');
  const other = document.getElementById('remark-other-' + rowId);
  const finalRemark = select.value === '__other__' ? other.value.trim() : select.value;

  if (!finalRemark) {
    toast('Please select or enter a remark.', 'err');
    return;
  }

  document.getElementById('remark-final-' + rowId).value = finalRemark;

  const actionInput = document.createElement('input');
  actionInput.type = 'hidden';
  actionInput.name = 'action';
  actionInput.value = action;
  form.appendChild(actionInput);

  form.submit();
}
</script>
@endpush
