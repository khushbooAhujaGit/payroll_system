# Attendance Geofencing App — Backend Implementation Plan

Covers items 53–56 from `HRMS System Workflow.xlsx` (items 1–52 are historical/already shipped; item 52 "Search issue on every page" was fixed separately this session via the AJAX search conversion).

**Scope of this plan:** the Laravel backend/API side only — new data model, API endpoints for the mobile app to call, and the HR/Admin-facing Blade UI pieces. The React Native app itself (screens, background location, wifi detection, app-store builds) is being built by a separate mobile team and is **not** estimated in detail here — see the note at the end.

---

## 1. What's actually being asked for (my reading of items 53–56)

| # | Item | What it means |
|---|------|----------------|
| 53 | Introduce attendance via a React Native app with geofencing | Headline item — the rest below is its breakdown. |
| 54.1 | Employee maps their attendance place on first login | WFH / Client Location / Hybrid employees set **one** lat/long point (home or client site). Office (WFO) employees don't need to map anything — **any** company branch location is automatically valid for them. |
| 54.2–54.4 | Office wifi networks mapped per branch; connecting = auto mark-in (only one time within a day), disconnecting = auto mark-out (along with time check); manual mark-out always available | Admin registers one or more wifi networks (SSID) per office/branch from a new admin screen. The app watches for connection/disconnection to a registered network. |
| 54.5 | HR manual mark-in/out **overrides** the system | Confirmed design: a **separate table**. When an HR override exists for an employee+date, it is used everywhere (payroll LOP, Attendance Report, My Attendance) **instead of** the system-recorded punch for that day — the system punch stays in the database but is not shown/used for that day. |
| 54.5 (cont.) | New Excel upload format, to eventually replace the current one | Template requires picking Month/Year first, Employee ID is a dropdown scoped to employees active that month, Employee Name auto-fills and is locked, plus Date, Mark In, Mark Out, Reason (optional). The current biometric/manual upload stays live in parallel for now. |
| 54.6 | First mark-in / last mark-out wins; full history kept for analytics | Already exactly how `WebReportController::buildAttendanceRows()` resolves today's biometric punches — same rule extends to app-sourced punches. The app will also send many raw punch *events* (every open/close of the day), which need their own log table distinct from the "final" resolved day record. |
| 55 | Flag "Unknown location" if a mark happens >50m from the mapped point | Haversine-distance check at mark-in/out time; flagged day requires the employee to supply a reason, shown on My Attendance. |
| 56 | Daily 10:30 AM exception email to management | Configurable To/Cc recipient list (editable from HR login), lists who hasn't arrived / is on leave / flagged, with an attendance Excel attached. Fully backend — no dependency on the mobile app, buildable and shippable independently. |

---

## 2. Confirmed design decisions (from our Q&A)

