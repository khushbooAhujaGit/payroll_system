@extends('layouts.app')
@section('title', 'Salary Revisions')

@section('page_actions')
  <button class="btn acc" onclick="om('rev-modal')">+ Raise Revision</button>
  <button class="btn" onclick="om('upload-modal')">📤 Bulk Upload</button>
@endsection

@section('content')

<form method="GET" action="{{ route('revisions') }}" class="card" data-ajax-filter style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end">
  <div class="fld" style="min-width:140px">
    <label>Status</label>
    <select name="status">
      <option value="">All</option>
      <option value="pending_approval" {{ request('status') === 'pending_approval' ? 'selected' : '' }}>Pending</option>
      <option value="implemented"      {{ request('status') === 'implemented'      ? 'selected' : '' }}>Implemented</option>
      <option value="rejected"         {{ request('status') === 'rejected'         ? 'selected' : '' }}>Rejected</option>
    </select>
  </div>
  <button type="submit" class="btn acc">Filter</button>
  <a href="{{ route('revisions') }}" class="btn" data-ajax-reset>Reset</a>
</form>

<div id="ajax-results">
<div class="card">
  @if($revisions->isNotEmpty())
  <table>
    <thead>
      <tr><th>Employee</th><th>Old CTC</th><th>New CTC</th><th>Hike %</th><th>Effective</th><th>Status</th><th>Actions</th></tr>
    </thead>
    <tbody>
      @foreach($revisions as $rev)
      <tr>
        <td>{{ $rev->employee?->full_name }}<br>
            <span style="font-size:10px;color:var(--t3)">{{ $rev->employee?->department?->name }}</span></td>
        <td>₹ {{ number_format($rev->old_ctc,0,'.',',') }}</td>
        <td>₹ {{ number_format($rev->new_ctc,0,'.',',') }}</td>
        <td style="color:{{ $rev->increment_percentage > 0 ? 'var(--grn)' : 'var(--red)' }}">
          {{ $rev->increment_percentage }}%
        </td>
        <td>{{ \Carbon\Carbon::parse($rev->effective_date)->format('d M Y') }}</td>
        <td>
          @php $colors = ['pending_approval'=>'var(--yel)','implemented'=>'var(--grn)','rejected'=>'var(--red)']; @endphp
          <span class="bx" style="color:{{ $colors[$rev->status] ?? 'var(--t3)' }}">{{ str_replace('_',' ',ucwords($rev->status,'_')) }}</span>
        </td>
        <td>
          @if($rev->status === 'pending_approval')
          <form method="POST" action="{{ route('revisions.approve', $rev) }}" style="display:inline">
            @csrf
            <input type="hidden" name="action" value="approved">
            <button type="submit" class="btn sm" style="background:var(--grn2);color:var(--grn)">Approve</button>
          </form>
          <form method="POST" action="{{ route('revisions.approve', $rev) }}" style="display:inline">
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
  <div style="margin-top:14px">{{ $revisions->links() }}</div>
  @else
  <div class="empty"><div class="ico">📈</div><p>No salary revisions yet.</p></div>
  @endif
</div>
</div>

{{-- Raise Revision Modal --}}
<div class="modal" id="rev-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Raise Salary Revision</span>
      <button class="mc" onclick="cm('rev-modal')">✕</button>
    </div>
    <form method="POST" action="{{ route('revisions.store') }}">
      @csrf
      <div class="fg2">
        <div class="fld full">
          <label class="req">Employee</label>
          <select name="employee_id" required @error('employee_id') class="inp-err" @enderror>
            <option value="">Select employee</option>
            @foreach($employees as $emp)
            <option value="{{ $emp->id }}" {{ old('employee_id') == $emp->id ? 'selected' : '' }}>{{ $emp->full_name }} ({{ $emp->employee_id }})</option>
            @endforeach
          </select>
          @error('employee_id') <div class="ferr">{{ $message }}</div> @enderror
        </div>
        <div class="fld">
          <label class="req">New CTC Annual (₹)</label>
          <input type="number" name="new_ctc" required min="1" value="{{ old('new_ctc') }}"
            @error('new_ctc') class="inp-err" @enderror>
          @error('new_ctc') <div class="ferr">{{ $message }}</div> @enderror
        </div>
        <div class="fld">
          <label class="req">Effective Date</label>
          <input type="date" name="effective_date" required value="{{ old('effective_date', now()->toDateString()) }}"
            @error('effective_date') class="inp-err" @enderror>
          @error('effective_date') <div class="ferr">{{ $message }}</div> @enderror
        </div>
        <div class="fld full">
          <label class="req">Reason</label>
          <textarea name="reason" required rows="3" style="resize:vertical"
            @error('reason') class="inp-err" @enderror>{{ old('reason') }}</textarea>
          @error('reason') <div class="ferr">{{ $message }}</div> @enderror
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('rev-modal')">Cancel</button>
        <button type="submit" class="btn acc">Submit</button>
      </div>
    </form>
  </div>
</div>

@include('partials.upload-modal', [
    'uploadId'      => 'upload-modal',
    'uploadTitle'   => 'Bulk Upload Salary Revisions',
    'uploadRoute'   => 'revisions.import',
    'templateRoute' => 'revisions.template',
    'uploadHint'    => 'Template columns: Employee ID, New CTC Annual, Effective Date (DD-MM-YYYY), Reason. Each row creates a pending salary revision.',
])

@if($errors->any())
<script>document.addEventListener('DOMContentLoaded',()=>om('rev-modal'))</script>
@endif
@endsection
