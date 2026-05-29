#!/usr/bin/env python3
"""
HRMS Employee Bulk Upload  (schema-verified against hrms_payroll.sql)
══════════════════════════════════════════════════════════════════════
Reads the company Excel template and populates these tables:
  employees                  — master record (incl. title, external_emp_id,
                               team_leader_id, attendance_group,
                               aadhaar_document_path, offer_letter_date)
  employee_statutory_details — PAN / UAN / PF / ESIC
  employee_bank_details      — bank account for salary credit
  exit_requests              — created when CurrentStatus = left/resigned/terminated
  excel_upload_logs          — audit trail for every import run

Lookups (auto-create if missing):
  companies     <- Firm column
  entities      <- derived from company
  branches      <- Office column (linked to entity)
  departments   <- Department column (linked to entity)
  designations  <- Designation Final column (linked to entity)

Dedup order (first match wins):
  1. external_emp_id + entity_id   (New Emp ID column)
  2. mobile         + entity_id
  3. official_email + entity_id

Bio IDs are uploaded separately via the "Bio ID Mapping" Excel sheet
  (use bio_id_upload.py or the web "Upload Bio IDs" button).

Usage:
    python employee_bulk_upload.py
    python employee_bulk_upload.py  path/to/file.xlsx
    python employee_bulk_upload.py  path/to/file.xlsx  <entity_id>
"""

from __future__ import annotations  # allows X | Y union types on Python 3.9

import sys, re, math, time, json, warnings
from datetime import datetime, date
from pathlib import Path

import pandas as pd
import mysql.connector
from mysql.connector import Error as MySQLError

warnings.filterwarnings("ignore")

# ──────────────────────────────────────────────────────────────
# CONFIG
# ──────────────────────────────────────────────────────────────

ROOT     = Path(__file__).parent.parent
ENV_PATH = ROOT / ".env"
BATCH_SIZE      = 50
DEFAULT_XLSX    = r"C:\Users\Shivam\Downloads\employee_data_template.xlsx"
DEFAULT_ENTITY  = 1   # fallback if Firm lookup fails


def _load_env(path: Path) -> dict:
    env = {}
    if not path.exists():
        return env
    for line in path.read_text(encoding="utf-8", errors="ignore").splitlines():
        line = line.strip()
        if "=" in line and not line.startswith("#"):
            k, _, v = line.partition("=")
            env[k.strip()] = v.strip().strip('"').strip("'")
    return env


_env = _load_env(ENV_PATH)
DB = {
    "host":      _env.get("DB_HOST", "127.0.0.1"),
    "port":      int(_env.get("DB_PORT", 3306)),
    "database":  _env.get("DB_DATABASE", "hrms_payroll"),
    "user":      _env.get("DB_USERNAME", "root"),
    "password":  _env.get("DB_PASSWORD", ""),
    "charset":   "utf8mb4",
    "autocommit": False,
    "connection_timeout": 10,
}

# ──────────────────────────────────────────────────────────────
# COLUMN → FIELD MAP
# Covers every meaningful column in the template.
# ──────────────────────────────────────────────────────────────

HEADER_MAP = {
    # Identity
    "Employee Name":                        "full_name",
    "Employee Name As Per Form Submission": "full_name_alt",
    "New Emp ID":                           "external_emp_id",   # → employees.external_emp_id
    "Old EmpID":                            "old_emp_id",        # reference only, not stored
    "BioID":                                "bio_id",            # → employee_bio_ids.bio_id
    "Title":                                "title",             # → employees.title

    # Personal
    "Gender":                               "gender",
    "Marital Status":                       "marital_status",
    "Birth Date":                           "date_of_birth",

    # Employment
    "Join Date":                            "date_of_joining",
    "Offer Letter Date":                    "offer_letter_date", # → employees.offer_letter_date
    "CurrentStatus":                        "current_status",
    "Left Date":                            "left_date",
    "Left Type":                            "left_type",

    # Contact
    "Mobile Number":                        "mobile",
    "Email address":                        "email",
    "RSC Mail ID":                          "rsc_email",

    # Org
    "Department":                           "department",
    "Designation Final":                    "designation",
    "Designation(as per Offer letter)":     "designation_offer",
    "Reporting Manager":                    "reporting_manager",
    "Team Leader":                          "team_leader",       # → employees.team_leader_id
    "Office":                               "office",
    "Firm":                                 "firm",
    "Location (City)":                      "city",
    "Attendance":                           "attendance_group",  # → employees.attendance_group

    # Statutory
    "PAN":                                  "pan",
    "UAN No (if applicable)":               "uan",
    "UAN Number":                           "uan2",
    "PF Number (if applicable)":            "pf_number",
    "ESI Number (if applicable)":           "esic",
    "Previouse UAN Track":                  "old_uan",
    "PF Applicability":                     "pf_applicability",
    "PF":                                   "pf_flag",

    # Bank
    "Bank Name":                            "bank_name",
    "Account Number":                       "account_number",
    "IFSC Code":                            "ifsc_code",

    # Documents
    "Please attach a copy of your AADHAAR card (size limit 10 MB)": "aadhaar_document_path",
}

