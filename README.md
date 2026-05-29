# HRMS + Payroll Platform — Laravel

Complete HR & Payroll SaaS for the Indian market. Multi-company, multi-entity, API-first.

---

## Quick Setup

```bash
# 1. Clone and install
composer install
cp .env.example .env
php artisan key:generate

# 2. Configure database in .env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hrms_payroll
DB_USERNAME=root
DB_PASSWORD=secret

# 3. Run migrations (order matters — 000 first)
php artisan migrate

# 4. Seed roles, permissions, TDS slabs
php artisan db:seed --class=PermissionSeeder
php artisan db:seed --class=TdsSlabSeeder

# 5. Storage link
php artisan storage:link

# 6. Start server
php artisan serve
```

---

## Architecture

```
┌─────────────────────────────────────────────────────┐
│                   API (Laravel 11)                  │
│           Auth: Sanctum  │  RBAC: Custom            │
├──────────────┬──────────────────────┬───────────────┤
│   Employee   │      Payroll         │   Statutory   │
│   Master     │      Engine          │   Compliance  │
├──────────────┼──────────────────────┼───────────────┤
│  Attendance  │   Salary Revision    │  Form 12BB    │
│  Leave Mgmt  │   Loan & Advance     │  POI Workflow │
├──────────────┼──────────────────────┼───────────────┤
│  Contractor  │   Bank File Gen      │  Reimbursement│
│  Payroll     │   (HDFC/ICICI/SBI)   │  F&F          │
├──────────────┼──────────────────────┼───────────────┤
│  Manager     │   HR Analytics       │  Helpdesk     │
│  Dashboard   │   Dashboard          │  Ticketing    │
└──────────────┴──────────────────────┴───────────────┘
           PostgreSQL / MySQL  │  Redis (Cache/Queue)
```

---

## Database — 8 Migration Files → 60+ Tables

| Migration | Tables Created |
|-----------|---------------|
| `000_users_rbac` | users, roles, permissions, role_permissions, user_roles, audit_logs, notifications, excel_upload_logs, approval_workflows, system_settings, communication_logs, nps_contributions |
| `001_companies` | companies, entities, branches, departments, designations, grades, cost_centers, holiday_calendars, holidays |
| `002_employees` | employees, employee_documents, employee_bank_details, employee_statutory_details, employee_history, onboarding_templates, onboarding_tasks, employee_onboarding, exit_requests, employee_education, employee_experience |
| `003_attendance` | shifts, employee_shifts, rosters, employee_rosters, attendance, attendance_regularizations, overtime_requests, attendance_upload_logs |
| `004_leave` | leave_types, leave_policies, leave_balances, leave_applications, leave_approvals, comp_offs |
| `005_payroll` | salary_components, salary_structures, salary_structure_components, employee_salaries, employee_salary_components, salary_revisions, payroll_periods, payroll_runs, payroll_employee_records, payroll_components, payroll_reconciliation, payroll_arrears |
| `006_statutory` | pf_settings, esic_settings, pt_slabs, lwf_slabs, tds_slabs, investment_declarations, declaration_hra, declaration_investments, poi_submissions, employee_tds_computation, compliance_challans, pf_ecr_registers |
| `007_loans_etc` | loan_types, employee_loans, loan_installments, reimbursement_types, reimbursement_claims, reimbursement_items, full_final_settlements, gratuity_provisions, bank_transfer_files, contractors, contractor_invoices, bonus_rules, employee_bonus, helpdesk_categories, helpdesk_tickets, helpdesk_comments |

---

## Key Design Decisions

### Salary Versioning (Never overwrite)
```
employee_salaries (is_current=true)  ← always points to active record
    ↓ on increment/revision
old record: effective_to = new_date - 1, is_current = false
new record: effective_from = new_date, is_current = true
```

### Payroll Immutability
- Payroll runs are **never deleted** after finalization
- Admin overrides tracked: old value, new value, reason, who, when
- `is_editable = false` after finalize

### Zero / Blocked Payroll
```
POST /payroll/records/{id}/block  { reason: "On suspension" }
→ net_salary = 0, status = blocked, payslip shows ₹0
POST /payroll/records/{id}/unblock
→ auto-recalculates
```

### Payroll Lock/Unlock
```
POST /payroll/periods/{id}/lock
→ period.is_locked = true
→ ALL attendance records in period locked (is_locked = true)
→ No further edits to attendance or payroll

POST /payroll/periods/{id}/unlock  { reason: "..." }
→ Reverses lock (full audit trail preserved)
```

---

