@extends('layouts.app')
@section('title', 'Holidays')

@section('page_actions')
  <button class="btn acc" onclick="om('holiday-modal')">+ Add Holiday</button>
  <button class="btn" onclick="om('upload-modal')">📤 Bulk Upload</button>
@endsection

@section('content')

  {{-- Calendar selector --}}
  <form method="GET" action="{{ route('holidays') }}" class="card"
    style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end">
    <div class="fld" style="min-width:200px">
      <label>Calendar</label>
      <select name="calendar_id" onchange="this.form.submit()">
        @foreach($calendars as $cal)
          <option value="{{ $cal->id }}" {{ $calendarId == $cal->id ? 'selected' : '' }}>{{ $cal->name }}</option>
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
    <input type="hidden" name="calendar_id" value="{{ $calendarId }}">
  </form>

  <div class="card">
    <div class="ch">
      <div class="ct">Holidays — {{ $year }}</div>
      <span style="font-size:12px;color:var(--t3)">{{ $holidays->count() }} holidays</span>
    </div>
    @if($holidays->isNotEmpty())
      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th>Day</th>
            <th>Name</th>
            <th>Type</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach($holidays as $h)
            <tr>
              <td>{{ \Carbon\Carbon::parse($h->date)->format('d M Y') }}</td>
              <td style="color:var(--t3)">{{ \Carbon\Carbon::parse($h->date)->format('l') }}</td>
              <td>{{ $h->name }}</td>
              <td>
                @php $types = ['national' => 'var(--acc)', 'regional' => 'var(--grn)', 'optional' => 'var(--yel)']; @endphp
                <span class="bx" style="color:{{ $types[$h->type] ?? 'var(--t3)' }}">{{ ucfirst($h->type) }}</span>
              </td>
              <td>
                <form method="POST" action="{{ route('holidays.destroy', $h) }}">
                  @csrf
                  @method('DELETE')
                  <button type="button" class="btn sm" style="background:var(--red2);color:var(--red)"
                    onclick="swConfirm(this.closest('form'), 'Delete Holiday?', 'This holiday will be permanently removed.', 'warning', 'Yes, delete', '#dc2626')">Delete</button>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @else
      <div class="empty">
        <div class="ico">🗓️</div>
        <p>No holidays for this year. Add one.</p>
      </div>
    @endif
  </div>

  {{-- Add Holiday Modal --}}
  <div class="modal" id="holiday-modal">
    <div class="mb">
      <div class="mh">
        <span class="mt">Add Holiday</span>
        <button class="mc" onclick="cm('holiday-modal')">✕</button>
      </div>
      <form method="POST" action="{{ route('holidays.store') }}">
        @csrf
        <input type="hidden" name="calendar_id" value="{{ $calendarId }}">
        <div class="fg2">
          <div class="fld">
            <label>Date *</label>
            <input type="date" name="date" required>
          </div>
          <div class="fld">
            <label>Type *</label>
            <select name="type" required>
              <option value="national">National</option>
              <option value="regional">Regional</option>
              <option value="optional">Optional</option>
            </select>
          </div>
          <div class="fld full">
            <label>Holiday Name *</label>
            <input type="text" name="name" required placeholder="e.g. Republic Day">
          </div>
          <div class="fld full">
            <label>Description</label>
            <input type="text" name="description" placeholder="Optional">
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
    'uploadHint'    => 'Template columns: Date (YYYY-MM-DD), Name, Type (national/regional/optional). One row per holiday.',
    'uploadFields'  => ['calendar_id' => $calendarId],
])