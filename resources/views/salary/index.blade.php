@extends('layouts.app')
@section('title', 'Salary Details')

@section('page_actions')
  @if(!$isHistoric)
    <button class="btn acc" onclick="openSalAddModal()">+ Add Salary</button>
    <a href="{{ route('salary.template') }}" class="btn">📥 Template</a>
    <button class="btn" onclick="om('sal-import-modal')">📤 Import</button>
  @endif
  <button class="btn" onclick="exportTableXLSX('salary_details')">⬇ Export</button>
  <a href="{{ route('salary.report') }}" class="btn acc">📊 Salary Report</a>
@endsection

@section('content')

  {{-- Filter bar --}}
  <form method="GET" action="{{ route('salary') }}" class="card" data-ajax-filter
    style="padding:14px 18px;margin-bottom:16px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
    <input type="hidden" name="company_id" value="{{ $companyId }}">
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
    <a href="{{ route('salary') }}" class="btn" data-ajax-reset>Reset</a>
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

  <div id="ajax-results">
  {{-- Bulk form — standalone, NOT wrapping the table (avoids nested-forms issue).
       Checkboxes inside the table are linked here via form="bulk-form". --}}
  @if(!$isHistoric)
  <form id="bulk-form" method="POST" action="{{ route('salary.bulk_destroy') }}" style="display:contents">
    @csrf
    <input type="hidden" name="select_all"  id="bulk-select-all-flag" value="0">
    <input type="hidden" name="search"       value="{{ request('search') }}">
    <input type="hidden" name="month_param"  value="{{ $monthParam ?? '' }}">
    <input type="hidden" name="company_id"   value="{{ $companyId }}">
  </form>

  {{-- Bulk-action bar — shown when ≥1 row selected --}}
  <div id="bulk-bar" style="display:none;background:var(--s2);border:1px solid var(--b1);border-radius:8px;
       padding:10px 16px;margin-bottom:12px;align-items:center;gap:12px;flex-wrap:wrap">
    <span id="bulk-count" style="font-size:13px;font-weight:600;color:var(--txt)">0 selected</span>

    <button type="button" id="btn-select-all-pages" class="btn sm"
      style="background:var(--acc2);color:var(--acc);border-color:var(--acc)"
      onclick="selectAllPages()">
      ☑ Select all {{ $employees->total() }} records (all pages)
    </button>
    <span id="all-pages-label" style="display:none;font-size:12px;font-weight:600;color:var(--acc)">
      ✔ All {{ $employees->total() }} records selected
    </span>
    <button type="button" id="btn-clear-all" style="display:none" class="btn sm"
      onclick="clearAllPages()">✕ Clear selection</button>

    <button type="button" class="btn sm" style="color:var(--red);margin-left:auto"
      onclick="confirmBulkDelete()">
      🗑 Delete Selected
    </button>
  </div>
  @endif

  <div class="card" style="overflow-x:auto">
    @if($employees->isNotEmpty())
      <table id="salary_details">
        <thead>
          <tr>
            @if(!$isHistoric)
            <th style="width:36px;padding:7px 10px">
              <input type="checkbox" id="chk-all" title="Select all on this page"
                style="cursor:pointer;width:14px;height:14px" onchange="toggleAll(this)"
                form="bulk-form">
            </th>
            @endif
            <th>Emp ID</th>
            <th>Name</th>
            <th>CTC Annual</th>
            <th>Gross Monthly</th>
            <th>Effective From</th>
            @if(!$isHistoric)
            <th>Actions</th>
            @endif
          </tr>
        </thead>
        <tbody>
          @foreach($employees as $emp)
            @php $salary = $emp->salaryForMonth; @endphp
            <tr>
              @if(!$isHistoric)
              <td style="padding:9px 10px">
                @if($salary)
                  <input type="checkbox" name="salary_ids[]" value="{{ $salary->id }}"
                    class="row-chk" style="cursor:pointer;width:14px;height:14px"
                    onchange="onRowCheck()" form="bulk-form">
                @endif
              </td>
              @endif
              <td class="mono">{{ $emp->employee_id }}</td>
              <td>{{ $emp->full_name }}</td>

              {{-- CTC Annual --}}
              <td>
                @if($salary)
                  @if($isAdmin)
                    @php $ctcDec = $salary->getCtcAnnualDecrypted(); @endphp
                    @if($ctcDec > 0)
                      ₹ {{ number_format($ctcDec, 0, '.', ',') }}
                      <span style="font-size:9px;color:var(--grn)" title="Encrypted in database">🔓</span>
                    @else <span style="color:var(--t3)">—</span> @endif
                  @else
                    <span title="Salary is confidential" style="color:var(--t3);letter-spacing:2px;font-size:11px">🔒 Confidential</span>
                  @endif
                @else —
                @endif
              </td>

              {{-- Gross Monthly --}}
              <td>
                @if($salary)
                  @if($isAdmin)
                    @php $grossDec = $salary->getGrossDecrypted(); @endphp
                    @if($grossDec > 0)
                      ₹ {{ number_format($grossDec, 0, '.', ',') }}
                      <span style="font-size:9px;color:var(--grn)" title="Encrypted in database">🔓</span>
                    @else <span style="color:var(--t3)">—</span> @endif
                  @else
                    <span title="Salary is confidential" style="color:var(--t3);letter-spacing:2px;font-size:11px">🔒 Confidential</span>
                  @endif
                @else — @endif
              </td>

              {{-- Effective From --}}
              <td>{{ $salary?->effective_from ? \Carbon\Carbon::parse($salary->effective_from)->format('d M Y') : '—' }}</td>

              {{-- Actions --}}
              @if(!$isHistoric)
                <td style="white-space:nowrap">
                  <button class="btn sm" onclick="openSalModal({{ $emp->id }},'{{ addslashes($emp->full_name) }}',{{ $salary ? $salary->getCtcAnnualDecrypted() : 0 }},{{ $salary ? $salary->getGrossDecrypted() : 0 }})">
                    {{ $salary ? 'Edit' : 'Set' }}
                  </button>
                  @if($salary)
                    <form method="POST" action="{{ route('salary.destroy', $salary) }}" style="display:inline">
                      @csrf @method('DELETE')
                      <button type="button" class="btn sm" style="color:var(--red)"
                        onclick="swConfirm(this.closest('form'),'Delete Salary Record?','This salary record will be permanently deleted.','warning','Yes, delete','#dc2626')">Del</button>
                    </form>
                  @endif
                </td>
              @endif
            </tr>
          @endforeach
        </tbody>
      </table>
      <div style="padding:14px 18px;border-top:1px solid var(--b1)">{{ $employees->links() }}</div>
    @else
      <div class="empty">
        <div class="ico">🧾</div>
        <p>{{ $isHistoric ? 'No salary records found for ' . $monthLabel . '.' : 'No employees found.' }}</p>
      </div>
    @endif
  </div>
  </div> {{-- #ajax-results --}}

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
            <div class="fld full" id="sal-modal-employee-wrap" style="display:none">
              <label class="req">Employee</label>
              <select id="sal-employee-select" required onchange="onSalEmployeeChange(this.value)">
                <option value="">Select employee</option>
                @foreach($allEmployeesForSalary as $emp)
                  <option value="{{ $emp->id }}">{{ $emp->employee_id }} — {{ $emp->full_name }}</option>
                @endforeach
              </select>
            </div>
            <div class="fld">
              <label class="req">CTC Annual (₹)</label>
              <input type="number" name="ctc_annual" id="sal-ctc" required min="1" step="1"
                placeholder="e.g. 300000" oninput="calcGross()"
                @error('ctc_annual') class="inp-err" @enderror>
              @error('ctc_annual') <div class="ferr">{{ $message }}</div> @enderror
              <div id="sal-ctc-warn" style="display:none;font-size:11px;color:var(--yel);margin-top:3px">
                ⚠ CTC seems very low — enter the full annual amount in ₹ (e.g. 3,00,000 not 3)
              </div>
            </div>
            <div class="fld">
              <label class="req">Gross Monthly (₹) <span style="font-size:10px;color:var(--t3)">(auto-filled)</span></label>
              <input type="number" name="gross_monthly" id="sal-gross" required min="1" step="1"
                placeholder="Auto-calculated from CTC"
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
      /* ── Set / Edit salary modal ─────────────────────────── */
      function openSalModal(empId, name, ctc, gross) {
        document.getElementById('sal-modal-employee-wrap').style.display = 'none';
        document.getElementById('sal-modal-title').textContent = 'Salary — ' + name;
        document.getElementById('sal-form').action = '/salary/' + empId + '/set';
        document.getElementById('sal-ctc').value  = ctc   || '';
        document.getElementById('sal-gross').value = gross || '';
        om('sal-modal');
      }

      /* ── Add salary for a manually-picked employee ─────────── */
      function openSalAddModal() {
        document.getElementById('sal-modal-employee-wrap').style.display = 'flex';
        document.getElementById('sal-modal-title').textContent = 'Add Salary';
        document.getElementById('sal-employee-select').value = '';
        document.getElementById('sal-form').action = '';
        document.getElementById('sal-ctc').value = '';
        document.getElementById('sal-gross').value = '';
        if (window._ssSync) window._ssSync(document.getElementById('sal-employee-select'));
        om('sal-modal');
      }

      function onSalEmployeeChange(empId) {
        document.getElementById('sal-form').action = empId ? '/salary/' + empId + '/set' : '';
      }
      function calcGross() {
        const ctc = parseFloat(document.getElementById('sal-ctc').value) || 0;
        document.getElementById('sal-gross').value = Math.round(ctc * 0.9 / 12);
        const warn = document.getElementById('sal-ctc-warn');
        if (warn) warn.style.display = (ctc > 0 && ctc < 12000) ? 'block' : 'none';
      }

      /* ── Cross-page bulk selection (sessionStorage) ──────────────────────
       * Selections survive page navigation. IDs are stored per filter-scope
       * so switching filters starts a fresh selection automatically.
       * ─────────────────────────────────────────────────────────────────── */

      // Scope key: unique per current filter combination
      const _storageKey = 'sal_bulk_{{ md5(request("search","")."|".request("month","")."|".request("company_id","0")) }}';
      const _total      = {{ $employees->total() }};
      let _allPagesSelected = false;

      // Load persisted IDs (Set for O(1) lookup)
      function _getStored() {
        try { return new Set(JSON.parse(sessionStorage.getItem(_storageKey) || '[]')); }
        catch { return new Set(); }
      }
      function _saveStored(set) {
        sessionStorage.setItem(_storageKey, JSON.stringify([...set]));
      }
      function _clearStored() {
        sessionStorage.removeItem(_storageKey);
        _allPagesSelected = false;
        document.getElementById('bulk-select-all-flag').value = '0';
      }

      // Restore checkbox states on this page from storage
      function restoreSelections() {
        const stored = _getStored();
        if (stored.size === 0) return;
        document.querySelectorAll('.row-chk').forEach(cb => {
          if (stored.has(cb.value)) cb.checked = true;
        });
        refreshBulkBar();
      }

      function refreshBulkBar() {
        const stored  = _getStored();
        const bar     = document.getElementById('bulk-bar');
        const countEl = document.getElementById('bulk-count');
        if (!bar) return;

        const totalSelected = _allPagesSelected ? _total : stored.size;

        if (_allPagesSelected || stored.size > 0) {
          bar.style.display = 'flex';
          countEl.textContent = _allPagesSelected
            ? 'All ' + _total + ' records selected'
            : totalSelected + ' selected (across all pages)';
        } else {
          bar.style.display = 'none';
        }

        // Show "select all pages" button only when cross-page is not yet active
        const checkedOnPage = document.querySelectorAll('.row-chk:checked').length;
        document.getElementById('btn-select-all-pages').style.display  = (!_allPagesSelected && stored.size > 0) ? 'inline-flex' : 'none';
        document.getElementById('all-pages-label').style.display        = _allPagesSelected ? 'inline' : 'none';
        document.getElementById('btn-clear-all').style.display          = (stored.size > 0 || _allPagesSelected) ? 'inline-flex' : 'none';

        // Sync header checkbox with this page's state
        const all = document.querySelectorAll('.row-chk');
        const hdr = document.getElementById('chk-all');
        if (hdr) {
          hdr.checked       = all.length > 0 && checkedOnPage === all.length;
          hdr.indeterminate = checkedOnPage > 0 && checkedOnPage < all.length;
        }
      }

      function toggleAll(hdr) {
        _allPagesSelected = false;
        document.getElementById('bulk-select-all-flag').value = '0';
        const stored = _getStored();
        document.querySelectorAll('.row-chk').forEach(cb => {
          cb.checked = hdr.checked;
          hdr.checked ? stored.add(cb.value) : stored.delete(cb.value);
        });
        _saveStored(stored);
        refreshBulkBar();
      }

      function onRowCheck() {
        _allPagesSelected = false;
        document.getElementById('bulk-select-all-flag').value = '0';
        const stored = _getStored();
        document.querySelectorAll('.row-chk').forEach(cb => {
          cb.checked ? stored.add(cb.value) : stored.delete(cb.value);
        });
        _saveStored(stored);
        refreshBulkBar();
      }

      function selectAllPages() {
        _allPagesSelected = true;
        document.getElementById('bulk-select-all-flag').value = '1';
        // Also check every box on the current page
        const stored = _getStored();
        document.querySelectorAll('.row-chk').forEach(cb => { cb.checked = true; stored.add(cb.value); });
        _saveStored(stored);
        refreshBulkBar();
      }

      function clearAllPages() {
        document.querySelectorAll('.row-chk').forEach(cb => cb.checked = false);
        const hdr = document.getElementById('chk-all');
        if (hdr) { hdr.checked = false; hdr.indeterminate = false; }
        _clearStored();
        refreshBulkBar();
      }

      // Before the bulk form submits, inject a hidden input for every stored ID
      document.getElementById('bulk-form').addEventListener('submit', function() {
        if (_allPagesSelected) return; // server handles "all" via select_all flag
        const stored = _getStored();
        stored.forEach(id => {
          const inp = document.createElement('input');
          inp.type = 'hidden'; inp.name = 'salary_ids[]'; inp.value = id;
          this.appendChild(inp);
        });
        // Clear storage after successful submission
        _clearStored();
      });

      // Clear storage after a successful delete redirect
      @if(session('success') && str_contains(session('success'), 'deleted'))
      _clearStored();
      @endif

      // Initialise on page load
      document.addEventListener('DOMContentLoaded', restoreSelections);

      // Searching/filtering now swaps #ajax-results in place instead of a
      // full reload — the per-filter storage key already means "a changed
      // filter starts a fresh selection" (see comment above), so replicate
      // that: clear any in-progress selection whenever the region refreshes.
      document.getElementById('ajax-results')?.addEventListener('ajax:loaded', clearAllPages);

      function confirmBulkDelete() {
        const stored = _getStored();
        const count  = _allPagesSelected ? _total : stored.size;

        if (count === 0) return;

        Swal.fire({
          title: 'Delete ' + count + ' salary record' + (count > 1 ? 's' : '') + '?',
          html:  'This will permanently delete the selected salary record' + (count > 1 ? 's' : '') + '.<br>This <strong>cannot be undone</strong>.',
          icon:  'warning',
          showCancelButton: true,
          confirmButtonText: 'Yes, delete',
          confirmButtonColor: '#dc2626',
          cancelButtonText: 'Cancel',
        }).then(r => { if (r.isConfirmed) document.getElementById('bulk-form').submit(); });
      }
    </script>
  @endpush
@endsection