## Payroll Calculation Flow

```
1. Get eligible employees (active, payroll_active, joined before period end)
2. For each employee:
   a. Pull attendance summary (working_days, present, LOP, leave)
   b. Load current salary structure + component breakdown
   c. Calculate each component (fixed / % / formula)
   d. Pro-rate by payable days
   e. Apply LOP deduction
   f. Compute statutory: PF, ESIC, PT, LWF, TDS
   g. Process loan EMI deductions
   h. Add pending arrears
   i. Add approved bonus
   j. Net = Gross Earnings - All Deductions
3. Save to payroll_employee_records + payroll_components
4. Admin can override any component or final net (tracked)
5. Finalize → reconciliation generated → bank file → payslips
```

---

## API Endpoints Summary

### Auth
```
POST   /api/v1/auth/login
POST   /api/v1/auth/logout
GET    /api/v1/auth/profile
PUT    /api/v1/auth/profile
POST   /api/v1/auth/change-password
GET    /api/v1/auth/permissions
```

### Employee Master
```
GET    /api/v1/employees                    # list with filters
POST   /api/v1/employees                    # create
GET    /api/v1/employees/{id}              # detail with all relations
PUT    /api/v1/employees/{id}              # update
POST   /api/v1/employees/{id}/confirm      # probation → confirmed
POST   /api/v1/employees/{id}/transfer     # dept / branch transfer
GET    /api/v1/employees/export            # Excel download
GET    /api/v1/employees/import/template   # template download
POST   /api/v1/employees/import            # Excel upload
```

### Payroll
```
GET    /api/v1/payroll/periods
POST   /api/v1/payroll/periods
POST   /api/v1/payroll/periods/{id}/lock
POST   /api/v1/payroll/periods/{id}/unlock
POST   /api/v1/payroll/runs                           # init run
POST   /api/v1/payroll/runs/{id}/calculate            # run calculation
GET    /api/v1/payroll/runs/{id}/salary-sheet         # review grid
POST   /api/v1/payroll/runs/{id}/finalize
POST   /api/v1/payroll/records/{id}/override-net      # admin edit
POST   /api/v1/payroll/records/{id}/block             # zero payroll
POST   /api/v1/payroll/records/{id}/unblock
POST   /api/v1/payroll/runs/{id}/bank-file            # generate bank file
POST   /api/v1/payroll/runs/{id}/generate-payslips
GET    /api/v1/payroll/runs/{id}/reconciliation       # month-over-month diff
GET    /api/v1/payroll/runs/{id}/payroll-register     # Excel export
```

### Salary Revision
```
GET    /api/v1/payroll/salary-revisions
POST   /api/v1/payroll/salary-revisions               # initiate
POST   /api/v1/payroll/salary-revisions/{id}/approve  # implement
POST   /api/v1/payroll/salary-revisions/{id}/reject
GET    /api/v1/payroll/salary-revisions/import/template
POST   /api/v1/payroll/salary-revisions/import        # bulk increment Excel
```

### Loans & Advances
```
GET    /api/v1/loans
POST   /api/v1/loans
GET    /api/v1/loans/{id}
POST   /api/v1/loans/{id}/approve                     # generates EMI schedule
POST   /api/v1/loans/{id}/reject
POST   /api/v1/loans/{id}/foreclose
GET    /api/v1/loans/{id}/emi-schedule
POST   /api/v1/loans/installments/{id}/waive
POST   /api/v1/loans/import                           # bulk loan upload
```

### Investment Declaration (Form 12BB)
```
GET    /api/v1/investment-declarations/my             # employee: my declaration
POST   /api/v1/investment-declarations/my             # save draft
POST   /api/v1/investment-declarations/my/submit      # submit for HR
GET    /api/v1/investment-declarations/sections       # section limits (80C, 80D...)
GET    /api/v1/investment-declarations                # HR: all declarations
POST   /api/v1/investment-declarations/{id}/approve
```

### POI Workflow
```
POST   /api/v1/poi/upload                             # employee: upload proof
GET    /api/v1/poi/my                                 # employee: my submissions
GET    /api/v1/poi/pending                            # HR: review queue
POST   /api/v1/poi/{id}/review                        # approve/reject/resubmit
POST   /api/v1/poi/bulk-review
```

### Contractor Payroll
```
GET    /api/v1/contractors
POST   /api/v1/contractors
GET    /api/v1/contractors/{id}/invoices
POST   /api/v1/contractors/{id}/invoices
POST   /api/v1/contractors/invoices/{id}/approve
POST   /api/v1/contractors/invoices/{id}/mark-paid
GET    /api/v1/contractors/tds-report                 # 26Q data
```

