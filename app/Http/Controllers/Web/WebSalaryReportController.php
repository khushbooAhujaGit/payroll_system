<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AttendanceApproval;
use App\Models\Company;
use App\Models\Entity;
use App\Models\ReimbursementClaim;
use App\Models\SalaryReportOverride;
use App\Models\SalaryReportSnapshot;
use App\Models\Employee;
use App\Models\EmployeeBankDetail;
use App\Models\EmployeeIncentive;
use App\Models\EmployeeMonthlyTds;
use App\Models\EmployeeStatutoryDetail;
use App\Models\Holiday;
use App\Models\HolidayCalendar;
use App\Models\LeavePolicy;
use App\Models\LeaveTrackerRecord;
use App\Models\LeaveType;
use App\Models\EmployeeLoan;
use App\Models\LoanInstallment;
use App\Services\OutlookMailerService;
use App\Services\PayslipPdfService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class WebSalaryReportController extends Controller
{
    // ─────────────────────────────────────────────
    // INDEX  — form + preview table
    // ─────────────────────────────────────────────

    public function index(Request $request)
    {
        $this->authorize('payroll_view');

        $companyId = $request->has('company_id')
            ? (int) $request->get('company_id')
            : 0;

        $month  = $request->get('month'); // YYYY-MM or null
        $search = trim((string) $request->get('search', ''));
        $filter = (string) ($request->get('filter') ?? ''); // tds | incentive | loan | lop

        $allRows = [];
        $summary = null;
        $isLocked      = false;
        $snapshotCount = 0;
        $lockedAt      = null;

        $companies = Company::whereHas('employees', fn($q) => $q->where('employee_status', 'active'))
            ->orderBy('id')
            ->get(['id', 'name', 'short_name']);

        if ($month) {
            $snapshotCount = SalaryReportSnapshot::where('year_month', $month)->count();
            $isLocked      = $snapshotCount > 0;

            // Always compute live data (needed for summary cards + table in live view).
            $liveRows = $this->compute($month, $companyId);

            if (!$isLocked) {
                // ── FIRST GENERATION — no snapshot exists yet → auto-save once ──────
                // Subsequent page loads (snapshot already exists) skip this block entirely
                // so the existing locked data is NEVER replaced by a page refresh.
                if (!empty($liveRows)) {
                    $this->performLock($month, $liveRows);
                    $snapshotCount = count($liveRows);
                    $isLocked      = true;
                }
            }

            if ($isLocked) {
                // Snapshot exists — serve the LIVE rows for display so the user
                // can see current data, but the locked snapshot stays untouched
                // until the user explicitly clicks "Save / Re-lock".
                $allRows = $liveRows;

                // For the summary and count we still show live numbers.
                // $lockedAt comes from the snapshot metadata.
                $snapMeta = SalaryReportSnapshot::where('year_month', $month)
                    ->orderByDesc('locked_at')
                    ->first(['locked_at', 'locked_by']);
                $lockedAt = $snapMeta?->locked_at;
            } else {
                $allRows  = $liveRows;
                $lockedAt = null;
            }

            $summary = $this->summarise($allRows);
        }

        $rows = $this->filterAndPaginate($allRows, $search, 25, $filter);

        return view('salary-report.index', compact(
            'month',
            'rows',
            'summary',
            'companyId',
            'companies',
            'isLocked',
            'snapshotCount',
            'lockedAt',
            'search',
            'filter'
        ));
    }

    // ─────────────────────────────────────────────
    // VIEW LOCKED REPORT  — dedicated read-only URL
    // ─────────────────────────────────────────────

    public function viewLockedReport(string $month): \Illuminate\Http\RedirectResponse|\Illuminate\View\View
    {
        $this->authorize('payroll_view');

        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            return redirect()->route('salary.report.saved')->with('error', 'Invalid month format.');
        }

        $snapshots = SalaryReportSnapshot::where('year_month', $month)->get()->keyBy('employee_id');

        if ($snapshots->isEmpty()) {
            return redirect()->route('salary.report.saved')
                ->with('error', 'No locked report found for ' . $month . '.');
        }

        $search    = trim((string) request()->get('search', ''));
        $filter    = (string) (request()->get('filter') ?? '');
        $allRows   = $snapshots->map(fn($s) => $s->snapshot_data)->values()->toArray();
        $summary   = $this->summarise($allRows);
        $rows      = $this->filterAndPaginate($allRows, $search, 25, $filter);

        $snapshotCount = $snapshots->count();
        $lockedAt      = $snapshots->sortByDesc('locked_at')->first()->locked_at;
        $companyId     = 0;
        $isLocked      = true;
        $isReadonly    = true;

        $companies = Company::whereHas('employees', fn($q) => $q->where('employee_status', 'active'))
            ->orderBy('id')
            ->get(['id', 'name', 'short_name']);

        return view('salary-report.view', compact(
            'month',
            'rows',
            'summary',
            'companyId',
            'companies',
            'isLocked',
            'snapshotCount',
            'lockedAt',
            'isReadonly',
            'search',
            'filter'
        ));
    }

    // ─────────────────────────────────────────────
    // EXCEL DOWNLOAD
    // ─────────────────────────────────────────────

    public function download(Request $request)
    {
        $this->authorize('payroll_view');

        $request->validate(['month' => 'required|date_format:Y-m']);
        $companyId = $request->has('company_id')
            ? (int) $request->get('company_id')
            : 0;

        $month = $request->get('month');

        // Use snapshot if locked, otherwise compute live
        $snapshots = SalaryReportSnapshot::where('year_month', $month)->get()->keyBy('employee_id');
        if ($snapshots->isNotEmpty()) {
            $rows = $snapshots->map(fn($s) => $s->snapshot_data)->values()->toArray();
            if ($companyId !== 0) {
                $rows = array_values(array_filter($rows, fn($r) => ($r['company_id'] ?? 0) === $companyId));
            }
        } else {
            $rows = $this->compute($month, $companyId);
        }

        $carbonMonth  = Carbon::createFromFormat('Y-m', $month);
        $entitySuffix = $companyId === 0 ? 'All_Firms' : ('Entity_' . $companyId);
        $filename     = 'Salary_Report_' . $carbonMonth->format('M_Y') . '_' . $entitySuffix . '.xlsx';

        $spreadsheet = $this->buildExcel($rows, $carbonMonth);
        $writer      = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ─────────────────────────────────────────────
    // CORE COMPUTATION
    // ─────────────────────────────────────────────

    private function compute(string $month, int $companyId): array
    {
        [$year, $mon] = array_map('intval', explode('-', $month));
        $carbonMonth  = Carbon::createFromDate($year, $mon, 1);
        $monthDays    = $carbonMonth->daysInMonth;
        $monthLabel   = $carbonMonth->format('M Y');

        $monthStart = $carbonMonth->copy()->startOfMonth()->toDateString();
        $monthEnd   = $carbonMonth->copy()->endOfMonth()->toDateString();

        // Previous month end — used for the leave-LOP delta calculation.
        // April is the first month of the FY so there is no prior FY month to compare against.
        $prevMonthCarbon = $carbonMonth->copy()->subMonth();
        $prevFyEnd = $mon === 4
            ? null
            : $prevMonthCarbon->copy()->endOfMonth()->toDateString();

        // NOTE: syncLeaverStatuses() is intentionally NOT called here.
        //
        // compute() is a READ-ONLY function — it must never mutate employee records.
        // Calling syncLeaverStatuses(now()) during report generation was causing a bug:
        // it would permanently set date_of_leaving on employees based on leave-tracker
        // LFT records dated TODAY, which could set a future leaver's date_of_leaving to
        // a date BEFORE the report month, excluding them from months where they were
        // still active (e.g. an employee with LFT=2026-01-31 in the tracker would have
        // date_of_leaving set to Jan 31, excluding them from any report month where
        // monthStart > Jan 31).
        //
        // Status/date maintenance is handled by scopeCurrentlyEmployed() (called once
        // per request via a service-container flag) which is the correct, isolated place.
        // Admins can also set date_of_leaving explicitly on the employee record.

        // Include employees who were employed AT ANY POINT during the report month:
        //  • joined on or before the last day of the month
        //  • AND either never left (date_of_leaving IS NULL)
        //       OR left on/after the first day of the month
        //         (i.e. they were still on payroll for at least part of the month)
        //
        // This correctly includes employees who have since become inactive but were
        // active in the report month (e.g. left in May 2025 → still in Apr 2025 report).
        $employeeQuery = Employee::where('date_of_joining', '<=', $monthEnd)
            ->where(function ($q) use ($monthStart) {
                $q->whereNull('date_of_leaving')
                    ->orWhere('date_of_leaving', '>=', $monthStart);
            })
            ->with(['designation:id,name', 'currentSalary', 'entity:id,name'])
            ->orderBy('company_id')
            ->orderBy('employee_id');

        if ($companyId !== 0) {
            $employeeQuery->where('company_id', $companyId);
        }

        $employees = $employeeQuery->get()->filter(fn($e) => $e->currentSalary);

        // Holiday dates per entity — loaded once, used per-employee in the loop.
        // Supports "All Firms" (entity_id = 0) without extra per-employee queries.
        $companyIdsInScope = $employees->pluck('company_id')->unique()->values()->all();

        // Load calendars for this year; fall back to any active calendar for the company
        $calendars = HolidayCalendar::whereIn('company_id', $companyIdsInScope)
            ->where(fn($q) => $q->where('year', $year)->orWhereNull('year'))
            ->orderByDesc('year')
            ->get()
            ->keyBy('company_id');   // first match per company wins (most specific year)
        $calendarIds = $calendars->map(fn($c) => $c->id)->all(); // entity_id → calendar_id

        // Load this month's holidays for all relevant calendars in one query.
        $allHolidays = Holiday::whereIn('holiday_calendar_id', array_values($calendarIds))
            ->whereYear('date', $year)
            ->whereMonth('date', $mon)
            ->get()
            ->groupBy('holiday_calendar_id');

        // ── Static weekoff fallback (used only when no weekly_off entries exist in DB) ──
        $companiesById = Company::whereIn('id', $companyIdsInScope)
            ->get(['id', 'name', 'short_name'])
            ->keyBy('id');

        $sundaysInMonth = [];
        $saturdaysInMonth = [];
        $d = $carbonMonth->copy()->startOfMonth();
        while ($d->month === $mon) {
            if ($d->dayOfWeek === Carbon::SUNDAY)   $sundaysInMonth[]   = $d->format('Y-m-d');
            elseif ($d->dayOfWeek === Carbon::SATURDAY) $saturdaysInMonth[] = $d->format('Y-m-d');
            $d->addDay();
        }

        // Build: company_id → [Y-m-d, ...] (all paid-off dates, deduped, sorted)
        //
        // Priority order for weekoff source:
        //   1. DB weekly_off entries (if any exist for this company + month) — user-configured
        //   2. Static fallback (all Sundays + company-rule Saturdays) — backward compat
        //
        // Named holidays from DB (national/regional/company) are ALWAYS merged regardless.
        $holidaysByCompany = [];
        foreach ($companyIdsInScope as $compId) {
            $calId = $calendarIds[$compId] ?? null;
            $calHolidays = $calId
                ? ($allHolidays[$calId] ?? collect())
                : collect();

            // Split into named holidays and weekoff entries
            $namedHolidayDates  = $calHolidays
                ->where('type', '!=', 'weekly_off')
                ->pluck('date')
                ->map(fn($dt) => Carbon::parse($dt)->format('Y-m-d'))
                ->toArray();

            $dbWeekoffDates = $calHolidays
                ->where('type', 'weekly_off')
                ->pluck('date')
                ->map(fn($dt) => Carbon::parse($dt)->format('Y-m-d'))
                ->toArray();

            // Use DB weekoffs if present; otherwise fall back to static calculation
            if (!empty($dbWeekoffDates)) {
                $weeklyOffs = $dbWeekoffDates;
            } else {
                $company    = $companiesById->get($compId);
                $weeklyOffs = array_merge(
                    $sundaysInMonth,
                    $this->companySaturdayWeeklyOffs($company?->name, $company?->short_name, $saturdaysInMonth)
                );
            }

            // Merge weekoffs + named holidays, deduplicate, sort
            $combined = array_values(array_unique(array_merge($weeklyOffs, $namedHolidayDates)));
            sort($combined);
            $holidaysByCompany[$compId] = $combined;
        }

        // Month-level defaults: use the first company's calendar for summary cards.
        $firstCompanyId     = $companyIdsInScope[0] ?? null;
        $monthHolidayDates  = $firstCompanyId
            ? ($holidaysByCompany[$firstCompanyId] ?? $sundaysInMonth)
            : $sundaysInMonth;
        $weekoffAndHolidays = count($monthHolidayDates);
        $workingDays        = max(1, $monthDays - $weekoffAndHolidays);

        $empIds = $employees->pluck('id');

        // Lookup tables keyed by employee DB id (primary key integer)
        // Use get()->keyBy() instead of pluck() to avoid potential key-type mismatch
        // between string keys from pluck and integer $emp->id.
        $incentives = EmployeeIncentive::where('month', $month)
            ->whereIn('employee_id', $empIds)
            ->get()
            ->keyBy('employee_id')
            ->map(fn($r) => (float) $r->amount);

        $monthlyTds = EmployeeMonthlyTds::where('month', $month)
            ->whereIn('employee_id', $empIds)
            ->get()
            ->keyBy('employee_id')
            ->map(fn($r) => (float) $r->amount);

        // ── Loan deduction logic ──────────────────────────────────────────
        // A loan qualifies for deduction in this month when ANY of these is true:
        //   A) first_emi_month is on or before the end of this payroll month
        //      (the normal case — EMI schedule starts at or before this month)
        //   B) The loan has at least one PAID installment in any prior month
        //      (catches loans whose first_emi_month was set to a future date but
        //       were manually processed earlier — e.g. LN-2026-xxxx with Apr 2025 EMI)
        //
        // NOTE: We intentionally do NOT skip already-paid installments here.
        // compute() is a VIEW function — it always shows the full deduction for the
        // requested month regardless of lock state.  The $alreadyPaid guard inside
        // lockSnapshot() prevents double-processing at lock time independently.
        $monthEndDate = $carbonMonth->copy()->endOfMonth()->toDateString();

        // IDs of loans that already have a paid installment on or before this month
        $loansWithPaidInstallments = LoanInstallment::where('status', 'paid')
            ->where(function ($q) use ($year, $mon) {
                $q->where('emi_year', '<', $year)
                    ->orWhere(function ($q2) use ($year, $mon) {
                        $q2->where('emi_year', $year)->where('emi_month', '<=', $mon);
                    });
            })
            ->pluck('employee_loan_id')
            ->unique()
            ->all();

        $activeLoansMap = EmployeeLoan::whereIn('employee_id', $empIds)
            ->where('status', 'active')
            ->where('outstanding_amount', '>', 0)
            ->where(function ($q) use ($monthEndDate, $loansWithPaidInstallments) {
                $q->where('first_emi_month', '<=', $monthEndDate);
                if (!empty($loansWithPaidInstallments)) {
                    $q->orWhereIn('id', $loansWithPaidInstallments);
                }
            })
            ->get()
            ->groupBy('employee_id');

        // Approved reimbursement claims for this month (sum of approved_amount on items)
        // reimbursement_claims stores year+month as separate integer columns
        $reimbursements = ReimbursementClaim::where('year', $year)
            ->where('month', $mon)
            ->whereIn('employee_id', $empIds)
            ->where('status', 'approved')
            ->with('items')
            ->get()
            ->groupBy('employee_id')
            ->map(fn($claims) => (float) $claims->flatMap(fn($c) => $c->items)->sum('approved_amount'));

        // LOP = only days where admin explicitly marked punch_in_approval = 'not_approved'
        // on the Attendance Report page. Leave Tracker records are informational only
        // and do NOT affect LOP or salary deductions.
        //
        // Exam Leave (EXL) is a deliberate, narrow exception to that rule — item #14
        // of the spec explicitly requires it to reduce pay and Net Paid Days for the
        // reporting month, unlike every other leave type.
        $notApprovedPunches = AttendanceApproval::where('punch_in_approval', 'not_approved')
            ->whereBetween('attendance_date', [
                Carbon::createFromDate($year, $mon, 1)->startOfMonth()->toDateString(),
                Carbon::createFromDate($year, $mon, 1)->endOfMonth()->toDateString(),
            ])
            ->whereIn('employee_id', $empIds)
            ->get()
            ->groupBy('employee_id')
            ->map(fn($g) => $g->count());

        $examLeaveDays = LeaveTrackerRecord::where('leave_type', 'EXL')
            ->where('is_active', true)
            ->where('is_approved', true)
            ->whereBetween('leave_date', [
                Carbon::createFromDate($year, $mon, 1)->startOfMonth()->toDateString(),
                Carbon::createFromDate($year, $mon, 1)->endOfMonth()->toDateString(),
            ])
            ->whereIn('employee_id', $empIds)
            ->get()
            ->groupBy('employee_id')
            ->map(fn($g) => $g->count());

        // Months that have a locked snapshot — attendance LOP in these months is
        // considered already processed, so we exclude them from carry-forward.
        // ── FY boundary for carry-forward queries ─────────────────────────
        // Carry-forward must NEVER cross a financial year boundary.
        // FY 2025-26 = Apr 2025 → Mar 2026. FY 2026-27 = Apr 2026 → Mar 2027.
        // Anything before $fyStart belongs to the previous FY — leave resets
        // on April 1 so prior-FY undeducted LOP is NOT the current FY's problem.
        //
        // $fyYear / $fyStart are also defined later (line ~498) for per-employee
        // leave calculations — computing them here first since they are needed for
        // these bulk carry-forward queries that run before the per-employee loop.
        $fyYear       = $mon >= 4 ? $year : $year - 1;
        $fyStart      = Carbon::createFromDate($fyYear, 4, 1)->toDateString();
        $fyMonthStart = sprintf('%d-04', $fyYear);  // e.g. '2026-04' for FY 2026-27

        // Only locked months WITHIN the current FY matter for carry-forward.
        $lockedPriorMonths = SalaryReportSnapshot::where('year_month', '<', $month)
            ->where('year_month', '>=', $fyMonthStart)  // current FY only
            ->distinct()
            ->pluck('year_month')
            ->all();

        // Carry-forward: not_approved attendance from prior UNLOCKED months in this FY.
        $carryForwardLop = AttendanceApproval::where('punch_in_approval', 'not_approved')
            ->where('attendance_date', '<', $monthStart)
            ->where('attendance_date', '>=', $fyStart)  // current FY only — no prior-FY bleed
            ->whereIn('employee_id', $empIds)
            ->get(['employee_id', 'attendance_date'])
            ->filter(fn($r) => !in_array(
                Carbon::parse($r->attendance_date)->format('Y-m'),
                $lockedPriorMonths
            ))
            ->groupBy('employee_id')
            ->map(fn($g) => $g->count());

        // Deferred LOP from LOCKED prior months IN THIS FY where admin reduced LOP via override.
        // Restricted to the current FY: leave balance resets on April 1 each year, so
        // undeducted LOP from FY 2025-26 must NOT appear in FY 2026-27 reports.
        $deferredLopFromSnapshots = !empty($lockedPriorMonths)
            ? SalaryReportSnapshot::where('year_month', '<', $month)
                ->where('year_month', '>=', $fyMonthStart)  // current FY only
                ->whereIn('employee_id', $empIds)
                ->get(['employee_id', 'snapshot_data'])
                ->groupBy('employee_id')
                ->map(function ($snaps) {
                    $originalLop = 0;
                    $deductedLop = 0;
                    foreach ($snaps as $snap) {
                        $d = $snap->snapshot_data;
                        $originalLop += (int)($d['att_lop']   ?? 0)
                                      + (int)($d['leave_lop'] ?? 0);
                        $deductedLop += (int)($d['total_lop'] ?? 0);
                    }
                    return max(0, $originalLop - $deductedLop);
                })
            : collect();

        // NOTE: Bonus For Diwali = CTC (Excel formula: each [CTC]).
        // Manual overrides are still supported via SalaryReportOverride.bonus_override.

        // Statutory details (UAN, PAN) keyed by employee_id
        $statutoryMap = EmployeeStatutoryDetail::whereIn('employee_id', $empIds)
            ->get()
            ->keyBy('employee_id');

        // Primary bank details (Account Number, IFSC) keyed by employee_id
        $bankMap = EmployeeBankDetail::whereIn('employee_id', $empIds)
            ->where('is_primary', true)
            ->get()
            ->keyBy('employee_id');

        // ── Financial year leave balance (FY = April 1 → March 31) ──────────
        // FY year / FY start — already set above for carry-forward queries; values are identical.
        // Re-assigning here is a no-op but keeps the per-employee block self-contained.
        $fyYear  = $mon >= 4 ? $year : $year - 1;
        $fyStart = Carbon::createFromDate($fyYear, 4, 1)->toDateString();       // "2025-04-01"
        $fyEnd   = $carbonMonth->copy()->endOfMonth()->toDateString();           // e.g. "2025-06-30"

        // Annual full-year allocations from leave_policies (defaults: CL=18, SL=7)
        // These are the 100% entitlements. Per-employee pro-rata is applied inside the loop.
        $clLtId           = LeaveType::where('code', 'CL')->value('id');
        $slLtId           = LeaveType::where('code', 'SL')->value('id');
        $clFullAllocation = (float) (LeavePolicy::where('leave_type_id', $clLtId)->where('is_active', true)->value('days_per_year') ?? 18);
        $slFullAllocation = (float) (LeavePolicy::where('leave_type_id', $slLtId)->where('is_active', true)->value('days_per_year') ?? 7);

        // FY boundary Carbon dates (reused per employee for pro-rata)
        $fyStartCarbon = Carbon::createFromDate($fyYear, 4, 1)->startOfDay();
        $fyEndCarbon   = Carbon::createFromDate($fyYear + 1, 3, 31)->startOfDay();

        // Load ALL active leave type codes by category so new types are picked up automatically.
        // Half-day types follow the 'H' prefix convention (HCL, HSL, HCO…) and count as 0.5.
        $ltByCategory = LeaveType::where('is_active', true)
            ->get(['code', 'category'])
            ->groupBy('category');

        $clCodes = $ltByCategory->get('casual',   collect())->pluck('code')->toArray(); // CL, HCL, PIM, POM, BDY…
        $slCodes = $ltByCategory->get('sick',     collect())->pluck('code')->toArray(); // SL, HSL…
        $coCodes = $ltByCategory->get('comp_off', collect())->pluck('code')->toArray(); // CO, HCO…
        $trackedLeaveCodes = array_merge($clCodes, $slCodes, $coCodes);

        \Log::info('DEBUG: Leave Type Categories', [
            'casual_codes' => $clCodes,
            'sick_codes' => $slCodes,
            'comp_off_codes' => $coCodes,
        ]);

        // Force write to file to verify code is executing
        file_put_contents(
            storage_path('logs/salary_report_debug.log'),
            "[" . now()->format('Y-m-d H:i:s') . "] COMPUTE STARTED - Leave codes: CL=" . json_encode($clCodes) . ", SL=" . json_encode($slCodes) . "\n",
            FILE_APPEND
        );

        // Count leave taken from April 1 of this FY through end of report month.
        $fyLeaveAvailed = LeaveTrackerRecord::whereIn('employee_id', $empIds)
            ->whereIn('leave_type', $trackedLeaveCodes)
            ->whereBetween('leave_date', [$fyStart, $fyEnd])
            ->where('is_active', true)
            ->get()
            ->groupBy('employee_id')
            ->map(function ($records) use ($clCodes, $slCodes, $coCodes) {
                $cl = $sl = $co = 0.0;
                foreach ($records as $r) {
                    $units = str_starts_with($r->leave_type, 'H') ? 0.5 : 1.0;
                    if (in_array($r->leave_type, $clCodes))      $cl += $units;
                    elseif (in_array($r->leave_type, $slCodes))  $sl += $units;
                    elseif (in_array($r->leave_type, $coCodes))  $co += $units;
                }
                return compact('cl', 'sl', 'co');
            });

        // Same leave query scoped to April 1 → previous month end (for the per-month LOP delta).
        // Returns an empty collection when $prevFyEnd is null (i.e. April is selected).
        $fyLeaveAvailedPrev = $prevFyEnd
            ? LeaveTrackerRecord::whereIn('employee_id', $empIds)
                ->whereIn('leave_type', $trackedLeaveCodes)
                ->whereBetween('leave_date', [$fyStart, $prevFyEnd])
                ->where('is_active', true)
                ->get()
                ->groupBy('employee_id')
                ->map(function ($records) use ($clCodes, $slCodes, $coCodes) {
                    $cl = $sl = $co = 0.0;
                    foreach ($records as $r) {
                        $units = str_starts_with($r->leave_type, 'H') ? 0.5 : 1.0;
                        if (in_array($r->leave_type, $clCodes))      $cl += $units;
                        elseif (in_array($r->leave_type, $slCodes))  $sl += $units;
                        elseif (in_array($r->leave_type, $coCodes))  $co += $units;
                    }
                    return compact('cl', 'sl', 'co');
                })
            : collect();

        // DEBUG: Log raw leave counts for specific employee
        \Log::info('DEBUG: Raw FY Leave Counts', [
            'employee_ids' => $empIds->toArray(),
            'cl_codes' => $clCodes,
            'sl_codes' => $slCodes,
            'co_codes' => $coCodes,
            'tracked_codes' => $trackedLeaveCodes,
            'fy_start' => $fyStart,
            'fy_end' => $fyEnd,
            'fy_leave_availed_sample' => $fyLeaveAvailed->take(5)->toArray(),
        ]);

        // Force write employee 73 leave data to file
        if ($fyLeaveAvailed->has(73)) {
            $emp73Leaves = $fyLeaveAvailed->get(73);
            $emp73LeavesPrev = $fyLeaveAvailedPrev->get(73) ?? ['cl' => 0, 'sl' => 0, 'co' => 0];
            file_put_contents(
                storage_path('logs/salary_report_debug.log'),
                "[" . now()->format('Y-m-d H:i:s') . "] EMPLOYEE 73 FOUND - FY Leaves: " . json_encode($emp73Leaves) . ", Prev: " . json_encode($emp73LeavesPrev) . "\n",
                FILE_APPEND
            );
            \Log::info('DEBUG: Employee 73 (Khushboo) FY Leaves', [
                'employee_id' => 73,
                'fy_leave_availed' => $emp73Leaves,
                'fy_leave_availed_prev' => $emp73LeavesPrev,
            ]);
        } else {
            file_put_contents(
                storage_path('logs/salary_report_debug.log'),
                "[" . now()->format('Y-m-d H:i:s') . "] EMPLOYEE 73 NOT FOUND in fyLeaveAvailed. Available keys: " . json_encode($fyLeaveAvailed->keys()->toArray()) . "\n",
                FILE_APPEND
            );
        }

        // Active manual overrides for this month — keyed by employee_id
        $overrides = SalaryReportOverride::where('year_month', $month)
            ->where('is_active', true)
            ->whereIn('employee_id', $empIds)
            ->get()
            ->keyBy('employee_id');

        $rows = [];
        foreach ($employees as $emp) {
            $sal = $emp->currentSalary;

            // Decrypt gross monthly from the model helper (plaintext col is always 0)
            $grossMonthly = $sal->getGrossDecrypted();

            if ($grossMonthly <= 0) continue;

            // Apply any active manual override for this employee + month
            $override = $overrides->get($emp->id);

            // ── DOJ / DOL — needed for both leave allocation and weekoff clipping ──
            $doj = $emp->date_of_joining ? Carbon::parse($emp->date_of_joining) : null;
            $dol = $emp->date_of_leaving  ? Carbon::parse($emp->date_of_leaving)  : null;

            // ── Leave allocation — full annual entitlement, no pro-rating ──────
            // Company policy: every employee receives the full annual CL and SL
            // allocation regardless of their joining date within the FY.
            // Pro-rating is NOT applied for new joiners.
            // Exception: employees who LEFT during this FY get a reduced allocation
            // proportional to their active period (they only earned leaves while employed).
            $clAllocation = $clFullAllocation;
            $slAllocation = $slFullAllocation;

            if ($dol && $dol->gte($fyStartCarbon) && $dol->lt($fyEndCarbon)) {
                $leaveStart    = ($doj && $doj->gt($fyStartCarbon)) ? $doj->copy()->startOfDay() : $fyStartCarbon->copy();
                $daysWorked    = max(1, (int) $leaveStart->diffInDays($dol->copy()->startOfDay()) + 1);
                $proRataFactor = min(1.0, $daysWorked / 365);
                $clAllocation  = round($clFullAllocation * $proRataFactor, 1);
                $slAllocation  = round($slFullAllocation * $proRataFactor, 1);
            }

            // ── FY-to-date leave balances (April 1 → end of selected month) ──
            $fyLeaves  = $fyLeaveAvailed[$emp->id] ?? ['cl' => 0.0, 'sl' => 0.0, 'co' => 0.0];
            $clAvailed = (float) $fyLeaves['cl'];
            $slAvailed = (float) $fyLeaves['sl'];
            $coAvailed = (float) $fyLeaves['co'];

            // SL exhaustion → spills into CL, then CL excess → CO, remainder → LOP
            $slExcess        = max(0.0, $slAvailed - $slAllocation);
            $effectiveCLUsed = $clAvailed + $slExcess;
            $clExcess          = max(0.0, $effectiveCLUsed - $clAllocation);
            $coUsedForClExcess = min($coAvailed, $clExcess);
            $coLop             = max(0.0, $clExcess - $coAvailed);

            $closingCL = round(max(0.0, $clAllocation - $effectiveCLUsed), 1);
            $closingSL = round(max(0.0, $slAllocation - $slAvailed), 1);
            $closingCO = round(max(0.0, $coAvailed - $coUsedForClExcess), 1);

            // Cumulative leave-based LOP from April 1 to end of selected month
            $lopLeave = (int) max(0.0, $clExcess - $coAvailed);

            // DEBUG: Log leave calculations for specific employees
            if ($emp->employee_id === '202421013') {
                file_put_contents(
                    storage_path('logs/salary_report_debug.log'),
                    "[" . now()->format('Y-m-d H:i:s') . "] CASCADE CALC - EmpID: {$emp->employee_id}, CL: {$clAvailed}/{$clAllocation}, SL: {$slAvailed}/{$slAllocation}, Excess: SL={$slExcess}, EffCL={$effectiveCLUsed}, CLExcess={$clExcess}, LOP={$lopLeave}\n",
                    FILE_APPEND
                );
                \Log::info('DEBUG: Leave Cascade Calculation', [
                    'employee_id' => $emp->employee_id,
                    'employee_name' => $emp->full_name,
                    'month' => $monthLabel,
                    'cl_availed' => $clAvailed,
                    'sl_availed' => $slAvailed,
                    'co_availed' => $coAvailed,
                    'cl_allocation' => $clAllocation,
                    'sl_allocation' => $slAllocation,
                    'sl_excess' => $slExcess,
                    'effective_cl_used' => $effectiveCLUsed,
                    'cl_excess' => $clExcess,
                    'lop_leave' => $lopLeave,
                ]);
            }

            // ── Leave-LOP delta: only the NEW leave-LOP days this specific month ──
            // Compare cumulative LOP through this month vs. through previous month.
            // The difference is what actually became LOP in the current month alone.
            $fyLeavesPrev  = $fyLeaveAvailedPrev[$emp->id] ?? ['cl' => 0.0, 'sl' => 0.0, 'co' => 0.0];
            $clAvailedPrev = (float) $fyLeavesPrev['cl'];
            $slAvailedPrev = (float) $fyLeavesPrev['sl'];
            $coAvailedPrev = (float) $fyLeavesPrev['co'];
            $slExcessPrev        = max(0.0, $slAvailedPrev - $slAllocation);
            $effectiveCLUsedPrev = $clAvailedPrev + $slExcessPrev;
            $clExcessPrev        = max(0.0, $effectiveCLUsedPrev - $clAllocation);
            $lopLeavePrev        = (int) max(0.0, $clExcessPrev - $coAvailedPrev);

            $lopLeaveThisMonth = max(0, $lopLeave - $lopLeavePrev);

            // DEBUG: Log delta calculation for specific employees
            if ($emp->employee_id === '202421013') {
                \Log::info('DEBUG: Leave LOP Delta Calculation', [
                    'employee_id' => $emp->employee_id,
                    'month' => $monthLabel,
                    'lop_leave_cumulative' => $lopLeave,
                    'lop_leave_prev_month' => $lopLeavePrev,
                    'lop_leave_this_month_delta' => $lopLeaveThisMonth,
                ]);
            }

            // ── Attendance LOP (this month's not-approved punches only) ──────
            $lopAtt = (int) ($notApprovedPunches[$emp->id] ?? 0);

            // ── Exam Leave LOP (this month's approved Exam Leave days) ────────
            $examLeaveLop = (int) ($examLeaveDays[$emp->id] ?? 0);

            // ── Carry-forward LOP ─────────────────────────────────────────────
            // Two sources (mutually exclusive by design, summed together):
            //   1. Attendance not-approved from prior months that were NEVER locked
            //   2. Deferred LOP from locked months where admin set override < computed
            $carryFwdLop = (int) ($carryForwardLop[$emp->id]          ?? 0)
                         + (int) ($deferredLopFromSnapshots[$emp->id] ?? 0);

            // Total computed LOP = attendance (this month) + leave delta + carry-forward + Exam Leave
            $computedLop = $lopAtt + $lopLeaveThisMonth + $carryFwdLop + $examLeaveLop;

            // DEBUG: Log total LOP for specific employees
            if ($emp->employee_id === '202421013') {
                \Log::info('DEBUG: Total LOP Calculation', [
                    'employee_id' => $emp->employee_id,
                    'month' => $monthLabel,
                    'attendance_lop' => $lopAtt,
                    'leave_lop_delta' => $lopLeaveThisMonth,
                    'carry_forward_lop' => $carryFwdLop,
                    'computed_lop' => $computedLop,
                    'has_override' => $override !== null,
                ]);
            }

            $empLop = $override && $override->lop_override !== null
                ? (int) $override->lop_override
                : $computedLop;

            // ── Per-employee WeekOff & Holidays (uses this employee's entity's calendar)
            //   • Joined mid-month → count holidays ON OR AFTER date_of_joining
            //   • Left mid-month   → count holidays ON OR BEFORE date_of_leaving
            //   • Both apply simultaneously when joining and leaving are in the same month
            //   Excel: Working Days = Month Days − WeekOff&Holidays
            //   Excel: Net Paid Days = Working Days − Total LOP + WeekOff&Holidays
            // Falls back to month-level defaults (which always include Sundays)
            $empHolidayDates       = $holidaysByCompany[$emp->company_id] ?? $monthHolidayDates;
            $empWeekoffAndHolidays = count($empHolidayDates);

            // Only clip if DOJ or DOL falls within this payroll month
            $dojInMonth = $doj && (int)$doj->format('Y') === $year && (int)$doj->format('n') === $mon && $doj->day > 1;
            $dolInMonth = $dol && (int)$dol->format('Y') === $year && (int)$dol->format('n') === $mon;

            // ── Effective month days for this employee ────────────────────────
            // Full month by default. Reduced when the employee joined or left
            // mid-month so that working-days and net-paid-days are proportional
            // to the actual days they were on payroll.
            //
            //   DOJ on Jan 22 in a 31-day month → empMonthDays = 31 - 22 + 1 = 10
            //   DOL on Jan 15 in a 31-day month → empMonthDays = 15 - 1 + 1  = 15
            //   Both in same month              → empMonthDays = DOL - DOJ + 1
            //
            // NOTE: $monthDays (full month) is still used as the salary divisor
            // in Payable Gross because the gross is a monthly rate, not a daily rate.
            $empMonthDays = $monthDays;

            if ($dojInMonth && $dolInMonth) {
                // Joined AND left within the same month
                $empMonthDays = max(1, $dol->day - $doj->day + 1);
            } elseif ($dojInMonth) {
                // Joined mid-month — count from DOJ to last day
                $empMonthDays = max(1, $monthDays - $doj->day + 1);
            } elseif ($dolInMonth) {
                // Left mid-month — count from first day to DOL
                $empMonthDays = max(1, $dol->day);
            }

            if ($dojInMonth || $dolInMonth) {
                $joiningDateStr = $dojInMonth ? $doj->format('Y-m-d') : null;
                $leavingDateStr = $dolInMonth ? $dol->format('Y-m-d') : null;
                $empWeekoffAndHolidays = count(array_filter(
                    $empHolidayDates,
                    fn($d) => ($joiningDateStr === null || $d >= $joiningDateStr)
                        && ($leavingDateStr === null || $d <= $leavingDateStr)
                ));
            }

            // Working Days = Employee's active days − WeekOff&Holidays in that period
            $empWorkingDays = max(0, $empMonthDays - $empWeekoffAndHolidays);

            // Net Paid Days = Working Days − LOP + WeekOff&Holidays
            $netPaidDays = max(0, $empWorkingDays - $empLop + $empWeekoffAndHolidays);

            // ── Excel formula: Payable Gross = Round((Gross / Month Days) × NPD, 0)
            $computedPayableGross = (int) round(($grossMonthly / $monthDays) * $netPaidDays);
            $payableGross = $override && $override->payable_gross_override !== null
                ? (int) $override->payable_gross_override
                : $computedPayableGross;

            // Explicit LOP deduction amount = per-day rate × LOP days
            // This is already embedded in payable_gross (via reduced net_paid_days),
            // but we surface it as a named field so the report makes the impact visible.
            $lopAmount = (int) round(($grossMonthly / $monthDays) * $empLop);

            // ── Excel formula: Basic = Round(Payable Gross × 0.70, 0)
            //                  HRA   = Round(Basic × 0.40, 0)   [40% of Basic = 28% of Gross]
            //                  Special = Round(Payable Gross − Basic − HRA, 0)
            $basic            = (int) round($payableGross * 0.70);
            $hra              = (int) round($basic * 0.40);
            $specialAllowance = $payableGross - $basic - $hra;   // exact remainder, ~2% of Gross

            // ── Excel formula: PF = min(1800, Round((Payable Gross − HRA) × 0.12, 0))
            //   Non-PF employees get 0; EPF wage-ceiling caps at ₹1,800
            $pfApplicable = (bool) ($emp->is_pf_applicable ?? true);
            $salForPf     = $payableGross - $hra;                 // Basic + Special ≈ 72% of Gross
            $pfCalc       = (int) round($salForPf * 0.12);
            $empPf        = $pfApplicable ? min(1800, $pfCalc) : 0;
            $emplPf       = $empPf;                               // Employer PF mirrors Employee PF

            // ── Excel formula: PT = if Gross Salary > 12000 then 200 else 0
            $pt = $grossMonthly > 12000 ? 200 : 0;

            $incentive = $override && $override->incentive_override !== null
                ? (float) $override->incentive_override
                : (float) ($incentives->get($emp->id) ?? 0);
            $tds = $override && $override->tds_override !== null
                ? (float) $override->tds_override
                : (float) ($monthlyTds->get($emp->id) ?? 0);
            // ── Loan deduction (dynamic: percent-based or fixed EMI) ─────────
            $computedAdvance = 0.0;
            foreach ($activeLoansMap->get($emp->id, collect()) as $loan) {
                // Percent-based: deduct percentage of payable gross
                $ded = $loan->percent_deduction > 0
                    ? $payableGross * ((float) $loan->percent_deduction / 100)
                    : (float) $loan->emi_amount;
                // Cap at remaining outstanding
                $computedAdvance += min($ded, (float) $loan->outstanding_amount);
            }
            $advance = $override && $override->advance_override !== null
                ? (float) $override->advance_override
                : $computedAdvance;
            $reimbursement = (float) ($reimbursements->get($emp->id) ?? 0);

            $totalDeduction = $empPf + $pt + $advance + $tds;
            // Net Pay = Payable Gross + Incentive + Reimbursement − Total Deductions
            $netPay = (int) round($payableGross + $incentive + $reimbursement - $totalDeduction);
            $ctc            = (int) round($grossMonthly + $emplPf);

            // ── Excel formula: Bonus For Diwali = [CTC]  (manual override takes priority)
            $bonus = $override && $override->bonus_override !== null
                ? (float) $override->bonus_override
                : (float) $ctc;

            // ── Excel formula: CTC with Diwali = Round((Bonus + CTC × 12) / 12, 0)
            //   = Round(CTC × 13/12, 0)
            $ctcWithBonus = (int) round(($bonus + $ctc * 12) / 12);

            // Supplementary data for additional report columns
            $statutory = $statutoryMap->get($emp->id);
            $bank      = $bankMap->get($emp->id);

            $rows[] = [
                // ── Override metadata (for edit UI) ──────────────────────────
                'employee_db_id'    => $emp->id,
                'has_override'      => $override !== null,
                'override_id'       => $override?->id,
                // Computed base values (before override — shown as "original" in edit modal)
                'computed_lop'           => $computedLop,
                'computed_incentive'     => (float) ($incentives->get($emp->id) ?? 0),
                'computed_advance'       => $computedAdvance,
                'computed_tds'           => (float) ($monthlyTds->get($emp->id) ?? 0),
                'computed_payable_gross' => $computedPayableGross,

                'emp_id'            => $emp->employee_id,
                'name'              => trim($emp->full_name),
                'designation'       => $emp->designation?->name ?? '—',
                'gross_salary'      => $grossMonthly,
                'month'             => $monthLabel,
                'month_days'        => $empMonthDays,   // actual days on payroll (< full month for mid-month joiners/leavers)
                'weekoff_holidays'  => $empWeekoffAndHolidays,
                'working_days'      => $empWorkingDays,
                'total_lop'         => $empLop,
                'lop_amount'        => $lopAmount,
                'net_paid_days'     => $netPaidDays,
                'payable_gross'     => $payableGross,
                'basic_pay'         => $basic,
                'hra'               => $hra,
                'special_allowance' => $specialAllowance,
                'incentive'         => $incentive,
                'reimbursement'     => $reimbursement,
                'employee_pf'       => $empPf,
                'professional_tax'  => $pt,
                'advance_salary'    => $advance,
                'tds'               => $tds,
                'total_deduction'   => $totalDeduction,
                'net_pay'           => $netPay,
                'employer_pf'       => $emplPf,
                'ctc'               => $ctc,
                'bonus_diwali'      => $bonus,
                'ctc_with_diwali'   => $ctcWithBonus,
                'firm'              => $emp->entity?->name ?? '—',
                'company_id'        => $emp->company_id,
                'pf_applicability'  => $pfApplicable ? 'Yes' : 'No',
                // ── Additional columns ───────────────────────────────────────
                'uan_number'        => $pfApplicable ? ($statutory?->uan_number ?? '') : '-',
                'closing_cl'        => $closingCL,
                'closing_sl'        => $closingSL,
                'current_cp'        => $closingCO,
                'current_lop'       => $lopLeave,        // cumulative FY leave-LOP (informational)
                'att_lop'           => $lopAtt,           // attendance LOP this month
                'leave_lop'         => $lopLeaveThisMonth, // leave-exhaustion LOP this month (delta)
                'carry_fwd_lop'     => $carryFwdLop,      // undeducted LOP from prior unlocked months
                'account_number'    => $bank?->account_number ?? '',
                'ifsc_code'         => $bank?->ifsc_code ?? '',
                'pan_number'        => $statutory?->pan_number ?? '',
                'rsc_mail_id'       => $emp->official_email ?? '',
            ];
        }

        return $rows;
    }

    // ─────────────────────────────────────────────
    // SALARY OVERRIDE — save / version editable fields
    // ─────────────────────────────────────────────

    public function updateOverride(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('payroll_override');

        $validated = $request->validate([
            'employee_id'  => 'required|exists:employees,id',
            'year_month'   => 'required|date_format:Y-m',
            'gross_salary' => 'nullable|numeric|min:1000',
            'lop'          => 'nullable|integer|min:0|max:31',
            'incentive'    => 'nullable|numeric|min:0',
            'tds'          => 'nullable|numeric|min:0',
            'notes'        => 'nullable|string|max:500',
            'orig_lop'       => 'nullable|integer',
            'orig_incentive' => 'nullable|numeric',
            'orig_tds'       => 'nullable|numeric',
        ]);

        // Deactivate any existing active override for this employee + month
        $existing = SalaryReportOverride::where('employee_id', $validated['employee_id'])
            ->where('year_month', $validated['year_month'])
            ->where('is_active', true)
            ->first();

        $newRecord = SalaryReportOverride::create([
            'employee_id'        => $validated['employee_id'],
            'year_month'         => $validated['year_month'],
            'original_lop'       => $validated['orig_lop']       ?? $existing?->original_lop,
            'original_incentive' => $validated['orig_incentive'] ?? $existing?->original_incentive,
            'original_tds'       => $validated['orig_tds']       ?? $existing?->original_tds,
            'lop_override'       => $validated['lop']       !== null ? (int)   $validated['lop']       : null,
            'incentive_override' => $validated['incentive'] !== null ? (float) $validated['incentive'] : null,
            'tds_override'       => $validated['tds']       !== null ? (float) $validated['tds']       : null,
            'notes'          => $validated['notes'] ?? null,
            'is_active'      => true,
            'superseded_by_id' => null,
            'created_by'     => auth()->id(),
        ]);

        if ($existing) {
            $existing->update([
                'is_active'        => false,
                'superseded_by_id' => $newRecord->id,
            ]);
        }

        $month = $validated['year_month'];

        // ── Gross salary revision ──────────────────────────────────────────────
        // If a new gross_salary was provided, deactivate ALL current salary records
        // and insert a new one with the updated gross — effective from this month.
        if (!empty($validated['gross_salary'])) {
            $newGross      = (float) $validated['gross_salary'];
            $effectiveFrom = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();
            $effectiveTo   = Carbon::parse($effectiveFrom)->subDay()->toDateString();

            // Load the most-recent active salary for reference data (ctc_enc values, structure id)
            $currentSalary = \App\Models\EmployeeSalary::where('employee_id', $validated['employee_id'])
                ->where('is_current', true)
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->first();

            if ($currentSalary && $newGross !== $currentSalary->getGrossDecrypted()) {
                \Illuminate\Support\Facades\DB::transaction(function () use (
                    $validated,
                    $newGross,
                    $effectiveFrom,
                    $effectiveTo,
                    $currentSalary
                ) {
                    // Query-builder update: deactivates ALL is_current=1 rows for this employee
                    // (same pattern as WebSalaryController, SalaryImporter, RevisionController)
                    \App\Models\EmployeeSalary::where('employee_id', $validated['employee_id'])
                        ->where('is_current', true)
                        ->update([
                            'is_current'   => false,
                            'effective_to' => $effectiveTo,
                        ]);

                    // Create the new active record
                    \App\Models\EmployeeSalary::create([
                        'employee_id'         => $currentSalary->employee_id,
                        'salary_structure_id' => $currentSalary->salary_structure_id,
                        'ctc_annual'          => 0,
                        'ctc_monthly'         => 0,
                        'gross_monthly'       => 0,
                        'gross_monthly_enc'   => Crypt::encrypt((string) $newGross),
                        'ctc_annual_enc'      => $currentSalary->ctc_annual_enc,
                        'ctc_monthly_enc'     => $currentSalary->ctc_monthly_enc,
                        'net_monthly'         => 0,
                        'effective_from'      => $effectiveFrom,
                        'effective_to'        => null,
                        'is_current'          => true,
                        'revision_type'       => 'correction',
                        'remarks'             => $validated['notes'] ?? 'Updated from salary report',
                        'created_by'          => auth()->id(),
                    ]);
                });
            }
        }

        $isMonthLocked = SalaryReportSnapshot::where('year_month', $month)->exists();
        $successMsg    = $isMonthLocked
            ? 'Override saved. Re-lock the report to update the saved snapshot with these changes.'
            : 'Salary override saved.';

        return redirect()->route('salary.report', ['month' => $month])
            ->with('success', $successMsg);
    }

    // ─────────────────────────────────────────────
    // SNAPSHOT — lock / unlock
    // ─────────────────────────────────────────────

    public function lockSnapshot(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('payroll_lock');

        $request->validate([
            'month'      => 'required|date_format:Y-m',
            'company_id' => 'nullable|integer',
        ]);

        $month     = $request->get('month');
        $companyId = (int) ($request->get('company_id', 0));

        $rows = $this->compute($month, $companyId);

        if (empty($rows)) {
            return redirect()->back()->with('error', 'No employee data found for this month. Cannot lock an empty report.');
        }

        $this->performLock($month, $rows);

        $count = count($rows);
        $queued = $this->autoSendPayslipsOnLock($month);

        $message = "Report locked ✓ — {$count} employee rows frozen. Loan deductions recorded.";
        $message .= $queued > 0
            ? " {$queued} payslip email(s) queued — sending in the background, check back shortly."
            : ' No new payslip emails to send.';

        return redirect()->route('salary.report', ['month' => $month, 'company_id' => $companyId])
            ->with('success', $message);
    }

    // ─────────────────────────────────────────────
    // SHARED LOCK LOGIC — called by both index() auto-lock and lockSnapshot()
    // ─────────────────────────────────────────────

    private function performLock(string $month, array $rows): void
    {
        $now    = now();
        $userId = auth()->id();

        [$lockYear, $lockMon] = array_map('intval', explode('-', $month));
        $carbonLock = Carbon::createFromFormat('Y-m', $month);

        // ── Process loan/advance deductions FIRST ───────────────────────────
        // Snapshots are saved AFTER loan processing so that the frozen
        // advance_salary reflects what was actually deducted — even on a re-lock
        // where compute() skips already-paid installments and returns advance = 0.
        DB::beginTransaction();
        try {
            foreach ($rows as $row) {
                $empId   = $row['employee_db_id'] ?? null;
                $advance = (float) ($row['advance_salary'] ?? 0);
                if (!$empId || $advance <= 0) continue;

                $lockMonthEnd = $carbonLock->copy()->endOfMonth()->toDateString();
                $priorPaidIds = LoanInstallment::where('employee_id', $empId)
                    ->where('status', 'paid')
                    ->where(function ($q) use ($lockYear, $lockMon) {
                        $q->where('emi_year', '<', $lockYear)
                            ->orWhere(function ($q2) use ($lockYear, $lockMon) {
                                $q2->where('emi_year', $lockYear)->where('emi_month', '<', $lockMon);
                            });
                    })
                    ->pluck('employee_loan_id')
                    ->unique()
                    ->all();

                $empLoans = EmployeeLoan::where('employee_id', $empId)
                    ->where('status', 'active')
                    ->where('outstanding_amount', '>', 0)
                    ->where(function ($q) use ($lockMonthEnd, $priorPaidIds) {
                        $q->where('first_emi_month', '<=', $lockMonthEnd);
                        if (!empty($priorPaidIds)) {
                            $q->orWhereIn('id', $priorPaidIds);
                        }
                    })
                    ->orderBy('id')
                    ->get();

                $payableGross = (float) ($row['payable_gross'] ?? 0);
                $remaining    = $advance;

                foreach ($empLoans as $loan) {
                    if ($remaining <= 0) break;

                    // Skip loans already marked paid this month (idempotent re-lock)
                    $alreadyPaid = LoanInstallment::where('employee_loan_id', $loan->id)
                        ->where('emi_year', $lockYear)
                        ->where('emi_month', $lockMon)
                        ->where('status', 'paid')
                        ->exists();
                    if ($alreadyPaid) continue;

                    $ded = $loan->percent_deduction > 0
                        ? round($payableGross * ((float) $loan->percent_deduction / 100), 2)
                        : (float) $loan->emi_amount;
                    $ded = min($ded, (float) $loan->outstanding_amount, $remaining);
                    if ($ded <= 0) continue;

                    $outBefore = (float) $loan->outstanding_amount;
                    $outAfter  = max(0.0, round($outBefore - $ded, 2));

                    $existing = LoanInstallment::where('employee_loan_id', $loan->id)
                        ->where('emi_year', $lockYear)
                        ->where('emi_month', $lockMon)
                        ->first();

                    if ($existing) {
                        $existing->update([
                            'total_emi_amount'   => round($ded, 2),
                            'principal_amount'   => round($ded, 2),
                            'interest_amount'    => 0,
                            'outstanding_before' => $outBefore,
                            'outstanding_after'  => $outAfter,
                            'status'             => 'paid',
                        ]);
                    } else {
                        $nextNum = (LoanInstallment::where('employee_loan_id', $loan->id)->max('installment_number') ?? 0) + 1;
                        LoanInstallment::create([
                            'employee_loan_id'   => $loan->id,
                            'employee_id'        => $empId,
                            'installment_number' => $nextNum,
                            'emi_year'           => $lockYear,
                            'emi_month'          => $lockMon,
                            'principal_amount'   => round($ded, 2),
                            'interest_amount'    => 0,
                            'total_emi_amount'   => round($ded, 2),
                            'outstanding_before' => $outBefore,
                            'outstanding_after'  => $outAfter,
                            'status'             => 'paid',
                        ]);
                    }

                    $loan->update([
                        'outstanding_amount' => $outAfter,
                        'status'             => $outAfter <= 0 ? 'closed' : 'active',
                    ]);

                    $remaining -= $ded;
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        // ── Read the ground-truth advance amounts from paid installments ─────
        $empIdsInScope   = array_values(array_filter(array_column($rows, 'employee_db_id')));
        $paidInstAmounts = LoanInstallment::where('emi_year', $lockYear)
            ->where('emi_month', $lockMon)
            ->where('status', 'paid')
            ->whereIn('employee_id', $empIdsInScope)
            ->get()
            ->groupBy('employee_id')
            ->map(fn($g) => (float) $g->sum('total_emi_amount'));

        // ── Save snapshots with corrected figures ────────────────────────────
        foreach ($rows as $row) {
            $empId = $row['employee_db_id'] ?? null;
            if (!$empId) continue;

            $row['company_id'] = $row['company_id'] ?? 0;

            if ($paidInstAmounts->has($empId)) {
                $trueAdvance            = (float) $paidInstAmounts->get($empId);
                $row['advance_salary']  = $trueAdvance;
                $row['total_deduction'] = (float)($row['employee_pf']      ?? 0)
                    + (float)($row['professional_tax'] ?? 0)
                    + $trueAdvance
                    + (float)($row['tds']              ?? 0);
                $row['net_pay'] = (int) round(
                    (float)($row['payable_gross']   ?? 0)
                        + (float)($row['incentive']     ?? 0)
                        + (float)($row['reimbursement'] ?? 0)
                        - $row['total_deduction']
                );
            }

            SalaryReportSnapshot::updateOrCreate(
                ['employee_id' => $empId, 'year_month' => $month],
                ['snapshot_data' => $row, 'locked_at' => $now, 'locked_by' => $userId]
            );
        }
    }

    public function unlockSnapshot(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('payroll_lock');

        $request->validate(['month' => 'required|date_format:Y-m']);

        $month    = $request->get('month');
        $companyId = (int) ($request->get('company_id', 0));

        // ── Reverse loan deductions for the unlocked month ──
        DB::beginTransaction();
        try {
            [$unlockYear, $unlockMon] = array_map('intval', explode('-', $month));

            $paidInstallments = LoanInstallment::where('emi_year', $unlockYear)
                ->where('emi_month', $unlockMon)
                ->where('status', 'paid')
                ->with('employeeLoan')
                ->get();

            foreach ($paidInstallments as $inst) {
                $loan = $inst->employeeLoan;
                if (!$loan) continue;

                // Restore the outstanding amount
                $restored = round((float) $loan->outstanding_amount + (float) $inst->total_emi_amount, 2);
                $loan->update([
                    'outstanding_amount' => $restored,
                    'status'             => 'active',
                ]);

                // Set installment back to pending (keeps the schedule record intact)
                $inst->update(['status' => 'pending']);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $deleted = SalaryReportSnapshot::where('year_month', $month)->delete();

        return redirect()->route('salary.report', ['month' => $month, 'company_id' => $companyId])
            ->with('success', "Report unlocked — {$deleted} snapshots removed. Loan deductions reversed. Report will now be computed live from current source data.");
    }

    // Delete snapshot entirely — stays on the Saved Reports page (no redirect to working report)
    public function deleteSnapshot(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('payroll_edit');

        $request->validate(['month' => 'required|date_format:Y-m']);

        $month   = $request->get('month');
        $label   = \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y');
        $deleted = SalaryReportSnapshot::where('year_month', $month)->delete();

        return redirect()->route('salary.report.saved')
            ->with('success', "Snapshot for {$label} deleted — {$deleted} employee records removed.");
    }

    // ─────────────────────────────────────────────
    // RE-UPLOAD EDITED EXCEL → REPLACE SNAPSHOT
    // ─────────────────────────────────────────────

    public function reuploadSnapshot(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('payroll_edit');

        $request->validate([
            'file'           => 'required|file|mimes:xlsx,xls|max:20480',
            'expected_month' => 'nullable|date_format:Y-m',
        ]);

        // The form passes the month the user is currently viewing.
        // We'll use this to cross-check the file's Month column.
        $expectedMonth = $request->input('expected_month');

        $path = $request->file('file')->store('tmp');

        // ── Read the Excel cells safely ────────────────────────────────────
        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile(
                \Illuminate\Support\Facades\Storage::path($path)
            );
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load(\Illuminate\Support\Facades\Storage::path($path));
            $ws          = $spreadsheet->getActiveSheet();

            $data = $ws->toArray(null, false, false, false); // 0-indexed rows and cols
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Storage::delete($path);
            return back()->with('error', 'Could not read the file: ' . $e->getMessage());
        } finally {
            \Illuminate\Support\Facades\Storage::delete($path);
        }

        if (count($data) < 2) {
            return back()->with('error', 'The uploaded file is empty or has only a header row.');
        }

        // ── Map header text → column index (0-based) ──────────────────────
        // Matches the exact headers written by buildExcel().
        // The 'LOP' column (col 35) is a duplicate of 'Total LOP' — skipped.
        $headerMap = [
            'Emp ID'                => 'emp_id',
            'Employee'              => 'name',
            'Designation'           => 'designation',
            'Gross Salary'          => 'gross_salary',
            'Month'                 => 'month',
            'Month Days'            => 'month_days',
            'WeekOff & Holidays'    => 'weekoff_holidays',
            'Working Days'          => 'working_days',
            'Total LOP'             => 'total_lop',
            'LOP Amount'            => 'lop_amount',
            'Net Paid Days'         => 'net_paid_days',
            'Payable Gross'         => 'payable_gross',
            'Basic Pay'             => 'basic_pay',
            'HRA'                   => 'hra',
            'Special Allowance'     => 'special_allowance',
            'Incentive'             => 'incentive',
            'Reimbursement'         => 'reimbursement',
            'Employee PF'           => 'employee_pf',
            'Professional Tax'      => 'professional_tax',
            'Advance Salary'        => 'advance_salary',
            'TDS'                   => 'tds',
            'Total Deduction'       => 'total_deduction',
            'Net Pay'               => 'net_pay',
            'Employer PF'           => 'employer_pf',
            'CTC'                   => 'ctc',
            'Bonus For Diwali'      => 'bonus_diwali',
            'CTC with Diwali Bonus' => 'ctc_with_diwali',
            'Firm'                  => 'firm',
            'PF Applicability'      => 'pf_applicability',
            'UAN Number'            => 'uan_number',
            'Closing CL'            => 'closing_cl',
            'Closing SL'            => 'closing_sl',
            'Current CP'            => 'current_cp',
            'Current LOP'           => 'current_lop',
            'Att LOP'               => 'att_lop',
            // 'LOP' at col 35 is a duplicate — intentionally not mapped
            'Account Number'        => 'account_number',
            'IFSC Code'             => 'ifsc_code',
            'PAN'                   => 'pan_number',
            'RSC Mail ID'           => 'rsc_mail_id',
        ];

        // Build col-index → snapshot-key map from the actual header row
        $colToKey   = [];
        $seenLop    = false;
        foreach ($data[0] as $colIdx => $headerVal) {
            $h = trim((string) $headerVal);
            if ($h === 'LOP') {
                // This is the duplicate col — skip it completely
                continue;
            }
            if (isset($headerMap[$h])) {
                // Guard against any accidental duplicate headers
                if ($headerMap[$h] === 'total_lop' && $seenLop) continue;
                if ($headerMap[$h] === 'total_lop') $seenLop = true;
                $colToKey[$colIdx] = $headerMap[$h];
            }
        }

        if (empty($colToKey)) {
            return back()->with('error', 'Header row not recognised. Please upload the file that was downloaded from the Salary Report.');
        }

        // ── Detect month-year from the first data row ──────────────────────
        $monthColIdx = array_search('month', $colToKey);
        $monthRaw    = ($monthColIdx !== false) ? ($data[1][$monthColIdx] ?? null) : null;
        $yearMonth   = null;

        if ($monthRaw !== null && $monthRaw !== '') {
            // Could be: Excel serial (float), "Feb 2026" string, or DateTime
            if ($monthRaw instanceof \DateTimeInterface) {
                $yearMonth = Carbon::instance($monthRaw)->format('Y-m');
            } elseif (is_numeric($monthRaw)) {
                try {
                    $yearMonth = Carbon::instance(
                        \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $monthRaw)
                    )->format('Y-m');
                } catch (\Throwable) {}
            } else {
                foreach (['M Y', 'F Y', 'Y-m', 'Y-m-d'] as $fmt) {
                    try {
                        $yearMonth = Carbon::createFromFormat($fmt, trim($monthRaw))->format('Y-m');
                        break;
                    } catch (\Throwable) {}
                }
                if (!$yearMonth) {
                    try { $yearMonth = Carbon::parse($monthRaw)->format('Y-m'); } catch (\Throwable) {}
                }
            }
        }

        if (!$yearMonth || !preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
            return back()->with('error', 'Could not determine the month-year from the uploaded file. Ensure the "Month" column is intact.');
        }

        // ── Month cross-check — ensure the file is for the expected month ────
        // The form passes the month the user is currently viewing so we can
        // catch an accidental wrong-file upload before touching any data.
        if ($expectedMonth && $yearMonth !== $expectedMonth) {
            $fileLabel     = Carbon::createFromFormat('Y-m', $yearMonth)->format('F Y');
            $expectedLabel = Carbon::createFromFormat('Y-m', $expectedMonth)->format('F Y');
            return back()->with('error',
                "Month mismatch — the uploaded file is for {$fileLabel} but you are viewing the "
                . "{$expectedLabel} report. Please upload the correct file."
            );
        }

        // ── Validation: snapshot must already exist ────────────────────────
        $existingSnapshots = SalaryReportSnapshot::where('year_month', $yearMonth)
            ->get()
            ->keyBy('employee_id');

        if ($existingSnapshots->isEmpty()) {
            $label = Carbon::createFromFormat('Y-m', $yearMonth)->format('F Y');
            return back()->with('error',
                "No saved/locked report exists for {$label}. "
                . "Re-upload is only allowed after the report has been saved at least once. "
                . "Generate and save the report first."
            );
        }

        // Build emp_id string → snapshot record lookup
        $empIdToSnap = [];
        foreach ($existingSnapshots as $snap) {
            $eid = $snap->snapshot_data['emp_id'] ?? null;
            if ($eid) $empIdToSnap[(string) $eid] = $snap;
        }

        // ── Only these columns are accepted from the uploaded Excel ──────────
        // All other columns are ignored — the snapshot keeps its existing values.
        //
        // Computation columns → trigger full recompute of all derived fields:
        //   payable_gross, incentive, advance_salary, tds, total_lop
        //
        // Employee master columns → also update the respective DB tables:
        //   account_number, ifsc_code  → employee_bank_details
        //   pan_number                 → employee_statutory_details
        //   rsc_mail_id                → employees.official_email
        $computationColumns = ['payable_gross', 'incentive', 'advance_salary', 'tds', 'total_lop'];
        $masterColumns      = ['account_number', 'ifsc_code', 'pan_number', 'rsc_mail_id'];
        $allowedColumns     = array_merge($computationColumns, $masterColumns);

        // ── Process rows ───────────────────────────────────────────────────
        $empIdColIdx = array_search('emp_id', $colToKey);
        $updated = 0;
        $skipped = 0;
        $now     = now();
        $userId  = auth()->id();

        DB::beginTransaction();
        try {
            foreach (array_slice($data, 1) as $row) {
                if (empty(array_filter($row, fn($v) => $v !== null && $v !== ''))) continue;

                $empId = $empIdColIdx !== false ? trim((string) ($row[$empIdColIdx] ?? '')) : '';
                if ($empId === '') continue;

                $snap = $empIdToSnap[$empId] ?? null;
                if (!$snap) { $skipped++; continue; }

                $newData  = $snap->snapshot_data;
                $changed  = false;

                // ── 1. Apply only the allowed editable columns ─────────────
                foreach ($colToKey as $colIdx => $snapshotKey) {
                    if (!in_array($snapshotKey, $allowedColumns)) continue;

                    $val = $row[$colIdx] ?? null;
                    if ($val === null || $val === '') continue;

                    // Cast numeric computation columns to int
                    if (in_array($snapshotKey, $computationColumns)) {
                        $val = is_numeric($val) ? (int) round((float) $val) : 0;
                    }
                    // String master columns — keep as string
                    if ($newData[$snapshotKey] !== $val) {
                        $newData[$snapshotKey] = $val;
                        $changed = true;
                    }
                }

                if (!$changed) { $updated++; continue; } // nothing to do

                // ── 2. Recompute all derived columns from the new values ────
                $newData = $this->recomputeSnapshotRow($newData);

                // ── 3. Update employee master tables WITH HISTORY ─────────
                $empDbId = $newData['employee_db_id'] ?? null;
                if ($empDbId) {
                    $newAccountNum = trim((string) ($newData['account_number'] ?? ''));
                    $newIfscCode   = strtoupper(trim((string) ($newData['ifsc_code']  ?? '')));
                    $newPan        = strtoupper(trim((string) ($newData['pan_number'] ?? '')));
                    $newEmail      = strtolower(trim((string) ($newData['rsc_mail_id']?? '')));

                    // ── Bank details — create new record, mark old as non-primary ──
                    if ($newAccountNum && $newIfscCode) {
                        $oldBank = \App\Models\EmployeeBankDetail::where('employee_id', $empDbId)
                            ->where('is_primary', true)->first();

                        $isSameBank = $oldBank &&
                            (string) $oldBank->account_number === $newAccountNum &&
                            $oldBank->ifsc_code === $newIfscCode;

                        if (!$isSameBank) {
                            // Deactivate old primary record
                            if ($oldBank) {
                                $oldBank->update(['is_primary' => false]);
                            }
                            // Insert new primary record
                            \App\Models\EmployeeBankDetail::create([
                                'employee_id'          => $empDbId,
                                'bank_name'            => $oldBank?->bank_name ?? '',
                                'account_number'       => $newAccountNum,
                                'ifsc_code'            => $newIfscCode,
                                'account_holder_name'  => $oldBank?->account_holder_name
                                                          ?? ($newData['name'] ?? ''),
                                'account_type'         => 'savings',
                                'is_primary'           => true,
                            ]);
                            // Log the change
                            \App\Models\EmployeeMasterHistory::create([
                                'employee_id' => $empDbId,
                                'field_type'  => 'bank_details',
                                'old_value'   => $oldBank
                                    ? ['account_number' => $oldBank->account_number, 'ifsc_code' => $oldBank->ifsc_code, 'bank_name' => $oldBank->bank_name]
                                    : null,
                                'new_value'   => ['account_number' => $newAccountNum, 'ifsc_code' => $newIfscCode],
                                'changed_via' => 'salary_reupload',
                                'changed_by'  => $userId,
                            ]);
                        }
                    }

                    // ── PAN — create new statutory record, mark old as is_current=false ──
                    if ($newPan) {
                        $oldStat = \App\Models\EmployeeStatutoryDetail::where('employee_id', $empDbId)
                            ->where('is_current', true)->latest()->first();

                        $isSamePan = $oldStat && $oldStat->pan_number === $newPan;

                        if (!$isSamePan) {
                            // Deactivate old current record
                            if ($oldStat) {
                                $oldStat->update(['is_current' => false]);
                            }
                            // Insert new current record (copy existing fields, update PAN)
                            \App\Models\EmployeeStatutoryDetail::create([
                                'employee_id'  => $empDbId,
                                'pan_number'   => $newPan,
                                'uan_number'   => $oldStat?->uan_number,
                                'pf_number'    => $oldStat?->pf_number,
                                'esic_number'  => $oldStat?->esic_number,
                                'aadhaar_number'=> $oldStat?->aadhaar_number,
                                'is_current'   => true,
                                'updated_by'   => $userId,
                            ]);
                            // Log
                            \App\Models\EmployeeMasterHistory::create([
                                'employee_id' => $empDbId,
                                'field_type'  => 'pan',
                                'old_value'   => $oldStat ? ['pan_number' => $oldStat->pan_number] : null,
                                'new_value'   => ['pan_number' => $newPan],
                                'changed_via' => 'salary_reupload',
                                'changed_by'  => $userId,
                            ]);
                        }
                    }

                    // ── Email — update employees table + log history ───────────
                    if ($newEmail) {
                        $emp = \App\Models\Employee::find($empDbId);
                        $oldEmail = $emp?->official_email;

                        if ($emp && strtolower($oldEmail ?? '') !== $newEmail) {
                            $emp->update(['official_email' => $newEmail]);
                            \App\Models\EmployeeMasterHistory::create([
                                'employee_id' => $empDbId,
                                'field_type'  => 'email',
                                'old_value'   => ['official_email' => $oldEmail],
                                'new_value'   => ['official_email' => $newEmail],
                                'changed_via' => 'salary_reupload',
                                'changed_by'  => $userId,
                            ]);
                        }
                    }
                }

                $snap->update([
                    'snapshot_data' => $newData,
                    'locked_at'     => $now,
                    'locked_by'     => $userId,
                ]);
                $updated++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Re-upload failed: ' . $e->getMessage());
        }

        $label = Carbon::createFromFormat('Y-m', $yearMonth)->format('F Y');
        $msg   = "Re-upload complete — {$updated} rows updated in the {$label} saved report.";
        if ($skipped > 0) $msg .= " {$skipped} rows skipped (Emp ID not found in existing snapshot).";

        return redirect()->route('salary.report.view', $yearMonth)->with('success', $msg);
    }

    // ─────────────────────────────────────────────
    // RECOMPUTE SNAPSHOT ROW after editable columns change
    // ─────────────────────────────────────────────

    private function recomputeSnapshotRow(array $d): array
    {
        $gross        = (float) ($d['gross_salary']      ?? 0);
        $monthDays    = max(1, (int) ($d['month_days']   ?? 31));
        $weekoffs     = (int) ($d['weekoff_holidays']    ?? 0);
        $workDays     = (int) ($d['working_days']        ?? 0);
        $reimbursement = (float) ($d['reimbursement']   ?? 0);
        $pfApplicable = (($d['pf_applicability'] ?? 'No') === 'Yes');

        // Editable columns (may have been updated)
        $payableGross = (int) ($d['payable_gross']   ?? 0);
        $totalLop     = (int) ($d['total_lop']       ?? 0);
        $incentive    = (float) ($d['incentive']     ?? 0);
        $advance      = (float) ($d['advance_salary']?? 0);
        $tds          = (float) ($d['tds']            ?? 0);

        // ── Salary breakdown from payable_gross ──────────────────────────
        $basic            = (int) round($payableGross * 0.70);
        $hra              = (int) round($basic * 0.40);
        $specialAllowance = $payableGross - $basic - $hra;

        // ── PF (capped at ₹1,800) ────────────────────────────────────────
        $pfEligible = $payableGross - $hra;
        $empPf      = $pfApplicable ? min(1800, (int) round($pfEligible * 0.12)) : 0;
        $emplPf     = $empPf;

        // ── Professional Tax (based on original gross, not payable) ──────
        $pt = $gross > 12000 ? 200 : 0;

        // ── LOP-derived fields ────────────────────────────────────────────
        $lopAmount   = (int) round(($gross / $monthDays) * $totalLop);
        $netPaidDays = max(0, $workDays - $totalLop + $weekoffs);

        // ── Totals ────────────────────────────────────────────────────────
        $totalDeduction = $empPf + $pt + $advance + $tds;
        $netPay         = (int) round($payableGross + $incentive + $reimbursement - $totalDeduction);

        // ── CTC ───────────────────────────────────────────────────────────
        $ctc          = (int) round($gross + $emplPf);
        $bonusDiwali  = (float) ($d['bonus_diwali'] ?? $ctc); // keep existing override
        $ctcWithBonus = (int) round(($bonusDiwali + $ctc * 12) / 12);

        return array_merge($d, [
            'basic_pay'         => $basic,
            'hra'               => $hra,
            'special_allowance' => $specialAllowance,
            'employee_pf'       => $empPf,
            'professional_tax'  => $pt,
            'lop_amount'        => $lopAmount,
            'net_paid_days'     => $netPaidDays,
            'total_deduction'   => $totalDeduction,
            'net_pay'           => $netPay,
            'employer_pf'       => $emplPf,
            'ctc'               => $ctc,
            'bonus_diwali'      => (int) $bonusDiwali,
            'ctc_with_diwali'   => $ctcWithBonus,
        ]);
    }

    // ─────────────────────────────────────────────
    // SAVED REPORTS  — history of locked snapshots
    // ─────────────────────────────────────────────

    public function savedReports(): \Illuminate\View\View
    {
        $this->authorize('payroll_view');

        $byMonth = SalaryReportSnapshot::orderByDesc('year_month')->get()
            ->groupBy('year_month')
            ->map(function ($snaps) {
                $rows = $snaps->pluck('snapshot_data');
                // Take the most recently locked record for metadata
                $latest = $snaps->sortByDesc('locked_at')->first();
                return [
                    'year_month'     => $snaps->first()->year_month,
                    'employee_count' => $snaps->count(),
                    'locked_at'      => $latest->locked_at,
                    'locked_by'      => $latest->locked_by,
                    'total_gross'    => $rows->sum(fn($r) => $r['gross_salary'] ?? 0),
                    'total_payable'  => $rows->sum(fn($r) => $r['payable_gross'] ?? 0),
                    'total_net_pay'  => $rows->sum(fn($r) => $r['net_pay'] ?? 0),
                    'total_deduction' => $rows->sum(fn($r) => $r['total_deduction'] ?? 0),
                ];
            })
            ->values();

        $userIds = $byMonth->pluck('locked_by')->filter()->unique()->all();
        $users   = \App\Models\User::whereIn('id', $userIds)->pluck('name', 'id');

        return view('salary-report.saved', compact('byMonth', 'users'));
    }

    private function filterAndPaginate(array $rows, string $search, int $perPage, ?string $filter = ''): \Illuminate\Pagination\LengthAwarePaginator
    {
        // ── Text search on Emp ID / Name ─────────────────────────────────────
        if ($search !== '') {
            $lower = strtolower($search);
            $rows = array_values(array_filter($rows, fn($r) =>
                str_contains(strtolower((string)($r['emp_id'] ?? '')), $lower) ||
                str_contains(strtolower((string)($r['name']   ?? '')), $lower)
            ));
        }

        $filter = (string) ($filter ?? '');

        // ── Column filter: only rows where the selected field has a value > 0 ─
        if ($filter !== '') {
            $fieldMap = [
                'tds'       => 'tds',
                'incentive' => 'incentive',
                'loan'      => 'advance_salary',
                'lop'       => 'total_lop',
            ];
            if (isset($fieldMap[$filter])) {
                $field = $fieldMap[$filter];
                $rows  = array_values(array_filter($rows, fn($r) => (float)($r[$field] ?? 0) > 0));
            }
        }

        $page   = (int) (request()->get('page', 1));
        $offset = ($page - 1) * $perPage;

        return new \Illuminate\Pagination\LengthAwarePaginator(
            array_slice($rows, $offset, $perPage),
            count($rows),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    private function companySaturdayWeeklyOffs(?string $companyName, ?string $shortName, array $saturdaysInMonth): array
    {
        $name = strtolower(trim(($companyName ?? '') . ' ' . ($shortName ?? '')));
        $isTnbtTech = str_contains($name, 'tnbt') && str_contains($name, 'tech');

        $weeklyOffs = [
            $saturdaysInMonth[1] ?? null, // second Saturday
        ];

        if ($isTnbtTech) {
            $weeklyOffs[] = $saturdaysInMonth[3] ?? null; // fourth Saturday
        }

        return array_values(array_filter($weeklyOffs));
    }

    private function summarise(array $rows): array
    {
        $keys = [
            'gross_salary',
            'payable_gross',
            'basic_pay',
            'hra',
            'special_allowance',
            'incentive',
            'reimbursement',
            'employee_pf',
            'professional_tax',
            'advance_salary',
            'tds',
            'total_deduction',
            'net_pay',
            'employer_pf',
            'ctc',
            'bonus_diwali',
            'ctc_with_diwali'
        ];
        $sum = array_fill_keys($keys, 0);
        foreach ($rows as $r) {
            foreach ($keys as $k) {
                $sum[$k] += $r[$k] ?? 0;
            }
        }
        $sum['headcount'] = count($rows);
        return $sum;
    }

    // ─────────────────────────────────────────────
    // EXCEL BUILDER
    // ─────────────────────────────────────────────

    private function buildExcel(array $rows, Carbon $month): Spreadsheet
    {
        $wb = new Spreadsheet();
        $ws = $wb->getActiveSheet();
        $ws->setTitle($month->format('M Y') . ' Salary');

        $headers = [
            'Emp ID',
            'Employee',
            'Designation',
            'Gross Salary',
            'Month',
            'Month Days',
            'WeekOff & Holidays',
            'Working Days',
            'Total LOP',
            'LOP Amount',
            'Net Paid Days',
            'Payable Gross',
            'Basic Pay',
            'HRA',
            'Special Allowance',
            'Incentive',
            'Reimbursement',
            'Employee PF',
            'Professional Tax',
            'Advance Salary',
            'TDS',
            'Total Deduction',
            'Net Pay',
            'Employer PF',
            'CTC',
            'Bonus For Diwali',
            'CTC with Diwali Bonus',
            'Firm',
            'PF Applicability',
            // Additional columns
            'UAN Number',
            'Closing CL',
            'Closing SL',
            'Current CP',
            'Current LOP',
            'Att LOP',
            'LOP',
            'Account Number',
            'IFSC Code',
            'PAN',
            'RSC Mail ID',
        ];
        $keys = [
            'emp_id',
            'name',
            'designation',
            'gross_salary',
            'month',
            'month_days',
            'weekoff_holidays',
            'working_days',
            'total_lop',
            'lop_amount',
            'net_paid_days',
            'payable_gross',
            'basic_pay',
            'hra',
            'special_allowance',
            'incentive',
            'reimbursement',
            'employee_pf',
            'professional_tax',
            'advance_salary',
            'tds',
            'total_deduction',
            'net_pay',
            'employer_pf',
            'ctc',
            'bonus_diwali',
            'ctc_with_diwali',
            'firm',
            'pf_applicability',
            // Additional columns
            'uan_number',
            'closing_cl',
            'closing_sl',
            'current_cp',
            'current_lop',
            'att_lop',
            'total_lop',
            'account_number',
            'ifsc_code',
            'pan_number',
            'rsc_mail_id',
        ];

        // Header row
        $ws->fromArray($headers, null, 'A1');

        // Columns that must be exported as plain text to prevent Excel from
        // collapsing long numeric strings into scientific notation.
        $textColumns = ['account_number', 'uan_number'];

        // 'month' column index (1-based) — written as a proper Excel date so
        // users can filter/sort by month.  Format: "MMM YYYY" (e.g. "Jan 2026").
        $monthColIdx = array_search('month', $keys);  // 0-based
        $monthColLetter = $monthColIdx !== false
            ? Coordinate::stringFromColumnIndex($monthColIdx + 1)
            : null;

        // Data rows — write cell-by-cell so text columns keep their TYPE_STRING
        foreach ($rows as $i => $row) {
            $r = $i + 2;
            foreach ($keys as $colIdx => $k) {
                $colLetter = Coordinate::stringFromColumnIndex($colIdx + 1);
                $cell      = $ws->getCell($colLetter . $r);

                if ($k === 'month' && !empty($row[$k])) {
                    // Convert "Jan 2026" → Excel date serial for the 1st of that month
                    try {
                        // Force UTC midnight so the Excel serial has no fractional-day
                        // component. Without this, servers running in non-UTC timezones
                        // (e.g. IST = UTC+5:30) write April 30 18:30 UTC instead of
                        // May 1 00:00 UTC, causing SheetJS to read back the wrong month.
                        $dt = Carbon::createFromFormat('M Y', $row[$k], 'UTC')->startOfMonth();
                        $cell->setValue(\PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($dt));
                    } catch (\Throwable) {
                        $cell->setValue($row[$k]);
                    }
                } elseif (in_array($k, $textColumns)) {
                    // Force text type — prevents 3.91802E+14 collapsing
                    $cell->setValueExplicit((string)($row[$k] ?? ''), DataType::TYPE_STRING);
                } else {
                    $cell->setValue($row[$k] ?? null);
                }
            }
        }

        $lastRow = count($rows) + 1;
        $lastCol = count($headers);

        // Apply "MMM YYYY" date format to the entire Month column
        if ($monthColLetter && $lastRow >= 2) {
            $ws->getStyle("{$monthColLetter}2:{$monthColLetter}{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode('MMM YYYY');
        }

        // Header styling
        $headerRange = 'A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastCol) . '1';
        $ws->getStyle($headerRange)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10, 'name' => 'Arial'],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'AAAAAA']]],
        ]);
        $ws->getRowDimension(1)->setRowHeight(28);

        // Currency cols: D(4)=GrossSalary, J(10)=LopAmount, L(12)=PayableGross, M-P(13-16)=Basic/HRA/Special/Incentive,
        // Q(17)=Reimbursement, R(18)=EmpPF, S(19)=PT, T(20)=Advance, U(21)=TDS,
        // V(22)=TotalDeduction, W(23)=NetPay, X(24)=EmplPF, Y(25)=CTC, Z(26)=Bonus, AA(27)=CtcWithDiwali
        $currencyCols = [4, 10, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27]; // 1-based
        if ($lastRow >= 2) {
            $dataRange = 'A2:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastCol) . $lastRow;
            $ws->getStyle($dataRange)->applyFromArray([
                'font'      => ['size' => 9, 'name' => 'Arial'],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]],
            ]);

            foreach ($currencyCols as $colIdx) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
                $ws->getStyle("{$colLetter}2:{$colLetter}{$lastRow}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.00');
            }

            // Zebra rows
            for ($r = 2; $r <= $lastRow; $r++) {
                if ($r % 2 === 0) {
                    $rowRange = 'A' . $r . ':' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastCol) . $r;
                    $ws->getStyle($rowRange)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('F5F7FA');
                }
            }
        }

        // Column widths (28 original + 11 extra = 39 total — added Reimbursement col after Incentive)
        $widths = [
            14,
            26,
            22,
            14,
            10,
            11,
            18,
            13,
            10,
            12,
            13,
            14,
            12,
            12,
            18,
            11,
            15,
            12,
            17,
            15,
            10,
            16,
            12,
            12,
            14,
            16,
            20,
            28,
            16,
            18,
            12,
            12,
            12,
            12,
            10,
            10,
            22,
            14,
            14,
            30,
        ];
        foreach ($widths as $ci => $w) {
            $ws->getColumnDimension(Coordinate::stringFromColumnIndex($ci + 1))->setWidth($w);
        }

        // Freeze header row
        $ws->freezePane('A2');

        return $wb;
    }

    // ─────────────────────────────────────────────
    // PAYSLIPS  — generate + email for every employee in a locked month
    // ─────────────────────────────────────────────

    /**
     * Items #24-25: emails every employee in this locked month their
     * payslip PDF. Only ever reads from the LOCKED snapshot (never live
     * data), so what's emailed always matches what was actually processed.
     * Deliberately a separate, explicit action from locking itself — a
     * month can be re-locked after a correction, and auto-sending on every
     * lock would risk emailing a duplicate/corrected payslip each time.
     */
    public function sendPayslips(Request $request)
    {
        $this->authorize('payroll_view');

        $request->validate(['month' => 'required|date_format:Y-m']);
        $month = $request->get('month');

        $snapshotIds = SalaryReportSnapshot::where('year_month', $month)->pluck('id');

        if ($snapshotIds->isEmpty()) {
            return redirect()->route('salary.report', ['month' => $month])
                ->with('error', 'No locked report found for ' . $month . ' — save/lock it first.');
        }

        $queued = $this->queuePayslipEmails($snapshotIds);

        $monthLabel = Carbon::createFromFormat('Y-m', $month)->format('F Y');
        return redirect()->route('salary.report', ['month' => $month])
            ->with('success', "Queued {$queued} payslip email(s) for {$monthLabel} — sending in the background, check back shortly.");
    }

    /**
     * Auto-send hook for lockSnapshot() (the explicit "Save / Re-lock"
     * button) only — deliberately NOT wired into performLock() itself,
     * since that's also called from index() the first time a month is
     * merely *viewed* with no snapshot yet. Emailing real payslips off a
     * page view instead of an explicit action would be a serious mistake.
     *
     * Guarded against re-lock duplicates by only queuing snapshots that
     * don't already have payslip_sent_at set — a correction + re-lock won't
     * re-email everyone, only anyone newly added since the last send.
     */
    protected function autoSendPayslipsOnLock(string $month): int
    {
        $snapshotIds = SalaryReportSnapshot::where('year_month', $month)
            ->whereNull('payslip_sent_at')
            ->pluck('id');

        return $this->queuePayslipEmails($snapshotIds);
    }

    /**
     * Dispatches one SendPayslipEmailJob per snapshot instead of sending
     * synchronously — generating a PDF and calling Graph's sendMail is a
     * real network round-trip per employee, and doing that inline for
     * 50+ people made "Save / Re-lock" hang for a minute or more. Requires
     * a queue worker actually running (QUEUE_CONNECTION=database here) —
     * see the note left for the user on setting that up.
     */
    protected function queuePayslipEmails($snapshotIds): int
    {
        foreach ($snapshotIds as $id) {
            \App\Jobs\SendPayslipEmailJob::dispatch($id);
        }

        return $snapshotIds->count();
    }

    /**
     * Admin/HR downloading any employee's payslip for any locked month.
     * Employee self-download lives on WebMyPayslipController instead, since
     * that one is scoped to "my own record only".
     */
    public function downloadPayslip(string $month, Employee $employee, PayslipPdfService $payslipService)
    {
        $this->authorize('payroll_view');

        $snapshot = SalaryReportSnapshot::where('employee_id', $employee->id)
            ->where('year_month', $month)
            ->first();

        abort_unless($snapshot, 404, 'No locked payslip found for this employee/month.');

        $path = $payslipService->ensureGenerated($employee, $month, $snapshot->snapshot_data);

        return response()->download($path, "Payslip_{$employee->employee_id}_{$month}.pdf", [
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