# ──────────────────────────────────────────────────────────────
# STATUS / EXIT TYPE MAPS
# ──────────────────────────────────────────────────────────────

EMP_STATUS_MAP = {
    "active":       "active",
    "left":         "inactive",
    "resigned":     "inactive",
    "terminated":   "inactive",
    "terminated":   "inactive",
    "inactive":     "inactive",
    "on notice":    "on_notice",
    "notice":       "on_notice",
    "absconded":    "absconding",
    "absconding":   "absconding",
    "suspended":    "suspended",
    "on leave":     "on_leave",
}

EXIT_TYPE_MAP = {
    "resigned":          "resignation",
    "resignation":       "resignation",
    "terminated":        "termination",
    "termination":       "termination",
    "retired":           "retirement",
    "retirement":        "retirement",
    "end of contract":   "end_of_contract",
    "absconded":         "absconding",
    "absconding":        "absconding",
    "death":             "death",
}

# ──────────────────────────────────────────────────────────────
# VALUE CLEANERS
# ──────────────────────────────────────────────────────────────

_EMPTY = {"", "nan", "none", "-", "n/a", "na", "nil", "null", "#n/a"}


def clean(v) -> str | None:
    if v is None:
        return None
    if isinstance(v, float) and math.isnan(v):
        return None
    s = str(v).strip()
    return None if s.lower() in _EMPTY else s


def parse_date(v) -> str | None:
    """Parse any common date representation → 'YYYY-MM-DD' or None."""
    if v is None:
        return None
    if isinstance(v, (datetime, date)):
        d = v.date() if isinstance(v, datetime) else v
        return d.strftime("%Y-%m-%d")
    s = clean(v)
    if not s:
        return None
    for fmt in (
        "%d-%m-%Y", "%d/%m/%Y", "%Y-%m-%d", "%m/%d/%Y",
        "%d.%m.%Y", "%d %b %Y", "%d-%b-%Y", "%b %d, %Y",
        "%d-%m-%y", "%d/%m/%y", "%Y/%m/%d",
    ):
        try:
            return datetime.strptime(s, fmt).strftime("%Y-%m-%d")
        except ValueError:
            pass
    return None


def parse_left_date(v) -> str | None:
    """
    Left Date can be a proper date OR a free-text string like 'Nov 2023'.
    Returns 'YYYY-MM-DD' if parseable, else None (we store it in remarks).
    """
    if v is None:
        return None
    # Try standard date parse first
    d = parse_date(v)
    if d:
        return d
    # Try month-year strings like "Nov 2023", "11/2023"
    s = clean(v)
    if not s:
        return None
    for fmt in ("%b %Y", "%B %Y", "%m/%Y", "%m-%Y"):
        try:
            # First of that month
            return datetime.strptime(s, fmt).replace(day=1).strftime("%Y-%m-%d")
        except ValueError:
            pass
    return None   # un-parseable — caller saves raw string in remarks


def clean_mobile(v) -> str | None:
    s = clean(v)
    if not s:
        return None
    digits = re.sub(r"\D", "", s)
    if digits.startswith("91") and len(digits) == 12:
        digits = digits[2:]
    return digits if len(digits) == 10 else None


def clean_pan(v) -> str | None:
    s = clean(v)
    if not s:
        return None
    s = re.sub(r"\s", "", s).upper()
    return s if re.match(r"^[A-Z]{5}[0-9]{4}[A-Z]$", s) else None


def clean_ifsc(v) -> str | None:
    s = clean(v)
    if not s:
        return None
    s = re.sub(r"\s", "", s).upper()
    return s if re.match(r"^[A-Z]{4}0[A-Z0-9]{6}$", s) else None


