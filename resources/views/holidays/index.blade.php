@extends('layouts.app')
@section('title', 'Holidays')

@section('page_actions')
  <button class="btn" onclick="om('weekoff-modal')">📅 Configure Week Offs</button>
  <button class="btn acc" onclick="om('holiday-modal')">+ Add Holiday</button>
  <button class="btn" onclick="om('upload-modal')">📤 Bulk Upload</button>
@endsection

@section('content')

  {{-- ── Filter + Search bar ────────────────────────────────────────────── --}}
  <form method="GET" action="{{ route('holidays') }}" class="card" data-ajax-filter
    style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">

    <div class="fld" style="min-width:160px">
      <label>Company</label>
      <select name="company_id">
        <option value="">All Companies</option>
        @foreach($companies as $co)
          <option value="{{ $co->id }}" {{ $companyFilter == $co->id ? 'selected' : '' }}>{{ $co->name }}</option>
        @endforeach
      </select>
    </div>

    <div class="fld" style="min-width:90px">
      <label>Year</label>
      <select name="year">
        @for($y = now()->year + 1; $y >= now()->year - 2; $y--)
          <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
        @endfor
      </select>
    </div>

    <div class="fld" style="min-width:200px">
      <label>Search by Name</label>
      <input type="text" name="search_name" value="{{ $searchName }}" placeholder="e.g. Diwali, Sunday…">
    </div>

    <div class="fld" style="min-width:160px">
      <label>Search by Date</label>
      <input type="date" name="search_date" value="{{ $searchDate }}">
    </div>

    <button type="submit" class="btn acc">Search</button>
    <a href="{{ route('holidays', ['year' => $year]) }}" class="btn" data-ajax-reset>Reset</a>
  </form>

  {{-- ── Holidays Table ───────────────────────────────────────────────────── --}}
  <div id="ajax-results">
  <div class="card">
    <div class="ch" style="display:flex;justify-content:space-between;align-items:center">
      <div class="ct">Holidays — {{ $year }}</div>
      <div style="font-size:12px;color:var(--t3)">
        {{ number_format($totalRecords) }} record(s)
        @if($searchName || $searchDate)
          <span style="color:var(--acc)"> — filtered</span>
        @endif
        @if($holidays->lastPage() > 1) · Page {{ $holidays->currentPage() }}/{{ $holidays->lastPage() }} @endif
      </div>
    </div>

    @if($holidays->isNotEmpty())
      <div style="overflow-x:auto">
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Day</th>
              <th>Holiday Name</th>
              <th>Type</th>
              <th>Company</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @foreach($holidays as $h)
              @php
                $carbon    = \Carbon\Carbon::parse($h->date);
                $isWeekend = in_array($carbon->dayOfWeek, [0, 6]);
                $typeColors = [
                  'national'   => ['bg'=>'#dbeafe','color'=>'#1d4ed8'],
                  'regional'   => ['bg'=>'#dcfce7','color'=>'#15803d'],
                  'optional'   => ['bg'=>'#fef9c3','color'=>'#a16207'],
                  'company'    => ['bg'=>'#f3e8ff','color'=>'#7e22ce'],
                  'weekly_off' => ['bg'=>'#f0fdf4','color'=>'#166534'],
                ];
                $tc = $typeColors[$h->type] ?? ['bg'=>'#f3f4f6','color'=>'#374151'];
              @endphp
              <tr>
                <td style="white-space:nowrap;font-weight:{{ $isWeekend ? '600' : '400' }}">
                  {{ $carbon->format('d M Y') }}
                </td>
                <td style="color:var(--t3);white-space:nowrap">{{ $carbon->format('l') }}</td>
                <td>{{ $h->name }}</td>
                <td>
                  <span style="display:inline-block;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:700;
                               background:{{ $tc['bg'] }};color:{{ $tc['color'] }}">
                    {{ str_replace('_', ' ', ucfirst($h->type)) }}
                  </span>
                </td>
                <td style="font-size:12px;color:var(--t2)">
                  {{ $h->calendar?->company?->name ?? ($h->calendar?->name ?? '—') }}
                </td>
                <td>
                  <form method="POST" action="{{ route('holidays.destroy', $h) }}" style="display:inline">
                    @csrf @method('DELETE')
                    <button type="button" class="btn sm" style="color:var(--red)"
                      onclick="swConfirm(this.closest('form'), 'Delete Holiday?',
                        'Remove {{ addslashes($h->name) }}?',
                        'warning', 'Yes, delete', '#dc2626')">Del</button>
                  </form>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @if($holidays->hasPages())
        <div style="padding:14px 18px;border-top:1px solid var(--b1)">{{ $holidays->links() }}</div>
      @endif

      @if(($searchName || $searchDate) && $holidays->total() === 0)
        <div class="empty" style="padding:24px">
          <div class="ico">🔍</div>
          <p>No holidays matching your search.</p>
          <a href="{{ route('holidays', ['year' => $year]) }}" class="btn acc" style="margin-top:10px" data-ajax-reset>Clear Search</a>
        </div>
      @endif
    @else
      <div class="empty">
        <div class="ico">🗓️</div>
        <p>No holidays found for {{ $year }}{{ $companyFilter ? ' for this company' : '' }}.</p>
        <p style="font-size:12px;color:var(--t3);margin-top:4px">Add a holiday, configure week offs, or bulk upload.</p>
      </div>
    @endif
  </div>
  </div>

  {{-- ── Configure Week Offs Modal ─────────────────────────────────────── --}}
  <div class="modal" id="weekoff-modal">
    <div class="mb wide">
      <div class="mh">
        <span class="mt">📅 Configure Week Offs</span>
        <button class="mc" onclick="cm('weekoff-modal')">✕</button>
      </div>
      <div style="font-size:12px;color:var(--t2);background:var(--acc2);border-radius:8px;padding:10px 14px;margin-bottom:14px;line-height:1.6">
        Auto-generates Sunday / Saturday off entries in the holidays table for the selected companies and period.
        These replace the static week-off calculation in the salary report.
      </div>
      <form method="POST" action="{{ route('holidays.weekoffs') }}">
        @csrf
        <div class="fg2">

          {{-- Company checkboxes --}}
          <div class="fld full">
            <label class="req">Companies / Firms</label>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:4px">
              <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;
                            background:var(--s2);border:1px solid var(--b1);border-radius:6px;padding:6px 10px">
                <input type="checkbox" id="co-all" onchange="toggleAllCo(this)"> <strong>All Companies</strong>
              </label>
              @foreach($companies as $co)
              <label style="display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer;
                            background:var(--s2);border:1px solid var(--b1);border-radius:6px;padding:6px 10px">
                <input type="checkbox" name="company_ids[]" value="{{ $co->id }}" class="co-chk"
                  {{ old('company_ids') && in_array($co->id, old('company_ids')) ? 'checked' : '' }}>
                {{ $co->name }}
              </label>
              @endforeach
            </div>
            @error('company_ids') <div class="ferr">{{ $message }}</div> @enderror
          </div>

          {{-- Period --}}
          <div class="fld">
            <label class="req">From Date</label>
            <input type="date" name="start_date" required value="{{ old('start_date', now()->startOfYear()->toDateString()) }}">
          </div>
          <div class="fld">
            <label class="req">To Date</label>
            <input type="date" name="end_date" required value="{{ old('end_date', now()->endOfYear()->toDateString()) }}">
          </div>

          {{-- Sundays --}}
          <div class="fld full">
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
              <input type="checkbox" name="include_sundays" value="1" checked
                style="width:15px;height:15px">
              <span>✅ All <strong>Sundays</strong> are weekly off (recommended, checked by default)</span>
            </label>
          </div>

          {{-- Saturdays --}}
          <div class="fld full">
            <label class="req" style="margin-bottom:6px;display:block">Saturday Off Configuration</label>
            <div style="display:flex;flex-wrap:wrap;gap:8px">
              @foreach([
                'none'    => 'No Saturdays off',
                'all'     => 'All Saturdays off',
                '1st'     => '1st Saturday only',
                '2nd'     => '2nd Saturday only',
                '3rd'     => '3rd Saturday only',
                '4th'     => '4th Saturday only',
                '1st_3rd' => '1st & 3rd Saturday',
                '2nd_4th' => '2nd & 4th Saturday',
              ] as $val => $label)
              <label style="display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer;
                            background:var(--s2);border:1px solid var(--b1);border-radius:6px;padding:6px 10px">
                <input type="radio" name="saturday_config" value="{{ $val }}"
                  {{ (old('saturday_config', '2nd_4th') === $val) ? 'checked' : '' }}>
                {{ $label }}
              </label>
              @endforeach
            </div>
          </div>

        </div>
        <div class="mf">
          <button type="button" class="btn" onclick="cm('weekoff-modal')">Cancel</button>
          <button type="submit" class="btn acc">Generate Week Offs</button>
        </div>
      </form>
    </div>
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
          {{-- Multi-company checkboxes --}}
          <div class="fld full">
            <label class="req">Apply to Companies</label>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:4px">
              <label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer;
                            background:var(--s2);border:1px solid var(--b1);border-radius:6px;padding:5px 8px">
                <input type="checkbox" id="hco-all" onchange="toggleAllHco(this)"> <strong>All</strong>
              </label>
              @foreach($companies as $co)
              <label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer;
                            background:var(--s2);border:1px solid var(--b1);border-radius:6px;padding:5px 8px">
                <input type="checkbox" name="company_ids[]" value="{{ $co->id }}" class="hco-chk"
                  {{ old('company_ids') && in_array($co->id, old('company_ids')) ? 'checked' : '' }}>
                {{ $co->name }}
              </label>
              @endforeach
            </div>
            @error('company_ids') <div class="ferr">{{ $message }}</div> @enderror
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

@push('scripts')
<script>
function toggleAllCo(master) {
  document.querySelectorAll('.co-chk').forEach(c => c.checked = master.checked);
}
function toggleAllHco(master) {
  document.querySelectorAll('.hco-chk').forEach(c => c.checked = master.checked);
}
</script>
@endpush
