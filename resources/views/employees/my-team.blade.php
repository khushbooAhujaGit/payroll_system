@extends('layouts.app')
@section('title', 'My Team')

@section('content')

<div class="card">
  <div class="ch"><div class="ct">My Direct Reports</div></div>
  @if($reports->isNotEmpty())
  <table>
    <thead><tr><th>Emp ID</th><th>Name</th><th>Designation</th><th>Department</th><th>Status</th><th></th></tr></thead>
    <tbody>
      @foreach($reports as $emp)
      <tr>
        <td class="mono" style="font-size:11px">{{ $emp->employee_id }}</td>
        <td>{{ $emp->full_name }}</td>
        <td style="color:var(--t2)">{{ $emp->designation?->name ?? '—' }}</td>
        <td style="color:var(--t2)">{{ $emp->department?->name ?? '—' }}</td>
        <td>
          <span class="bx {{ $emp->employee_status === 'active' ? 'g' : ($emp->employee_status === 'inactive' ? 'r' : 'y') }}">
            {{ str_replace('_', ' ', ucfirst($emp->employee_status)) }}
          </span>
        </td>
        <td>
          @if($emp->employee_status !== 'inactive')
          <button class="btn sm" style="color:var(--yel)"
            onclick="openMarkAsLeft({{ $emp->id }}, '{{ addslashes($emp->full_name) }}')">Mark as Left</button>
          @endif
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @else
  <div class="empty"><div class="ico">👥</div><p>No one reports to you yet.</p></div>
  @endif
</div>

{{-- Mark as Left Modal --}}
<div class="modal" id="mark-left-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt" id="mark-left-title">Mark as Left</span>
      <button class="mc" onclick="cm('mark-left-modal')">✕</button>
    </div>
    <form method="POST" id="mark-left-form">
      @csrf
      <div class="fg2">
        <div class="fld full">
          <label class="req">Date of Leaving</label>
          <input type="date" name="date_of_leaving" id="mark-left-date" required value="{{ now()->toDateString() }}">
          <div style="font-size:11px;color:var(--t3);margin-top:4px">
            Their portal login is deactivated immediately, regardless of the date entered here.
          </div>
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('mark-left-modal')">Cancel</button>
        <button type="submit" class="btn" style="background:var(--yel);border-color:var(--yel);color:#fff">Confirm — Mark as Left</button>
      </div>
    </form>
  </div>
</div>

@endsection

@push('scripts')
<script>
function openMarkAsLeft(empId, name) {
  document.getElementById('mark-left-title').textContent = 'Mark as Left — ' + name;
  document.getElementById('mark-left-form').action = '/employees/' + empId + '/mark-as-left';
  document.getElementById('mark-left-date').value = new Date().toISOString().slice(0, 10);
  om('mark-left-modal');
}
</script>
@endpush