def clean_account(v) -> str | None:
    s = clean(v)
    if not s:
        return None
    s = re.sub(r"[\s\-]", "", s)
    return s if re.match(r"^\d{6,20}$", s) else None


def parse_bool(v, default: bool = True) -> int:
    if v is None:
        return 1 if default else 0
    s = str(v).strip().lower()
    return 1 if s in ("yes", "y", "1", "true", "applicable", "yes - applicable") else 0


def split_name(full: str) -> tuple:
    parts = full.strip().split()
    if len(parts) == 1:
        return parts[0], None, parts[0]
    if len(parts) == 2:
        return parts[0], None, parts[1]
    return parts[0], " ".join(parts[1:-1]), parts[-1]


# ──────────────────────────────────────────────────────────────
# LOOKUP / AUTO-CREATE HELPERS  (all cached)
# ──────────────────────────────────────────────────────────────

_cache: dict[str, dict] = {
    "company":     {},
    "entity":      {},
    "branch":      {},
    "department":  {},
    "designation": {},
    "manager":     {},
    "team_leader": {},
}


def _get(cursor, table: str, col: str, val: str, extra: dict = None) -> int | None:
    if not val:
        return None
    q = f"SELECT id FROM `{table}` WHERE LOWER(TRIM(`{col}`))=LOWER(%s)"
    params = [val.strip()]
    if extra:
        for k, v in extra.items():
            q += f" AND `{k}`=%s"
            params.append(v)
    q += " LIMIT 1"
    cursor.execute(q, params)
    row = cursor.fetchone()
    return row[0] if row else None


def lookup_company(cursor, name: str | None) -> int | None:
    if not name:
        return None
    key = name.strip().lower()
    if key not in _cache["company"]:
        _cache["company"][key] = _get(cursor, "companies", "name", name)
    return _cache["company"][key]


def lookup_or_create_company(cursor, name: str) -> int:
    cid = lookup_company(cursor, name)
    if cid:
        return cid
    cursor.execute(
        "INSERT INTO companies (name, is_active, created_at, updated_at) VALUES (%s, 1, NOW(), NOW())",
        (name.strip(),),
    )
    cid = cursor.lastrowid
    _cache["company"][name.strip().lower()] = cid
    return cid


def lookup_entity_by_company(cursor, company_id: int) -> int | None:
    """Return single entity under a company, or None if multiple / none."""
    cursor.execute(
        "SELECT id FROM entities WHERE company_id=%s AND is_active=1 ORDER BY id LIMIT 1",
        (company_id,),
    )
    row = cursor.fetchone()
    return row[0] if row else None


def resolve_entity(cursor, firm: str | None, default_entity: int) -> int:
    """
    Firm column → company → entity.
    Falls back to default_entity if lookup fails.
    """
    if not firm:
        return default_entity
    key = firm.strip().lower()
    if key in _cache["entity"]:
        return _cache["entity"][key]

    cid = lookup_company(cursor, firm)
    eid = None
    if cid:
        eid = lookup_entity_by_company(cursor, cid)
    if not eid:
        eid = default_entity
    _cache["entity"][key] = eid
    return eid


def lookup_or_create_branch(cursor, name: str | None, entity_id: int, city: str | None) -> int | None:
    """
    Office column → branches table.
    Auto-creates the branch under the entity if not found.
    """
    if not name:
        return None
    key = f"{entity_id}|{name.strip().lower()}"
    if key not in _cache["branch"]:
        bid = _get(cursor, "branches", "name", name, {"entity_id": entity_id})
        if not bid:
            # Auto-create
            cursor.execute(
                "INSERT INTO branches (entity_id, name, code, city, is_active, created_at, updated_at) "
                "VALUES (%s, %s, %s, %s, 1, NOW(), NOW())",
                (entity_id, name.strip(), name.strip()[:20].upper(), city),
            )
            bid = cursor.lastrowid
        _cache["branch"][key] = bid
    return _cache["branch"][key]


def lookup_or_create_department(cursor, name: str | None, entity_id: int) -> int | None:
    if not name:
        return None
    key = f"{entity_id}|{name.strip().lower()}"
    if key not in _cache["department"]:
        did = _get(cursor, "departments", "name", name, {"entity_id": entity_id})
        if not did:
            code = re.sub(r"[^A-Z0-9]", "", name.upper())[:20] or name[:20].upper()
            cursor.execute(
                "INSERT INTO departments (entity_id, name, code, is_active, created_at, updated_at) "
                "VALUES (%s, %s, %s, 1, NOW(), NOW())",
                (entity_id, name.strip(), code),
            )
            did = cursor.lastrowid
        _cache["department"][key] = did
    return _cache["department"][key]


