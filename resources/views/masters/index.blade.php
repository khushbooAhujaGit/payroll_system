@extends('layouts.app')
@section('title', 'Companies')

@section('page_actions')
  <button class="btn acc" onclick="om('company-modal')">+ Add Company</button>
@endsection

@section('content')

  <div class="card">
    <div class="ch"><div class="ct">Companies</div></div>
    @if($companies->isNotEmpty())
    <table>
      <thead>
        <tr>
          <th>Company Name</th>
          <th>Short Name</th>
          <th>PAN</th>
          <th>TAN</th>
          <th>GSTIN</th>
          <th>CIN</th>
          <th>Email</th>
          <th>Phone</th>
          <th>State / City</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @foreach($companies as $co)
        <tr>
          <td style="font-weight:600">{{ $co->name }}</td>
          <td class="mono">{{ $co->short_name ?? '—' }}</td>
          <td class="mono" style="font-size:11px">{{ $co->pan ?? '—' }}</td>
          <td class="mono" style="font-size:11px">{{ $co->tan ?? '—' }}</td>
          <td class="mono" style="font-size:11px">{{ $co->gstin ?? '—' }}</td>
          <td class="mono" style="font-size:11px">{{ $co->cin ?? '—' }}</td>
          <td style="font-size:12px">{{ $co->email ?? '—' }}</td>
          <td style="font-size:12px">{{ $co->phone ?? '—' }}</td>
          <td style="font-size:12px;color:var(--t3)">
            {{ implode(', ', array_filter([$co->state, $co->city])) ?: '—' }}
          </td>
          <td>
            <form method="POST" action="{{ route('masters.company.destroy', $co) }}" style="display:inline">
              @csrf @method('DELETE')
              <button type="button" class="btn sm" style="color:var(--red)"
                onclick="swConfirm(this.closest('form'), 'Delete Company?',
                  'Delete {{ addslashes($co->name) }}? This cannot be undone.',
                  'warning', 'Yes, delete', '#dc2626')">Del</button>
            </form>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @else
    <div class="empty">
      <div class="ico">🏢</div>
      <p>No companies yet. <a href="#" onclick="om('company-modal')">Add one now.</a></p>
    </div>
    @endif
  </div>

{{-- Add Company Modal --}}
<div class="modal" id="company-modal">
  <div class="mb wide">
    <div class="mh">
      <span class="mt">Add Company</span>
      <button class="mc" onclick="cm('company-modal')">✕</button>
    </div>
    <form method="POST" action="{{ route('masters.company') }}">
      @csrf
      <div style="margin:14px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
        Basic Info
      </div>
      <div class="fg2">
        <div class="fld">
          <label class="req">Company Name</label>
          <input type="text" name="name" required value="{{ old('name') }}"
            @error('name') class="inp-err" @enderror>
          @error('name') <div class="ferr">{{ $message }}</div> @enderror
        </div>
        <div class="fld">
          <label>Short Name</label>
          <input type="text" name="short_name" maxlength="50" value="{{ old('short_name') }}">
        </div>
        <div class="fld">
          <label>PAN</label>
          <input type="text" name="pan" maxlength="10" value="{{ old('pan') }}"
            placeholder="ABCDE1234F" style="text-transform:uppercase"
            oninput="this.value=this.value.toUpperCase()">
        </div>
        <div class="fld">
          <label>TAN</label>
          <input type="text" name="tan" maxlength="10" value="{{ old('tan') }}"
            style="text-transform:uppercase" oninput="this.value=this.value.toUpperCase()">
        </div>
        <div class="fld">
          <label>GSTIN</label>
          <input type="text" name="gstin" maxlength="15" value="{{ old('gstin') }}"
            style="text-transform:uppercase" oninput="this.value=this.value.toUpperCase()">
        </div>
        <div class="fld">
          <label>CIN</label>
          <input type="text" name="cin" maxlength="21" value="{{ old('cin') }}">
        </div>
      </div>

      <div style="margin:14px 0 8px;font-size:11px;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.08em">
        Contact & Address
      </div>
      <div class="fg2">
        <div class="fld">
          <label>Email</label>
          <input type="email" name="email" value="{{ old('email') }}">
        </div>
        <div class="fld">
          <label>Phone</label>
          <input type="text" name="phone" maxlength="20" value="{{ old('phone') }}">
        </div>
        <div class="fld">
          <label>State</label>
          <input type="text" name="state" maxlength="100" value="{{ old('state') }}">
        </div>
        <div class="fld">
          <label>City</label>
          <input type="text" name="city" maxlength="100" value="{{ old('city') }}">
        </div>
        <div class="fld full">
          <label>Address</label>
          <textarea name="address" rows="2" style="width:100%">{{ old('address') }}</textarea>
        </div>
      </div>

      <div class="mf">
        <button type="button" class="btn" onclick="cm('company-modal')">Cancel</button>
        <button type="submit" class="btn acc">Create Company</button>
      </div>
    </form>
  </div>
</div>

@if($errors->any())
  <script>document.addEventListener('DOMContentLoaded', () => om('company-modal'))</script>
@endif

@endsection
