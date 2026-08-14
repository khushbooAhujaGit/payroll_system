@extends('layouts.app')
@section('title', 'Leave Calendar')

@section('page_actions')
  <a href="{{ route('leave') }}" class="btn">← Leave Management</a>
@endsection

@section('content')

@php
  $prevMonth = $monthStart->copy()->subMonth()->format('Y-m');
  $nextMonth = $monthStart->copy()->addMonth()->format('Y-m');
  $weekdays  = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
@endphp

<div class="card" style="margin-bottom:16px">
  <div class="ch">
    <div class="ct">{{ $monthStart->format('F Y') }}</div>
    <div style="display:flex;gap:6px">
      <a href="{{ route('leave.calendar', ['month' => $prevMonth]) }}" class="btn sm">← Prev</a>
      <a href="{{ route('leave.calendar') }}" class="btn sm">Today</a>
      <a href="{{ route('leave.calendar', ['month' => $nextMonth]) }}" class="btn sm">Next →</a>
    </div>
  </div>

  <div style="padding:14px 18px">
    <div class="cal-grid" style="margin-bottom:10px">
      @foreach($weekdays as $wd)
        <div class="cal-weekday">{{ $wd }}</div>
      @endforeach
    </div>

    <div class="cal-grid">
      @foreach($days as $day)
        @if(is_null($day))
          <div class="cal-day empty"></div>
        @else
          <div class="cal-day {{ $day['is_weekend'] ? 'weekend' : '' }} {{ $day['is_today'] ? 'today' : '' }} {{ $day['count'] > 0 ? 'has-leave' : '' }}"
            @if($day['count'] > 0) onclick="om('cal-day-{{ $day['date'] }}')" @endif>
            <div class="cal-day-num">{{ $day['day'] }}</div>
            @if($day['count'] > 0)
              <div class="cal-day-count">{{ $day['count'] }}</div>
              <div class="cal-dots">
                @foreach($day['by_type'] as $t)
                  <span class="cal-dot" style="background:{{ $t['color'] }}" title="{{ $t['label'] }}"></span>
                @endforeach
              </div>
            @endif
          </div>
        @endif
      @endforeach
    </div>
  </div>
</div>

@if($legend->isNotEmpty())
<div class="card">
  <div class="ch"><div class="ct">Leave Types</div></div>
  <div style="padding:14px 18px">
    <div class="cal-legend">
      @foreach($legend as $lt)
        <div class="cal-legend-item">
          <span class="cal-legend-dot" style="background:{{ $lt->color }}"></span>
          {{ $lt->name }}
        </div>
      @endforeach
    </div>
  </div>
</div>
@endif

{{-- One detail modal per day that has leave activity --}}
@foreach($days as $day)
  @if(!is_null($day) && $day['count'] > 0)
    <div class="modal" id="cal-day-{{ $day['date'] }}">
      <div class="mb">
        <div class="mh">
          <span class="mt">{{ \Carbon\Carbon::parse($day['date'])->format('d M Y (D)') }}</span>
          <button class="mc" onclick="cm('cal-day-{{ $day['date'] }}')">✕</button>
        </div>

        @if($canSeeNames)
          {{-- Admin/HR/Manager view: who specifically is out, and on what --}}
          @foreach($day['names'] as $n)
            <div class="cal-detail-row">
              <span class="cal-legend-dot" style="background:{{ $n['color'] }}"></span>
              <span style="flex:1">{{ $n['name'] }}</span>
              <span style="color:var(--t3);font-size:11px">{{ $n['label'] }}</span>
            </div>
          @endforeach
        @else
          {{-- Plain employee view: aggregate counts by leave type only, no names --}}
          @foreach($day['by_type'] as $t)
            <div class="cal-detail-row">
              <span class="cal-legend-dot" style="background:{{ $t['color'] }}"></span>
              <span style="flex:1">{{ $t['label'] }}</span>
              <span style="color:var(--t3);font-size:11px">{{ $t['count'] }} {{ $t['count'] === 1 ? 'person' : 'people' }}</span>
            </div>
          @endforeach
        @endif

        <div class="mf">
          <button type="button" class="btn" onclick="cm('cal-day-{{ $day['date'] }}')">Close</button>
        </div>
      </div>
    </div>
  @endif
@endforeach

@endsection