def lookup_or_create_designation(cursor, name: str | None, entity_id: int) -> int | None:
    if not name:
        return None
    key = f"{entity_id}|{name.strip().lower()}"
    if key not in _cache["designation"]:
        dsid = _get(cursor, "designations", "name", name, {"entity_id": entity_id})
        if not dsid:
            code = re.sub(r"[^A-Z0-9]", "", name.upper())[:20] or name[:20].upper()
            cursor.execute(
                "INSERT INTO designations (entity_id, name, code, is_active, created_at, updated_at) "
                "VALUES (%s, %s, %s, 1, NOW(), NOW())",
                (entity_id, name.strip(), code),
            )
            dsid = cursor.lastrowid
        _cache["designation"][key] = dsid
    return _cache["designation"][key]


def _lookup_employee_by_name(cursor, name: str, entity_id: int) -> int | None:
    """Shared helper: find employee id by full name or first name within an entity."""
    cursor.execute(
        "SELECT id FROM employees WHERE entity_id=%s AND "
        "LOWER(CONCAT(TRIM(first_name),' ',TRIM(last_name)))=LOWER(%s) LIMIT 1",
        (entity_id, name.strip()),
    )
    row = cursor.fetchone()
    if not row:
        parts = name.strip().split()
        cursor.execute(
            "SELECT id FROM employees WHERE entity_id=%s AND LOWER(first_name)=LOWER(%s) LIMIT 1",
            (entity_id, parts[0]),
        )
        row = cursor.fetchone()
    return row[0] if row else None


def lookup_reporting_manager(cursor, name: str | None, entity_id: int) -> int | None:
    if not name:
        return None
    key = f"{entity_id}|{name.strip().lower()}"
    if key not in _cache["manager"]:
        _cache["manager"][key] = _lookup_employee_by_name(cursor, name, entity_id)
    return _cache["manager"][key]


def lookup_team_leader(cursor, name: str | None, entity_id: int) -> int | None:
    if not name:
        return None
    key = f"{entity_id}|{name.strip().lower()}"
    if key not in _cache["team_leader"]:
        _cache["team_leader"][key] = _lookup_employee_by_name(cursor, name, entity_id)
    return _cache["team_leader"][key]


# ──────────────────────────────────────────────────────────────
# EMPLOYEE-ID COUNTER  (in-memory, no extra DB hit per row)
# ──────────────────────────────────────────────────────────────

_emp_counter = 0


def init_counter(cursor, entity_id: int):
    global _emp_counter
    cursor.execute(
        "SELECT employee_id FROM employees WHERE entity_id=%s AND employee_id IS NOT NULL "
        "ORDER BY id DESC LIMIT 1",
        (entity_id,),
    )
    row = cursor.fetchone()
    _emp_counter = 0
    if row:
        m = re.search(r"(\d+)$", row[0])
        if m:
            _emp_counter = int(m.group(1))


def next_emp_id() -> str:
    global _emp_counter
    _emp_counter += 1
    return f"EMP{_emp_counter:04d}"


# ──────────────────────────────────────────────────────────────
# ROW PARSER  — converts raw Excel dict → structured record
# ──────────────────────────────────────────────────────────────

