<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo $__env->yieldContent('title', 'HRMS'); ?> — HRMS Payroll</title>
    <link rel="icon" href="<?php echo e(asset('favicontnbt.png')); ?>" type="image/x-icon">
    <link rel="shortcut icon" href="<?php echo e(asset('favicontnbt.png')); ?>" type="image/x-icon">
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

        .ss-opt:hover,
        .ss-opt.ss-hl {
            background: var(--s2);
        }

        .ss-opt.ss-sel {
            background: var(--acc2);
            color: var(--acc);
            font-weight: 600;
        }

        .ss-opt.ss-hl.ss-sel {
            background: var(--acc2);
            filter: brightness(0.96);
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
            <?php if (! (auth()->user()?->isRestrictedToLeaveOnly())): ?>
            <div class="nl">Overview</div>
            <a class="ni <?php echo e(request()->routeIs('dashboard') ? 'on' : ''); ?>"
                href="<?php echo e(route('dashboard')); ?>">Dashboard</a>
            <?php endif; ?>

            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->any(['employee_view', 'bioid_view', 'attendance_view', 'attendance_self_view', 'attendance_analytics_view', 'leave_management_view', 'reimbursment_self_view'])): ?>
            <div class="nl">HR</div>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('employee_view')): ?>
            <a class="ni <?php echo e(request()->routeIs('employees') ? 'on' : ''); ?>"
                href="<?php echo e(route('employees')); ?>">Employees</a>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('bioid_view')): ?>
            <a class="ni <?php echo e(request()->routeIs('bio_ids') ? 'on' : ''); ?>" href="<?php echo e(route('bio_ids')); ?>">
                Bio IDs</a>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('attendance_view')): ?>
            <a class="ni <?php echo e(request()->routeIs('attendance') ? 'on' : ''); ?>"
                href="<?php echo e(route('attendance')); ?>">Attendance</a>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('attendance_self_view')): ?>
            <?php if(auth()->user()->employee): ?>
            <a class="ni <?php echo e(request()->routeIs('my-attendance') ? 'on' : ''); ?>"
                href="<?php echo e(route('my-attendance')); ?>">My Attendance</a>
            <?php endif; ?>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('attendance_edit')): ?>
            <a class="ni <?php echo e(request()->routeIs('attendance.change_requests*') ? 'on' : ''); ?>"
                href="<?php echo e(route('attendance.change_requests')); ?>">Attendance Requests</a>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('attendance_analytics_view')): ?>
            <a class="ni <?php echo e(request()->routeIs('attendance.analytics') ? 'on' : ''); ?>"
                href="<?php echo e(route('attendance.analytics')); ?>">Attendance Analytics</a>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('leave_management_view')): ?>
            <a class="ni <?php echo e(request()->routeIs('leave') ? 'on' : ''); ?>" href="<?php echo e(route('leave')); ?>">
                Leave Management</a>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('reimbursment_self_view')): ?>
            <?php if(auth()->user()->employee): ?>
            <a class="ni <?php echo e(request()->routeIs('my-reimbursements') ? 'on' : ''); ?>"
                href="<?php echo e(route('my-reimbursements')); ?>">My Reimbursements</a>
            <?php endif; ?>
            <?php endif; ?>
            <?php endif; ?>

            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->any(['payroll_view', 'salary_details', 'incentive_view', 'monthly_tds_view'])): ?>
            <div class="nl">Payroll</div>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('payroll_view')): ?>
            <a class="ni <?php echo e(request()->routeIs('payroll', 'salary.report*') ? 'on' : ''); ?>"
                href="<?php echo e(route('payroll')); ?>">Payroll & Reports</a>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('salary_details')): ?>
            <a class="ni <?php echo e(request()->routeIs('salary') ? 'on' : ''); ?>" href="<?php echo e(route('salary')); ?>">
                Salary Details</a>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('incentive_view')): ?>
            <a class="ni <?php echo e(request()->routeIs('incentives') ? 'on' : ''); ?>"
                href="<?php echo e(route('incentives')); ?>">Incentives</a>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('monthly_tds_view')): ?>
            <a class="ni <?php echo e(request()->routeIs('tds.monthly') ? 'on' : ''); ?>"
                href="<?php echo e(route('tds.monthly')); ?>">Monthly TDS</a>
            <?php endif; ?>
            <?php endif; ?>

            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->any(['loan_tab_view', 'reimbursment_tab', 'reimb_report', 'reimbursment_approve'])): ?>
            <div class="nl">Finance</div>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('loan_tab_view')): ?>
            <a class="ni <?php echo e(request()->routeIs('loans') ? 'on' : ''); ?>" href="<?php echo e(route('loans')); ?>">
                Loans / Advance</a>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('reimbursment_tab')): ?>
            <a class="ni <?php echo e(request()->routeIs('reimbursements.tracker*') ? 'on' : ''); ?>"
                href="<?php echo e(route('reimbursements.tracker')); ?>">Reimbursements</a>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('reimbursment_approve')): ?>
            <a class="ni <?php echo e(request()->routeIs('reimbursements.approvals*') ? 'on' : ''); ?>"
                href="<?php echo e(route('reimbursements.approvals')); ?>">Reimbursement Approvals</a>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('reimb_report')): ?>
            <a class="ni <?php echo e(request()->routeIs('reimbursements.report*') ? 'on' : ''); ?>"
                href="<?php echo e(route('reimbursements.report.saved')); ?>">Reimb. Report</a>
            <?php endif; ?>
            <?php endif; ?>

            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->any(['holidays_view', 'company_view', 'department_view', 'branch_view', 'leave_policies_view', 'rbac_roles_manage', 'user_view'])): ?>
            <div class="nl">Masters</div>
             <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('holidays_view')): ?>
            <a class="ni <?php echo e(request()->routeIs('holidays') ? 'on' : ''); ?>"
                href="<?php echo e(route('holidays')); ?>">Holidays</a>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('company_view')): ?>
            <a class="ni <?php echo e(request()->routeIs('masters') ? 'on' : ''); ?>"
                href="<?php echo e(route('masters')); ?>">Company / Entity</a>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('department_view')): ?>
            <a class="ni <?php echo e(request()->routeIs('departments') ? 'on' : ''); ?>"
                href="<?php echo e(route('departments')); ?>">Departments</a>
            <a class="ni <?php echo e(request()->routeIs('designations') ? 'on' : ''); ?>"
                href="<?php echo e(route('designations')); ?>">Designations</a>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('branch_view')): ?>
            <a class="ni <?php echo e(request()->routeIs('branches') ? 'on' : ''); ?>"
                href="<?php echo e(route('branches')); ?>">Branches</a>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('leave_policies_view')): ?>
            <a class="ni <?php echo e(request()->routeIs('leave.policies') ? 'on' : ''); ?>"
                href="<?php echo e(route('leave.policies')); ?>">Leave Policies</a>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('rbac_roles_manage')): ?>
            <a class="ni <?php echo e(request()->routeIs('rbac.roles', 'rbac.permission_groups', 'rbac.permissions', 'rbac.index') ? 'on' : ''); ?>"
                href="<?php echo e(route('rbac.roles')); ?>">Role &amp; Permissions</a>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('user_view')): ?>
            <a class="ni <?php echo e(request()->routeIs('users.*') ? 'on' : ''); ?>"
                href="<?php echo e(route('users.index')); ?>">Users</a>
            <?php endif; ?>
            <?php endif; ?>

        </nav>
        <div class="sf">
            <a href="<?php echo e(route('profile.show')); ?>" class="up" style="text-decoration:none;color:inherit" title="My Profile">
                <div class="uav"><?php echo e(strtoupper(substr(auth()->user()->name ?? auth()->user()->email, 0, 1))); ?>

                </div>
                <div style="flex:1;min-width:0">
                    <div
                        style="font-size:12px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                        <?php echo e(auth()->user()->name ?? auth()->user()->email); ?>

                    </div>
                    <div style="font-size:10px;color:var(--t3)">
                        <?php echo e(auth()->user()->roles->first()?->name ?? 'user'); ?>

                    </div>
                </div>
            </a>
            <form method="POST" action="<?php echo e(route('logout')); ?>" id="logout-form">
                <?php echo csrf_field(); ?>
                <button type="submit" title="Sign out"
                    style="background:none;border:none;color:var(--t3);font-size:12px;cursor:pointer;padding:6px 8px;width:100%;text-align:left">
                    ⏻ Sign out
                </button>
            </form>
        </div>
    </aside>

    <main>
        <div class="hdr">
            <h2><?php echo $__env->yieldContent('title', 'Dashboard'); ?></h2>
            <div style="display:flex;gap:6px" id="pacts"><?php echo $__env->yieldContent('page_actions'); ?></div>
            <div class="bell-wrap">
                <button type="button" class="bell-btn" onclick="toggleBell(event)" title="Notifications">
                    🔔
                    <?php if($unreadNotifCount > 0): ?>
                        <span class="bell-badge"><?php echo e($unreadNotifCount > 9 ? '9+' : $unreadNotifCount); ?></span>
                    <?php endif; ?>
                </button>
                <div class="bell-drop" id="bellDrop">
                    <div class="bell-hd">
                        <span>Notifications</span>
                        <?php if($unreadNotifCount > 0): ?>
                            <form method="POST" action="<?php echo e(route('notifications.read_all')); ?>">
                                <?php echo csrf_field(); ?>
                                <button type="submit" class="bell-mark-all">Mark all read</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="bell-list">
                        <?php $__empty_1 = true; $__currentLoopData = $recentNotifications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $n): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <a href="<?php echo e(route('notifications.open', $n->id)); ?>"
                                class="bell-item <?php echo e($n->read_at ? '' : 'unread'); ?>">
                                <div class="bell-item-title"><?php echo e($n->data['title'] ?? 'Notification'); ?></div>
                                <div class="bell-item-msg"><?php echo e($n->data['message'] ?? ''); ?></div>
                                <div class="bell-item-time"><?php echo e($n->created_at->diffForHumans()); ?></div>
                            </a>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <div class="bell-empty">No notifications yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="pg" id="pg">
            <?php if(session('error') && session('error_file')): ?>
                <div
                    style="background:#fff7ed;border:1px solid #fb923c;color:#7c2d12;border-radius:8px;
                padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;
                justify-content:space-between;gap:14px;box-shadow:0 2px 10px rgba(124,45,18,.08)">
                    <div style="font-size:13px;line-height:1.45">
                        <strong>Import completed with errors.</strong>
                        Download the error report to see every failed row with its original Excel data and error reason.
                    </div>
                    <a href="<?php echo e(route('error_export.download', session('error_file'))); ?>"
                        style="flex-shrink:0;background:#ea580c;color:#fff;border:1px solid #c2410c;
                padding:8px 12px;border-radius:6px;font-size:12px;font-weight:700;
                text-decoration:none;white-space:nowrap">
                        Download Error Report
                    </a>
                </div>
            <?php endif; ?>
            <?php echo $__env->yieldContent('content'); ?>
        </div>
    </main>

    <div id="toast"></div>

    <?php if(session('success')): ?>
        <div id="flash-success" class="flash flash-ok"><?php echo e(session('success')); ?></div>
        <script>
            setTimeout(() => document.getElementById('flash-success')?.remove(), 4000)
        </script>
    <?php endif; ?>

    <?php if(session('error')): ?>
        <div id="flash-error" class="flash flash-err"
            style="display:flex;justify-content:space-between;align-items:center;gap:14px;max-width:520px">
            <span style="flex:1"><?php echo e(session('error')); ?></span>
            <?php if(session('error_file')): ?>
                <a href="<?php echo e(route('error_export.download', session('error_file'))); ?>"
                    style="flex-shrink:0;background:#991b1b;border:1px solid #7f1d1d;
            color:#fff;padding:7px 12px;border-radius:6px;font-size:12px;font-weight:700;
            text-decoration:none;white-space:nowrap">
                    Download Error Report
                </a>
            <?php endif; ?>
        </div>
        <?php if(!session('error_file')): ?>
            <script>
                setTimeout(() => document.getElementById('flash-error')?.remove(), 5000)
            </script>
        <?php endif; ?>
    <?php endif; ?>

    
    <?php if(session('skipped_file')): ?>
        <div id="flash-skipped" class="flash"
            style="background:#fffbeb;border:1px solid #f59e0b;color:#78350f;
     border-radius:8px;padding:11px 16px;margin-bottom:10px;max-width:640px;
     display:flex;justify-content:space-between;align-items:center;gap:14px">
            <span style="flex:1;font-size:13px">⚠️ <?php echo e(session('skipped_warning')); ?></span>
            <a href="<?php echo e(route('error_export.download', session('skipped_file'))); ?>"
                style="flex-shrink:0;background:#d97706;color:#fff;border:1px solid #b45309;
            padding:7px 12px;border-radius:6px;font-size:12px;font-weight:700;
            text-decoration:none;white-space:nowrap">
                Download Skipped Report
            </a>
        </div>
    <?php endif; ?>

    
    <?php if(session('error_file')): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var a = document.createElement('a');
                a.href = <?php echo json_encode(route('error_export.download', session('error_file')), 512) ?>;
                a.download = <?php echo json_encode(session('error_file'), 15, 512) ?>;
                a.style.display = 'none';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            });
        </script>
    <?php endif; ?>

    <?php if($errors->any()): ?>
        <div id="flash-validation" class="flash flash-err"><?php echo e($errors->first()); ?></div>
        <script>
            setTimeout(() => document.getElementById('flash-validation')?.remove(), 6000)
        </script>
    <?php endif; ?>

    <script>
        // ── Shared utilities ──────────────────────────────────────────────
        const CSRF = () => document.querySelector('meta[name="csrf-token"]').content;
        const ENTITY =
            <?php echo e(auth()->user()->employee?->company_id ?? (\App\Models\Employee::whereNotNull('company_id')->value('company_id') ?? 1)); ?>;
        const M = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        const $ = id => document.getElementById(id);
        const fmt = n => Number(n || 0).toLocaleString('en-IN');
        const fmtD = d => d ? String(d).slice(0, 10) : '-';
        const om = id => $(id).classList.add('on');
        const cm = id => $(id).classList.remove('on');

        // ── Notification bell ─────────────────────────────────────────────
        function toggleBell(e) {
            e.stopPropagation();
            $('bellDrop').classList.toggle('on');
        }
        document.addEventListener('click', e => {
            const drop = $('bellDrop');
            if (drop && drop.classList.contains('on') && !e.target.closest('.bell-wrap')) {
                drop.classList.remove('on');
            }
        });

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
                var hlIdx = -1; // index into the currently rendered .ss-opt list (keyboard highlight)

                function selectedOpt() {
                    return Array.from(sel.options).find(o => o.value == sel.value);
                }

                function syncDisplay() {
                    var o = selectedOpt();
                    inp.value = (o && o.value) ? o.text : '';
                }
                syncDisplay();

                function optEls() {
                    return Array.from(drop.querySelectorAll('.ss-opt'));
                }

                function applyHighlight() {
                    var els = optEls();
                    els.forEach(function(el, i) {
                        el.classList.toggle('ss-hl', i === hlIdx);
                    });
                    if (hlIdx >= 0 && els[hlIdx]) {
                        els[hlIdx].scrollIntoView({
                            block: 'nearest'
                        });
                    }
                }

                function choose(el) {
                    var o = el._opt;
                    if (!o) return;
                    sel.value = o.value;
                    syncDisplay();
                    hideDrop();
                    sel.dispatchEvent(new Event('change', {
                        bubbles: true
                    }));
                }

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
                        d._opt = o;
                        d.addEventListener('mousedown', function(e) {
                            e.preventDefault();
                            choose(d);
                        });
                        d.addEventListener('mouseenter', function() {
                            hlIdx = optEls().indexOf(d);
                            applyHighlight();
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
                    // Land the keyboard highlight on the current selection, if visible
                    var els = optEls();
                    hlIdx = els.findIndex(el => el.classList.contains('ss-sel'));
                    applyHighlight();
                }

                function showDrop(q) {
                    render(q);
                    drop.style.display = 'block';
                    inp.classList.add('ss-open');
                }

                function hideDrop() {
                    drop.style.display = 'none';
                    inp.classList.remove('ss-open');
                    hlIdx = -1;
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
                    if (e.key === 'Escape') {
                        hideDrop();
                        return;
                    }
                    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                        e.preventDefault();
                        if (drop.style.display === 'none') {
                            render(inp.value === (selectedOpt()?.text || '') ? '' : inp.value);
                            drop.style.display = 'block';
                            inp.classList.add('ss-open');
                        }
                        var count = optEls().length;
                        if (!count) return;
                        if (e.key === 'ArrowDown') {
                            hlIdx = hlIdx < count - 1 ? hlIdx + 1 : 0;
                        } else {
                            hlIdx = hlIdx > 0 ? hlIdx - 1 : count - 1;
                        }
                        applyHighlight();
                        return;
                    }
                    if (e.key === 'Enter') {
                        if (drop.style.display === 'none') return;
                        e.preventDefault();
                        var els = optEls();
                        if (hlIdx >= 0 && els[hlIdx]) choose(els[hlIdx]);
                    }
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
    <?php echo $__env->yieldPushContent('scripts'); ?>

    
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
<?php /**PATH D:\hrms-payroll\resources\views/layouts/app.blade.php ENDPATH**/ ?>