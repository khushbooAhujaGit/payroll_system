@extends('layouts.app')
@section('title', 'Payroll & Salary Reports')

@section('page_actions')
  <button class="btn acc" onclick="om('gen-modal')">📊 Generate Report</button>
  <button class="btn" onclick="om('period-modal')">+ New Period</button>
@endsection

@section('content')

{{-- ── Stats row ──────────────────────────────────────────────── --}}
<div class="g4" style="margin-bottom:16px">
  <div class="sc">
    <div class="lbl">Saved Reports</div>
    <div class="val">{{ $savedReports->count() }}</div>
  </div>
  <div class="sc">
    <div class="lbl">Latest Report</div>
    <div class="val" style="font-size:13px">
      @if($savedReports->isNotEmpty())
        {{ \Carbon\Carbon::createFromFormat('Y-m', $savedReports->first()['year_month'])->format('M Y') }}
      @else —
      @endif
    </div>
  </div>
  <div class="sc">
    <div class="lbl">Payroll Periods</div>
    <div class="val">{{ $periods->total() }}</div>
  </div>
  @if($lastRun)
  <div class="sc">
    <div class="lbl">Last Run</div>
    <div class="val" style="font-size:12px">{{ $lastRun->run_name }} — {{ $lastRun->period?->year }}/{{ str_pad($lastRun->period?->month,2,'0',STR_PAD_LEFT) }}</div>
  </div>
  @else
  <div class="sc"><div class="lbl">Last Run</div><div class="val" style="color:var(--t3)">None</div></div>
  @endif
</div>

{{-- ═══════════════════════════════════════════════════════════
     SECTION 1 — SALARY REPORTS
═══════════════════════════════════════════════════════════ --}}
<div class="card" style="margin-bottom:16px">
  <div class="ch" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <div class="ct">🗂 Salary Reports</div>
    <button class="btn sm acc" onclick="om('gen-modal')">📊 Generate / Preview Report</button>
  </div>

  @if($savedReports->isNotEmpty())
  <table>
    <thead>
      <tr>
        <th>Month</th>
        <th style="text-align:center">Employees</th>
        <th style="text-align:right">Total Payable</th>
        <th style="text-align:right">Total Net Pay</th>
        <th>Locked</th>
        <th>By</th>
        <th style="text-align:center">Actions</th>
      </tr>
    </thead>
    <tbody>
      @foreach($savedReports as $rep)
      @php
        $repLabel  = \Carbon\Carbon::createFromFormat('Y-m', $rep['year_month'])->format('F Y');
        $lockerName = $lockers[$rep['locked_by']] ?? '—';
      @endphp
      <tr>
        <td>
          <span class="mono" style="font-weight:600">{{ $rep['year_month'] }}</span>
          <div style="font-size:10px;color:var(--t3)">{{ $repLabel }}</div>
        </td>
        <td style="text-align:center">{{ $rep['employee_count'] }}</td>
        <td style="text-align:right;color:var(--acc);font-weight:600">
          ₹ {{ number_format($rep['total_gross'], 0) }}
        </td>
        <td style="text-align:right;color:var(--grn);font-weight:700">
          ₹ {{ number_format($rep['total_net_pay'], 0) }}
        </td>
        <td style="font-size:11px;color:var(--t3)">
          {{ \Carbon\Carbon::parse($rep['locked_at'])->format('d M Y, h:i A') }}
        </td>
        <td style="font-size:11px">{{ $lockerName }}</td>
        <td style="white-space:nowrap;text-align:center">
          {{-- View locked snapshot --}}
          <a href="{{ route('salary.report.view', $rep['year_month']) }}" class="btn sm">
            🔒 View
          </a>
          {{-- Download --}}
          <form method="POST" action="{{ route('salary.report.download') }}" style="display:inline">
            @csrf
            <input type="hidden" name="month" value="{{ $rep['year_month'] }}">
            <input type="hidden" name="entity_id" value="0">
            <button type="submit" class="btn sm" style="background:var(--grn2);color:var(--grn)">
              📥 Excel
            </button>
          </form>
          {{-- Unlock --}}
          <form method="POST" action="{{ route('salary.report.unlock') }}" style="display:inline">
            @csrf
            <input type="hidden" name="month" value="{{ $rep['year_month'] }}">
            <input type="hidden" name="entity_id" value="0">
            <button type="button" class="btn sm" style="background:#fff7ed;color:#c2410c"
              onclick="swConfirm(this.closest('form'),
                'Unlock {{ $repLabel }}?',
                'This removes the frozen snapshot and reverses any loan EMI deductions recorded for this month. The report will recalculate live again.',
                'warning','Yes, unlock','#c2410c')">
              🔓 Unlock
            </button>
          </form>
          {{-- Delete --}}
          <form method="POST" action="{{ route('salary.report.delete') }}" style="display:inline">
            @csrf
            <input type="hidden" name="month" value="{{ $rep['year_month'] }}">
            <button type="button" class="btn sm" style="color:var(--red)"
              onclick="swConfirm(this.closest('form'),
                'Delete {{ $repLabel }} snapshot?',
                'This permanently removes the saved report. The data cannot be recovered.',
                'warning','Yes, delete','#dc2626')">
              🗑
            </button>
          </form>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @else
  <div class="empty">
    <div class="ico">📊</div>
    <p>No saved salary reports yet.</p>
    <p style="font-size:12px;color:var(--t3)">Click "Generate / Preview Report" to create and lock a salary report for any month.</p>
  </div>
  @endif
