# HRMS Payroll Platform — How It Actually Works

A detailed walkthrough of the live business processes in this application, based on direct reading of the current codebase (not the original design docs — see **README vs. Reality** at the bottom for where they diverge).

## Contents

1. [The shape of the app](#the-shape-of-the-app)
2. [Identity & access](#identity--access)
3. [Employee lifecycle](#employee-lifecycle)
4. [Attendance](#attendance)
5. [Leave management](#leave-management)
6. [Payroll — the core engine](#payroll--the-core-engine)
7. [Loans & Advances](#loans--advances)
8. [Salary Revision](#salary-revision)
9. [Reimbursements](#reimbursements)
10. [Statutory compliance tracking](#statutory-compliance-tracking)
11. [Contractors](#contractors)
12. [Helpdesk](#helpdesk)
13. [Bio IDs, Monthly TDS, Incentives](#bio-ids-monthly-tds-incentives)
14. [Notifications](#notifications)
15. [Background processing & scheduling](#background-processing--scheduling)
16. [README vs. reality](#readme-vs-reality)

---

## The shape of the app

This is a server-rendered Laravel 11 Blade monolith — no SPA, no frontend build step (no `package.json`), no API consumed by the UI.

There are **two parallel implementations** of nearly every feature in this codebase:

- **`routes/web.php` + `App\Http\Controllers\Web\Web*Controller` + Blade views** — this is the real, deployed product.
- **`routes/api.php` + non-`Web`-namespaced controllers** (`HR\EmployeeController`, `Leave\LeaveController`, `Payroll\PayrollController`, etc.) — a separate Sanctum API layer left over from the project's original API-first design.

Only the first layer is live. At least one whole feature built in the second layer (Comp-Off leave set-off logic) has zero effect on production because nothing in the real app calls it. Two entire modules from the original design — **Investment Declaration (Form 12BB)** and **POI (Proof of Investment) upload** — exist *only* in that dead API layer; there is no route for either anywhere in `routes/web.php`. Before trusting or extending any non-`Web` controller, verify something in the live app actually calls it.

---

## Identity & access

A user authenticates via `WebAuthController`. Every permission check in the app ultimately flows through `Gate::before()` in `AppServiceProvider`, which checks `User::isSuperAdmin()` (role name `Admin`) — an Admin bypasses every `$this->authorize()`/`@can` check unconditionally. Everyone else is governed by Spatie `laravel-permission` role/permission assignments.

New employees get a login account auto-provisioned by `UserProvisioningService` — a shared default password, baseline `Employee` role, and a welcome email with their credentials — whether they were added manually or via bulk Excel import.

There is **no true multi-tenant database isolation**. Every company shares one database; almost every query is scoped by a `company_id` column, resolved per-request by `Controller::resolveCompanyId()`:

1. The logged-in user's own linked `Employee.company_id`, if they have one.
2. An explicit `company_id` request parameter (used by an admin company-switcher).
3. The first company in the database that has any employee at all.
4. Hard fallback: company `1`.

Something outside Laravel periodically resets the RBAC tables (roles/permissions/assignments) back to an older snapshot; `php artisan rbac:repair-baseline` is the documented recovery command.

---

## Employee lifecycle

**Onboarding.** HR creates an employee record with a minimum required field set: company, name, gender, official email, mobile, date of joining, work type, department, designation, reporting manager, branch and/or location (depending on work type — see below), probation period, and PF applicability. Four documents are mandatory at creation: PAN, Aadhaar, marksheet, cancelled cheque. The new employee then logs in with their auto-provisioned credentials and completes the rest themselves via a self-service `/profile` page — personal details, statutory numbers, bank details, remaining optional documents. That self-service update path deliberately never touches the fields HR set at creation, so a tampered request can't change company/department/manager/etc.

**Work Type → Branch/Location split.** An employee's Work Type (Work from Office / Work from Home / Hybrid / Client Location) determines which location field applies:
- Work from Office → **Branch** required
- Work from Home → **Location** (a city, from a fixed dropdown) required
- Hybrid → both required
- Client Location → neither; a free-text **Client Name** field applies instead

**Master data.** Company, Department, Designation, Branch, and Location are all simple, company-scoped master-data tables (each with its own Add/Edit/Delete/Bulk-Delete page), with a guard preventing deletion of a record still assigned to any employee.

**Bulk import.** New employees can be created via an Excel template. Department/Designation/Branch/Location columns are "soft" dropdowns in that template — Excel's data validation shows suggestions but still allows typing any value not on the list, and the importer (`App\Imports\EmployeeImporter`, built on `BaseImporter`) auto-creates a matching master-data row for anything typed that doesn't already exist for that company.

---

## Attendance

Raw biometric punch data (`AttendanceRawPunch`) is imported and processed by `AttendanceService`, which computes a daily status (present / late / absent) against the employee's shift definition and the company's holiday calendar.

An employee can request a correction to a specific day via **Attendance Regularization**; once approved, the day's check-in/check-out and status are overwritten and the row is flagged `is_regularized`.

Separately — and this is the piece that actually matters for payroll — an Admin can explicitly mark a specific day's punch as `not_approved` via the Attendance Report page. **Only this flag feeds into payroll as Loss of Pay from attendance.** Raw attendance data and leave-tracker data do not directly cause LOP on their own; they feed into payroll through entirely separate calculations (see Payroll below).

A weekly scheduled job checks whether any employee averaged under 9 clocked hours for the week just ended and emails them a notice.

---

## Leave management

There are two leave tables in this schema; only one is live.

- **`LeaveTrackerRecord`** (current, real) — one row per employee per calendar day. New leave requests — from the Apply Leave modal, bulk Excel import, or manual tracker entry — all write directly here, with `is_approved`/`is_active` flags.
- **`LeaveApplication`** (legacy) — no longer written to by the real app.

Approving or rejecting a tracker row flips `is_approved`/`is_active`; approval also marks that day's `Attendance` row as `leave` (except for a few attendance-exception leave types — Early Leave, Late Arrival, Punch Miss Request — which represent a day the employee *did* work, so they don't overwrite attendance as an absence).

**Balances.** `LeaveEntitlementService` computes live CL/SL/Comp-Off balances straight from `LeaveTrackerRecord` usage for the current financial year — not from the separate `leave_balances` table, which exists in the schema but is never kept in sync and should not be trusted.

**Scoping distinction.** HR/Admin-facing views deliberately distinguish tracker rows that came from a real employee submission (`source_file = 'leave_application'`) from bulk-imported or manually-entered rows, so historical bulk-import data doesn't bury genuinely pending approvals in the main approval queue. A date-range filter lifts this restriction, so filtered searches still surface everything that actually matches.

**Notifications.** Submitting a request emails the reporting manager (to) with HR cc'd; resolving it emails the employee (to) with HR cc'd. A bell notification fires to the manager + HR the moment a request is submitted and clears itself automatically once the request is resolved. A daily 9:00 AM digest email goes to every manager and HR user listing their team's / the company's outstanding pending approvals.

**Known gap.** Comp-Off set-off (using up Casual Leave first before drawing down the separate Comp-Off pool) was only ever implemented in the dead API-layer `Leave\LeaveController` — it does not run for real leave requests submitted through the live app today.

**Calendar.** A Teams-style monthly leave calendar shows a per-day count to everyone, expands to show names for TL/Admin only (plain employees see leave-type only, not who), color-coded per leave type.

---

## Payroll — the core engine

This is the most complex process in the application, almost entirely contained in `WebSalaryReportController::compute()`. It is **read-only** — a live preview — until the month is explicitly locked.

### Step by step, per employee, per month

1. **Eligibility.** Include anyone who joined on or before the month's end and either hasn't left or left on/after the month's start (so someone who left mid-month still appears for that month), who has a current salary structure.

2. **Working days.** Calendar days in the month minus weekoffs and holidays. Weekoffs come from DB-configured `weekly_off` calendar entries if any exist for that company/month; otherwise a static fallback (all Sundays, plus company-specific Saturday rules). Named holidays are always merged in from the holiday calendar regardless of weekoff source. Mid-month joiners/leavers get this clipped to their actual days on payroll that month.

3. **Leave-based LOP.** FY-to-date (from April 1) CL/SL usage is tracked against each employee's full annual allocation — allocations are **not** pro-rated for new joiners, only for employees who left partway through the FY. The cascade:
   - Sick Leave used beyond its allocation spills into the Casual Leave pool.
   - Casual Leave used (including that spillover) beyond its allocation spills into the Comp-Off pool.
   - Whatever's still left over after that becomes Loss of Pay.
   - Only the **delta** versus the cumulative figure through last month counts as *this* month's leave-LOP, so the same shortfall is never deducted twice across months.

4. **Attendance LOP.** A simple count of this month's explicitly `not_approved` punches (see Attendance above), plus an explicit exception: approved **Exam Leave (EXL)** days also count as LOP for the month, by deliberate policy — unlike every other leave type.

5. **Carry-forward LOP.** Unresolved LOP from prior *unlocked* months within the same financial year, plus any gap an Admin override left under-deducted in a prior *locked* month. Both are strictly bounded to the current FY — nothing crosses the April 1 reset.

6. **Net Paid Days & Payable Gross.**
   - `Net Paid Days = Working Days − Total LOP + Weekoffs/Holidays`
   - `Payable Gross = round(Monthly Gross / Month Days × Net Paid Days)`

7. **Earnings split** (on Payable Gross):
   - `Basic = round(Payable Gross × 0.70)`
   - `HRA = round(Basic × 0.40)`
   - `Special Allowance = Payable Gross − Basic − HRA` (exact remainder)

8. **Statutory deductions:**
   - `Employee PF = min(₹1,800, round((Payable Gross − HRA) × 0.12))`, only if the employee is PF-applicable; Employer PF mirrors the employee contribution.
   - `Professional Tax = ₹200` flat if monthly gross exceeds ₹12,000, else ₹0.

9. **Other lines:** ad-hoc Incentive and Monthly TDS entries (separate override tables, entered manually or imported), active Loan EMI deductions (percentage-of-gross or fixed amount, capped at remaining outstanding balance), and approved Reimbursement claims for the month.

10. **Net Pay:**
    ```
    Net Pay = Payable Gross + Incentive + Reimbursement
              − (Employee PF + Professional Tax + Loan EMI + TDS)
    ```
    CTC and a "CTC with Diwali Bonus" figure (`Bonus = CTC` by default; `CTC with Bonus = round(CTC × 13/12)`) are also computed for reporting.

11. **Overrides.** Every one of the values above — LOP, Incentive, Advance, TDS, Payable Gross, Bonus — can be manually overridden per employee per month (`SalaryReportOverride`). When present, the override value is what flows through the rest of the calculation, not the computed one.

### Locking a month

Locking (`performLock()`) is the step that makes a month's numbers permanent, and it runs in a specific order:

1. **Process that month's loan EMI deductions for real** — creates or updates `LoanInstallment` rows as `paid`, decrements each loan's `outstanding_amount`, and auto-closes any loan that reaches zero. This is idempotent: re-locking an already-locked month skips installments already marked paid for that month.
2. **Re-derive the snapshot's advance and net-pay figures** from what was *actually* deducted in step 1 — not just the preview numbers — so a re-lock after a loan was partially paid off mid-process still reports the true figures.
3. **Write one immutable `SalaryReportSnapshot` row per employee** for that month. Everything downstream (payslip, Form 16, the employee's "My Payslips" page) reads from this frozen snapshot, never from a live recomputation.
4. **Dispatch one queued background job per employee** to render their payslip PDF (with the correct company logo) and email it to their real address via Microsoft Graph. This is deliberately queued rather than synchronous (50+ sequential API calls were making the lock request hang for a minute or more), and deliberately wired only into the explicit lock action — never into simply viewing or previewing the report page.

Form 16 generation follows the same locked-snapshot pattern.

---

## Loans & Advances

A loan/advance can be set up as either a fixed number of EMIs or a percentage-of-gross deduction — for the latter, the system back-calculates how many months it will take to pay off from the employee's current salary. If an Admin creates a loan for someone, it auto-approves itself immediately (skipping the normal approval queue, since requiring an Admin to separately approve their own action was redundant); anyone else's request goes to an approver. Approval generates the complete installment schedule up front (`generateSchedule()`), but no money actually moves until a payroll month that includes that employee is locked — that's when installments get marked paid and the loan's outstanding balance actually decreases.

---

## Salary Revision

Follows a genuine "never overwrite" versioning pattern: approving a pending revision closes out the employee's current `EmployeeSalary` row (`is_current = false`, `effective_to` set to the day before the new effective date) and inserts a brand-new row as the new current record, rather than mutating salary history in place. An `EmployeeHistory` audit entry is written alongside it.

---

## Reimbursements

An employee submits one claim per calendar month (blocked from submitting a second unless the first was rejected), with individual line items by type, date, description, and claimed amount. Approval is gated by a specific **permission**, not a role — deliberately scoped to one designated approver rather than "whoever is HR." Once a claim is approved, **payment status** is set separately, via a bulk Excel import against a dedicated tracker table (`payment_status` / `payment_date` / `payment_reference`) — not through the claim itself. The employee sees both their approval status and their payment status on their own claims page.

---

## Statutory compliance tracking

A compliance-challan ledger: PF/ESIC/PT/TDS/LWF challans with due dates, an overdue view, a due-this-month view, annual totals grouped by challan type, and a manual mark-as-paid action (challan number + payment date). This is a tracking/reminder tool, not an automated filing system — it does not generate ECR files, Form 16/24Q, PT challans, ESIC returns, or 26Q reports.

---

## Contractors

A contractor directory: add/edit/deactivate contractor records (name, type, PAN, GSTIN, bank details), search and filter, bulk Excel import. There is no invoice management, no payment tracking, and no TDS reporting for contractors in the live app.

---

## Helpdesk

Employees raise tickets against a category (which carries an SLA in hours, used to compute a `due_by` timestamp), with a subject, description, and priority. Resolution requires closure remarks, which are also logged as a comment on the ticket. Tickets can also be bulk-created via Excel import.

---

## Bio IDs, Monthly TDS, Incentives

- **Bio IDs** map employees to biometric device IDs, with a "next expected number" suggestion shown on the add form per device/type.
- **Monthly TDS** and **Incentives** are both simple per-employee-per-month override tables (manual entry or Excel import) that feed directly into the payroll formula's TDS and Incentive lines described above.

---

## Notifications

All outbound email goes through `OutlookMailerService`, which calls the Microsoft Graph API directly — Laravel's `Mail` facade is not used for real sends. Every call defaults to redirecting to a hardcoded test inbox unless the caller explicitly passes `useRealRecipient: true`; currently only payslip emails and the pending-leave-reminder digest do. Every outbound subject line carries an unconditional `[TEST MAIL]` prefix regardless of recipient, by design, while the project remains under active testing. In-app notifications use Laravel's built-in database notifications, surfaced via a bell dropdown shared across every page.

---

## Background processing & scheduling

Queued jobs (payslip emails) and scheduled commands (the daily leave reminder, weekly attendance check, RBAC/data-maintenance jobs) require infrastructure that isn't on by default:

- `QUEUE_CONNECTION` must be `database` (it defaults to `sync` in `.env.example`), with an actual `php artisan queue:work` process running.
- A system cron must invoke `php artisan schedule:run` every minute for any `Schedule::command(...)` entry in `routes/console.php` to ever fire.
- A long-running `queue:work` process caches PHP code in memory and does not pick up a new deploy until it's restarted — a very easy way to silently run stale logic after shipping a fix.

---

## README vs. reality

The project's `README.md` describes the system's original design intent, not its current state. Confirmed divergences:

| README claims | Actual live behavior |
|---|---|
| 8 migrations, 60+ tables | 75+ individual migration files, schema evolved well past the original design |
| API-first architecture (`/api/v1/...`) | The live app is entirely `routes/web.php` + Blade; the API layer described is mostly unused |
| "POI Workflow ✅ Complete" | No route exists in `web.php` — not reachable in the live app at all |
| "Investment Declaration (Form 12BB) ✅ Complete" | Same — API-only, no live route |
| "Contractor / Vendor Payroll ✅ Complete ... + TDS report" | Live `WebContractorController` is a plain directory (CRUD + import); no invoicing, no payments, no TDS report |
| Comp-Off set-off logic | Implemented, but only in the dead API-layer leave controller — doesn't run for real requests |
| 6-role RBAC table (`super_admin`, `hr_manager`, `payroll_admin`, `manager`, `employee`, `finance_admin`) | Live RBAC uses `Admin`/`HR`/`Employee` roles plus granular permissions managed via `/rbac`, not these named roles |

---

*Compiled by direct reading of the live controllers, services, and routes on 2026-10-03 — not from the README, and not from assumptions about what a typical HRMS would do.*
