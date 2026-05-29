@extends('layouts.app')
@section('title', 'Salary Details')

@section('page_actions')
  @if(!$isHistoric)
    <a href="{{ route('salary.template') }}" class="btn">📥 Template</a>
    <button class="btn" onclick="om('sal-import-modal')">📤 Import</button>
  @endif
  <a href="{{ route('salary.report') }}" class="btn acc">📊 Salary Report</a>
@endsection

@section('content')

  {{-- Filter bar --}}
  <form method="GET" action="{{ route('salary') }}" class="card"
    style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
    <div class="fld" style="flex:1;min-width:160px">
      <label>Search</label>
      <input type="text" name="search" value="{{ request('search') }}" placeholder="Name or employee ID…">
    </div>
    <div class="fld">
      <label>Month</label>
      <input type="month" name="month" value="{{ $monthParam ?? '' }}"
        style="padding:8px 12px;border:1.5px solid var(--b1);border-radius:8px;background:#f9fafb;font-family:inherit;font-size:13px;color:var(--txt);outline:none"
        onfocus="this.style.borderColor='var(--acc)'" onblur="this.style.borderColor='var(--b1)'">
    </div>
    <button type="submit" class="btn acc">Filter</button>
    <a href="{{ route('salary') }}" class="btn">Reset</a>
  </form>

  {{-- Historical view notice --}}
  @if($isHistoric)
    <div class="ibox" style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
      <span style="font-size:15px">📅</span>
      <span>Showing salaries effective during <strong>{{ $monthLabel }}</strong>.
        Edits are disabled for historical view.
        <a href="{{ route('salary') }}" style="color:var(--acc);font-weight:600;margin-left:6px">View current →</a>
      </span>
    </div>
  @endif

  <div class="card" style="overflow-x:auto">
    @if($employees->isNotEmpty())
      <table>
        <thead>
          <tr>
            <th>Emp ID</th>
            <th>Name</th>
            <th>CTC Annual</th>
            <th>Gross Monthly</th>
            <th>Effective From</th>
            @if(!$isHistoric)
            <th>Actions</th>@endif
          </tr>
        </thead>
        <tbody>
          @foreach($employees as $emp)
            @php $salary = $emp->salaryForMonth; @endphp
            <tr>
              <td class="mono">{{ $emp->employee_id }}</td>
              <td>{{ $emp->first_name }} {{ $emp->last_name }}</td>
              
              {{-- CTC Annual (admin only — decrypted) --}}
              <td>
                @if($salary)
                  @if($isAdmin)
                    @php $ctcDec = $salary->getCtcAnnualDecrypted(); @endphp
                    @if($ctcDec > 0)
                      ₹ {{ number_format($ctcDec, 0, '.', ',') }}
                      <span style="font-size:9px;color:var(--grn)" title="Encrypted in database">🔓</span>
                    @else
                      <span style="color:var(--t3)">—</span>
                    @endif
                  @else
                    <span title="Salary is confidential" style="color:var(--t3);letter-spacing:2px;font-size:11px">🔒
                      Confidential</span>
                  @endif
                @else
                  —
                @endif
              </td>

              {{-- Gross Monthly (admin only — decrypted) --}}
              <td>
                @if($salary)
                  @if($isAdmin)
                    @php $grossDec = $salary->getGrossDecrypted(); @endphp
                    @if($grossDec > 0)
                      ₹ {{ number_format($grossDec, 0, '.', ',') }}
                      <span style="font-size:9px;color:var(--grn)" title="Encrypted in database">🔓</span>
                    @else
                      <span style="color:var(--t3)">—</span>
                    @endif
                  @else
                    <span title="Salary is confidential" style="color:var(--t3);letter-spacing:2px;font-size:11px">🔒
                      Confidential</span>
                  @endif
                @else
                  —
                @endif
              </td>

              {{-- Effective From --}}
              <td>
                {{ $salary?->effective_from
            ? \Carbon\Carbon::parse($salary->effective_from)->format('d M Y')
            : '—' }}
              </td>

              {{-- Actions (current view only) --}}
              @if(!$isHistoric)
                <td style="white-space:nowrap">
                  <button class="btn sm" onclick="openSalModal(
                      {{ $emp->id }},
                      '{{ addslashes($emp->first_name . ' ' . $emp->last_name) }}',
                      {{ $salary ? $salary->getCtcAnnualDecrypted() : 0 }},
                      {{ $salary ? $salary->getGrossDecrypted() : 0 }}
                    )">
                    {{ $salary ? 'Edit' : 'Set' }}
                  </button>
                  @if($salary)
                    <form method="POST" action="{{ route('salary.destroy', $salary) }}" style="display:inline">
                      @csrf @method('DELETE')
                      <button type="button" class="btn sm" style="color:var(--red)"
                        onclick="swConfirm(this.closest('form'), 'Delete Salary Record?', 'This salary record will be permanently deleted.', 'warning', 'Yes, delete', '#dc2626')">Del</button>
                    </form>
                  @endif
                </td>
              @endif
            </tr>
          @endforeach
        </tbody>
      </table>
      <div style="margin-top:14px">{{ $employees->links() }}</div>
    @else
      <div class="empty">
        <div class="ico">🧾</div>
        <p>{{ $isHistoric ? 'No salary records found for ' . $monthLabel . '.' : 'No employees found.' }}</p>
      </div>
    @endif
  </div>

  {{-- Set/Edit Salary Modal (shown in current view only) --}}
  @if(!$isHistoric)
    <div class="modal" id="sal-modal">
      <div class="mb">
        <div class="mh">
          <span class="mt" id="sal-modal-title">Set Salary</span>
          <button class="mc" onclick="cm('sal-modal')">✕</button>
        </div>
        <form method="POST" id="sal-form">
          @csrf
          <div class="fg2">
            <div class="fld">
              <label class="req">CTC Annual (₹)</label>
              <input type="number" name="ctc_annual" id="sal-ctc" required min="1" oninput="calcGross()"
                @error('ctc_annual') class="inp-err" @enderror>
              @error('ctc_annual') <div class="ferr">{{ $message }}</div> @enderror
            </div>
            <div class="fld">
              <label class="req">Gross Monthly (₹)</label>
              <input type="number" name="gross_monthly" id="sal-gross" required min="1"
                @error('gross_monthly') class="inp-err" @enderror>
              @error('gross_monthly') <div class="ferr">{{ $message }}</div> @enderror
            </div>
            <div class="fld full">
              <label class="req">Effective From</label>
              <input type="date" name="effective_from" required value="{{ now()->toDateString() }}"
                @error('effective_from') class="inp-err" @enderror>
              @error('effective_from') <div class="ferr">{{ $message }}</div> @enderror
            </div>
          </div>
          <div class="mf">
            <button type="button" class="btn" onclick="cm('sal-modal')">Cancel</button>
            <button type="submit" class="btn acc">Save</button>
          </div>
        </form>
      </div>
    </div>

    {{-- Salary Import Modal --}}
    <div class="modal" id="sal-import-modal">
      <div class="mb">
        <div class="mh">
          <span class="mt">Import Salary Data</span>
          <button class="mc" onclick="cm('sal-import-modal')">✕</button>
        </div>
        <div style="background:var(--acc2);border:1px solid var(--acc);border-radius:8px;
                    padding:12px 14px;margin-bottom:14px;font-size:12px;line-height:1.6;color:var(--t2)">
          Template columns: <span class="mono" style="font-size:11px">Employee Name · Emp ID · Gross Salary</span><br>
          Gross Salary is stored encrypted. Visible to admin only.
        </div>
        <form method="POST" action="{{ route('salary.import') }}" enctype="multipart/form-data">
          @csrf
          <div class="fg2" style="margin-bottom:14px">
            <div class="fld">
              <label>Effective Month *</label>
              <input type="month" name="import_month" required value="{{ now()->format('Y-m') }}"
                style="padding:9px 12px;border:1.5px solid var(--b1);border-radius:8px;background:#f9fafb;font-family:inherit;font-size:13px;color:var(--txt);outline:none;width:100%">
              <span style="font-size:10px;color:var(--t3)">Salaries will be effective from the 1st of this month</span>
            </div>
            <div class="fld" style="display:flex;flex-direction:column;gap:5px">
              <label>Salary File (.xlsx / .xls) *</label>
              <input type="file" name="file" accept=".xlsx,.xls" required
                style="background:var(--s2);border:1.5px dashed var(--b2);border-radius:8px;padding:10px;cursor:pointer;width:100%;flex:1">
            </div>
          </div>
          <div class="mf">
            <button type="button" class="btn" onclick="cm('sal-import-modal')">Cancel</button>
            <button type="submit" class="btn acc">📤 Import</button>
          </div>
        </form>
      </div>
    </div>
  @endif

  @push('scripts')
    <script>
      function openSalModal(empId, name, ctc, gross) {
        document.getElementById('sal-modal-title').textContent = 'Salary — ' + name;
        document.getElementById('sal-form').action = '/salary/' + empId + '/set';
        document.getElementById('sal-ctc').value = ctc || '';
        document.getElementById('sal-gross').value = gross || '';
        om('sal-modal');
      }
      function calcGross() {
        const ctc = parseFloat(document.getElementById('sal-ctc').value) || 0;
        document.getElementById('sal-gross').value = Math.round(ctc * 0.9 / 12);
      }
    </script>
  @endpush
@endsection