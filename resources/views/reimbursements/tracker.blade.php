@extends('layouts.app')
@section('title', 'Reimbursement Tracker')

@section('page_actions')
  <a href="{{ route('reimbursements.report.saved') }}" class="btn">📊 Reports</a>
  <button class="btn" onclick="om('img-upload-modal')">📸 Upload Images</button>
  <a href="{{ route('reimbursements.tracker.template') }}" class="btn">📄 Template</a>
  <button class="btn" onclick="om('import-modal')">📤 Import</button>
  <button class="btn acc" onclick="om('add-modal')">+ Add Row</button>
@endsection

@section('content')

{{-- Filters --}}
<form method="GET" action="{{ route('reimbursements.tracker') }}"
      class="card" style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
  <div class="fld" style="min-width:160px">
    <label>Month</label>
    <input type="month" name="month" value="{{ $month }}" required>
  </div>
  <div class="fld" style="min-width:240px">
    <label>Search by Emp ID / Name</label>
    <input type="text" name="search" value="{{ $search }}" placeholder="e.g. EMP001 or John Doe">
  </div>
  <button type="submit" class="btn acc">View</button>
  @if($search !== '')
    <a href="{{ route('reimbursements.tracker', ['month' => $month]) }}" class="btn">✕ Clear</a>
  @endif
  @if($totalRows > 0)
    <form method="GET" action="{{ route('reimbursements.tracker.export') }}" style="margin:0">
      <input type="hidden" name="month" value="{{ $month }}">
      <button type="submit" class="btn">📥 Export</button>
    </form>
  @endif
  @if($isLocked)
    <span style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--t2)">
      🔒 Report saved
      @if($lockedAt)
        <span style="color:var(--t3)">on {{ $lockedAt->format('d M Y, h:i A') }}</span>
      @endif
    </span>
  @endif
</form>

{{-- Summary Cards --}}
@if($totalRows > 0)
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:16px">
  <div class="card" style="padding:14px 18px">
    <div style="font-size:11px;color:var(--t3);text-transform:uppercase;letter-spacing:.05em">Total Rows</div>
    <div style="font-size:22px;font-weight:700;margin-top:4px">{{ number_format($totalRows) }}</div>
  </div>
  <div class="card" style="padding:14px 18px">
    <div style="font-size:11px;color:var(--t3);text-transform:uppercase;letter-spacing:.05em">Approved Amount</div>
    <div style="font-size:22px;font-weight:700;color:var(--grn);margin-top:4px">₹{{ number_format($totalApproved, 2) }}</div>
  </div>
  <div class="card" style="padding:14px 18px">
    <div style="font-size:11px;color:var(--t3);text-transform:uppercase;letter-spacing:.05em">Total Amount</div>
    <div style="font-size:22px;font-weight:700;margin-top:4px">₹{{ number_format($totalAll, 2) }}</div>
  </div>
  <div class="card" style="padding:14px 18px;display:flex;flex-direction:column;justify-content:center">
    <a href="{{ route('reimbursements.report', ['month' => $month]) }}" class="btn acc" style="text-align:center">
      {{ $isLocked ? '📊 View Report' : '📊 Generate Report' }}
    </a>
  </div>
</div>
@endif