- **Backend/API only** — mobile app build is a separate team's responsibility.
- **Platforms:** Android + iOS (I don't build this, but API design should not assume Android-only quirks).
- **WFO geofence scope:** any company office location is valid, not just the employee's assigned branch.
- **Mapped location:** one point at a time per employee (re-mapping replaces the old point, no multi-location picker).
- **Wifi networks:** managed from a new Admin panel screen, supports multiple networks per branch.
- **HR override:** separate table, wins outright for that employee+date across every consumer (payroll, My Attendance, Monthly Report) — system-recorded punches are kept in the DB but excluded from all calculations for that day once an override exists.
- **Daily mail recipients:** a configurable To + Cc list, editable from HR login (not derived from RBAC roles, not per-manager).

### Assumptions I'm carrying forward (flag if wrong before I start coding)

- Deviation radius is a fixed 50m for everyone (not configurable per employee) — matches item 55's literal wording.
- The rebuilt Excel importer (54.5) writes into the **same HR-override table** as the manual modal — it's bulk manual entry, so it should behave identically (full override), not merge as just another punch source.
- Initial location mapping is self-service (employee sets it in the app, no HR approval step blocking it) — HR can still view/audit it from the web side.
- "Management" for the daily mail is company-wide by default per the recipient list, not filtered per-branch/company — since this is a multi-company app, I'll scope the recipient list (and the report itself) to **per-company**, matching how every other report in this codebase already uses `resolveCompanyId()`.

---

## 3. New data model

Four new tables, all MyISAM to match the rest of this database (per the project's existing convention — see `CLAUDE.md`'s MyISAM note; this also means none of the multi-row writes below get real transactional rollback, so error handling needs to be careful about partial writes).

1. **`employee_attendance_locations`** — one row per employee (unique on `employee_id`).
   `employee_id`, `latitude`, `longitude`, `label` (e.g. "Home" / "Client Site"), `mapped_at`, `updated_at`.

2. **`office_wifi_networks`** — admin-managed, many rows per branch.
   `id`, `company_id`, `branch_id` (nullable — some networks may span a whole site), `ssid`, `bssid` (nullable, more precise than SSID alone), `is_active`, `created_by`, timestamps.

3. **`attendance_app_punch_events`** — full raw log of every app-originated mark in/out, never summarized away (this is the "all logs maintained for analytics" requirement).
   `id`, `employee_id`, `event_type` (`in`/`out`), `method` (`gps`/`wifi`/`manual_app`), `event_at` (datetime), `latitude`, `longitude` (nullable, gps method only), `distance_from_mapped_m` (nullable), `is_deviation` (bool), `deviation_reason` (nullable), `wifi_ssid` (nullable), `source_app_version` (nullable, useful for debugging).

4. **`attendance_hr_overrides`** — the "wins over everything" table.
   `id`, `employee_id`, `attendance_date`, `check_in` (nullable), `check_out` (nullable), `reason`, `source` (`manual`/`excel_import`), `created_by`, `source_file` (nullable, for the Excel path), timestamps. Unique on (`employee_id`, `attendance_date`).

---

## 4. The hard part: `AttendanceDayResolver`

This is the single highest-risk piece of the whole plan, because it has to be threaded into **every place that currently reads attendance directly**, without breaking the payroll/report logic already in production:

- `WebReportController::buildAttendanceRows()` — the Monthly Attendance Report engine.
- `AttendanceService::getMonthlySummary()` — payroll LOP calculation.
- `AttendanceWeeklyAverageService::forEmployeeDateRange()` — weekly-average shortfall notifications.
- `WebMyAttendanceController` — the employee's own attendance view.

**New resolution order, for a given employee + date:**
1. Check `attendance_hr_overrides` first. If a row exists, use its `check_in`/`check_out` exclusively — ignore everything else for that day.
2. Otherwise, merge `attendance_app_punch_events` (first `in`, last `out`) with the existing `attendance_raw_punches` (biometric/manual-web-punch) using the same earliest-in/latest-out rule already in place today.

I'll build this as a single service class (`AttendanceDayResolver`) and swap each of the four consumers above to call it instead of querying `AttendanceRawPunch` directly. This preserves every existing calculation's shape (same `punch_in_type`/`clocked_time`/LOP outputs) — only the *input* changes.

---

## 5. API surface (for the React Native app)

Sanctum-token-authenticated, under `/api/app/...` (the existing, mostly-dormant `routes/api.php` layer — see `CLAUDE.md`'s two-controller-layers note; this would be the first real use of that layer in a while, so it needs a fresh look rather than assuming anything currently there is wired correctly):

- `GET  /api/app/location` — current mapped location + list of valid office locations/wifi networks for this employee's work type.
- `POST /api/app/location` — set/replace the employee's mapped point.
- `POST /api/app/attendance/mark-in` — body: `method` (gps/wifi), coordinates or SSID.
- `POST /api/app/attendance/mark-out` — same shape.
- `POST /api/app/attendance/deviation-reason` — attach a reason to a flagged event.

---

## 6. Admin/HR-facing web UI pieces (this Blade app)

1. **Office Wifi Networks** — new Masters-style CRUD page (list/add/edit/delete), scoped by company and branch — same pattern as Holidays/Departments.
2. **HR Manual Override** — extend the existing "Add Punch" modal on `attendance/index.blade.php` (or a new dedicated modal) to write into `attendance_hr_overrides` instead of `attendance_raw_punches`, with a visible "Overridden" badge wherever that day is shown on the Attendance Report / My Attendance.
3. **Rebuilt Excel importer** — the existing `AttendanceImporter`/`attendance.import`/`attendance.template` routes are currently dead code (confirmed broken — fatal error on instantiation, from last session's research). This gets rebuilt to the new spec: Month/Year selected before download, Employee ID dropdown scoped to employees active that month, Name locked, Date/Mark In/Mark Out/Reason columns, writing into `attendance_hr_overrides`.
4. **Daily Mail recipients settings** — simple To/Cc list editor, under HR-accessible settings.
5. **"Unknown location" indicator** — badge + reason display on My Attendance for flagged days.

---

## 7. Daily exception mail (item 56) — independently buildable today

No mobile-app dependency at all — this can be built and shipped on its own:

- New scheduled Artisan command, run daily at 10:30 AM via the existing Laravel scheduler (needs the system cron already documented in `CLAUDE.md` to actually be running every minute).
- Per company: pulls employees with no attendance record yet today (via the resolver above), employees on approved leave today (`LeaveTrackerRecord`), and any flagged-deviation reasons.
- Sent through `OutlookMailerService` (not Laravel Mail, per this project's convention) with `useRealRecipient: true`.
- HTML email template (needs a design pass) + an `.xlsx` attachment of the day's attendance, reusing the existing Excel-building logic already in `WebReportController`.
- To/Cc pulled from the new recipient-list settings in §6.4.

---

## 8. Tentative timeline

Estimated in working days of iterative, AI-assisted development with your review/testing between pieces — the same cadence we've used this session (build a piece, verify in-browser, move on).

| Piece | Estimate |
|---|---|
| New data model (4 migrations + models) | 0.5–1 day |
| `AttendanceDayResolver` + rewiring the 4 existing consumers | 2–3 days |
| API endpoints (location, mark-in/out, deviation-reason) + Sanctum wiring | 1.5–2 days |
| Office Wifi Networks admin CRUD | 0.5 day |
| HR Manual Override UI + "Overridden" badges | 1–1.5 days |
| Rebuilt Excel importer (new format, month-scoped dropdown) | 1–1.5 days |
| "Unknown location" deviation UI on My Attendance | 0.5–1 day |
| Daily exception mail (command + template + attachment + recipient settings) | 1.5–2 days |
| End-to-end regression pass (payroll, Attendance Report, My Attendance) | 1–2 days |
| **Total** | **~10–15 working days (roughly 2–3 calendar weeks)** |

**Mobile app (React Native) — you're building this yourself.** See §9 below for a dedicated timeline.

---

## 9. Mobile app (React Native) — self-build timeline

This is a solo-developer estimate, broken into the same kind of phases as the backend above. A few things make this harder than a typical CRUD app, worth knowing going in:

- **Background location is the single biggest risk**, especially on iOS. Android's `ACCESS_BACKGROUND_LOCATION` is a runtime permission with a scary-but-manageable system dialog; iOS requires "Always" location authorization, a precise Info.plist justification string, and Apple reviewers specifically scrutinize apps that track location in the background — budget for at least one rejection-and-resubmit cycle if this is your first background-location app.
- **Wifi-SSID detection on iOS is much more restricted than Android.** `react-native-wifi-reborn` / `NetInfo` can read the *currently connected* SSID on Android easily. On iOS, reading the SSID requires `NEHotspotHelper` (an Apple entitlement you have to apply for and that's rarely granted to small teams) or `CNCopyCurrentNetworkInfo` which needs location permission and generally only works while the app is in the foreground — true background wifi-triggered mark-in on iOS may not be reliably achievable without that entitlement. Worth deciding early whether iOS wifi auto-mark-in is a hard requirement or a "best effort, foreground-only" feature, since it changes the iOS timeline meaningfully.
- **Real-device testing is mandatory**, not optional — simulators/emulators don't produce trustworthy GPS or wifi behavior for this kind of feature.
- **Google Play's closed-testing requirement**: if this is a new (or not-yet-established) Play Console developer account, Google requires 12+ testers actively using a closed test track for 14 continuous days before you can publish to production — this is a calendar-time constraint, not a dev-effort one, so it's worth starting as early as possible in parallel with development rather than after it.

| Phase | What | Estimate |
|---|---|---|
| 1 | Project setup, navigation, auth (Sanctum token login against the new API), basic app shell | 3–5 days |
| 2 | Location-mapping screen (one-time home/client-site point capture) + wiring to `/api/app/location` | 2–3 days |
| 3 | GPS geofencing — background location tracking, entry/exit detection, the 50m deviation check | 5–8 days |
| 4 | Wifi-based mark-in/out — Android first (straightforward), then iOS (foreground-only fallback unless you pursue the `NEHotspotHelper` entitlement) | 4–7 days |
| 5 | Manual mark-out fallback + deviation-reason prompt UI | 2–3 days |
| 6 | Offline handling — queue marks locally and retry when connectivity returns, so a dead zone doesn't lose a punch | 2–3 days |
| 7 | Push notifications (optional but useful — remind to mark out, surface a flagged deviation) | 2–3 days |
| 8 | Polish — error/loading states, app icon, splash screen, empty states | 2–3 days |
| 9 | Real-device testing (Android + iOS) and bug-fixing | 4–6 days |
| 10 | Store submission prep — screenshots, store listing text, privacy policy covering background location data use (both stores require this explicitly for location-tracking apps) | 2–3 days |
| **Dev total** | | **~27–41 working days (roughly 5.5–8 weeks)** |
| 11 | Review/release buffer — Apple review cycles (budget 2 rounds for a background-location app) + Google Play's mandatory 14-day closed-testing window before production | **+1–3 weeks**, largely calendar time rather than dev effort, and can run partly in parallel with phases 7–10 if you start the Play closed track early |

**Combined realistic range, dev effort + store buffers: roughly 7–11 weeks**, assuming you're working on it close to full-time and have *some* prior React Native experience. If this is your first React Native app, add 1–2 weeks for tooling/learning-curve friction, especially around the native-module pieces in phases 3–4 (geofencing and wifi detection both usually need a native library, not pure JS).

The two workstreams only truly depend on each other at the API contract in §5 — you can start phase 1–2 of the app against mocked responses before the backend pieces in §3–4 are finished, as long as the request/response shapes are agreed first.

---

## 10. Suggested build order

1. Data model + `AttendanceDayResolver` first (§3–4) — everything else depends on this being correct, and it's the riskiest piece to get right against existing payroll logic.
2. Daily exception mail (§7) — fully independent, quick win, immediately useful even before the app exists.
3. HR Manual Override UI + rebuilt Excel importer (§6.2–6.3) — gives HR the override capability (and its replacement for the current Excel flow) without waiting on the mobile app.
4. Office Wifi Networks admin screen (§6.1) — needed before you can test wifi-based mark-in from the app.
5. API endpoints (§5) — once the resolver and override table are solid, these are ready for the app to build against (though per §9, you can start the app's early screens against mocked responses before this point).
6. "Unknown location" UI (§6.5) — last, since it only matters once real app-sourced events are flowing in.

No code has been written for any of this yet — this is the plan only, per your instruction.
