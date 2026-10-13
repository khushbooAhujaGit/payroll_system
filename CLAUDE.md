# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A Laravel 11 / PHP 8.1+ HRMS + Payroll platform for the Indian market (multi-company: PAN/Aadhaar/UAN/PF/ESIC/PT/LWF/TDS). The live, actually-used product is a server-rendered Blade application — there is no SPA and no frontend build step (no `package.json`).

## Commands

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate                              # 75+ migrations, order matters — run in sequence, not individually out of order
php artisan rbac:repair-baseline                 # seeds Admin role/account + full permission catalog — run this, not an old PermissionSeeder reference in README.md
php artisan storage:link
php artisan serve
```

- **Code style:** `vendor/bin/pint` (Laravel Pint is installed; no custom `pint.json`, so it uses Pint's default Laravel preset).
- **No automated test suite exists** — there is no `tests/` directory and no `phpunit.xml`, despite `phpunit`/`mockery`/`faker` being present in `composer.json require-dev`. Don't assume a test command works; don't invent test files unless asked.
- **Queued features require a worker + scheduler**, neither of which runs by default (`QUEUE_CONNECTION` defaults to `sync` in `.env.example`, but production/local dev here needs it set to `database`):
  ```bash
  php artisan queue:work          # processes SendPayslipEmailJob etc.
  php artisan schedule:run         # normally invoked every minute by a system cron; schedule:list shows what's due
  ```
  A long-running `queue:work` process caches PHP code in memory — restart it after deploying code changes, or jobs will silently run stale logic.
- Other non-obvious artisan commands worth knowing about (`php artisan list` shows all): `rbac:add-missing-permissions`, `leave:send-pending-reminders`, `leave:sync-balances`, `leave:year-close`, `documents:fetch-onedrive`, `payslips:regenerate`, `accounts:deactivate-leavers`, `employees:backfill-user-accounts`, `encrypt:existing-data`.

## Architecture

### Two parallel controller/route layers — only one is live

This is the single most important thing to know before touching this codebase. There are two separate, independently-routed implementations of most features:

- **`routes/web.php` + `App\Http\Controllers\Web\Web*Controller` + Blade views** — this is the real, actually-deployed application (`hrms.escrips.in`, `payroll.paymentguru.in`). All controllers here are named `Web{Thing}Controller` (`WebEmployeeController`, `WebLeaveController`, `WebDepartmentController`, …) and render Blade views, not JSON.
- **`routes/api.php` + non-`Web`-namespaced controllers** (`App\Http\Controllers\HR\EmployeeController`, `Leave\LeaveController`, `Payroll\PayrollController`, etc., Sanctum-authenticated) — this is the original API-first design described in `README.md`. At least `Leave\LeaveController` is confirmed **not** wired into the live site at all (a whole Comp-Off leave feature was once built into it by mistake, with zero effect on production, because the real leave flow runs through `WebLeaveController`). Treat the rest of `routes/api.php` with the same suspicion: **before extending or trusting any non-`Web` controller, verify something actually calls it** — don't assume parity with its `Web*` counterpart.

`README.md`'s architecture diagram, API endpoint list, RBAC role table, and "Stage N" delivery plan describe this original API-first design, not the current reality — e.g. it says 8 migrations/60 tables; there are actually 75+ migration files now. Read it as historical design intent, not as current state.

### Multi-company scoping

Every `Web*Controller` extends `App\Http\Controllers\Controller`, which provides `resolveCompanyId(?Request $request = null): int`. This is how multi-company scoping works everywhere — there is no tenant-per-database isolation, just a `company_id` column on most tables filtered via this one helper. Resolution order: (1) the logged-in user's own linked `Employee.company_id`, (2) an explicit `company_id` request param (used by the admin company-switcher), (3) the first company that has any employee, (4) hard fallback to `1`. A route handler or background job that builds a dropdown/list from company-scoped master data (`Department`, `Designation`, `Branch`, `Location`) **must** filter by this resolved company ID explicitly — several of these were previously unscoped and leaked every company's rows into one dropdown.

### RBAC

Spatie `laravel-permission`, with `User::isSuperAdmin()` (role name `Admin`) wired into `Gate::before()` in `AppServiceProvider` — an Admin bypasses every `$this->authorize()`/`@can` check unconditionally. Something outside Laravel periodically resets this database to an older snapshot, wiping `roles`/`permissions`/`model_has_roles`; `php artisan rbac:repair-baseline` is the documented recovery path (idempotent — re-seeds Admin + the full permission catalog from a static list in the command itself, only re-seeds HR/Employee default permission sets if those roles currently have zero permissions, and only syncs Admin to hold every permission on every run). When you add a new `authorize('some_permission')` call, add the matching slug to that command's catalog array, or run `rbac:add-missing-permissions` afterward — otherwise the permission silently doesn't exist until someone manually creates it via `/rbac`.

### Leave management: two tables, one is legacy

`LeaveApplication` (legacy) and `LeaveTrackerRecord` (current, one row per employee per calendar day) both exist. New leave requests — from the Apply Leave modal, from bulk Excel import, from manual tracker entry — write directly to `LeaveTrackerRecord` (`is_approved`/`is_active` flags); `LeaveApplication` is no longer written to by the live app. `LeaveEntitlementService` is the single live source of CL/SL/Comp-Off balances (computed from `LeaveTrackerRecord` usage), not the `leave_balances` table, which is stale/unmaintained. A `source_file` column on `LeaveTrackerRecord` distinguishes rows that came from an actual Apply Leave submission (`source_file = 'leave_application'`) from bulk-import/manual tracker rows — several HR/Admin views deliberately filter to just the former so historical bulk-import data doesn't bury real pending requests.

### Mail goes through Microsoft Graph, not Laravel Mail

All outbound mail goes through `App\Services\OutlookMailerService`, which calls the Microsoft Graph API directly (`config('services.msgraph.*')`, env vars `MS_GRAPH_CLIENT_ID`/`MS_GRAPH_CLIENT_SECRET`/`MS_GRAPH_TENANT_ID`/`MS_GRAPH_SENDER_EMAIL`) — Laravel's `Mail` facade and `MAIL_MAILER` config are not used for real sends. `sendMail()` defaults `useRealRecipient` to `false`, meaning every call silently redirects to a hardcoded test inbox unless the caller explicitly opts in — currently only payslip sending (`SendPayslipEmailJob`) and the pending-leave-reminder command pass `useRealRecipient: true`. The subject line always gets an unconditional `[TEST MAIL]` prefix regardless of recipient, by design, while this project is still "under testing."

### File storage is under `public_path()`, deliberately unauthenticated

Payslips, Form 16s, and employee documents are written under `public_path()` (via `PublicFileStore`, `PayslipPdfService`) rather than Laravel's `storage/app` disk — a deliberate, explicitly-confirmed decision, not an oversight, so don't "fix" it by moving files back to a storage disk. It means any file under those paths is reachable by direct URL with no auth check at all if the path/token is known. On shared hosting, a bulk file/zip deploy can leave these folders owned by the wrong system user (e.g. `root` instead of the hosting account), which breaks `mkdir()` for new uploads with a permission-denied error that has nothing to do with Laravel — check `ls -la` ownership on the server before assuming it's a code bug.

### Excel bulk import framework

`App\Imports\BaseImporter` is the abstract base (`getTemplateHeaders()`, `getRequiredHeaders()`, `getColumnValidations()`, `mapRow()`, `validate()`, `process()`); `EmployeeImporter` is the concrete implementation `ExcelImportService` drives for both template generation and upload processing. Dropdown columns in generated templates (Department, Designation, Branch, Work Location, Company) use Excel's WARNING-style data validation (`ExcelImportService::applyDropdownValidation()`) — the dropdown is a suggestion, not an enforced list; typing an unlisted value is allowed by Excel, and `resolveOrCreateMasterId()` on the importer side auto-creates a matching master-data row for any such value on import. `headerToField()` keeps a legacy header-name alias block so older exported-and-reimported files and old template versions still map correctly — don't remove entries from it when renaming current headers.

### Frontend: Blade + vanilla JS, no build step

No Vite, no npm, no `package.json`. Shared JS lives inline in `resources/views/layouts/app.blade.php`: `om(id)`/`cm(id)` open/close any `.modal`, `swConfirm(form, title, text, icon, btnText, btnColor)` wraps SweetAlert2 for a confirm-then-submit pattern, and `onRowCheck()`/`toggleAll()`/`bulkDelete()` are generic helpers for the checkbox-select-then-bulk-delete pattern used on the simple master-data pages. Critically: **every `<select>` with 5+ options on the page is automatically progressively-enhanced** into a custom searchable-dropdown widget by a `MutationObserver`-driven script in that same layout file — this hides the native `<select>` (`display:none`) and replaces it with a styled text input + dropdown. Setting a `<select>`'s `.value` via JS (e.g. to pre-fill an Edit modal) does **not** update that visible widget; you must also call `window._ssSync(selectEl)` afterward (the existing `setVal(id, val)` helper in `employees/index.blade.php` already does this — follow that pattern for any new select you populate programmatically).

## A real, discovered data-integrity gotcha

Most tables in this database (confirmed: 100 of 109 locally) use the **MyISAM** storage engine, not InnoDB — `DB::transaction()`/`DB::rollBack()` silently do nothing on them; writes are permanent the instant they execute. This matters for two things: (1) the app's own `DB::transaction()` calls elsewhere in the codebase are not actually atomic if the table involved is MyISAM, and (2) if you write a throwaway verification script that creates test rows "inside a transaction, then rolls back," **check the table's engine first** (`SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_NAME = '...'`) — otherwise you will leave real stray rows behind and have to clean them up by hand afterward.