def parse_row(raw: dict) -> dict | None:
    r: dict[str, str | None] = {}
    for col, field in HEADER_MAP.items():
        if col in raw:
            v = clean(raw[col])
            # Don't overwrite a non-None value (handles duplicate UAN columns)
            if field not in r or r[field] is None:
                r[field] = v

    full = r.get("full_name") or r.get("full_name_alt") or ""
    full = clean(full)
    if not full:
        return None

    # date_of_joining is NOT NULL in DB — skip row if missing
    doj = parse_date(raw.get("Join Date"))
    if not doj:
        return None

    # date_of_birth  → always DATE or None (never store a raw string)
    dob = parse_date(raw.get("Birth Date"))

    # Left Date → attempt proper date; raw string saved in remarks if not parseable
    left_date_raw = clean(raw.get("Left Date"))
    left_date      = parse_left_date(left_date_raw) if left_date_raw else None
    left_date_note = left_date_raw if (left_date_raw and not left_date) else None

    # Employee status
    raw_status = (r.get("current_status") or "active").lower().strip()
    emp_status  = EMP_STATUS_MAP.get(raw_status, "active")
    is_left     = emp_status in ("inactive", "absconding", "suspended")

    # Exit type
    raw_left_type = (r.get("left_type") or "").lower().strip()
    exit_type      = EXIT_TYPE_MAP.get(raw_left_type) or (
        EXIT_TYPE_MAP.get(raw_status) if is_left else None
    )

    # UAN: prefer explicit UAN Number over old column
    uan = r.get("uan2") or r.get("uan")

    # PF flag (the 'PF' column is a free-text flag; treat as pf_applicability fallback)
    pf_applicable = parse_bool(r.get("pf_applicability") or r.get("pf_flag"), default=True)

    return {
        # ── employees ──────────────────────────────────────────
        "full_name":             full,
        "first_name":            None,   # filled after split_name()
        "middle_name":           None,
        "last_name":             None,
        "title":                 r.get("title"),
        "external_emp_id":       r.get("external_emp_id"),
        "gender":                r.get("gender"),
        "marital_status":        r.get("marital_status"),
        "date_of_birth":         dob,                   # DATE or None
        "date_of_joining":       doj,                   # DATE, NOT NULL
        "offer_letter_date":     parse_date(r.get("offer_letter_date")),
        "mobile":                clean_mobile(raw.get("Mobile Number")),
        "official_email":        r.get("email") or r.get("rsc_email"),
        "personal_email":        r.get("rsc_email") if r.get("email") else None,
        "current_city":          r.get("city"),
        # badge_number / badge_id intentionally omitted — Bio IDs live in employee_bio_ids
        "attendance_group":      r.get("attendance_group"),
        "aadhaar_document_path": r.get("aadhaar_document_path"),
        "employee_status":       emp_status,
        "is_payroll_active":     0 if is_left else 1,
        "is_pf_applicable":      pf_applicable,
        "employment_type":       "full_time",
        "work_type":             "wfo",
        # FK fields (resolved later with cursor)
        "_firm":            r.get("firm"),
        "_office":          r.get("office"),
        "_department":      r.get("department"),
        "_designation":     r.get("designation") or r.get("designation_offer"),
        "_manager":         r.get("reporting_manager"),
        "_team_leader":     r.get("team_leader"),
        "_bio_id":          r.get("bio_id"),
        # ── employee_statutory_details ─────────────────────────
        "_statutory": {
            "pan_number":   clean_pan(raw.get("PAN")),
            "uan_number":   uan,
            "pf_number":    r.get("pf_number"),
            "esic_number":  r.get("esic"),
        },
        # ── employee_bank_details ──────────────────────────────
        "_bank": {
            "bank_name":           r.get("bank_name"),
            "account_number":      clean_account(raw.get("Account Number")),
            "ifsc_code":           clean_ifsc(raw.get("IFSC Code")),
            "account_holder_name": full,
        },
        # ── exit_requests  (only if employee has left) ─────────
        "_exit": {
            "needed":           is_left,
            "exit_type":        exit_type or "resignation",
            "last_working_date": left_date or doj,   # fallback to join date if left_date unparseable
            "remarks":          left_date_note,       # raw string if date parse failed
        },
    }


# ──────────────────────────────────────────────────────────────
# SQL STATEMENTS  (exact column names from hrms_payroll.sql)
# ──────────────────────────────────────────────────────────────

SQL_INSERT_EMP = """
INSERT INTO employees (
    entity_id, employee_id, branch_id, department_id, designation_id,
    reporting_manager_id, team_leader_id,
    title, external_emp_id,
    first_name, middle_name, last_name, gender, marital_status,
    date_of_birth, date_of_joining, offer_letter_date,
    mobile, official_email, personal_email, current_city,
    employee_status, is_payroll_active,
    attendance_group, aadhaar_document_path,
    is_pf_applicable, is_esic_applicable, is_pt_applicable,
    is_lwf_applicable, is_tds_applicable,
    employment_type, work_type,
    created_at, updated_at
) VALUES (
    %s,%s,%s,%s,%s,
    %s,%s,
    %s,%s,
    %s,%s,%s,%s,%s,
    %s,%s,%s,
    %s,%s,%s,%s,
    %s,%s,
    %s,%s,
    %s,%s,%s,
    %s,%s,
    %s,%s,
    NOW(),NOW()
)
"""

