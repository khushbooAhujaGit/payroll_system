@extends('layouts.app')
@section('title', 'Branches')

@section('page_actions')
  <button class="btn acc" onclick="om('branch-modal')">+ Add Branch</button>
@endsection

@section('content')

<div class="card">
  <div class="ch"><div class="ct">Branches</div><span style="font-size:11px;color:var(--t3)">{{ $branches->count() }} total</span></div>
  @if($branches->isNotEmpty())
  <table>
    <thead><tr><th>Code</th><th>Name</th><th>City</th><th>State</th><th>Status</th></tr></thead>
    <tbody>
      @foreach($branches as $branch)
      <tr>
        <td class="mono">{{ $branch->code ?? '—' }}</td>
        <td>{{ $branch->name }}</td>
        <td>{{ $branch->city ?? '—' }}</td>
        <td>{{ $branch->state ?? '—' }}</td>
        <td><span class="bx {{ $branch->is_active ? 'g' : 'gr' }}">{{ $branch->is_active ? 'Active' : 'Inactive' }}</span></td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @else
  <div class="empty"><div class="ico">🏢</div><p>No branches yet.</p></div>
  @endif
</div>

{{-- Add Branch Modal --}}
<div class="modal" id="branch-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Add Branch</span>
      <button class="mc" onclick="cm('branch-modal')">✕</button>
    </div>
    <form method="POST" action="{{ route('masters.branches.store') }}">
      @csrf
      <div class="fg2">
        <div class="fld">
          <label>Name *</label>
          <input type="text" name="name" required value="{{ old('name') }}">
        </div>
        <div class="fld">
          <label>Code</label>
          <input type="text" name="code" maxlength="20" value="{{ old('code') }}">
        </div>
        <div class="fld">
          <label>City</label>
          <input type="text" name="city" value="{{ old('city') }}">
        </div>
        <div class="fld">
          <label>State</label>
          <input type="text" name="state" value="{{ old('state') }}">
        </div>
        <div class="fld full">
          <label>Address</label>
          <input type="text" name="address" value="{{ old('address') }}">
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('branch-modal')">Cancel</button>
        <button type="submit" class="btn acc">Create</button>
      </div>
    </form>
  </div>
</div>
@endsection
