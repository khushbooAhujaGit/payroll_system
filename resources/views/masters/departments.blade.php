@extends('layouts.app')
@section('title', 'Departments')

@section('page_actions')
  <button class="btn acc" onclick="om('dept-modal')">+ Add Department</button>
  <button class="btn" onclick="om('desig-modal')">+ Add Designation</button>
  <button class="btn" onclick="om('upload-modal')">📤 Bulk Upload</button>
@endsection

@section('content')

<div class="g2">
  {{-- Departments --}}
  <div class="card">
    <div class="ch"><div class="ct">Departments</div><span style="font-size:11px;color:var(--t3)">{{ $departments->count() }} total</span></div>
    @if($departments->isNotEmpty())
    <table>
      <thead><tr><th>Code</th><th>Name</th><th>Status</th></tr></thead>
      <tbody>
        @foreach($departments as $dept)
        <tr>
          <td class="mono">{{ $dept->code }}</td>
          <td>{{ $dept->name }}</td>
          <td><span class="bx {{ $dept->is_active ? 'g' : 'gr' }}">{{ $dept->is_active ? 'Active' : 'Inactive' }}</span></td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @else
    <div class="empty"><div class="ico">🏗️</div><p>No departments yet.</p></div>
    @endif
  </div>

  {{-- Designations --}}
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
</div>

{{-- Add Department Modal --}}
<div class="modal" id="dept-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Add Department</span>
      <button class="mc" onclick="cm('dept-modal')">✕</button>
    </div>
    <form method="POST" action="{{ route('departments.store') }}">
      @csrf
      <div class="fg2">
        <div class="fld">
          <label>Name *</label>
          <input type="text" name="name" required value="{{ old('name') }}">
        </div>
        <div class="fld">
          <label>Code *</label>
          <input type="text" name="code" required maxlength="10" value="{{ old('code') }}" placeholder="HR, FIN, ENG…">
        </div>
        <div class="fld full">
          <label>Parent Department</label>
          <select name="parent_id">
            <option value="">None (top-level)</option>
            @foreach($departments as $d)
            <option value="{{ $d->id }}">{{ $d->name }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('dept-modal')">Cancel</button>
        <button type="submit" class="btn acc">Create</button>
      </div>
    </form>
  </div>
</div>

{{-- Add Designation Modal --}}
<div class="modal" id="desig-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Add Designation</span>
      <button class="mc" onclick="cm('desig-modal')">✕</button>
    </div>
    <form method="POST" action="{{ route('designations.store') }}">
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

@include('partials.upload-modal', [
    'uploadId'      => 'upload-modal',
    'uploadTitle'   => 'Bulk Upload Departments & Designations',
    'uploadRoute'   => 'departments.import',
    'templateRoute' => 'departments.template',
    'uploadHint'    => 'Sheet 1 — Departments: Name, Code, Parent Department Code (optional). Sheet 2 — Designations: Name, Code, Level (optional). One row per entry.',
])
