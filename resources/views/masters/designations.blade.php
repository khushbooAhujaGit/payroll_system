@extends('layouts.app')
@section('title', 'Designations')

@section('page_actions')
  <button class="btn acc" onclick="om('desig-modal')">+ Add Designation</button>
@endsection

@section('content')

<div class="card">
  <div class="ch"><div class="ct">Designations</div><span style="font-size:11px;color:var(--t3)">{{ $designations->count() }} total</span></div>
  @if($designations->isNotEmpty())
  <table>
    <thead><tr><th>Code</th><th>Name</th><th>Level</th></tr></thead>
    <tbody>
      @foreach($designations as $desig)
      <tr>
        <td class="mono">{{ $desig->code }}</td>
        <td>{{ $desig->name }}</td>
        <td>{{ $desig->level ?? '—' }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @else
  <div class="empty"><div class="ico">🎯</div><p>No designations yet.</p></div>
  @endif
</div>

{{-- Add Designation Modal --}}
<div class="modal" id="desig-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Add Designation</span>
      <button class="mc" onclick="cm('desig-modal')">✕</button>
    </div>
    <form method="POST" action="{{ route('masters.designations.store') }}">
      @csrf
      <div class="fg2">
        <div class="fld">
          <label>Name *</label>
          <input type="text" name="name" required value="{{ old('name') }}">
        </div>
        <div class="fld">
          <label>Code *</label>
          <input type="text" name="code" required maxlength="10" value="{{ old('code') }}">
        </div>
        <div class="fld full">
          <label>Level (optional)</label>
          <input type="number" name="level" min="1" value="{{ old('level') }}" placeholder="1 = intern, 10 = CEO">
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('desig-modal')">Cancel</button>
        <button type="submit" class="btn acc">Create</button>
      </div>
    </form>
  </div>
</div>
@endsection