</div>

{{-- ═══════════════════════════════════════════════════════════
     SECTION 2 — PAYROLL PERIODS
═══════════════════════════════════════════════════════════ --}}
<div class="card">
  <div class="ch" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <div class="ct">📅 Payroll Periods</div>
    <button class="btn sm" onclick="om('period-modal')">+ New Period</button>
  </div>

  @if($periods->isNotEmpty())
  <table>
    <thead>
      <tr>
        <th>Period</th>
        <th>From</th>
        <th>To</th>
        <th>Status</th>
        <th style="text-align:center">Runs</th>
        <th>Salary Report</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      @foreach($periods as $period)
      @php
        $ym        = $period->year . '-' . str_pad($period->month, 2, '0', STR_PAD_LEFT);
        $hasReport = $savedReports->firstWhere('year_month', $ym) !== null;
        $clr       = ['open' => 'var(--grn)', 'closed' => 'var(--t3)', 'locked' => 'var(--red)'][$period->status] ?? 'var(--t3)';
      @endphp
      <tr>
        <td class="mono">{{ $ym }}</td>
        <td>{{ \Carbon\Carbon::parse($period->period_from)->format('d M Y') }}</td>
        <td>{{ \Carbon\Carbon::parse($period->period_to)->format('d M Y') }}</td>
        <td>
          <span class="bx" style="color:{{ $clr }}">{{ ucfirst($period->status) }}</span>
          @if($period->is_locked)<span class="bx" style="background:var(--red2);color:var(--red)">Locked</span>@endif
        </td>
        <td style="text-align:center">{{ $period->runs_count }}</td>
        <td>
          @if($hasReport)
            <a href="{{ route('salary.report.view', $ym) }}" class="btn sm" style="background:var(--grn2);color:var(--grn)">
              🔒 View Report
            </a>
          @else
            <a href="{{ route('salary.report') }}?month={{ $ym }}" class="btn sm">
              📊 Generate
            </a>
          @endif
        </td>
        <td>
          @if(!$period->is_locked)
          <button class="btn sm" onclick="om('run-modal');document.getElementById('run-period-id').value='{{ $period->id }}'">
            ▶ Run Payroll
          </button>
          @endif
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
  <div style="margin-top:14px">{{ $periods->links() }}</div>
  @else
  <div class="empty"><div class="ico">💰</div><p>No payroll periods yet. Create one to get started.</p></div>
  @endif