{{-- Data Table --}}
<div class="card">
  @if($rows->total() > 0)
  <div class="ch" style="display:flex;justify-content:space-between;align-items:center">
    <div class="ct">
      {{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}
      — {{ number_format($rows->total()) }} row(s)
      @if($search !== '')
        <span style="color:var(--acc);font-size:11px"> matching "{{ e($search) }}"</span>
      @endif
    </div>
    <div style="font-size:12px;color:var(--t3)">
      Page {{ $rows->currentPage() }} of {{ $rows->lastPage() }}
    </div>
  </div>
  <div style="overflow-x:auto">
  <table style="font-size:12px;min-width:800px">
    <thead>
      <tr>
        <th style="width:40px">#</th>
        <th>Date</th>
        <th>Emp ID</th>
        <th>Employee Name</th>
        <th style="text-align:right">Amount (₹)</th>
        <th>Remarks</th>
        <th style="text-align:center">Image</th>
        <th style="text-align:center">Status</th>
        <th style="text-align:center">Actions</th>
      </tr>
    </thead>
    <tbody>
      @foreach($rows as $row)
      @php $rowNum = ($rows->currentPage() - 1) * $rows->perPage() + $loop->iteration; @endphp
      <tr>
        <td style="color:var(--t3)">{{ $rowNum }}</td>
        <td style="color:var(--t2);white-space:nowrap">
          {{ $row->expense_date ? \Carbon\Carbon::parse($row->expense_date)->format('d M Y') : '—' }}
        </td>
        <td class="mono" style="font-size:11px">{{ $row->emp_id ?? '—' }}</td>
        <td style="font-weight:500">{{ $row->employee_name }}</td>
        <td style="text-align:right;font-weight:600">₹{{ number_format($row->amount, 2) }}</td>
        <td style="color:var(--t2);max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $row->remarks }}">
          {{ $row->remarks ?? '—' }}
        </td>
        <td style="text-align:center">
          @if($row->image_filename)
            <a href="{{ route('reimbursements.tracker.image.view', ['month' => $row->year_month, 'filename' => $row->image_filename]) }}"
               target="_blank" class="btn sm" style="font-size:10px">🖼 View</a>
          @else
            <span style="color:var(--t3);font-size:11px">—</span>
          @endif
        </td>
        <td style="text-align:center">
          @php $sc = ['approved' => 'var(--grn)', 'rejected' => 'var(--red)'];
               $sb = ['approved' => 'var(--grn2)', 'rejected' => 'var(--red2)']; @endphp
          <span class="bx" style="color:{{ $sc[$row->status] ?? 'var(--t3)' }};background:{{ $sb[$row->status] ?? 'var(--s2)' }}">
            {{ ucfirst($row->status) }}
          </span>
        </td>
        <td style="text-align:center;white-space:nowrap">
          @if($row->status === 'approved')
            <form method="POST" action="{{ route('reimbursements.tracker.status', $row) }}" style="display:inline"
                  data-has-report="{{ $isLocked ? 'true' : 'false' }}"
                  data-month="{{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}">
              @csrf <input type="hidden" name="status" value="rejected">
              <button type="button" class="btn sm" style="color:var(--red);font-size:10px"
                onclick="confirmStatusChange(this.closest('form'))">Reject</button>
            </form>
          @else
            <form method="POST" action="{{ route('reimbursements.tracker.status', $row) }}" style="display:inline"
                  data-has-report="{{ $isLocked ? 'true' : 'false' }}"
                  data-month="{{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}">
              @csrf <input type="hidden" name="status" value="approved">
              <button type="button" class="btn sm" style="color:var(--grn);font-size:10px"
                onclick="confirmStatusChange(this.closest('form'))">Approve</button>
            </form>
          @endif
          <form method="POST" action="{{ route('reimbursements.tracker.destroy', $row) }}" style="display:inline">
            @csrf @method('DELETE')
            <button type="button" class="btn sm" style="color:var(--red);font-size:10px"
              onclick="swConfirm(this.closest('form'), 'Delete row?', 'This cannot be undone.', 'warning', 'Delete', '#dc2626')">
              🗑
            </button>
          </form>
        </td>
      </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr style="background:var(--s2);font-weight:700">
        <td colspan="4" style="text-align:right;padding:8px 12px">Total Approved:</td>
        <td style="text-align:right;padding:8px 12px;color:var(--grn)">₹{{ number_format($totalApproved, 2) }}</td>
        <td colspan="4"></td>
      </tr>
    </tfoot>
  </table>
  </div>
  {{-- Pagination --}}
  @if($rows->hasPages())
    <div style="padding:14px 18px;border-top:1px solid var(--b1)">
      {{ $rows->links() }}
    </div>
  @endif

  @elseif($search !== '')
  <div class="empty">
    <div class="ico">🔍</div>
    <p>No results for "{{ e($search) }}" in {{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}.</p>
    <a href="{{ route('reimbursements.tracker', ['month' => $month]) }}" class="btn acc" style="margin-top:10px">Clear Search</a>
  </div>

  @else
  <div class="empty">
    <div class="ico">💳</div>
    <p>No reimbursement data for {{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}.</p>
    <p style="font-size:12px;color:var(--t3);margin-top:4px">Add rows manually or import from Excel.</p>
    <button class="btn acc" style="margin-top:10px" onclick="om('add-modal')">+ Add Row</button>
  </div>
  @endif
</div>

{{-- Add Row Modal --}}
<div class="modal" id="add-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Add Reimbursement Row</span>
      <button class="mc" onclick="cm('add-modal')">✕</button>
    </div>
    <form method="POST" action="{{ route('reimbursements.tracker.store') }}">
      @csrf
      <input type="hidden" name="year_month" value="{{ $month }}">
      <div class="fg2">
        <div class="fld">
          <label>Date</label>
          <input type="date" name="expense_date" value="{{ old('expense_date', now()->toDateString()) }}">
        </div>
        <div class="fld">
          <label>Emp ID</label>
          <input type="text" name="emp_id" placeholder="e.g. EMP001" value="{{ old('emp_id') }}">
        </div>
        <div class="fld full">
          <label class="req">Employee Name</label>
          <input type="text" name="employee_name" required placeholder="e.g. John Doe"
            value="{{ old('employee_name') }}" @error('employee_name') class="inp-err" @enderror>
          @error('employee_name') <div class="ferr">{{ $message }}</div> @enderror
        </div>
        <div class="fld">
          <label class="req">Amount (₹)</label>
          <input type="number" name="amount" required min="0.01" step="0.01" placeholder="1800"
            value="{{ old('amount') }}" @error('amount') class="inp-err" @enderror>
          @error('amount') <div class="ferr">{{ $message }}</div> @enderror
        </div>
        <div class="fld full">
          <label>Remarks</label>
          <input type="text" name="remarks" placeholder="e.g. Petrol & Recharge" value="{{ old('remarks') }}">
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('add-modal')">Cancel</button>
        <button type="submit" class="btn acc">Add</button>
      </div>
    </form>
  </div>
</div>

{{-- Import Modal --}}
<div class="modal" id="import-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Import from Excel</span>
      <button class="mc" onclick="cm('import-modal')">✕</button>
    </div>
    <form method="POST" action="{{ route('reimbursements.tracker.import') }}" enctype="multipart/form-data">
      @csrf
      <div class="fg2">
        <div class="fld full">
          <label class="req">Month</label>
          <input type="month" name="month" value="{{ $month }}" required>
        </div>
        <div class="fld full">
          <label class="req">Excel File</label>
          <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
          <div style="font-size:11px;color:var(--t3);margin-top:4px">
            Columns: <strong>Date, Emp ID, Employee Name, Amount, Remarks, Image</strong>
            — <a href="{{ route('reimbursements.tracker.template') }}" style="color:var(--acc)">Download Template</a><br>
            <strong>Image naming rules:</strong> No spaces · Must contain employee name · Must include date &amp; time · Any extension (.jpg .png .pdf etc.)<br>
            Example: <code style="background:var(--s2);padding:1px 5px;border-radius:3px">VADHER_NEHA_DIPAKBHAI_03-06-2026_10-30.png</code>
            <span style="color:var(--t3)">— underscore (_) as separator, any valid extension</span>
          </div>
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('import-modal')">Cancel</button>
        <button type="submit" class="btn acc">📤 Import</button>
      </div>
    </form>
  </div>
</div>

{{-- Image Upload Modal --}}
<div class="modal" id="img-upload-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Upload Images</span>
      <button class="mc" onclick="cm('img-upload-modal')">✕</button>
    </div>
    <form method="POST" action="{{ route('reimbursements.tracker.images') }}" enctype="multipart/form-data">
      @csrf
      <input type="hidden" name="month" value="{{ $month }}">
      <div class="fg2">
        <div class="fld full">
          <label class="req">Image Files</label>
          <input type="file" name="images[]" accept="image/*,.pdf" multiple required>
          <div style="font-size:11px;color:var(--t3);margin-top:4px">
            File names must match the <strong>Image</strong> column in your data.
            Accepted: JPG, PNG, GIF, WEBP, PDF — max 5 MB each.
          </div>
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('img-upload-modal')">Cancel</button>
        <button type="submit" class="btn acc">Upload</button>
      </div>
    </form>
  </div>
</div>

@if($errors->any())
<script>document.addEventListener('DOMContentLoaded', () => om('add-modal'))</script>
@endif

@endsection

@push('scripts')
<script>
function confirmStatusChange(form) {
  if (form.dataset.hasReport === 'true') {
    Swal.fire({
      title: 'Report already saved',
      html: `The saved report for <strong>${form.dataset.month}</strong> will <strong>not</strong> change automatically.<br>
             Go to <strong>Reimb. Report → Re-save Report</strong> after making all your changes.`,
      icon: 'info',
      showCancelButton: true,
      confirmButtonText: 'OK, change status',
      confirmButtonColor: '#2563eb',
      cancelButtonText: 'Cancel',
    }).then(r => { if (r.isConfirmed) form.submit(); });
  } else {
    form.submit();
  }
}
</script>
@endpush