SQL_UPDATE_EMP = """
UPDATE employees SET
    branch_id=%s, department_id=%s, designation_id=%s,
    reporting_manager_id=%s, team_leader_id=%s,
    title=%s, external_emp_id=%s,
    first_name=%s, middle_name=%s, last_name=%s,
    gender=%s, marital_status=%s, date_of_birth=%s, offer_letter_date=%s,
    official_email=%s, personal_email=%s, current_city=%s,
    employee_status=%s, is_payroll_active=%s, is_pf_applicable=%s,
    attendance_group=%s, aadhaar_document_path=%s,
    updated_at=NOW()
WHERE id=%s
"""

SQL_UPSERT_STATUTORY = """
INSERT INTO employee_statutory_details
    (employee_id, pan_number, uan_number, pf_number, esic_number, created_at, updated_at)
VALUES (%s,%s,%s,%s,%s, NOW(),NOW())
ON DUPLICATE KEY UPDATE
    pan_number  = COALESCE(VALUES(pan_number),  pan_number),
    uan_number  = COALESCE(VALUES(uan_number),  uan_number),
    pf_number   = COALESCE(VALUES(pf_number),   pf_number),
    esic_number = COALESCE(VALUES(esic_number), esic_number),
    updated_at  = NOW()
"""

SQL_UPSERT_BANK = """
INSERT INTO employee_bank_details
    (employee_id, bank_name, account_number, ifsc_code,
     account_holder_name, account_type, is_primary, created_at, updated_at)
VALUES (%s,%s,%s,%s,%s,'savings',1, NOW(),NOW())
ON DUPLICATE KEY UPDATE
    bank_name  = COALESCE(VALUES(bank_name),  bank_name),
    ifsc_code  = COALESCE(VALUES(ifsc_code),  ifsc_code),
    updated_at = NOW()
"""

SQL_UPSERT_EXIT = """
INSERT INTO exit_requests
    (employee_id, exit_type, notice_date, last_working_date,
     notice_period_days, actual_notice_days,
     exit_reason, status, created_at, updated_at)
VALUES (%s,%s,%s,%s, 0,0, %s,'completed', NOW(),NOW())
ON DUPLICATE KEY UPDATE
    last_working_date = VALUES(last_working_date),
    exit_type         = VALUES(exit_type),
    updated_at        = NOW()
"""

SQL_UPSERT_BIO_ID = """
INSERT INTO employee_bio_ids (employee_id, bio_id, created_at, updated_at)
VALUES (%s, %s, NOW(), NOW())
ON DUPLICATE KEY UPDATE employee_id=employee_id, updated_at=NOW()
"""

SQL_LOG_UPLOAD = """
INSERT INTO excel_upload_logs
    (entity_id, user_id, module, file_name, file_path,
     total_rows, success_rows, error_rows, errors, status, created_at, updated_at)
VALUES (%s,%s,'employee',%s,%s, %s,%s,%s,%s,'completed', NOW(),NOW())
"""


# ──────────────────────────────────────────────────────────────
# CORE UPSERT
# ──────────────────────────────────────────────────────────────

