@extends('layouts.app')
@section('title', 'Attendance')

@section('page_actions')
  <button class="btn acc" onclick="om('att-modal')">✔ Mark Attendance</button>
  <button class="btn" onclick="om('punch-add-modal')">+ Add Punch</button>
  <button class="btn" onclick="om('biometric-upload-modal')">🖱 Bulk Upload</button>
@endsection

@section('content')

  {{-- Summary cards --}}
  <div class="g4" style="margin-bottom:16px">
    <div class="sc">
      <div class="lbl">Present Today</div>
      <div class="val" style="color:var(--grn)">{{ $todaySummary['present'] ?? 0 }}</div>
    </div>
    <div class="sc">
      <div class="lbl">Absent Today</div>
      <div class="val" style="color:var(--red)">{{ $todaySummary['absent'] ?? 0 }}</div>
    </div>
    <div class="sc">
      <div class="lbl">On Leave</div>
      <div class="val" style="color:var(--yel)">{{ $todaySummary['leave'] ?? 0 }}</div>
    </div>
    <div class="sc">
      <div class="lbl">WFH</div>
      <div class="val" style="color:var(--pur)">{{ $todaySummary['work_from_home'] ?? 0 }}</div>
    </div>
  </div>

  {{-- Month / Entity filter --}}
  <form method="GET" action="{{ route('attendance') }}" class="card"
    style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
    @if($entities->count() > 1)
    <div class="fld" style="min-width:200px">
      <label>Entity / Company</label>
      <select name="entity_id">
        @foreach($entities as $ent)
          <option value="{{ $ent->id }}" {{ $entityId == $ent->id ? 'selected' : '' }}>
            {{ $ent->name }}
          </option>
        @endforeach
      </select>
    </div>
    @else
      <input type="hidden" name="entity_id" value="{{ $entityId }}">
    @endif
    <div class="fld">
      <label>Year</label>
      <select name="year">
        @for($y = now()->year; $y >= now()->year - 3; $y--)
          <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
        @endfor
      </select>
    </div>
    <div class="fld">
      <label>Month</label>
      <select name="month">
        @foreach(['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'] as $i => $m)
          <option value="{{ $i + 1 }}" {{ $month == $i + 1 ? 'selected' : '' }}>{{ $m }}</option>
        @endforeach
      </select>
    </div>
    <button type="submit" class="btn acc">Load</button>
    @if($biometricDataRange?->min_date)
    <div style="font-size:11px;color:var(--t3);align-self:center;padding-bottom:2px">
      📡 Biometric data available:
      <strong style="color:var(--t2)">
        {{ \Carbon\Carbon::parse($biometricDataRange->min_date)->format('M Y') }}
        — {{ \Carbon\Carbon::parse($biometricDataRange->max_date)->format('M Y') }}
      </strong>
    </div>
    @endif
  </form>

  <div class="card">
    <div class="ch">
      <div class="ct">Attendance — {{ \Carbon\Carbon::createFromDate($year, $month, 1)->format('F Y') }}</div>
    </div>
    @php
      // Legend note: bio = from biometric machine, manual = from attendance table
      $statusMap = [
        'present'       => 'P',
        'absent'        => 'A',
        'leave'         => 'L',
        'holiday'       => 'H',
        'weekly_off'    => 'W',
        'work_from_home'=> 'WFH',
        'half_day'      => 'HD',
        'on_duty'       => 'OD',
        // Biometric-derived (no manual record exists)
        'bio_present'   => 'P',
        'bio_absent'    => 'A',
        'bio_partial'   => 'P?',
      ];
      $colorMap = [
        'present'       => 'var(--grn)',
        'absent'        => 'var(--red)',
        'leave'         => 'var(--yel)',
        'holiday'       => 'var(--pur)',
        'weekly_off'    => 'var(--t3)',
        'work_from_home'=> '#06b6d4',
        'half_day'      => 'var(--yel)',
        'on_duty'       => 'var(--grn)',
        'bio_present'   => '#16a34a88',   // faded green — biometric, no manual confirm
        'bio_absent'    => '#dc262688',   // faded red
        'bio_partial'   => '#ca8a0488',   // faded amber — only check-in or only check-out
      ];
      $days = \Carbon\Carbon::createFromDate($year, $month, 1)->daysInMonth;
    @endphp
    @if($employees->isNotEmpty())
      {{-- Legend --}}
      <div style="font-size:10px;color:var(--t3);margin-bottom:8px;display:flex;gap:14px;flex-wrap:wrap">
        <span style="color:var(--grn);font-weight:600">■</span> Present (manual)
        <span style="color:#16a34a88;font-weight:600">■</span> Present (biometric)
        <span style="color:var(--red);font-weight:600">■</span> Absent (manual)
        <span style="color:#dc262688;font-weight:600">■</span> Absent (biometric)
        <span style="color:#ca8a0488;font-weight:600">■</span> Partial punch
        <span style="color:var(--yel);font-weight:600">■</span> Leave
        <span style="color:var(--t3)">·</span> No data
      </div>
      <div style="overflow-x:auto">
        <table>
          <thead>
            <tr>
              <th>Employee</th>
              <th>Dept</th>
              @for($d = 1; $d <= $days; $d++)
                @php
                  $dow = \Carbon\Carbon::createFromDate($year, $month, $d)->format('D');
                  $isWeekend = in_array($dow, ['Sat','Sun']);
                @endphp
                <th style="min-width:26px;text-align:center;{{ $isWeekend ? 'color:var(--t3)' : '' }}">{{ $d }}</th>
              @endfor
              <th title="Present">P</th>
              <th title="Absent">A</th>
              <th title="Leave">L</th>
            </tr>
          </thead>
          <tbody>
            @foreach($employees as $emp)
              @php
                // Manual attendance records for this employee, keyed by day
                $empRecords = $records[$emp->id] ?? collect();
                $attByDate  = $empRecords->keyBy(fn($r) => (int)\Carbon\Carbon::parse($r->attendance_date)->format('j'));

                // Biometric raw punches, keyed by day
                $empPunches = $rawPunches[$emp->id] ?? collect();
                $bioByDate  = $empPunches->groupBy(fn($p) => (int)\Carbon\Carbon::parse($p->attendance_date)->format('j'));

                $present = 0; $absent = 0; $leave = 0;
              @endphp
              <tr>
                <td style="white-space:nowrap;font-size:12px">{{ $emp->first_name }} {{ $emp->last_name }}</td>
                <td style="font-size:11px;color:var(--t3)">{{ $emp->department?->name ?? '—' }}</td>
                @for($d = 1; $d <= $days; $d++)
                  @php
                    $rec      = $attByDate[$d] ?? null;
                    $st       = $rec?->status;
                    $bioPunch = $bioByDate[$d] ?? null;   // collection of punches this day
                    $tooltip  = '';

                    if ($st) {
                      // Manual record wins
                      if ($st === 'present') $present++;
                      elseif ($st === 'absent') $absent++;
                      elseif (in_array($st, ['leave','half_day'])) $leave++;
                    } elseif ($bioPunch && $bioPunch->isNotEmpty()) {
                      // Derive status from biometric
                      $hasAbsent  = $bioPunch->where('is_absent', true)->isNotEmpty();
                      $hasCheckIn = $bioPunch->whereNotNull('check_in')->isNotEmpty();
                      $hasCheckOut= $bioPunch->whereNotNull('check_out')->isNotEmpty();

                      if ($hasAbsent && !$hasCheckIn && !$hasCheckOut) {
                        $st = 'bio_absent'; $absent++;
                      } elseif ($hasCheckIn && $hasCheckOut) {
                        $st = 'bio_present'; $present++;
                      } elseif ($hasCheckIn || $hasCheckOut) {
                        $st = 'bio_partial'; $present++;
                      } else {
                        $st = 'bio_absent'; $absent++;
                      }

                      // Build tooltip showing each machine's punch
                      $tipParts = [];
                      foreach ($bioPunch as $bp) {
                        $in  = $bp->check_in  ? substr($bp->check_in, 0, 5)  : '—';
                        $out = $bp->check_out ? substr($bp->check_out, 0, 5) : '—';
                        $tipParts[] = "M{$bp->machine_name}: {$in}→{$out}";
                      }
                      $tooltip = implode(' | ', $tipParts);
                    }

                    $isBio = str_starts_with($st ?? '', 'bio_');
                  @endphp
                  <td style="text-align:center;font-size:10px;color:{{ $colorMap[$st] ?? 'var(--t4)' }};
                             {{ $isBio ? 'font-style:italic' : '' }}"
                      @if($tooltip) title="{{ $tooltip }}" @endif>
                    {{ $statusMap[$st] ?? '·' }}
                  </td>
                @endfor
                <td style="color:var(--grn);font-weight:600;font-size:11px">{{ $present }}</td>
                <td style="color:var(--red);font-size:11px">{{ $absent }}</td>
                <td style="color:var(--yel);font-size:11px">{{ $leave }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <div class="empty">
        <div class="ico">🕐</div>
        <p>No active employees found for the selected entity / period.</p>
        <p style="font-size:12px;color:var(--t3);margin-top:4px">
          Make sure you have selected the correct <strong>Entity</strong>, <strong>Year</strong>, and <strong>Month</strong>.
        </p>
      </div>
    @endif
  </div>

  {{-- Biometric Punch Records --}}
  @if($rawPunches->isNotEmpty())
  <div class="card" style="margin-top:20px">
    <div class="ch">
      <div class="ct">Biometric Punch Records — {{ \Carbon\Carbon::createFromDate($year, $month, 1)->format('F Y') }}</div>
    </div>
    <div style="overflow-x:auto">
      <table>
        <thead>
          <tr>
            <th>Employee</th>
            <th>Date</th>
            <th>Machine</th>
            <th>Bio ID</th>
            <th style="color:var(--grn)">Check In</th>
            <th style="color:var(--red)">Check Out</th>
            <th>Hours</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach($employees as $emp)
            @php $empPunches = $rawPunches[$emp->id] ?? collect(); @endphp
            @foreach($empPunches as $punch)
              @php
                $hours = null;
                if ($punch->check_in && $punch->check_out) {
                  $in  = \Carbon\Carbon::parse($punch->check_in);
                  $out = \Carbon\Carbon::parse($punch->check_out);
                  $diff = $in->diffInMinutes($out);
                  $hours = sprintf('%dh %02dm', intdiv($diff, 60), $diff % 60);
                }
              @endphp
              <tr>
                <td style="white-space:nowrap">{{ $emp->first_name }} {{ $emp->last_name }}</td>
                <td>{{ \Carbon\Carbon::parse($punch->attendance_date)->format('d M') }}</td>
                <td style="font-size:11px;color:var(--t3)">{{ $punch->machine_name ?? '—' }}</td>
                <td style="font-size:11px;color:var(--t3)">{{ $punch->bio_id_full ?? '—' }}</td>
                <td style="color:var(--grn);font-weight:600">
                  {{ $punch->check_in ? \Carbon\Carbon::parse($punch->check_in)->format('H:i') : '—' }}
                </td>
                <td style="color:var(--red)">
                  {{ $punch->check_out ? \Carbon\Carbon::parse($punch->check_out)->format('H:i') : '—' }}
                </td>
                <td style="font-size:11px">{{ $hours ?? '—' }}</td>
                <td>
                  @if($punch->is_absent)
                    <span style="color:var(--red);font-size:11px;font-weight:600">Absent</span>
                  @elseif($punch->check_in && $punch->check_out)
                    <span style="color:var(--grn);font-size:11px;font-weight:600">Present</span>
                  @elseif($punch->check_in)
                    <span style="color:var(--yel);font-size:11px">In only</span>
                  @else
                    <span style="color:var(--t4);font-size:11px">—</span>
                  @endif
                </td>
                <td style="white-space:nowrap">
                  <button class="btn sm" style="font-size:10px"
                    onclick="openPunchEdit({{ $punch->id }}, '{{ substr($punch->check_in ?? '', 0, 5) }}', '{{ substr($punch->check_out ?? '', 0, 5) }}', '{{ $punch->machine_name ?? '' }}')">
                    Edit
                  </button>
                  <form method="POST" action="{{ route('attendance.punch.destroy', $punch) }}" style="display:inline">
                    @csrf @method('DELETE')
                    <button type="button" class="btn sm" style="font-size:10px;color:var(--red)"
                      onclick="swConfirm(this.closest('form'), 'Delete Punch Record?', 'This punch record will be permanently removed.', 'warning', 'Yes, delete', '#dc2626')">Del</button>
                  </form>
                </td>
              </tr>
            @endforeach
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @endif

  {{-- Mark Attendance Modal --}}
  <div class="modal" id="att-modal">
    <div class="mb xl">
      <div class="mh">
        <span class="mt">Mark Attendance</span>
        <button class="mc" onclick="cm('att-modal')">✕</button>
      </div>
      <form method="POST" action="{{ route('attendance.mark') }}">
        @csrf
        <div class="fld" style="margin-bottom:14px">
          <label>Date *</label>
          <input type="date" name="attendance_date" required value="{{ now()->toDateString() }}">
        </div>
        <div style="overflow-y:auto;max-height:360px">
          <table>
            <thead>
              <tr>
                <th>Employee</th>
                <th>Department</th>
                <th>Status</th>
                <th>Work Type</th>
                <th>Remarks</th>
              </tr>
            </thead>
            <tbody>
              @foreach($employees as $i => $emp)
                <input type="hidden" name="records[{{ $i }}][emp_id]" value="{{ $emp->id }}">
                <tr>
                  <td>{{ $emp->first_name }} {{ $emp->last_name }}</td>
                  <td>{{ $emp->department?->name ?? '—' }}</td>
                  <td>
                    <select name="records[{{ $i }}][status]"
                      style="padding:4px 8px;border-radius:6px;border:1px solid var(--b1);font-size:12px">
                      <option value="present">Present</option>
                      <option value="absent">Absent</option>
                      <option value="work_from_home">WFH</option>
                      <option value="leave">Leave</option>
                      <option value="holiday">Holiday</option>
                      <option value="weekly_off">Weekly Off</option>
                    </select>
                  </td>
                  <td>
                    <select name="records[{{ $i }}][work_type]"
                      style="padding:4px 8px;border-radius:6px;border:1px solid var(--b1);font-size:12px">
                      <option value="wfo">WFO</option>
                      <option value="wfh">WFH</option>
                      <option value="hybrid">Hybrid</option>
                      <option value="client_location">Client</option>
                    </select>
                  </td>
                  <td>
                    <input type="text" name="records[{{ $i }}][remarks]"
                      style="padding:4px 8px;border-radius:6px;border:1px solid var(--b1);font-size:12px;width:120px"
                      placeholder="optional">
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <div class="mf">
          <button type="button" class="btn" onclick="setAll('present')">All Present</button>
          <button type="button" class="btn" onclick="cm('att-modal')">Cancel</button>
          <button type="submit" class="btn acc">Save Attendance</button>
        </div>
      </form>
    </div>
  </div>
@endsection

{{-- Attendance Upload Modal (needs year + month extra fields) --}}
<div class="modal" id="upload-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Bulk Upload Attendance</span>
      <button class="mc" onclick="cm('upload-modal')">✕</button>
    </div>
    <form method="POST" action="{{ route('attendance.import') }}" enctype="multipart/form-data">
      @csrf
      <div class="fg2">
        <div class="fld">
          <label>Year *</label>
          <input type="number" name="year" required value="{{ now()->year }}">
        </div>
        <div class="fld">
          <label>Month *</label>
          <select name="month" required>
            @foreach(['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'] as $i => $m)
              <option value="{{ $i + 1 }}" {{ now()->month == $i + 1 ? 'selected' : '' }}>{{ $m }}</option>
            @endforeach
          </select>
        </div>
        <div class="fld full">
          <label>Excel File (.xlsx / .xls) *</label>
          <input type="file" name="file" accept=".xlsx,.xls" required multiple
            style="background:var(--s2);border:1.5px dashed var(--b2);border-radius:8px;padding:14px;cursor:pointer;width:100%">
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('upload-modal')">Cancel</button>
        <button type="submit" class="btn acc">📤 Upload &amp; Import</button>
      </div>
    </form>
  </div>
</div>

{{-- ── Biometric Machine XLS Upload Modal ─────────────────────────────── --}}
<div class="modal" id="biometric-upload-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Biometric XLS Upload</span>
      <button class="mc" onclick="cm('biometric-upload-modal')">✕</button>
    </div>

    <div style="background:var(--s2);border:1px solid var(--b1);border-radius:8px;
                padding:10px 14px;margin-bottom:14px;font-size:12px;color:var(--t2);line-height:1.7">
      <strong>Filename rules</strong> — file must start with the machine prefix:<br>
      &nbsp;&nbsp;<code>10_…xls</code> → Machine 10 &nbsp;|&nbsp;
      <code>12_…xls</code> → Machine 12 &nbsp;|&nbsp;
      <code>K_…xls</code> → Machine K<br>
      You can select <strong>one or all three files at once</strong>. All files are fully
      validated before any data is saved. If any row is invalid an error report
      downloads automatically.
    </div>

    <form method="POST" action="{{ route('attendance.biometric_import') }}"
          enctype="multipart/form-data" id="bio-import-form">
      @csrf

      <div class="fg2" style="margin-bottom:10px">
        <div class="fld">
          <label>Attendance Month *</label>
          <input type="month" name="import_month" required value="{{ now()->format('Y-m') }}"
                 style="padding:9px 12px;border:1.5px solid var(--b1);border-radius:8px;background:#f9fafb;font-family:inherit;font-size:13px;color:var(--txt);outline:none;width:100%">
          <span style="font-size:10px;color:var(--t3)">Month the attendance data belongs to</span>
        </div>
        <div class="fld">
          <label>Machine XLS Files (.xls) *</label>
          <input type="file" name="files[]" id="bio-files-input"
                 accept=".xls" multiple required
                 style="background:var(--s2);border:1.5px dashed var(--b2);
                        border-radius:8px;padding:10px;cursor:pointer;width:100%"
                 onchange="showBioFiles(this)">
        </div>
      </div>

      {{-- Live file-name list so user can confirm what they selected --}}
      <ul id="bio-file-list"
          style="margin:0 0 12px;padding:0 0 0 18px;font-size:12px;
                 color:var(--t2);line-height:1.8;display:none"></ul>

      <div class="mf">
        <button type="button" class="btn" onclick="cm('biometric-upload-modal')">Cancel</button>
        <button type="submit" class="btn acc" id="bio-submit-btn">📤 Import Attendance</button>
      </div>
    </form>
  </div>
</div>

{{-- Add Punch Modal --}}
<div class="modal" id="punch-add-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Add Biometric Punch</span>
      <button class="mc" onclick="cm('punch-add-modal')">✕</button>
    </div>
    <form method="POST" action="{{ route('attendance.punch.store') }}">
      @csrf
      <input type="hidden" name="year" value="{{ $year }}">
      <input type="hidden" name="month" value="{{ $month }}">
      <input type="hidden" name="entity_id" value="{{ $entityId }}">
      <div class="fg2">
        <div class="fld full">
          <label>Employee *</label>
          <select name="employee_id" required>
            <option value="">Select employee</option>
            @foreach($employees as $emp)
            <option value="{{ $emp->id }}">{{ $emp->employee_id }} — {{ $emp->first_name }} {{ $emp->last_name }}</option>
            @endforeach
          </select>
        </div>
        <div class="fld">
          <label>Date *</label>
          <input type="date" name="attendance_date" required value="{{ now()->toDateString() }}">
        </div>
        <div class="fld">
          <label>Machine Name</label>
          <input type="text" name="machine_name" placeholder="e.g. 10, 12, K" value="manual">
        </div>
        <div class="fld">
          <label>Check In (HH:MM)</label>
          <input type="time" name="check_in">
        </div>
        <div class="fld">
          <label>Check Out (HH:MM)</label>
          <input type="time" name="check_out">
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('punch-add-modal')">Cancel</button>
        <button type="submit" class="btn acc">Add Punch</button>
      </div>
    </form>
  </div>
</div>

{{-- Edit Punch Modal --}}
<div class="modal" id="punch-edit-modal">
  <div class="mb">
    <div class="mh">
      <span class="mt">Edit Punch Record</span>
      <button class="mc" onclick="cm('punch-edit-modal')">✕</button>
    </div>
    <form method="POST" id="punch-edit-form">
      @csrf
      <input type="hidden" name="year" value="{{ $year }}">
      <input type="hidden" name="month" value="{{ $month }}">
      <input type="hidden" name="entity_id" value="{{ $entityId }}">
      <div class="fg2">
        <div class="fld">
          <label>Check In (HH:MM)</label>
          <input type="time" name="check_in" id="edit-punch-in">
        </div>
        <div class="fld">
          <label>Check Out (HH:MM)</label>
          <input type="time" name="check_out" id="edit-punch-out">
        </div>
        <div class="fld full">
          <label>Machine Name</label>
          <input type="text" name="machine_name" id="edit-punch-machine">
        </div>
      </div>
      <div class="mf">
        <button type="button" class="btn" onclick="cm('punch-edit-modal')">Cancel</button>
        <button type="submit" class="btn acc">Save Changes</button>
      </div>
    </form>
  </div>
</div>

@push('scripts')
  <script>
    function setAll(status) {
      document.querySelectorAll('#att-modal select[name$="[status]"]').forEach(s => s.value = status);
    }

    // Show selected filenames inside the modal
    function showBioFiles(input) {
      var list = document.getElementById('bio-file-list');
      list.innerHTML = '';
      if (!input.files.length) { list.style.display = 'none'; return; }
      Array.from(input.files).forEach(function(f) {
        var li = document.createElement('li');
        li.textContent = f.name + '  (' + (f.size / 1024).toFixed(0) + ' KB)';
        list.appendChild(li);
      });
      list.style.display = 'block';
    }

    // Disable submit button while uploading to prevent double-submit
    document.getElementById('bio-import-form').addEventListener('submit', function() {
      var btn = document.getElementById('bio-submit-btn');
      btn.disabled = true;
      btn.textContent = '⏳ Importing…';
    });

    function openPunchEdit(id, checkIn, checkOut, machine) {
      document.getElementById('edit-punch-in').value      = checkIn;
      document.getElementById('edit-punch-out').value     = checkOut;
      document.getElementById('edit-punch-machine').value = machine;
      document.getElementById('punch-edit-form').action   = '/attendance/punch/' + id + '/update';
      om('punch-edit-modal');
    }
  </script>
@endpush