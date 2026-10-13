{{--
    Reusable leave list: legacy leave_applications rows + leave_tracker_records
    rows (which now cover everything else — new leave requests, bulk Excel
    imports, manual entries) combined into one table, search + pagination,
    and (optionally) a bulk approve/reject action bar with per-row
    checkboxes and "select all". Included from leave/index.blade.php for the
    unified (HR/Admin/plain employee) card and, separately, for a manager's
    Self/Team buckets.

    Expected variables:
    $rows          LengthAwarePaginator of normalized row objects (see
                   WebLeaveController::normalizeApplicationRow()/
                   normalizeTrackerRow()) — each has kind, id, employee,
                   leave_type, from_date, to_date, days, reason, status,
                   application (nullable), trackerRecord (nullable)
    $title         card heading
    $searchParam   query-string name for this card's search box
    $searchValue   current value of that search box
    $showBulk      bool — render checkboxes + bulk approve/reject bar
    $isAdmin       bool — Admin bypasses the reporting-manager check
    $viewerEmployeeId
    $emptyText     shown when $rows is empty
    $formId        unique id for this card's bulk-action <form>
    $tabValue      optional — 'self'/'team' when this card is one of two tabs,
                   so the approve/reject/bulk-approve redirect lands back on
                   the same tab instead of resetting to the default
    $leaveSummaries optional — array keyed by employee_id of
                   ['cl','sl','co','pending'], shown as extra columns
                   whenever $showBulk is true (HR/Admin/manager reviewing
                   someone else's leave) — never on a self-view table, where
                   "My Leave Balances" above already covers the viewer's own
                   numbers.
--}}
@php $tabValue = $tabValue ?? null; $leaveSummaries = $leaveSummaries ?? []; @endphp
<div class="card" style="margin-top:16px">
  <div class="ch"><div class="ct">{{ $title }}</div></div>

  <form method="GET" action="{{ route('leave') }}" data-ajax-filter
    style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;padding:10px 0 14px;border-bottom:1px solid var(--b2)">
    {{-- preserve every other card's query-string state --}}
    @foreach(request()->except([$searchParam, 'page', 'self_page', 'team_page']) as $key => $val)
      @if(is_array($val))
        @foreach($val as $v)<input type="hidden" name="{{ $key }}[]" value="{{ $v }}">@endforeach
      @else
        <input type="hidden" name="{{ $key }}" value="{{ $val }}">
      @endif
    @endforeach
    <div class="fld" style="min-width:200px">
      <label style="font-size:11px">Search</label>
      <input type="text" name="{{ $searchParam }}" value="{{ $searchValue }}" placeholder="Name, emp code, leave type…" style="font-size:12px">
    </div>
    <button type="submit" class="btn sm acc">Filter</button>
    @if($searchValue)
      <a href="{{ route('leave', request()->except([$searchParam])) }}" class="btn sm" data-ajax-reset>Reset</a>
    @endif
  </form>

  @if($rows->isNotEmpty())
  <form method="POST" action="{{ route('leave.bulk_approve') }}" id="{{ $formId }}">
    @csrf
    @if($tabValue)<input type="hidden" name="applications_tab" value="{{ $tabValue }}">@endif
    <div class="tw">
    <table>
      <thead>
        <tr>
          @if($showBulk)
          <th style="width:24px"><input type="checkbox" onclick="document.querySelectorAll('#{{ $formId }} .row-chk').forEach(c => c.checked = this.checked)"></th>
          @endif
          <th>Employee</th><th>Leave Type</th><th>From</th><th>To</th><th>Days</th><th>Reason</th><th>Status</th>
          @if($showBulk)
          <th>CL</th><th>SL</th><th>CO</th><th>Pending</th>
          @endif
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach($rows as $row)
        @php
          $canApprove = $isAdmin || ($row->employee?->reporting_manager_id === $viewerEmployeeId && $viewerEmployeeId !== null);
          $isActionable = $row->status === 'pending' && $canApprove;
          $approveUrl = $row->kind === 'application'
              ? route('leave.approve', $row->application)
              : route('leave.tracker_request.approve', $row->trackerRecord);
          $checkboxName = $row->kind === 'application' ? 'application_ids[]' : 'tracker_ids[]';
          $summary = $leaveSummaries[$row->employee?->id] ?? null;
        @endphp
        <tr>
          @if($showBulk)
          <td>
            @if($isActionable)
              <input type="checkbox" class="row-chk" name="{{ $checkboxName }}" value="{{ $row->id }}">
            @endif
          </td>
          @endif
          <td>{{ $row->employee?->full_name }}</td>
          <td>{{ $row->leave_type }}</td>
          <td>{{ \Carbon\Carbon::parse($row->from_date)->format('d M Y') }}</td>
          <td>{{ \Carbon\Carbon::parse($row->to_date)->format('d M Y') }}</td>
          <td>{{ $row->days }}</td>
          <td>
            @if($row->reason)
              <span class="leave-reason-cell" data-reason="{{ $row->reason }}" onclick="showLeaveReason(this)"
                style="display:block;max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:12px;color:var(--t2);cursor:pointer"
                title="Click to view full reason">{{ $row->reason }}</span>
            @else
              <span style="font-size:12px;color:var(--t3)">—</span>
            @endif
          </td>
          <td>
            @php $color = ['pending'=>'var(--yel)','approved'=>'var(--grn)','rejected'=>'var(--red)'][$row->status] ?? 'var(--t3)'; @endphp
            <span class="bx" style="color:{{ $color }}">{{ ucfirst($row->status) }}</span>
          </td>
          @if($showBulk)
          <td class="mono">{{ $summary['cl'] ?? '—' }}</td>
          <td class="mono">{{ $summary['sl'] ?? '—' }}</td>
          <td class="mono">{{ $summary['co'] ?? '—' }}</td>
          <td class="mono">{{ $summary['pending'] ?? '—' }}</td>
          @endif
          <td>
            {{-- Individual approve/reject re-targets this same outer <form>
                 to the row's approve route via formaction — forms can't
                 nest, and this card's bulk checkboxes already live in the
                 enclosing <form>. --}}
            @if($isActionable)
            <button type="submit" name="action" value="approved" formaction="{{ $approveUrl }}"
              class="btn sm" style="background:var(--grn2);color:var(--grn)">Approve</button>
            <button type="submit" name="action" value="rejected" formaction="{{ $approveUrl }}"
              class="btn sm" style="background:var(--red2);color:var(--red)">Reject</button>
            @endif
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
    </div>

    @if($showBulk)
    <div style="display:flex;gap:8px;margin-top:12px">
      <button type="submit" name="action" value="approved" class="btn sm" style="background:var(--grn2);color:var(--grn)"
        onclick="return confirm('Approve all selected rows?')">✔ Approve Selected</button>
      <button type="submit" name="action" value="rejected" class="btn sm" style="background:var(--red2);color:var(--red)"
        onclick="return confirm('Reject all selected rows?')">✕ Reject Selected</button>
    </div>
    @endif
  </form>
  <div style="margin-top:14px">{{ $rows->links() }}</div>
  @else
  <div class="empty"><div class="ico">🏖️</div><p>{{ $emptyText }}</p></div>
  @endif
</div>
