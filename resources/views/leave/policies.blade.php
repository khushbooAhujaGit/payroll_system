@extends('layouts.app')
@section('title', 'Leave Policies')

@section('page_actions')
  <button class="btn acc" onclick="om('add-policy-modal')">+ Add Policy</button>
@endsection

@section('content')

<div class="g4" style="margin-bottom:16px">
  <div class="sc">
    <div class="lbl">Total Policies</div>
    <div class="val">{{ $policies->count() }}</div>
  </div>
  <div class="sc">
    <div class="lbl">Active</div>
    <div class="val" style="color:var(--grn)">{{ $policies->where('is_active', true)->count() }}</div>
  </div>
  <div class="sc">
    <div class="lbl">Leave Types Available</div>
    <div class="val">{{ $leaveTypes->count() }}</div>
  </div>
</div>

{{-- Policies Table --}}
<div class="card" style="margin-bottom:20px">
  <div class="ch">
    <div class="ct">Leave Policies</div>
  </div>
  @if($policies->isNotEmpty())
  <table>
    <thead>
      <tr>
        <th>Policy Name</th>
        <th>Leave Type</th>
        <th>Days / Year</th>
        <th>Accrual</th>
        <th>Proration</th>
        <th>Sandwich</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @foreach($policies as $policy)
      <tr>
        <td style="font-weight:500">{{ $policy->name }}</td>
        <td>
          @if($policy->leaveType)
            <span class="bx" style="background:var(--acc2);color:var(--acc)">{{ $policy->leaveType->code }}</span>
            {{ $policy->leaveType->name }}
          @else
            <span style="color:var(--t3)">—</span>
          @endif
        </td>
        <td class="mono">{{ number_format($policy->days_per_year, 1) }}</td>
        <td style="text-transform:capitalize">{{ $policy->accrual_type }}</td>
        <td style="text-transform:capitalize;font-size:11px;color:var(--t2)">
          {{ str_replace('_', ' ', $policy->proration) }}
        </td>
        <td>
          @if($policy->sandwich_policy)
            <span style="color:var(--grn)">Yes</span>
          @else
            <span style="color:var(--t3)">No</span>
          @endif
        </td>
        <td>
          @if($policy->is_active)
            <span class="bx" style="color:var(--grn)">Active</span>
          @else
            <span class="bx" style="color:var(--red)">Inactive</span>
          @endif
        </td>
        <td style="white-space:nowrap">
          <button class="btn sm" onclick="openEdit({{ $policy->id }},
            {{ json_encode($policy->name) }},
            {{ $policy->leave_type_id }},
            {{ $policy->days_per_year }},
            {{ json_encode($policy->accrual_type) }},
            {{ json_encode((string)$policy->accrual_days) }},
            {{ json_encode($policy->proration) }},
            {{ $policy->sandwich_policy ? 1 : 0 }},
            {{ $policy->is_active ? 1 : 0 }}
          )">Edit</button>
          <form method="POST" action="{{ route('leave.policies.destroy', $policy) }}" style="display:inline">
            @csrf @method('DELETE')
            <button type="button" class="btn sm" style="background:var(--red2);color:var(--red)"
              onclick="swConfirm(this.closest('form'), 'Delete Policy?', 'The leave policy &quot;{{ addslashes($policy->name) }}&quot; will be permanently deleted.', 'warning', 'Yes, delete', '#dc2626')">Delete</button>
          </form>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @else
  <div class="empty">
    <div class="ico">📋</div>
    <p>No leave policies configured yet.</p>
    <p style="font-size:12px;color:var(--t3);margin-top:4px">Click <strong>+ Add Policy</strong> to create one.</p>
  </div>
  @endif
</div>

{{-- Leave Types Reference --}}
<div class="card">
  <div class="ch">
    <div class="ct">Leave Type Codes Reference</div>
    <div style="font-size:11px;color:var(--t3)">{{ $leaveTypes->count() }} types available</div>
  </div>
  <div style="display:flex;flex-wrap:wrap;gap:8px;padding:4px 0 8px">
    @foreach($leaveTypes as $lt)
    <div style="background:var(--s2);border:1px solid var(--b2);border-radius:6px;
                padding:6px 12px;font-size:12px;min-width:160px">
      <span style="font-weight:700;color:var(--acc);font-size:13px">{{ $lt->code }}</span>
      <span style="color:var(--t2);margin-left:6px">{{ $lt->name }}</span>
      <div style="font-size:10px;color:var(--t3);margin-top:2px;text-transform:capitalize">
        {{ str_replace('_', ' ', $lt->category) }}
        {{ $lt->is_paid ? ' · Paid' : ' · Unpaid' }}
      </div>
    </div>
    @endforeach
  </div>
</div>

{{-- Add Policy Modal --}}
<div class="modal" id="add-policy-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Add Leave Policy</span>
      <button class="mc" onclick="cm('add-policy-modal')">✕</button>
    </div>
    <form method="POST" action="{{ route('leave.policies.store') }}">
      @csrf
      @include('leave._policy-form', ['policy' => null, 'leaveTypes' => $leaveTypes,
        'accrualTypes' => $accrualTypes, 'prorationsTypes' => $prorationsTypes])
      <div class="mf">
        <button type="button" class="btn" onclick="cm('add-policy-modal')">Cancel</button>
        <button type="submit" class="btn acc">Save Policy</button>
      </div>
    </form>
  </div>
</div>

{{-- Edit Policy Modal --}}
<div class="modal" id="edit-policy-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Edit Leave Policy</span>
      <button class="mc" onclick="cm('edit-policy-modal')">✕</button>
    </div>
    <form method="POST" id="edit-policy-form" action="">
      @csrf
      @include('leave._policy-form', ['policy' => null, 'leaveTypes' => $leaveTypes,
        'accrualTypes' => $accrualTypes, 'prorationsTypes' => $prorationsTypes, 'edit' => true])
      <div class="mf">
        <button type="button" class="btn" onclick="cm('edit-policy-modal')">Cancel</button>
        <button type="submit" class="btn acc">Update Policy</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEdit(id, name, ltId, days, accrual, accrualDays, proration, sandwich, isActive) {
  document.getElementById('edit-policy-form').action = '/leave-policies/' + id;
  var f = document.getElementById('edit-policy-form');
  f.querySelector('[name="name"]').value         = name;
  f.querySelector('[name="leave_type_id"]').value = ltId;
  f.querySelector('[name="days_per_year"]').value  = days;
  f.querySelector('[name="accrual_type"]').value   = accrual;
  f.querySelector('[name="accrual_days"]').value   = accrualDays;
  f.querySelector('[name="proration"]').value      = proration;
  f.querySelector('[name="sandwich_policy"]').checked = sandwich == 1;
  f.querySelector('[name="is_active"]').checked    = isActive == 1;
  om('edit-policy-modal');
}
</script>
@endsection
