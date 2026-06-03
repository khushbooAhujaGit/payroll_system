@extends('layouts.app')
@section('title', 'Holidays')

@section('page_actions')
  <button class="btn acc" onclick="om('holiday-modal')">+ Add Holiday</button>
  <button class="btn" onclick="om('upload-modal')">📤 Bulk Upload</button>
@endsection

@section('content')

  {{-- ── Filter bar ──────────────────────────────────────────────────────── --}}
  <form method="GET" action="{{ route('holidays') }}" class="card"
    style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">

    <div class="fld" style="min-width:180px">
      <label>Company</label>
      <select name="company_id" onchange="this.form.submit()">
        <option value="">All Companies</option>
        @foreach($companies as $co)
          <option value="{{ $co->id }}" {{ $companyFilter == $co->id ? 'selected' : '' }}>{{ $co->name }}</option>
        @endforeach
      </select>
    </div>

    <div class="fld" style="min-width:100px">
      <label>Year</label>
      <select name="year" onchange="this.form.submit()">
        @for($y = now()->year + 1; $y >= now()->year - 2; $y--)
          <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
        @endfor
      </select>
    </div>

    <button type="submit" class="btn acc">Filter</button>
    <a href="{{ route('holidays', ['year' => $year]) }}" class="btn">Reset</a>
  </form>

  {{-- ── Date-wise Holiday Table ─────────────────────────────────────────── --}}
  <div class="card">
    <div class="ch">
      <div class="ct">Holidays — {{ $year }}</div>
      <span style="font-size:12px;color:var(--t3)">{{ $allHolidays->count() }} total · {{ $byDate->count() }} unique dates</span>
    </div>

    @if($byDate->isNotEmpty())
      <div style="overflow-x:auto">
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Day</th>
              <th>Holiday Name</th>
              <th>Type</th>
              @if($activeCompanies->count() > 1)
                <th>Company</th>
              @endif
              <th></th>
            </tr>
          </thead>
          <tbody>
            @foreach($byDate as $dateStr => $holidaysOnDate)
              @php
                $isFirst = true;
                $rowspan = $holidaysOnDate->count();
                $carbon  = \Carbon\Carbon::parse($dateStr);
                $isWeekend = in_array($carbon->dayOfWeek, [0, 6]);
              @endphp
              @foreach($holidaysOnDate as $h)
                <tr style="{{ $isWeekend ? 'background:rgba(var(--acc-rgb,66,114,196),.04)' : '' }}">
                  @if($isFirst)
                    <td rowspan="{{ $rowspan }}" style="white-space:nowrap;font-weight:600;vertical-align:top;padding-top:10px">
                      {{ $carbon->format('d M Y') }}
                    </td>
                    <td rowspan="{{ $rowspan }}" style="color:var(--t3);white-space:nowrap;vertical-align:top;padding-top:10px">
                      {{ $carbon->format('l') }}
                    </td>
                  @endif

                  <td>{{ $h->name }}</td>

                  <td>
                    @php
                      $typeColors = [
                        'national' => ['bg'=>'#dbeafe','color'=>'#1d4ed8'],
                        'regional' => ['bg'=>'#dcfce7','color'=>'#15803d'],
                        'optional' => ['bg'=>'#fef9c3','color'=>'#a16207'],
                        'company'  => ['bg'=>'#f3e8ff','color'=>'#7e22ce'],
                      ];
                      $tc = $typeColors[$h->type] ?? ['bg'=>'#f3f4f6','color'=>'#374151'];
                    @endphp
                    <span style="display:inline-block;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:700;
                                 background:{{ $tc['bg'] }};color:{{ $tc['color'] }}">
                      {{ ucfirst($h->type) }}
                    </span>
                  </td>

                  @if($activeCompanies->count() > 1)
                    <td style="font-size:12px;color:var(--t3)">{{ $h->company_name }}</td>
                  @endif

                  <td>
                    <form method="POST" action="{{ route('holidays.destroy', $h) }}" style="display:inline">
                      @csrf @method('DELETE')
                      <button type="button" class="btn sm" style="color:var(--red)"
                        onclick="swConfirm(this.closest('form'), 'Delete Holiday?',
                          'Remove {{ addslashes($h->name) }} from {{ addslashes($h->company_name) }}?',
                          'warning', 'Yes, delete', '#dc2626')">Del</button>
                    </form>
                  </td>
                </tr>
                @php $isFirst = false; @endphp
              @endforeach
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <div class="empty">
        <div class="ico">🗓️</div>
        <p>No holidays found for {{ $year }}{{ $companyFilter ? ' for this company' : '' }}. Add one or bulk upload.</p>
      </div>
    @endif
  </div>

  {{-- ── Add Holiday Modal ───────────────────────────────────────────────── --}}
  <div class="modal" id="holiday-modal">
    <div class="mb">
      <div class="mh">
        <span class="mt">Add Holiday</span>
        <button class="mc" onclick="cm('holiday-modal')">✕</button>
      </div>
      <form method="POST" action="{{ route('holidays.store') }}">
        @csrf
        <div class="fg2">
          <div class="fld full">
            <label class="req">Company</label>
            <select name="company_id" required @error('company_id') class="inp-err" @enderror>
              <option value="">Select Company</option>
              @foreach($companies as $co)
                <option value="{{ $co->id }}" {{ old('company_id', $companyFilter) == $co->id ? 'selected' : '' }}>
                  {{ $co->name }}
                </option>
              @endforeach
            </select>
            @error('company_id') <div class="ferr">{{ $message }}</div> @enderror
          </div>
          <div class="fld">
            <label class="req">Date</label>
            <input type="date" name="date" required value="{{ old('date') }}"
              @error('date') class="inp-err" @enderror>
            @error('date') <div class="ferr">{{ $message }}</div> @enderror
          </div>
          <div class="fld">
            <label class="req">Type</label>
            <select name="type" required>
              @foreach(['national'=>'National','regional'=>'Regional','optional'=>'Optional','company'=>'Company'] as $val => $label)
                <option value="{{ $val }}" {{ old('type') === $val ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="fld full">
            <label class="req">Holiday Name</label>
            <input type="text" name="name" required placeholder="e.g. Republic Day"
              value="{{ old('name') }}" @error('name') class="inp-err" @enderror>
            @error('name') <div class="ferr">{{ $message }}</div> @enderror
          </div>
          <div class="fld full">
            <label>Description</label>
            <input type="text" name="description" placeholder="Optional" value="{{ old('description') }}">
          </div>
        </div>
        <div class="mf">
          <button type="button" class="btn" onclick="cm('holiday-modal')">Cancel</button>
          <button type="submit" class="btn acc">Add Holiday</button>
        </div>
      </form>
    </div>
  </div>

  @if($errors->any())
    <script>document.addEventListener('DOMContentLoaded', () => om('holiday-modal'))</script>
  @endif

@endsection

@include('partials.upload-modal', [
    'uploadId'      => 'upload-modal',
    'uploadTitle'   => 'Bulk Upload Holidays',
    'uploadRoute'   => 'holidays.import',
    'templateRoute' => 'holidays.template',
    'uploadHint'    => 'Template columns: Company, Date (YYYY-MM-DD), Name, Type (national/regional/optional/company). One row per holiday per company.',
    'uploadFields'  => [],
])
