<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AttendanceApproval;
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
        // entity_id = 0 → All Firms (default when no param in URL).
        // Explicit request param takes priority so admins can switch firms.
        $entityId = $request->has('entity_id')
            ? (int) $request->get('entity_id')
            : 0;

        $month = $request->get('month'); // YYYY-MM or null
        $rows    = [];
        $summary = null;
        $isLocked            = false;
        $snapshotCount       = 0;
        $lockedAt            = null;
        $isReadonly          = false;
        $hasPendingOverrides = false;

        // Entities that have at least one active employee (for the selector dropdown)
        $entities = Entity::whereHas('employees', fn($q) => $q->where('employee_status', 'active'))
            ->orderBy('id')
            ->get(['id', 'name', 'short_name']);

        if ($month) {
            // Always compute live data — the index page is a working view.
            // The frozen snapshot is only used on the dedicated /view/{month} page.
            $rows    = $this->compute($month, $entityId);
            $summary = $this->summarise($rows);

            // Check snapshot existence for UI state only (lock/re-lock button display)
            $snapshotCount = SalaryReportSnapshot::where('year_month', $month)->count();
            $isLocked      = $snapshotCount > 0;
            $lockedAt      = $isLocked
                ? SalaryReportSnapshot::where('year_month', $month)->max('locked_at')
                : null;
        }

        return view('salary-report.index', compact(
            'month', 'rows', 'summary', 'entityId', 'entities',
            'isLocked', 'snapshotCount', 'lockedAt'
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
        $entityId      = 0;
        $isLocked      = true;
        $isReadonly    = true;

        $entities = Entity::whereHas('employees', fn($q) => $q->where('employee_status', 'active'))
            ->orderBy('id')
            ->get(['id', 'name', 'short_name']);

        return view('salary-report.view', compact(
            'month', 'rows', 'summary', 'entityId', 'entities',
            'isLocked', 'snapshotCount', 'lockedAt', 'isReadonly'
        ));
    }

    // ─────────────────────────────────────────────
    // EXCEL DOWNLOAD
    // ─────────────────────────────────────────────

    public function download(Request $request)
    {
        $request->validate(['month' => 'required|date_format:Y-m']);
        $entityId = $request->has('entity_id')
            ? (int) $request->get('entity_id')
            : 0;

        $month = $request->get('month');

        // Use snapshot if locked, otherwise compute live
        $snapshots = SalaryReportSnapshot::where('year_month', $month)->get()->keyBy('employee_id');
        if ($snapshots->isNotEmpty()) {
            $rows = $snapshots->map(fn($s) => $s->snapshot_data)->values()->toArray();
            if ($entityId !== 0) {
                $rows = array_values(array_filter($rows, fn($r) => ($r['entity_id'] ?? 0) === $entityId));
            }
        } else {
            $rows = $this->compute($month, $entityId);
        }

        $carbonMonth  = Carbon::createFromFormat('Y-m', $month);
        $entitySuffix = $entityId === 0 ? 'All_Firms' : ('Entity_' . $entityId);
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

    private function compute(string $month, int $entityId): array
    {
        [$year, $mon] = array_map('intval', explode('-', $month));
        $carbonMonth  = Carbon::createFromDate($year, $mon, 1);
        $monthDays    = $carbonMonth->daysInMonth;
        $monthLabel   = $carbonMonth->format('M Y');

        // Active employees with salary — load all entities or one specific entity.
        $employeeQuery = Employee::where('employee_status', 'active')
            ->with(['designation:id,name', 'currentSalary', 'entity:id,name'])
            ->orderBy('entity_id')
            ->orderBy('employee_id');

        if ($entityId !== 0) {
            $employeeQuery->where('entity_id', $entityId);
        }

        $employees = $employeeQuery->get()->filter(fn($e) => $e->currentSalary);

        // Holiday dates per entity — loaded once, used per-employee in the loop.
        // Supports "All Firms" (entity_id = 0) without extra per-employee queries.
        $entityIdsInScope = $employees->pluck('entity_id')->unique()->values()->all();

        $calendars = HolidayCalendar::whereIn('entity_id', $entityIdsInScope)->get()->keyBy('entity_id');
        $calendarIds = $calendars->map(fn($c) => $c->id)->all(); // entity_id → calendar_id

        // Load this month's holidays for all relevant calendars in one query.
        $allHolidays = Holiday::whereIn('holiday_calendar_id', array_values($calendarIds))
            ->whereYear('date', $year)
            ->whereMonth('date', $mon)
            ->get()
            ->groupBy('holiday_calendar_id');

        // Build: entity_id → [Y-m-d, ...]
        $holidaysByEntity = [];
        foreach ($entityIdsInScope as $eid) {
            $calId = $calendarIds[$eid] ?? null;
            $holidaysByEntity[$eid] = $calId
                ? ($allHolidays[$calId] ?? collect())
                    ->pluck('date')
                    ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
                    ->sort()->values()->all()
                : [];
        }

        // Month-level defaults: use the first entity's calendar for summary cards.
        $firstEntityId      = $entityIdsInScope[0] ?? null;
        $monthHolidayDates  = $firstEntityId ? ($holidaysByEntity[$firstEntityId] ?? []) : [];
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
        // Load ALL active loans for this employee set whose first_emi_month is on or
        // before the end of this payroll month. This handles:
        //   • Loans that started in a prior month (ongoing)
        //   • Percent-based deductions (computed per-employee using payable_gross)
        //   • Fixed-EMI loans (use emi_amount from the loan record)
        //
        // NOTE: We intentionally do NOT skip already-paid installments here.
        // compute() is a VIEW function — it always shows the full deduction for the
        // requested month regardless of lock state.  The $alreadyPaid guard inside
        // lockSnapshot() prevents double-processing at lock time independently.
        $activeLoansMap = EmployeeLoan::whereIn('employee_id', $empIds)
            ->where('status', 'active')
            ->where('outstanding_amount', '>', 0)
            ->where('first_emi_month', '<=', $carbonMonth->copy()->endOfMonth()->toDateString())
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

        $lopDays = $empIds->mapWithKeys(fn($id) => [
            $id => (int) ($notApprovedPunches[$id] ?? 0),
        ]);

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

            $empLop = $override && $override->lop_override !== null
                ? (int) $override->lop_override
                : (int) ($lopDays[$emp->id] ?? 0);

            // ── Per-employee WeekOff & Holidays (uses this employee's entity's calendar)
            //   • Joined mid-month → count holidays ON OR AFTER date_of_joining
            //   • Left mid-month   → count holidays ON OR BEFORE date_of_leaving
            //   • Both apply simultaneously when joining and leaving are in the same month
            //   Excel: Working Days = Month Days − WeekOff&Holidays
            //   Excel: Net Paid Days = Working Days − Total LOP + WeekOff&Holidays
            $empHolidayDates       = $holidaysByEntity[$emp->entity_id] ?? $monthHolidayDates;
            $empWeekoffAndHolidays = count($empHolidayDates);

            $doj = $emp->date_of_joining ? Carbon::parse($emp->date_of_joining) : null;
            $dol = $emp->date_of_leaving  ? Carbon::parse($emp->date_of_leaving)  : null;

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

            // ── Pro-rata CL / SL allocation ──────────────────────────────────
            // Full FY = 365 days (Apr 1 – Mar 31).
            // New joinee:      DOJ falls after Apr 1 of this FY  → effective start = DOJ
            // Left employee:   DOL falls before Mar 31 of this FY → effective end   = DOL
            // Both can apply together (joined and left in same FY).
            $proStart = $fyStartCarbon->copy();
            $proEnd   = $fyEndCarbon->copy();

            // Only clip if DOJ is within the current FY (Apr 1 – Mar 31).
            // A DOJ from a future FY (e.g. bad data entry like Dec 2026 for an Apr 2025 report)
            // would invert the range and collapse the allocation to near-zero.
            if ($doj && $doj->gt($fyStartCarbon) && $doj->lte($fyEndCarbon)) {
                $proStart = $doj->copy()->startOfDay();
            }
            // Only clip the end date if DOL falls WITHIN the current FY.
            // A date_of_leaving from a previous FY must not collapse the allocation to zero.
            if ($dol && $dol->gte($fyStartCarbon) && $dol->lt($fyEndCarbon)) {
                $proEnd = $dol->copy()->startOfDay();
            }

            // +1 to make the range inclusive (e.g. Apr 1–Mar 31 = 365 days)
            $daysWorked    = max(1, (int) $proStart->diffInDays($proEnd) + 1);
            $proRataFactor = min(1.0, $daysWorked / 365);
            $clAllocation  = round($clFullAllocation * $proRataFactor, 1);
            $slAllocation  = round($slFullAllocation * $proRataFactor, 1);

            // ── FY-to-date leave balances ────────────────────────────────────
            // Leaves taken from April 1 of this FY through the end of the selected month.
            // dd($fyLeaveAvailed);
            $fyLeaves  = $fyLeaveAvailed[$emp->id] ?? ['cl' => 0.0, 'sl' => 0.0, 'co' => 0.0];
            $clAvailed = (float) $fyLeaves['cl'];
            $slAvailed = (float) $fyLeaves['sl'];
            $coAvailed = (float) $fyLeaves['co'];

            // SL exhaustion → spills into CL:
            //   If SL days taken > SL allocation, the excess is charged against the CL balance.
            //   If CL is also exhausted after the SL spillover, the remaining deficit
            //   becomes LOP (captured separately by the leave-tracker LOP records).
            $slExcess        = max(0.0, $slAvailed - $slAllocation);
            $effectiveCLUsed = $clAvailed + $slExcess;

            $closingCL = max(0.0, $clAllocation - $effectiveCLUsed);
            $closingSL = max(0.0, $slAllocation - $slAvailed);
            $closingCO = $coAvailed; // CO has no fixed annual allocation — show count used

            $lopLeave = 0; // leave tracker is informational only — does not affect LOP
            $lopAtt   = (int) ($notApprovedPunches[$emp->id] ?? 0); // attendance not-approved punches

            $rows[] = [
                // ── Override metadata (for edit UI) ──────────────────────────
                'employee_db_id'    => $emp->id,
                'has_override'      => $override !== null,
                'override_id'       => $override?->id,
                // Computed base values (before override — shown as "original" in edit modal)
                'computed_lop'           => (int) ($lopDays[$emp->id] ?? 0),
                'computed_incentive'     => (float) ($incentives->get($emp->id) ?? 0),
                'computed_advance'       => $computedAdvance,
                'computed_tds'           => (float) ($monthlyTds->get($emp->id) ?? 0),
                'computed_payable_gross' => $computedPayableGross,

                'emp_id'            => $emp->employee_id,
                'name'              => trim($emp->first_name . ' ' . $emp->last_name),
                'designation'       => $emp->designation?->name ?? '—',
                'gross_salary'      => $grossMonthly,
                'month'             => $monthLabel,
                'month_days'        => $monthDays,
                'weekoff_holidays'  => $empWeekoffAndHolidays,
                'working_days'      => $empWorkingDays,
                'total_lop'         => $empLop,
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
                'entity_id'         => $emp->entity_id,  // stored in snapshot for entity filtering
                'pf_applicability'  => $pfApplicable ? 'Yes' : 'No',
                // ── Additional columns ───────────────────────────────────────
                'uan_number'        => $pfApplicable ? ($statutory?->uan_number ?? '') : '-',
                'closing_cl'        => $closingCL,
                'closing_sl'        => $closingSL,
                'current_cp'        => $closingCO,
                'current_lop'       => $lopLeave,
                'att_lop'           => $lopAtt,
                // 'total_lop' (above) = lopLeave + lopAtt — reused as "LOP" column
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
                    $validated, $newGross, $effectiveFrom, $effectiveTo, $currentSalary
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
            'month'     => 'required|date_format:Y-m',
            'entity_id' => 'nullable|integer',
        ]);

        $month    = $request->get('month');
        $entityId = (int) ($request->get('entity_id', 0));

        // Compute live data for all employees in scope
        $rows = $this->compute($month, $entityId);

        if (empty($rows)) {
            return redirect()->back()->with('error', 'No employee data found for this month. Cannot lock an empty report.');
        }

        $now    = now();
        $userId = auth()->id();

        // ── Process loan/advance deductions FIRST ───────────────────────────
        // Snapshots are saved AFTER loan processing so that the frozen
        // advance_salary reflects what was actually deducted — even on a re-lock
        // where compute() skips already-paid installments and returns advance = 0.
        DB::beginTransaction();
        try {
            [$lockYear, $lockMon] = array_map('intval', explode('-', $month));
            $carbonLock = Carbon::createFromFormat('Y-m', $month);

            foreach ($rows as $row) {
                $empId   = $row['employee_db_id'] ?? null;
                $advance = (float) ($row['advance_salary'] ?? 0);
                if (!$empId || $advance <= 0) continue;

                $empLoans = EmployeeLoan::where('employee_id', $empId)
                    ->where('status', 'active')
                    ->where('outstanding_amount', '>', 0)
                    ->where('first_emi_month', '<=', $carbonLock->copy()->endOfMonth()->toDateString())
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

                    // Update existing pending installment for this month, or create one
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
        // This corrects snapshots on re-lock: compute() returns advance=0 for
        // already-paid loans, but the installment records have the real amount.
        $empIdsInScope = array_values(array_filter(array_column($rows, 'employee_db_id')));
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

            $row['entity_id'] = $row['entity_id'] ?? 0;

            // Patch advance_salary from actual loan installments (handles re-lock)
            if ($paidInstAmounts->has($empId)) {
                $trueAdvance           = (float) $paidInstAmounts->get($empId);
                $row['advance_salary'] = $trueAdvance;
                $row['total_deduction'] = (float)($row['employee_pf']       ?? 0)
                                        + (float)($row['professional_tax']  ?? 0)
                                        + $trueAdvance
                                        + (float)($row['tds']               ?? 0);
                $row['net_pay'] = (int) round(
                    (float)($row['payable_gross']  ?? 0)
                    + (float)($row['incentive']    ?? 0)
                    + (float)($row['reimbursement']?? 0)
                    - $row['total_deduction']
                );
            }

            SalaryReportSnapshot::updateOrCreate(
                ['employee_id' => $empId, 'year_month' => $month],
                ['snapshot_data' => $row, 'locked_at' => $now, 'locked_by' => $userId]
            );
        }

        $count = count($rows);
        return redirect()->route('salary.report', ['month' => $month, 'entity_id' => $entityId])
            ->with('success', "Report locked ✓ — {$count} employee rows frozen. Loan deductions recorded.");
    }

    public function unlockSnapshot(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate(['month' => 'required|date_format:Y-m']);

        $month    = $request->get('month');
        $entityId = (int) ($request->get('entity_id', 0));

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

        return redirect()->route('salary.report', ['month' => $month, 'entity_id' => $entityId])
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
                    'total_deduction'=> $rows->sum(fn($r) => $r['total_deduction'] ?? 0),
                ];
            })
            ->values();

        $userIds = $byMonth->pluck('locked_by')->filter()->unique()->all();
        $users   = \App\Models\User::whereIn('id', $userIds)->pluck('name', 'id');

        return view('salary-report.saved', compact('byMonth', 'users'));
    }

    private function summarise(array $rows): array
    {
        $keys = ['gross_salary','payable_gross','basic_pay','hra','special_allowance',
                 'incentive','reimbursement','employee_pf','professional_tax','advance_salary','tds',
                 'total_deduction','net_pay','employer_pf','ctc','bonus_diwali','ctc_with_diwali'];
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
            'Emp ID', 'Employee', 'Designation', 'Gross Salary', 'Month',
            'Month Days', 'WeekOff & Holidays', 'Working Days', 'Total LOP',
            'Net Paid Days', 'Payable Gross', 'Basic Pay', 'HRA',
            'Special Allowance', 'Incentive', 'Reimbursement', 'Employee PF', 'Professional Tax',
            'Advance Salary', 'TDS', 'Total Deduction', 'Net Pay',
            'Employer PF', 'CTC', 'Bonus For Diwali', 'CTC with Diwali Bonus',
            'Firm', 'PF Applicability',
            // Additional columns
            'UAN Number', 'Closing CL', 'Closing SL', 'Current CP',
            'Current LOP', 'Att LOP', 'LOP',
            'Account Number', 'IFSC Code', 'PAN', 'RSC Mail ID',
        ];
        $keys = [
            'emp_id','name','designation','gross_salary','month',
            'month_days','weekoff_holidays','working_days','total_lop',
            'net_paid_days','payable_gross','basic_pay','hra',
            'special_allowance','incentive','reimbursement','employee_pf','professional_tax',
            'advance_salary','tds','total_deduction','net_pay',
            'employer_pf','ctc','bonus_diwali','ctc_with_diwali',
            'firm','pf_applicability',
            // Additional columns
            'uan_number','closing_cl','closing_sl','current_cp',
            'current_lop','att_lop','total_lop',
            'account_number','ifsc_code','pan_number','rsc_mail_id',
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

        // Currency cols: D(4)=GrossSalary, K(11)=PayableGross, L-O(12-15)=Basic/HRA/Special/Incentive,
        // P(16)=Reimbursement, Q(17)=EmpPF, R(18)=PT, S(19)=Advance, T(20)=TDS,
        // U(21)=TotalDeduction, V(22)=NetPay, W(23)=EmplPF, X(24)=CTC, Y(25)=Bonus, Z(26)=CtcWithDiwali
        $currencyCols = [4, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26]; // 1-based
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
            14, 26, 22, 14, 10, 11, 18, 13, 10, 13, 14, 12, 12, 18, 11, 15, 12, 17, 15, 10, 16, 12, 12, 14, 16, 20, 28, 16,
            18, 12, 12, 12, 12, 10, 10, 22, 14, 14, 30,
        ];
        foreach ($widths as $ci => $w) {
            $ws->getColumnDimension(Coordinate::stringFromColumnIndex($ci + 1))->setWidth($w);
        }

        // Freeze header row
        $ws->freezePane('A2');

        return $wb;
    }
}
