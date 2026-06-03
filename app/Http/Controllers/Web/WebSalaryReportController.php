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
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
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
        $companyId = $request->has('company_id')
            ? (int) $request->get('company_id')
            : 0;

        $month = $request->get('month'); // YYYY-MM or null
        $rows    = [];
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

            // Auto-lock on first generation: compute + freeze immediately.
            // Subsequent visits (snapshot already exists) skip this block.
            // if (!$isLocked) {
                $liveRows = $this->compute($month, $companyId);
                if (!empty($liveRows)) {
                    $this->performLock($month, $liveRows);
                    $snapshotCount = count($liveRows);
                    $isLocked      = true;
                }
            // }

            // Always serve rows from the frozen snapshot (auto-locked or re-locked).
            // if ($isLocked) {
                $snapshots = SalaryReportSnapshot::where('year_month', $month)
                    ->get()
                    ->keyBy('employee_id');

                if ($companyId !== 0) {
                    $snapshots = $snapshots->filter(
                        fn($s) => ($s->snapshot_data['company_id'] ?? 0) === $companyId
                    );
                }

                $rows     = $snapshots->map(fn($s) => $s->snapshot_data)->values()->toArray();
                $lockedAt = $snapshots->sortByDesc('locked_at')->first()?->locked_at;
            // }

            $summary = $this->summarise($rows);
        }

        return view('salary-report.index', compact(
            'month',
            'rows',
            'summary',
            'companyId',
            'companies',
            'isLocked',
            'snapshotCount',
            'lockedAt'
        ));
    }

    // ─────────────────────────────────────────────
    // VIEW LOCKED REPORT  — dedicated read-only URL
    // ─────────────────────────────────────────────

    public function viewLockedReport(string $month): \Illuminate\Http\RedirectResponse|\Illuminate\View\View
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            return redirect()->route('salary.report.saved')->with('error', 'Invalid month format.');
        }

        $snapshots = SalaryReportSnapshot::where('year_month', $month)->get()->keyBy('employee_id');

        if ($snapshots->isEmpty()) {
            return redirect()->route('salary.report.saved')
                ->with('error', 'No locked report found for ' . $month . '.');
        }

        $rows          = $snapshots->map(fn($s) => $s->snapshot_data)->values()->toArray();
        $summary       = $this->summarise($rows);
        $snapshotCount = $snapshots->count();
        $lockedAt      = $snapshots->sortByDesc('locked_at')->first()->locked_at;
        $companyId      = 0;
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
            'isReadonly'
        ));
    }

    // ─────────────────────────────────────────────
    // EXCEL DOWNLOAD
    // ─────────────────────────────────────────────

    public function download(Request $request)
    {
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

        // Ensure employee statuses / date_of_leaving are up to date before querying.
        Employee::syncLeaverStatuses();

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

        // ── All Sundays in the month (standard weekly off, always counted) ──────
        $companiesById = Company::whereIn('id', $companyIdsInScope)
            ->get(['id', 'name', 'short_name'])
            ->keyBy('id');

        $sundaysInMonth = [];
        $saturdaysInMonth = [];
        $d = $carbonMonth->copy()->startOfMonth();
        while ($d->month === $mon) {
            if ($d->dayOfWeek === Carbon::SUNDAY) {
                $sundaysInMonth[] = $d->format('Y-m-d');
            } elseif ($d->dayOfWeek === Carbon::SATURDAY) {
                $saturdaysInMonth[] = $d->format('Y-m-d');
            }
            $d->addDay();
        }

        // Build: company_id → [Y-m-d, ...] (Sundays + DB holidays, deduped, sorted)
        $holidaysByCompany = [];
        foreach ($companyIdsInScope as $compId) {
            $calId = $calendarIds[$compId] ?? null;
            $dbHolidays = $calId
                ? ($allHolidays[$calId] ?? collect())
                ->pluck('date')
                ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
                ->all()
                : [];

            $company = $companiesById->get($compId);
            $weeklyOffs = array_merge(
                $sundaysInMonth,
                $this->companySaturdayWeeklyOffs($company?->name, $company?->short_name, $saturdaysInMonth)
            );

            // Merge weekly offs + DB holidays, deduplicate, sort
            $combined = array_values(array_unique(array_merge($weeklyOffs, $dbHolidays)));
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
        $notApprovedPunches = AttendanceApproval::where('punch_in_approval', 'not_approved')
            ->whereBetween('attendance_date', [
                Carbon::createFromDate($year, $mon, 1)->startOfMonth()->toDateString(),
                Carbon::createFromDate($year, $mon, 1)->endOfMonth()->toDateString(),
            ])
            ->whereIn('employee_id', $empIds)
            ->get()
            ->groupBy('employee_id')
            ->map(fn($g) => $g->count());

        // Months that have a locked snapshot — attendance LOP in these months is
        // considered already processed, so we exclude them from carry-forward.
        $lockedPriorMonths = SalaryReportSnapshot::where('year_month', '<', $month)
            ->distinct()
            ->pluck('year_month')
            ->all();

        // Carry-forward: not_approved attendance from prior months that were NEVER locked.
        // If a month has a snapshot, its LOP was already deducted — skip it.
        $carryForwardLop = AttendanceApproval::where('punch_in_approval', 'not_approved')
            ->where('attendance_date', '<', $monthStart)
            ->whereIn('employee_id', $empIds)
            ->get(['employee_id', 'attendance_date'])
            ->filter(fn($r) => !in_array(
                Carbon::parse($r->attendance_date)->format('Y-m'),
                $lockedPriorMonths
            ))
            ->groupBy('employee_id')
            ->map(fn($g) => $g->count());

        // Deferred LOP from LOCKED prior months where the admin reduced the LOP via override
        // (i.e. total_lop < computed_lop in the snapshot).
        // Formula: sum(att_lop + leave_lop) − sum(total_lop) across all locked prior months.
        // att_lop + leave_lop is the "fresh" LOP generated that month (excludes carry-forward
        // already counted in carry_fwd_lop so there is no double-count across months).
        // For snapshots created before this change, leave_lop defaults to 0 safely.
        $deferredLopFromSnapshots = !empty($lockedPriorMonths)
            ? SalaryReportSnapshot::where('year_month', '<', $month)
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
        // FY year = calendar year of April 1 that started this FY.
        //   Apr-Dec 2025  → fyYear = 2025  (FY 2025-26)
        //   Jan-Mar 2025  → fyYear = 2024  (FY 2024-25)
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

            // ── Carry-forward LOP ─────────────────────────────────────────────
            // Two sources (mutually exclusive by design, summed together):
            //   1. Attendance not-approved from prior months that were NEVER locked
            //   2. Deferred LOP from locked months where admin set override < computed
            $carryFwdLop = (int) ($carryForwardLop[$emp->id]          ?? 0)
                         + (int) ($deferredLopFromSnapshots[$emp->id] ?? 0);

            // Total computed LOP = attendance (this month) + leave delta + carry-forward
            $computedLop = $lopAtt + $lopLeaveThisMonth + $carryFwdLop;

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

            if ($dojInMonth || $dolInMonth) {
                $joiningDateStr = $dojInMonth ? $doj->format('Y-m-d') : null;
                $leavingDateStr = $dolInMonth ? $dol->format('Y-m-d') : null;
                $empWeekoffAndHolidays = count(array_filter(
                    $empHolidayDates,
                    fn($d) => ($joiningDateStr === null || $d >= $joiningDateStr)
                        && ($leavingDateStr === null || $d <= $leavingDateStr)
                ));
            }

            // Working Days = Month Days − WeekOff&Holidays
            $empWorkingDays = max(0, $monthDays - $empWeekoffAndHolidays);

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
                'month_days'        => $monthDays,
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
        return redirect()->route('salary.report', ['month' => $month, 'company_id' => $companyId])
            ->with('success', "Report locked ✓ — {$count} employee rows frozen. Loan deductions recorded.");
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
        $request->validate(['month' => 'required|date_format:Y-m']);

        $month   = $request->get('month');
        $label   = \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y');
        $deleted = SalaryReportSnapshot::where('year_month', $month)->delete();

        return redirect()->route('salary.report.saved')
            ->with('success', "Snapshot for {$label} deleted — {$deleted} employee records removed.");
    }

    // ─────────────────────────────────────────────
    // SAVED REPORTS  — history of locked snapshots
    // ─────────────────────────────────────────────

    public function savedReports(): \Illuminate\View\View
    {
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

        // Data rows — write cell-by-cell so text columns keep their TYPE_STRING
        foreach ($rows as $i => $row) {
            $r = $i + 2;
            foreach ($keys as $colIdx => $k) {
                $colLetter = Coordinate::stringFromColumnIndex($colIdx + 1);
                $cell      = $ws->getCell($colLetter . $r);
                if (in_array($k, $textColumns)) {
                    // Force text type — prevents 3.91802E+14 collapsing
                    $cell->setValueExplicit((string)($row[$k] ?? ''), DataType::TYPE_STRING);
                } else {
                    $cell->setValue($row[$k] ?? null);
                }
            }
        }

        $lastRow = count($rows) + 1;
        $lastCol = count($headers);

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
}