</div>

{{-- ════════════ GENERATE REPORT MODAL ════════════ --}}
<div class="modal" id="gen-modal">
  <div class="mb" style="max-width:440px">
    <div class="mh">
      <span class="mt">Generate Salary Report</span>
      <button class="mc" onclick="cm('gen-modal')">✕</button>
    </div>
    <p style="font-size:12px;color:var(--t3);margin-bottom:16px">
      Select month and firm to preview the live salary sheet. From there you can review, edit overrides, and lock (save) the report.
    </p>
    <div class="fg2">
      <div class="fld">
        <label>Month *</label>
        <input type="month" id="gen-month" value="{{ now()->format('Y-m') }}" required>
      </div>
      <div class="fld">
        <label>Firm</label>
        <select id="gen-entity">
          <option value="0">All Firms</option>
          @foreach($entities as $ent)
          <option value="{{ $ent->id }}">{{ $ent->short_name ?? $ent->name }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <div class="mf" style="margin-top:16px">
      <button type="button" class="btn" onclick="cm('gen-modal')">Cancel</button>
      <button type="button" class="btn acc" onclick="goToReport()">Preview Report →</button>
    </div>
  </div>
</div>

{{-- ════════════ NEW PERIOD MODAL ════════════ --}}
<div class="modal" id="period-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Create Payroll Period</span>
      <button class="mc" onclick="cm('period-modal')">✕</button>
    </div>
    <form method="POST" action="{{ route('payroll.period') }}">
      @csrf
      <div class="fg2">
        <div class="fld">
          <label>Year *</label>
          <input type="number" name="year" required value="{{ now()->year }}" min="2020" max="2100">
        </div>
        <div class="fld">
          <label>Month *</label>
          <select name="month" required>
            @foreach(['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'] as $i => $m)
            <option value="{{ $i+1 }}" {{ now()->month == $i+1 ? 'selected' : '' }}>{{ $m }}</option>
            @endforeach
          </select>
        </div>
        <div class="fld">
          <label>Period From *</label>
          <input type="date" name="period_from" required value="{{ now()->startOfMonth()->toDateString() }}">
        </div>
        <div class="fld">
          <label>Period To *</label>
          <input type="date" name="period_to" required value="{{ now()->endOfMonth()->toDateString() }}">
        </div>
        <div class="fld full">
          <label>Payment Date</label>
          <input type="date" name="payment_date" value="{{ now()->endOfMonth()->addDays(5)->toDateString() }}">
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('period-modal')">Cancel</button>
        <button type="submit" class="btn acc">Create Period</button>
      </div>
    </form>
  </div>
</div>

{{-- ════════════ INIT RUN MODAL ════════════ --}}
<div class="modal" id="run-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Initiate Payroll Run</span>
      <button class="mc" onclick="cm('run-modal')">✕</button>
    </div>
    <form method="POST" action="{{ route('payroll.run') }}">
      @csrf
      <input type="hidden" name="payroll_period_id" id="run-period-id">
      <div class="fld" style="margin-bottom:14px">
        <label>Run Type *</label>
        <select name="run_type" required>
          <option value="regular">Regular</option>
          <option value="supplementary">Supplementary</option>
          <option value="arrear">Arrear</option>
          <option value="bonus">Bonus</option>
        </select>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('run-modal')">Cancel</button>
        <button type="submit" class="btn acc">▶ Initiate</button>
      </div>
    </form>
  </div>
</div>

@if($errors->any())
<script>document.addEventListener('DOMContentLoaded',()=>om('period-modal'))</script>
@endif

@push('scripts')
<script>
function goToReport() {
  const month  = document.getElementById('gen-month').value;
  const entity = document.getElementById('gen-entity').value;
  if (!month) { alert('Please select a month.'); return; }
  window.location.href = '{{ route('salary.report') }}?month=' + month + '&entity_id=' + entity;
}
</script>
@endpush
@endsection
