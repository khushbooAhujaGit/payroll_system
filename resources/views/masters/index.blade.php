@extends('layouts.app')
@section('title', 'Company / Entity')

@section('page_actions')
  <button class="btn acc" onclick="om('company-modal')">+ Add Company</button>
  <button class="btn" onclick="om('entity-modal')">+ Add Entity</button>
  <button class="btn" onclick="om('upload-modal')">📤 Bulk Upload</button>
@endsection

@section('content')

<div class="g2">
  {{-- Companies --}}
  <div class="card">
    <div class="ch"><div class="ct">Companies</div></div>
    @if($companies->isNotEmpty())
    <table>
      <thead><tr><th>Name</th><th>PAN</th><th>Entities</th></tr></thead>
      <tbody>
        @foreach($companies as $co)
        <tr>
          <td>
            <div style="font-weight:600">{{ $co->name }}</div>
            <div style="font-size:11px;color:var(--t3)">{{ $co->legal_name }}</div>
          </td>
          <td class="mono">{{ $co->pan ?? '—' }}</td>
          <td>{{ $co->entities->count() }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @else
    <div class="empty"><div class="ico">🏢</div><p>No companies yet.</p></div>
    @endif
  </div>

  {{-- Entities --}}
  <div class="card">
    <div class="ch"><div class="ct">Entities</div></div>
    @if($entities->isNotEmpty())
    <table>
      <thead><tr><th>Name</th><th>Code</th><th>State</th><th>PF #</th></tr></thead>
      <tbody>
        @foreach($entities as $ent)
        <tr>
          <td>
            <div style="font-weight:600">{{ $ent->name }}</div>
            <div style="font-size:11px;color:var(--t3)">{{ $ent->company?->name }}</div>
          </td>
          <td class="mono">{{ $ent->code }}</td>
          <td>{{ $ent->state ?? '—' }}</td>
          <td class="mono" style="font-size:11px">{{ $ent->pf_number ?? '—' }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @else
    <div class="empty"><div class="ico">🏗️</div><p>No entities yet.</p></div>
    @endif
  </div>
</div>

{{-- Add Company Modal --}}
<div class="modal" id="company-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Add Company</span>
      <button class="mc" onclick="cm('company-modal')">✕</button>
    </div>
    <form method="POST" action="{{ route('masters.company') }}">
      @csrf
      <div class="fg2">
        <div class="fld full">
          <label>Company Name *</label>
          <input type="text" name="name" required value="{{ old('name') }}">
        </div>
        <div class="fld full">
          <label>Legal Name</label>
          <input type="text" name="legal_name" value="{{ old('legal_name') }}">
        </div>
        <div class="fld">
          <label>PAN</label>
          <input type="text" name="pan" maxlength="10" value="{{ old('pan') }}">
        </div>
        <div class="fld">
          <label>CIN</label>
          <input type="text" name="cin" maxlength="21" value="{{ old('cin') }}">
        </div>
        <div class="fld">
          <label>GSTIN</label>
          <input type="text" name="gstin" maxlength="15" value="{{ old('gstin') }}">
        </div>
        <div class="fld full">
          <label>Registered Address</label>
          <textarea name="registered_address" rows="2">{{ old('registered_address') }}</textarea>
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('company-modal')">Cancel</button>
        <button type="submit" class="btn acc">Create Company</button>
      </div>
    </form>
  </div>
</div>

{{-- Add Entity Modal --}}
<div class="modal" id="entity-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Add Entity</span>
      <button class="mc" onclick="cm('entity-modal')">✕</button>
    </div>
    <form method="POST" action="{{ route('masters.entity') }}">
      @csrf
      <div class="fg2">
        <div class="fld full">
          <label>Company *</label>
          <select name="company_id" required>
            <option value="">Select Company</option>
            @foreach($companies as $co)
            <option value="{{ $co->id }}">{{ $co->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="fld">
          <label>Entity Name *</label>
          <input type="text" name="name" required value="{{ old('name') }}">
        </div>
        <div class="fld">
          <label>Code *</label>
          <input type="text" name="code" required maxlength="20" value="{{ old('code') }}">
        </div>
        <div class="fld">
          <label>State</label>
          <input type="text" name="state" value="{{ old('state') }}">
        </div>
        <div class="fld">
          <label>City</label>
          <input type="text" name="city" value="{{ old('city') }}">
        </div>
        <div class="fld">
          <label>PF Number</label>
          <input type="text" name="pf_number" value="{{ old('pf_number') }}" maxlength="22">
        </div>
        <div class="fld">
          <label>ESIC Number</label>
          <input type="text" name="esic_number" value="{{ old('esic_number') }}" maxlength="17">
        </div>
        <div class="fld">
          <label>PT Number</label>
          <input type="text" name="pt_number" value="{{ old('pt_number') }}" maxlength="20">
        </div>
        <div class="fld">
          <label>TAN</label>
          <input type="text" name="tan" value="{{ old('tan') }}" maxlength="10">
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('entity-modal')">Cancel</button>
        <button type="submit" class="btn acc">Create Entity</button>
      </div>
    </form>
  </div>
</div>
@endsection

@include('partials.upload-modal', [
    'uploadId'      => 'upload-modal',
    'uploadTitle'   => 'Bulk Upload Entities',
    'uploadRoute'   => 'masters.import',
    'templateRoute' => 'masters.template',
    'uploadHint'    => 'Template columns: Company Name, Entity Name, Entity Code, State, City, PF Number, ESIC Number, PT Number, TAN. One row per entity.',
])