def upsert_employee(cursor, rec: dict, entity_id: int) -> tuple[int, bool]:
    """
    Insert or update employee.
    Returns (db_id, is_new).
    Dedup order: external_emp_id → mobile → official_email (all scoped to entity_id).
    Bio IDs are stored separately in employee_bio_ids via the Bio ID Mapping upload.
    """
    external_emp_id = rec.get("external_emp_id")
    mobile          = rec["mobile"]
    email           = rec["official_email"]

    emp_db_id = None

    if external_emp_id:
        cursor.execute(
            "SELECT id FROM employees WHERE external_emp_id=%s AND entity_id=%s LIMIT 1",
            (external_emp_id, entity_id),
        )
        row = cursor.fetchone()
        if row:
            emp_db_id = row[0]

    if emp_db_id is None and mobile:
        cursor.execute(
            "SELECT id FROM employees WHERE mobile=%s AND entity_id=%s LIMIT 1",
            (mobile, entity_id),
        )
        row = cursor.fetchone()
        if row:
            emp_db_id = row[0]

    if emp_db_id is None and email:
        cursor.execute(
            "SELECT id FROM employees WHERE official_email=%s AND entity_id=%s LIMIT 1",
            (email, entity_id),
        )
        row = cursor.fetchone()
        if row:
            emp_db_id = row[0]

    # Resolve FK lookups (auto-creates happen inside the transaction)
    branch_id      = lookup_or_create_branch(cursor, rec.pop("_office"), entity_id, rec.get("current_city"))
    dept_id        = lookup_or_create_department(cursor, rec.pop("_department"), entity_id)
    desig_id       = lookup_or_create_designation(cursor, rec.pop("_designation"), entity_id)
    manager_id     = lookup_reporting_manager(cursor, rec.pop("_manager"), entity_id)
    team_leader_id = lookup_team_leader(cursor, rec.pop("_team_leader"), entity_id)

    if emp_db_id:
        cursor.execute(SQL_UPDATE_EMP, (
            branch_id, dept_id, desig_id,
            manager_id, team_leader_id,
            rec["title"], rec["external_emp_id"],
            rec["first_name"], rec["middle_name"], rec["last_name"],
            rec["gender"], rec["marital_status"], rec["date_of_birth"], rec["offer_letter_date"],
            rec["official_email"], rec["personal_email"], rec["current_city"],
            rec["employee_status"], rec["is_payroll_active"], rec["is_pf_applicable"],
            rec["attendance_group"], rec["aadhaar_document_path"],
            emp_db_id,
        ))
        return emp_db_id, False
    else:
        new_eid = next_emp_id()
        cursor.execute(SQL_INSERT_EMP, (
            entity_id, new_eid, branch_id, dept_id, desig_id,
            manager_id, team_leader_id,
            rec["title"], rec["external_emp_id"],
            rec["first_name"], rec["middle_name"], rec["last_name"],
            rec["gender"], rec["marital_status"],
            rec["date_of_birth"], rec["date_of_joining"], rec["offer_letter_date"],
            rec["mobile"], rec["official_email"], rec["personal_email"],
            rec["current_city"],
            rec["employee_status"], rec["is_payroll_active"],
            rec["attendance_group"], rec["aadhaar_document_path"],
            rec["is_pf_applicable"], 1, 1, 1, 1,   # esic/pt/lwf/tds default True
            rec["employment_type"], rec["work_type"],
        ))
        return cursor.lastrowid, True


# ──────────────────────────────────────────────────────────────
# MAIN
# ──────────────────────────────────────────────────────────────

