<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'HRMS') — HRMS Payroll</title>
    <link rel="icon" href="{{ asset('favicontnbt.png') }}" type="image/x-icon">
    <link rel="shortcut icon" href="{{ asset('favicontnbt.png') }}" type="image/x-icon">
    <link rel="stylesheet" href="/css/hrms.css">
    <style>
        /* ── Searchable Select ─────────────────────────────── */
        .ss-wrap {
            position: relative;
            display: block;
        }

        .ss-inp {
            width: 100%;
            background: #f9fafb;
            border: 1.5px solid var(--b1);
            border-radius: 8px;
            padding: 9px 12px 9px 10px;
            color: var(--txt);
            font-family: inherit;
            font-size: 13px;
            outline: none;
            transition: border-color .15s;
            cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            padding-right: 30px;
        }

        .ss-inp:focus {
            border-color: var(--acc);
            background-color: #fff;
            cursor: text;
        }

        .ss-inp.ss-open {
            border-color: var(--acc);
            background-color: #fff;
            border-bottom-left-radius: 0;
            border-bottom-right-radius: 0;
        }

        .ss-drop {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--s1);
            border: 1.5px solid var(--acc);
            border-top: none;
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
            max-height: 220px;
            overflow-y: auto;
            z-index: 9999;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .12);
        }

        .ss-opt {
            padding: 8px 12px;
            cursor: pointer;
            font-size: 13px;
            color: var(--txt);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .ss-opt:hover {
            background: var(--s2);
        }

        .ss-opt.ss-sel {
            background: var(--acc2);
            color: var(--acc);
            font-weight: 600;
        }

        .ss-none {
            padding: 9px 12px;
            font-size: 12px;
            color: var(--t3);
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

    <aside>
        <div class="sh">
            <div class="ico">💼</div>
            <div>
                <div style="font-size:13px;font-weight:700">HRMS</div>
                <div style="font-size:10px;color:var(--t3)">Payroll Platform</div>
            </div>
        </div>
        <nav>
            <div class="nl">Overview</div>
            <a class="ni {{ request()->routeIs('dashboard') ? 'on' : '' }}"
                href="{{ route('dashboard') }}"><span>📊</span> Dashboard</a>

            <div class="nl">HR</div>
            <a class="ni {{ request()->routeIs('employees') ? 'on' : '' }}"
                href="{{ route('employees') }}"><span>👥</span> Employees</a>
            <a class="ni {{ request()->routeIs('bio_ids') ? 'on' : '' }}" href="{{ route('bio_ids') }}"><span>🔖</span>
                Bio IDs</a>
            <a class="ni {{ request()->routeIs('attendance') ? 'on' : '' }}"
                href="{{ route('attendance') }}"><span>🕐</span> Attendance</a>
            <a class="ni {{ request()->routeIs('leave') ? 'on' : '' }}" href="{{ route('leave') }}"><span>🏖️</span>
                Leave Management</a>

            <div class="nl">Payroll</div>
            <a class="ni {{ request()->routeIs('payroll', 'salary.report*') ? 'on' : '' }}"
                href="{{ route('payroll') }}"><span>💰</span> Payroll & Reports</a>
            <a class="ni {{ request()->routeIs('salary') ? 'on' : '' }}" href="{{ route('salary') }}"><span>🧾</span>
                Salary Details</a>
            <a class="ni {{ request()->routeIs('incentives') ? 'on' : '' }}"
                href="{{ route('incentives') }}"><span>🎁</span> Incentives</a>
            <a class="ni {{ request()->routeIs('tds.monthly') ? 'on' : '' }}"
                href="{{ route('tds.monthly') }}"><span>📑</span> Monthly TDS</a>

            <div class="nl">Finance</div>
            <a class="ni {{ request()->routeIs('loans') ? 'on' : '' }}" href="{{ route('loans') }}"><span>🏦</span>
                Loans / Advance</a>
            <a class="ni {{ request()->routeIs('reimbursements.tracker*') ? 'on' : '' }}"
                href="{{ route('reimbursements.tracker') }}"><span>📋</span> Reimbursements</a>
            <a class="ni {{ request()->routeIs('reimbursements.report*') ? 'on' : '' }}"
                href="{{ route('reimbursements.report.saved') }}"><span>📊</span> Reimb. Report</a>
            <a class="ni {{ request()->routeIs('contractors') ? 'on' : '' }}"
                href="{{ route('contractors') }}"><span>🏭</span> Contractors</a>

            <div class="nl">Statutory</div>
            <a class="ni {{ request()->routeIs('statutory') ? 'on' : '' }}"
                href="{{ route('statutory') }}"><span>📋</span> Compliance</a>
            <a class="ni {{ request()->routeIs('tds') ? 'on' : '' }}" href="{{ route('tds') }}"><span>📄</span> TDS
                Slabs</a>

            <div class="nl">More</div>
            <a class="ni {{ request()->routeIs('holidays') ? 'on' : '' }}"
                href="{{ route('holidays') }}"><span>🗓️</span> Holidays</a>
            <a class="ni {{ request()->routeIs('helpdesk') ? 'on' : '' }}"
                href="{{ route('helpdesk') }}"><span>🎫</span> Helpdesk</a>
            <a class="ni {{ request()->routeIs('reports') ? 'on' : '' }}"
                href="{{ route('reports') }}"><span>📊</span> Reports</a>

            <div class="nl">Masters</div>
            <a class="ni {{ request()->routeIs('masters') ? 'on' : '' }}"
                href="{{ route('masters') }}"><span>🏢</span> Company / Entity</a>
            <a class="ni {{ request()->routeIs('departments') ? 'on' : '' }}"
                href="{{ route('departments') }}"><span>🏗️</span> Departments</a>
            <a class="ni {{ request()->routeIs('leave.policies') ? 'on' : '' }}"
                href="{{ route('leave.policies') }}"><span>📋</span> Leave Policies</a>

        </nav>
        <div class="sf">
            <form method="POST" action="{{ route('logout') }}" id="logout-form">
                @csrf
                <div class="up" onclick="document.getElementById('logout-form').submit()" title="Sign out">
                    <div class="uav">{{ strtoupper(substr(auth()->user()->name ?? auth()->user()->email, 0, 1)) }}
                    </div>
                    <div style="flex:1;min-width:0">
                        <div
                            style="font-size:12px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                            {{ auth()->user()->name ?? auth()->user()->email }}
                        </div>
                        <div style="font-size:10px;color:var(--t3)">
                            {{ auth()->user()->roles->first()?->slug ?? 'user' }}
                        </div>
                    </div>
                    <span style="color:var(--t3);font-size:11px">⏻</span>
                </div>
            </form>
        </div>
    </aside>

    <main>
        <div class="hdr">
            <h2>@yield('title', 'Dashboard')</h2>
            <div style="display:flex;gap:6px" id="pacts">@yield('page_actions')</div>
        </div>
        <div class="pg" id="pg">
            @if (session('error') && session('error_file'))
                <div
                    style="background:#fff7ed;border:1px solid #fb923c;color:#7c2d12;border-radius:8px;
                padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;
                justify-content:space-between;gap:14px;box-shadow:0 2px 10px rgba(124,45,18,.08)">
                    <div style="font-size:13px;line-height:1.45">
                        <strong>Import completed with errors.</strong>
                        Download the error report to see every failed row with its original Excel data and error reason.
                    </div>
                    <a href="{{ route('error_export.download', session('error_file')) }}"
                        style="flex-shrink:0;background:#ea580c;color:#fff;border:1px solid #c2410c;
                padding:8px 12px;border-radius:6px;font-size:12px;font-weight:700;
                text-decoration:none;white-space:nowrap">
                        Download Error Report
                    </a>
                </div>
            @endif
            @yield('content')
        </div>
    </main>

    <div id="toast"></div>

    @if (session('success'))
        <div id="flash-success" class="flash flash-ok">{{ session('success') }}</div>
        <script>
            setTimeout(() => document.getElementById('flash-success')?.remove(), 4000)
        </script>
    @endif

    @if (session('error'))
        <div id="flash-error" class="flash flash-err"
            style="display:flex;justify-content:space-between;align-items:center;gap:14px;max-width:520px">
            <span style="flex:1">{{ session('error') }}</span>
            @if (session('error_file'))
                <a href="{{ route('error_export.download', session('error_file')) }}"
                    style="flex-shrink:0;background:#991b1b;border:1px solid #7f1d1d;
            color:#fff;padding:7px 12px;border-radius:6px;font-size:12px;font-weight:700;
            text-decoration:none;white-space:nowrap">
                    Download Error Report
                </a>
            @endif
        </div>
        @if (!session('error_file'))
            <script>
                setTimeout(() => document.getElementById('flash-error')?.remove(), 5000)
            </script>
        @endif
    @endif

    {{-- Skipped-rows warning: import succeeded but some rows were skipped (e.g. EMP ID not found) --}}
    @if (session('skipped_file'))
        <div id="flash-skipped" class="flash"
            style="background:#fffbeb;border:1px solid #f59e0b;color:#78350f;
     border-radius:8px;padding:11px 16px;margin-bottom:10px;max-width:640px;
     display:flex;justify-content:space-between;align-items:center;gap:14px">
            <span style="flex:1;font-size:13px">⚠️ {{ session('skipped_warning') }}</span>
            <a href="{{ route('error_export.download', session('skipped_file')) }}"
                style="flex-shrink:0;background:#d97706;color:#fff;border:1px solid #b45309;
            padding:7px 12px;border-radius:6px;font-size:12px;font-weight:700;
            text-decoration:none;white-space:nowrap">
                Download Skipped Report
            </a>
        </div>
    @endif

    {{-- Auto-download the error Excel as soon as the page loads (no button click needed) --}}
    @if (session('error_file'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var a = document.createElement('a');
                a.href = @json(route('error_export.download', session('error_file')));
                a.download = @json(session('error_file'));
                a.style.display = 'none';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            });
        </script>
    @endif

    @if ($errors->any())
        <div id="flash-validation" class="flash flash-err">{{ $errors->first() }}</div>
        <script>
            setTimeout(() => document.getElementById('flash-validation')?.remove(), 6000)
        </script>
    @endif

    <script>
        // ── Shared utilities ──────────────────────────────────────────────
        const CSRF = () => document.querySelector('meta[name="csrf-token"]').content;
        const ENTITY =
            {{ auth()->user()->employee?->company_id ?? (\App\Models\Employee::whereNotNull('company_id')->value('company_id') ?? 1) }};
        const M = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        const $ = id => document.getElementById(id);
        const fmt = n => Number(n || 0).toLocaleString('en-IN');
        const fmtD = d => d ? String(d).slice(0, 10) : '-';
        const om = id => $(id).classList.add('on');
        const cm = id => $(id).classList.remove('on');

        function toast(msg, t = 'ok') {
            const el = $('toast');
            el.textContent = (t === 'ok' ? '✓ ' : '✗ ') + msg;
            el.className = 'on ' + t;
            clearTimeout(el._t);
            el._t = setTimeout(() => el.className = '', 4000);
        }

        // ── SweetAlert confirm helper ─────────────────────────────────────
        // Usage: onclick="swConfirm(this.closest('form'), 'Title', 'Message', 'icon', 'Confirm text', '#color')"
        function swConfirm(form, title, text, icon, btnText, btnColor) {
            Swal.fire({
                title: title || 'Are you sure?',
                text: text || '',
                icon: icon || 'warning',
                showCancelButton: true,
                confirmButtonText: btnText || 'Yes, proceed',
                confirmButtonColor: btnColor || '#dc2626',
                cancelButtonColor: '#6b7280',
                cancelButtonText: 'Cancel',
                reverseButtons: true,
            }).then(r => {
                if (r.isConfirmed) form.submit();
            });
        }

        function filterTbl(q, id) {
            document.querySelectorAll(`#${id} tbody tr`).forEach(r => {
                r.style.display = r.textContent.toLowerCase().includes(q.toLowerCase()) ? '' : 'none';
            });
        }

        function exDate(v) {
            if (!v) return '';
            if (typeof v === 'number') return new Date((v - 25569) * 86400000).toISOString().slice(0, 10);
            if (v instanceof Date) return v.toISOString().slice(0, 10);
            const s = String(v).slice(0, 10);
            if (/^\d{2}-\d{2}-\d{4}$/.test(s)) {
                const [d, mo, y] = s.split('-');
                return `${y}-${mo}-${d}`;
            }
            return s;
        }

        // ── API wrapper — session cookie sent automatically (same domain).
        //    CSRF token required only for state-changing methods.
        async function api(path, opts = {}) {
            const method = (opts.method || 'GET').toUpperCase();
            const headers = {
                'Accept': 'application/json',
                ...(opts.body ? {
                    'Content-Type': 'application/json'
                } : {}),
                ...(method !== 'GET' ? {
                    'X-CSRF-TOKEN': CSRF()
                } : {}),
                ...(opts.headers || {}),
            };
            const res = await fetch('/api' + path, {
                ...opts,
                headers,
                credentials: 'same-origin'
            });
            const data = await res.json().catch(() => ({}));
            if (res.status === 401 || res.status === 419) {
                // Guard against redirect loop: only redirect if not already heading to login
                if (!window._loggingOut && window.location.pathname !== '/login') {
                    window._loggingOut = true;
                    window.location.href = '/login';
                }
                return null;
            }
            if (!res.ok) throw data;
            return data;
        }

        // Shared employee/dept/designation cache for dropdowns
        let _emps = [],
            _depts = [],
            _desigs = [],
            _leaveTypes = [];

        async function loadDropdowns() {
            try {
                const r = await api('/employees?company_id=' + ENTITY + '&per_page=300');
                _emps = r?.data || [];
            } catch {}
            try {
                const r = await api('/master/departments?company_id=' + ENTITY + '&per_page=100');
                _depts = r?.data || [];
            } catch {}
            try {
                const r = await api('/master/designations?company_id=' + ENTITY + '&per_page=100');
                _desigs = r?.data || [];
            } catch {}
            try {
                const r = await api('/leave/types?company_id=' + ENTITY);
                _leaveTypes = r?.data || [];
            } catch {}
        }
    </script>

    <script>
        /* ════════════════════════════════════════════════════════════════
           Searchable Select — global progressive enhancement
           Converts every <select> with ≥6 options into a live-search
           dropdown. Works with dynamically opened modals.
           Exposes window._ssSync(el) for programmatic value updates.
        ════════════════════════════════════════════════════════════════ */
        (function() {
            var MIN_OPTS = 5;

            function initSS(sel) {
                if (sel._ssOk || sel.options.length < MIN_OPTS || sel.dataset.noSearch != null) return;
                sel._ssOk = true;
                sel.style.display = 'none';

                var wrap = document.createElement('div');
                wrap.className = 'ss-wrap';
                sel.parentNode.insertBefore(wrap, sel);
                wrap.appendChild(sel);

                var inp = document.createElement('input');
                inp.type = 'text';
                inp.className = 'ss-inp';
                inp.autocomplete = 'off';
                inp.placeholder = sel.options[0]?.text || 'Select…';
                wrap.appendChild(inp);

                var drop = document.createElement('div');
                drop.className = 'ss-drop';
                drop.style.display = 'none';
                wrap.appendChild(drop);

                /* ---------- helpers ---------- */
                function selectedOpt() {
                    return Array.from(sel.options).find(o => o.value == sel.value);
                }

                function syncDisplay() {
                    var o = selectedOpt();
                    inp.value = (o && o.value) ? o.text : '';
                }
                syncDisplay();

                function render(q) {
                    drop.innerHTML = '';
                    var lq = (q || '').toLowerCase();
                    var shown = 0;
                    Array.from(sel.options).forEach(function(o) {
                        if (!lq && !o.value) return; // hide placeholder unless searching
                        if (lq && !o.text.toLowerCase().includes(lq)) return; // filter
                        var d = document.createElement('div');
                        d.className = 'ss-opt' + (o.value == sel.value ? ' ss-sel' : '');
                        d.textContent = o.text;
                        d.addEventListener('mousedown', function(e) {
                            e.preventDefault();
                            sel.value = o.value;
                            syncDisplay();
                            hideDrop();
                            sel.dispatchEvent(new Event('change', {
                                bubbles: true
                            }));
                        });
                        drop.appendChild(d);
                        shown++;
                    });
                    if (!shown) {
                        var em = document.createElement('div');
                        em.className = 'ss-none';
                        em.textContent = 'No results';
                        drop.appendChild(em);
                    }
                }

                function showDrop(q) {
                    render(q);
                    drop.style.display = 'block';
                    inp.classList.add('ss-open');
                }

                function hideDrop() {
                    drop.style.display = 'none';
                    inp.classList.remove('ss-open');
                    syncDisplay();
                }

                /* ---------- events ---------- */
                inp.addEventListener('click', function() {
                    inp.select();
                    showDrop('');
                });
                inp.addEventListener('focus', function() {
                    inp.select();
                    showDrop('');
                });
                inp.addEventListener('input', function() {
                    showDrop(inp.value);
                });
                inp.addEventListener('blur', function() {
                    setTimeout(hideDrop, 200);
                });
                inp.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') hideDrop();
                });

                /* exposed so setVal() can sync display after programmatic changes */
                sel._ssSync = syncDisplay;
                sel.addEventListener('change', syncDisplay);
            }

            function initAll(root) {
                (root || document).querySelectorAll('select').forEach(initSS);
            }

            /* Initialise on load + whenever DOM changes (modals) */
            document.addEventListener('DOMContentLoaded', function() {
                initAll();
                new MutationObserver(function(ms) {
                    ms.forEach(function(m) {
                        m.addedNodes.forEach(function(n) {
                            if (n.querySelectorAll) initAll(n);
                        });
                    });
                }).observe(document.body, {
                    childList: true,
                    subtree: true
                });
            });

            /* Global helper for page scripts that set select values programmatically */
            window._ssSync = function(el) {
                if (el && el._ssSync) el._ssSync();
            };
        }());
    </script>

    <script>
        /* ── Table → Excel export (SheetJS) ─────────────────────────────────
           exportTableXLSX(filename, tableId?)
           tableId is optional; defaults to the first <table> inside .card     */
        function exportTableXLSX(filename, tableId) {
            var tbl = tableId ?
                document.getElementById(tableId) :
                document.querySelector('.card table');
            if (!tbl) {
                toast('No table found to export', 'er');
                return;
            }
            var wb = XLSX.utils.table_to_book(tbl, {
                sheet: 'Data',
                raw: false
            });
            XLSX.writeFile(wb, (filename || 'export') + '_' + new Date().toISOString().slice(0, 10) + '.xlsx');
        }

        /* ── Debounced search: auto-submit the filter form 400 ms after
           the user stops typing in any input[name=search] field.            */
        (function() {
            function attach(inp) {
                if (inp._dbOk) return;
                inp._dbOk = true;
                var t;
                inp.addEventListener('keyup', function(e) {
                    if (e.key === 'Enter') {
                        clearTimeout(t);
                        if (inp.form) inp.form.submit();
                        return;
                    }
                    if (e.key === 'Escape') {
                        clearTimeout(t);
                        return;
                    }
                    clearTimeout(t);
                    t = setTimeout(function() {
                        if (inp.form) inp.form.submit();
                    }, 400);
                });
            }
            document.addEventListener('DOMContentLoaded', function() {
                document.querySelectorAll('input[name=search], input[name=emp_id], input[name=tracker_emp]')
                    .forEach(attach);
            });
        }());
    </script>
    @stack('scripts')

    {{-- ── Salary report re-upload: client-side month verification ─────────
         Reads the uploaded Excel using SheetJS, finds the "Month" column,
         extracts the value from the first data row, and shows a SweetAlert
         if it doesn't match the expected month for the current report.     --}}
    <script>
    function verifyReuploadMonth(input, expectedYM) {
      const file = input.files[0];
      if (!file || !expectedYM) return;

      // Show verification in progress
      const okDiv = input.parentElement.querySelector('[id^="reupload-month-ok"]');

      const reader = new FileReader();
      reader.onload = function(e) {
        try {
          const wb   = XLSX.read(new Uint8Array(e.target.result), { type: 'array', cellDates: true });
          const ws   = wb.Sheets[wb.SheetNames[0]];
          const rng  = XLSX.utils.decode_range(ws['!ref'] || 'A1');

          // Locate the "Month" column by scanning the header row
          let monthCol = -1;
          for (let c = rng.s.c; c <= rng.e.c; c++) {
            const cell = ws[XLSX.utils.encode_cell({ r: rng.s.r, c })];
            if (cell && String(cell.v).trim().toLowerCase() === 'month') {
              monthCol = c;
              break;
            }
          }

          if (monthCol === -1) {
            // Header not found — let server decide
            if (okDiv) okDiv.style.display = 'none';
            return;
          }

          // Get the Month value from the first data row
          const monthCell = ws[XLSX.utils.encode_cell({ r: rng.s.r + 1, c: monthCol })];
          if (!monthCell) return;

          const MONTHS = { jan:1, feb:2, mar:3, apr:4, may:5, jun:6,
                           jul:7, aug:8, sep:9, oct:10, nov:11, dec:12 };
          const MNAMES = ['Jan','Feb','Mar','Apr','May','Jun',
                          'Jul','Aug','Sep','Oct','Nov','Dec'];

          let filledYM = null;
          const v = monthCell.v;

          if (v instanceof Date) {
            // Round to nearest UTC midnight before extracting year/month.
            // PHP servers in non-UTC timezones (e.g. IST = UTC+5:30) write Excel
            // date serials at 18:30 UTC instead of 00:00 UTC, shifting the Date
            // object to the previous day in UTC. Rounding ±12 h fixes this.
            const DAY_MS = 86400000;
            const snapped = new Date(Math.round(v.getTime() / DAY_MS) * DAY_MS);
            filledYM = snapped.getUTCFullYear() + '-' + String(snapped.getUTCMonth() + 1).padStart(2, '0');
          } else if (typeof v === 'number') {
            // Excel date serial (cell not yet parsed as Date by SheetJS)
            const d = new Date(Math.round((v - 25569) * 86400000));
            filledYM = d.getUTCFullYear() + '-' + String(d.getUTCMonth() + 1).padStart(2, '0');
          } else if (typeof v === 'string') {
            // "May 2026" or "2026-05" etc.
            const s = v.trim();
            // Try YYYY-MM
            if (/^\d{4}-\d{2}$/.test(s)) {
              filledYM = s;
            } else {
              const parts = s.split(/[\s,\-\/]+/).filter(Boolean);
              if (parts.length >= 2) {
                const a = parts[0], b = parts[1];
                const monA = MONTHS[a.toLowerCase().substring(0, 3)];
                const monB = MONTHS[b.toLowerCase().substring(0, 3)];
                if (monA && /^\d{4}$/.test(b)) filledYM = b + '-' + String(monA).padStart(2, '0');
                else if (monB && /^\d{4}$/.test(a)) filledYM = a + '-' + String(monB).padStart(2, '0');
              }
            }
          }

          if (!filledYM) {
            if (okDiv) okDiv.style.display = 'none';
            return; // Can't parse — let server handle
          }

          if (filledYM !== expectedYM) {
            // ── MISMATCH — show SweetAlert and clear the file ──────────────
            const [ey, em] = expectedYM.split('-');
            const [fy, fm] = filledYM.split('-');
            const expectedLabel = MNAMES[parseInt(em) - 1] + ' ' + ey;
            const fileLabel     = MNAMES[parseInt(fm) - 1] + ' ' + fy;

            Swal.fire({
              icon: 'error',
              title: 'Wrong Report File!',
              html:
                'The uploaded file is the <strong>' + fileLabel + '</strong> salary report,<br>'
                + 'but you are viewing the <strong>' + expectedLabel + '</strong> report.<br><br>'
                + 'Please select the correct <strong>' + expectedLabel + '</strong> Excel file.',
              confirmButtonText: 'OK, select correct file',
              confirmButtonColor: '#dc2626',
            });

            input.value = '';           // clear the file input
            if (okDiv) okDiv.style.display = 'none';
          } else {
            // ── Match — show green confirmation ────────────────────────────
            if (okDiv) okDiv.style.display = 'block';
          }
        } catch (err) {
          // Silent fail — server will catch it
          if (okDiv) okDiv.style.display = 'none';
        }
      };
      reader.readAsArrayBuffer(file);
    }
    </script>
</body>

</html>