### Helpdesk
```
POST   /api/v1/helpdesk/tickets                       # employee: raise
GET    /api/v1/helpdesk/tickets/my                    # employee: my tickets
GET    /api/v1/helpdesk/tickets/agent                 # HR/IT: agent view
POST   /api/v1/helpdesk/tickets/{id}/resolve
POST   /api/v1/helpdesk/tickets/{id}/close            # with rating
GET    /api/v1/helpdesk/analytics                     # SLA, volume stats
```

### Dashboards
```
GET    /api/v1/dashboards/manager                     # my team view
GET    /api/v1/dashboards/manager/attendance-trends
GET    /api/v1/dashboards/hr-analytics                # company-wide
GET    /api/v1/dashboards/hr-analytics/payroll-trend
GET    /api/v1/dashboards/hr-analytics/attrition-trend
GET    /api/v1/dashboards/hr-analytics/leave-utilisation
```

---

## RBAC — 6 Default Roles

| Role | Key Capabilities |
|------|-----------------|
| `super_admin` | Everything |
| `hr_manager` | All HR ops, leave approvals, declarations, helpdesk |
| `payroll_admin` | Payroll run, lock, bank file, statutory, TDS |
| `manager` | View team, approve leave/regularization, manager dashboard |
| `employee` | ESS — apply leave, submit declaration, POI, loans, tickets |
| `finance_admin` | Loan approvals, reimbursements, contractor payments |

---

## Stage-wise Delivery Plan

### ✅ Stage 1 — COMPLETE (This delivery)
- Full database schema (60+ tables across 8 migrations)  
- Employee Master (CRUD, Excel import/export, documents, lifecycle)
- Payroll Engine (component calc, LOP, statutory, loan deductions, arrears)
- Statutory Service (PF, ESIC, PT, LWF, TDS with monthly recalculation)
- All 12 requested features (see below)
- RBAC (roles, permissions, middleware, seeder)
- Universal Excel import framework

### ⏳ Stage 2 — Attendance & Leave UI Completion
- AttendanceController full endpoints (biometric sync, regularization UI)
- LeaveController full endpoints (accrual engine, comp-off, encashment)
- Shift management
- Holiday calendar management

### ⏳ Stage 3 — Statutory Outputs
- ECR file generation (PF)
- Form 16 / Form 24Q
- PT challan, ESIC return
- 26Q (contractor TDS)

### ⏳ Stage 4 — ESS Portal + Reimbursements
- Employee self-service (payslip download, leave balance, tax computation)
- Full reimbursement workflow
- Full & Final Settlement automation
- Gratuity provisioning

### ⏳ Stage 5 — Reports & Analytics
- Custom report builder
- Payroll register, PF register, ESIC register exports
- HR dashboard with charts (headcount, attrition, cost trends)
- Compliance dashboard (challan status, ECR status)

---

## All 12 Requested Features — Status

| Feature | Status | File |
|---------|--------|------|
| Loan & Advance Management | ✅ Complete | `LoanController.php` + `loan_installments` table |
| Salary Revision Workflow | ✅ Complete | `SalaryRevisionController.php` + approval flow |
| Investment Declaration (Form 12BB) | ✅ Complete | `InvestmentDeclarationController.php` |
| POI Workflow | ✅ Complete | `PoiController.php` |
| Bank File Generation | ✅ Complete | `BankFileService.php` — HDFC/ICICI/SBI/Axis/Kotak |
| Contractor / Vendor Payroll | ✅ Complete | `ContractorController.php` + TDS report |
| Zero Payroll / Blocked Payroll | ✅ Complete | `PayrollService::blockEmployee()` |
| Payroll Lock/Unlock | ✅ Complete | `PayrollService::lockPayrollPeriod()` |
| Manager Dashboard | ✅ Complete | `ManagerDashboardController` |
| HR Analytics Dashboard | ✅ Complete | `HrAnalyticsDashboardController` |
| Payroll Reconciliation | ✅ Complete | `PayrollService::generateReconciliation()` |
| Employee Helpdesk / Ticketing | ✅ Complete | `HelpdeskController.php` + SLA |

---

## Key Packages

| Package | Purpose |
|---------|---------|
| `laravel/sanctum` | Token-based API auth |
| `phpoffice/phpspreadsheet` | Excel import + template generation |
| `barryvdh/laravel-dompdf` | Payslip PDF generation |
| `spatie/laravel-activitylog` | Optional activity logging |