def run(xlsx_path: str, default_entity: int):
    t0 = time.time()
    print(f"\n{'━'*62}")
    print(f"  HRMS Employee Bulk Upload")
    print(f"{'━'*62}")
    print(f"  File      : {xlsx_path}")
    print(f"  Entity ID : {default_entity}  (used when Firm lookup fails)")
    print(f"  Database  : {DB['database']}@{DB['host']}:{DB['port']}")
    print()

    # ── Read Excel ────────────────────────────────────────────
    print("📖  Reading Excel …")
    try:
        df = pd.read_excel(xlsx_path, dtype=str, sheet_name=0)
    except Exception as e:
        print(f"❌  Cannot read file: {e}")
        sys.exit(1)

    df.columns = [c.strip() for c in df.columns]   # strip \n from headers
    df = df.where(pd.notnull(df), None)             # NaN → None
    total = len(df)
    print(f"   ✓  {total} data rows  |  {len(df.columns)} columns\n")
    if total == 0:
        print("⚠   No data rows. Exiting.")
        return

    # ── Connect ───────────────────────────────────────────────
    print("🔌  Connecting …")
    try:
        conn   = mysql.connector.connect(**DB)
        cursor = conn.cursor()
        print("   ✓  Connected\n")
    except MySQLError as e:
        print(f"❌  Connection failed: {e}")
        sys.exit(1)

    init_counter(cursor, default_entity)

    rows          = df.to_dict("records")
    total_batches = math.ceil(total / BATCH_SIZE)
    stats         = {"inserted": 0, "updated": 0, "skipped": 0, "exit_created": 0, "errors": []}

    print(f"⚙   Processing {total} rows in {total_batches} batch(es) of {BATCH_SIZE} …\n")

    for b_idx in range(total_batches):
        batch = rows[b_idx * BATCH_SIZE : (b_idx + 1) * BATCH_SIZE]
        b_new = b_upd = b_skip = b_exit = 0

        try:
            for offset, raw in enumerate(batch):
                row_num = b_idx * BATCH_SIZE + offset + 2   # 1-indexed Excel row

                rec = parse_row(raw)
                if rec is None:
                    stats["skipped"] += 1
                    b_skip += 1
                    continue

                # Split name into fields
                rec["first_name"], rec["middle_name"], rec["last_name"] = split_name(rec["full_name"])
                full_name = rec.pop("full_name")

                # Resolve entity from Firm column
                firm      = rec.pop("_firm")
                entity_id = resolve_entity(cursor, firm, default_entity)

                # Pull sub-records out before upsert
                stat_rec = rec.pop("_statutory")
                bank_rec = rec.pop("_bank")
                exit_rec = rec.pop("_exit")
                rec.pop("_bio_id", None)   # Bio IDs are uploaded separately via Bio ID Mapping sheet

                # Upsert main record
                emp_db_id, is_new = upsert_employee(cursor, rec, entity_id)

                # ── employee_statutory_details ────────────────
                if any(v for v in stat_rec.values()):
                    cursor.execute(SQL_UPSERT_STATUTORY, (
                        emp_db_id,
                        stat_rec["pan_number"],
                        stat_rec["uan_number"],
                        stat_rec["pf_number"],
                        stat_rec["esic_number"],
                    ))

                # ── employee_bank_details ─────────────────────
                if bank_rec["account_number"] and bank_rec["ifsc_code"]:
                    cursor.execute(SQL_UPSERT_BANK, (
                        emp_db_id,
                        bank_rec["bank_name"] or "",
                        bank_rec["account_number"],
                        bank_rec["ifsc_code"],
                        bank_rec["account_holder_name"],
                    ))

                # ── exit_requests ─────────────────────────────
                if exit_rec["needed"] and exit_rec["last_working_date"]:
                    remarks = exit_rec["remarks"]
                    if remarks:
                        remarks = f"Left Date (raw): {remarks}"
                    cursor.execute(SQL_UPSERT_EXIT, (
                        emp_db_id,
                        exit_rec["exit_type"],
                        exit_rec["last_working_date"],
                        exit_rec["last_working_date"],
                        remarks,
                    ))
                    stats["exit_created"] += 1
                    b_exit += 1

                if is_new:
                    stats["inserted"] += 1
                    b_new += 1
                else:
                    stats["updated"] += 1
                    b_upd += 1

            conn.commit()

            done = stats["inserted"] + stats["updated"] + stats["skipped"]
            pct  = (done / total) * 100
            print(
                f"  Batch {b_idx+1:>3}/{total_batches}"
                f"  +{b_new} new"
                f"  ~{b_upd} updated"
                f"  exit:{b_exit}"
                f"  skip:{b_skip}"
                f"  [{pct:5.1f}%]"
            )

        except Exception as exc:
            conn.rollback()
            msg = f"Batch {b_idx+1} (rows {b_idx*BATCH_SIZE+2}–{(b_idx+1)*BATCH_SIZE+1}): {exc}"
            stats["errors"].append(msg)
            print(f"  ⚠  {msg}")

    # ── Write upload log ──────────────────────────────────────
    try:
        errors_json = json.dumps(stats["errors"]) if stats["errors"] else None
        cursor.execute(SQL_LOG_UPLOAD, (
            default_entity, None,
            Path(xlsx_path).name, xlsx_path,
            total,
            stats["inserted"] + stats["updated"],
            stats["skipped"] + len(stats["errors"]) * BATCH_SIZE,
            errors_json,
        ))
        conn.commit()
    except Exception:
        pass  # log failure shouldn't abort the run

    cursor.close()
    conn.close()

    elapsed = time.time() - t0

    print(f"\n{'━'*62}")
    print(f"  +   Inserted       : {stats['inserted']:>5}")
    print(f"  ~   Updated        : {stats['updated']:>5}")
    print(f"      Exit records   : {stats['exit_created']:>5}  (in exit_requests)")
    print(f"      Skipped        : {stats['skipped']:>5}  (blank / no join date)")
    print(f"      Batch errors   : {len(stats['errors']):>5}")
    print(f"      Bio IDs        :       use 'Bio ID Mapping.xlsx' upload separately")
    if stats["errors"]:
        for err in stats["errors"]:
            print(f"      • {err}")
    print(f"\n  ⏱  Done in {elapsed:.1f}s")
    print(f"{'━'*62}\n")


if __name__ == "__main__":
    xlsx  = sys.argv[1] if len(sys.argv) > 1 else DEFAULT_XLSX
    eid   = int(sys.argv[2]) if len(sys.argv) > 2 else DEFAULT_ENTITY
    run(xlsx, eid)
