@extends('layouts.app')
@section('title', 'TDS Slabs')

@section('page_actions')
  <form method="GET" action="{{ route('tds') }}" style="display:flex;gap:8px;align-items:center">
    <div class="fld" style="margin:0">
      <select name="fy" onchange="this.form.submit()">
        @foreach($years->merge([now()->month >= 4 ? now()->year : now()->year - 1])->unique()->sort() as $y)
          <option value="{{ $y }}" {{ $fy == $y ? 'selected' : '' }}>FY {{ $y }}-{{ $y + 1 }}</option>
        @endforeach
      </select>
    </div>
  </form>
  <button class="btn" onclick="om('tds-add-modal')">+ Add Slab</button>
  <button class="btn" onclick="om('upload-modal')">📤 Bulk Upload</button>
@endsection

@section('content')

  @if($slabs->isNotEmpty())
    @foreach($slabs as $regime => $regimeSlabs)
      <div class="card" style="margin-bottom:16px">
        <div class="ch">
          <div class="ct">{{ ucfirst($regime) }} Regime — FY {{ $fy }}-{{ $fy + 1 }}</div>
        </div>
        <table>
          <thead>
            <tr>
              <th>Income From (₹)</th>
              <th>Income To (₹)</th>
              <th>Rate (%)</th>
              <th>Surcharge (%)</th>
              <th>Cess (%)</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @foreach($regimeSlabs as $slab)
              <tr>
                <td>{{ number_format($slab->income_from, 0, '.', ',') }}</td>
                <td>{{ $slab->income_to ? number_format($slab->income_to, 0, '.', ',') : 'No limit' }}</td>
                <td>{{ $slab->tax_rate }}%</td>
                <td>{{ $slab->surcharge_rate ?? 0 }}%</td>
                <td>{{ $slab->cess_rate ?? 0 }}%</td>
                <td style="white-space:nowrap">
                  <button class="btn sm" style="font-size:10px"
                    onclick="openTdsEdit({{ $slab->id }}, {{ $slab->income_from }}, '{{ $slab->income_to ?? '' }}', {{ $slab->tax_rate }}, {{ $slab->surcharge_rate ?? 0 }}, {{ $slab->cess_rate ?? 0 }})">
                    Edit
                  </button>
                  <form method="POST" action="{{ route('tds.destroy', $slab) }}" style="display:inline">
                    @csrf @method('DELETE')
                    <button type="button" class="btn sm" style="font-size:10px;color:var(--red)"
                      onclick="swConfirm(this.closest('form'), 'Delete TDS Slab?', 'This TDS slab will be permanently removed.', 'warning', 'Yes, delete', '#dc2626')">Del</button>
                  </form>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endforeach
  @else
    <div class="card">
      <div class="empty">
        <div class="ico">📑</div>
        <p>No TDS slabs found for FY {{ $fy }}-{{ $fy + 1 }}.</p>
      </div>
    </div>
  @endif
@endsection

{{-- Add Slab Modal --}}
<div class="modal" id="tds-add-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Add TDS Slab</span>
      <button class="mc" onclick="cm('tds-add-modal')">✕</button>
    </div>
    <form method="POST" action="{{ route('tds.store') }}">
      @csrf
      <div class="fg2">
        <div class="fld">
          <label>Financial Year *</label>
          <input type="number" name="financial_year" required value="{{ $fy }}" min="2000" max="2099"
            placeholder="e.g. 2024">
          <span style="font-size:10px;color:var(--t3)">Enter start year (2024 = FY 2024-25)</span>
        </div>
        <div class="fld">
          <label>Regime *</label>
          <select name="regime" required>
            <option value="new">New Regime</option>
            <option value="old">Old Regime</option>
          </select>
        </div>
        <div class="fld">
          <label>Income From (₹) *</label>
          <input type="number" name="income_from" required min="0" step="1" placeholder="0">
        </div>
        <div class="fld">
          <label>Income To (₹)</label>
          <input type="number" name="income_to" min="1" step="1" placeholder="Leave blank = No limit">
        </div>
        <div class="fld">
          <label>Tax Rate (%) *</label>
          <input type="number" name="tax_rate" required min="0" max="100" step="0.01" placeholder="e.g. 5">
        </div>
        <div class="fld">
          <label>Surcharge (%)</label>
          <input type="number" name="surcharge_rate" min="0" max="100" step="0.01" value="0" placeholder="0">
        </div>
        <div class="fld full">
          <label>Cess (%)</label>
          <input type="number" name="cess_rate" min="0" max="100" step="0.01" value="4" placeholder="4">
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('tds-add-modal')">Cancel</button>
        <button type="submit" class="btn acc">Add Slab</button>
      </div>
    </form>
  </div>
</div>

{{-- Edit Slab Modal --}}
<div class="modal" id="tds-edit-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Edit TDS Slab</span>
      <button class="mc" onclick="cm('tds-edit-modal')">✕</button>
    </div>
    <form method="POST" id="tds-edit-form">
      @csrf
      <div class="fg2">
        <div class="fld">
          <label>Income From (₹) *</label>
          <input type="number" name="income_from" id="edit-tds-from" required min="0" step="1">
        </div>
        <div class="fld">
          <label>Income To (₹)</label>
          <input type="number" name="income_to" id="edit-tds-to" min="1" step="1" placeholder="Leave blank = No limit">
        </div>
        <div class="fld">
          <label>Tax Rate (%) *</label>
          <input type="number" name="tax_rate" id="edit-tds-rate" required min="0" max="100" step="0.01">
        </div>
        <div class="fld">
          <label>Surcharge (%)</label>
          <input type="number" name="surcharge_rate" id="edit-tds-surcharge" min="0" max="100" step="0.01">
        </div>
        <div class="fld full">
          <label>Cess (%)</label>
          <input type="number" name="cess_rate" id="edit-tds-cess" min="0" max="100" step="0.01">
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('tds-edit-modal')">Cancel</button>
        <button type="submit" class="btn acc">Save Changes</button>
      </div>
    </form>
  </div>
</div>

@push('scripts')
  <script>
    function openTdsEdit(id, from, to, rate, surcharge, cess) {
      document.getElementById('edit-tds-from').value = from;
      document.getElementById('edit-tds-to').value = to;
      document.getElementById('edit-tds-rate').value = rate;
      document.getElementById('edit-tds-surcharge').value = surcharge;
      document.getElementById('edit-tds-cess').value = cess;
      document.getElementById('tds-edit-form').action = '/tds/' + id;
      om('tds-edit-modal');
    }
  </script>
@endpush

@include('partials.upload-modal', [
  'uploadId' => 'upload-modal',
  'uploadTitle' => 'Bulk Upload TDS Slabs',
  'uploadRoute' => 'tds.import',
  'templateRoute' => 'tds.template',
  'uploadHint' => 'Template columns: Financial Year (e.g. 2024), Regime (old/new), Income From, Income To (blank = no limit), Tax Rate %, Surcharge %, Cess %. One row per slab.',
])
