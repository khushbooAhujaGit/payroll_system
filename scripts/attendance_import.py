#!/usr/bin/env python3
"""
Attendance XLS Import  —  HRMS Payroll
=======================================
Reads biometric machine exports (XLS) for machines 10, 12 and K and
writes raw punch records to  attendance_raw_punches.

Usage
-----
  python attendance_import.py <file1.xls> [file2.xls ...]

  file.xls   — one or more machine XLS files; filename MUST start with the
               machine name followed by an underscore:
               10_xxx.xls  /  12_xxx.xls  /  K_xxx.xls

Rules
-----
  * Machine is detected from the filename prefix (10_ / 12_ / K_).
  * Only "employee" sheets are parsed — sheets whose name is digits separated
    by dots (e.g. "35.34.33", "2.1").  Summary sheets are ignored.
  * Each employee sheet holds up to 3 employee blocks (15 columns each).
  * A Bio ID number in a block is looked up in employee_bio_ids joined to
    employees.  Bio IDs not in the system are silently skipped.
  * Blank days (no punches, not marked Absent) are skipped — they represent
    Sundays / holidays that have no attendance record.
  * ALL records across ALL files are validated before any DB write.
    If one error is found → no data is inserted → error Excel is saved and
    opened automatically.
  * Re-running is idempotent (ON DUPLICATE KEY UPDATE).

Cross-office scenarios
----------------------
  Employee logs in at machine 10, logs out at machine 12:
    • machine-10 row : first_in=09:55, first_out=NULL
    • machine-12 row : first_in=NULL,  first_out=19:05
  Both rows are stored. Payroll queries join by (employee_id, date).

  Employee works at office A for 10 days then office B:
    Same employee may have a Bio ID on both machines; each machine produces
    its own rows and they co-exist under the unique key
    (employee_id, attendance_date, machine_name).
"""

from __future__ import annotations  # allows X | Y union types on Python 3.9

import os
import re
import sys
import subprocess
from datetime import date, time, datetime

import xlrd
import xlsxwriter
import mysql.connector

# ── Bootstrap ─────────────────────────────────────────────────────────────────

SCRIPT_DIR   = os.path.dirname(os.path.abspath(__file__))
PROJECT_ROOT = os.path.abspath(os.path.join(SCRIPT_DIR, '..'))

_env_path = os.path.join(PROJECT_ROOT, '.env')
if os.path.isfile(_env_path):
    with open(_env_path, encoding='utf-8') as _fh:
        for _line in _fh:
            _line = _line.strip()
            if _line and not _line.startswith('#') and '=' in _line:
                _k, _, _v = _line.partition('=')
                os.environ.setdefault(_k.strip(), _v.strip().strip('"').strip("'"))

DB_CONFIG = {
    'host':       os.getenv('DB_HOST',     '127.0.0.1'),
    'port':       int(os.getenv('DB_PORT', '3306')),
    'user':       os.getenv('DB_USERNAME', 'root'),
    'password':   os.getenv('DB_PASSWORD', ''),
    'database':   os.getenv('DB_DATABASE', 'hrms'),
    'charset':    'utf8mb4',
    'autocommit': False,
}

# ── Sheet layout constants ─────────────────────────────────────────────────────

# Only sheets named like "35.34.33" or "2.1" or "5" contain employee data.
BIO_SHEET_RE = re.compile(r'^\d+(\.\d+)*$')

BLOCK_OFFSETS = [0, 15, 30]   # up to 3 employee blocks per sheet

# Column offsets within a 15-column block
# (confirmed from actual XLS dump — 44 cols total, 3 blocks × 15 cols)
#
#  +0  WeekDate       "01 TUE"
#  +1  First TZ       On-duty  (check-in)
#  +3  First TZ       Off-duty (check-out)
#  +6  Second TZ      On-duty  (check-in)   ← fallback when First TZ empty
#  +8  Second TZ      Off-duty (check-out)  ← fallback when First TZ empty
#  +10 Overtime       Check-In              ← last fallback
#  +12 Overtime       Check-Out             ← last fallback
#  +9  (header row 3) Bio ID number
#  +9  (header row 2) Employee name
REL_DATE       = 0
REL_FIRST_IN   = 1
REL_FIRST_OUT  = 3
REL_SECOND_IN  = 6
REL_SECOND_OUT = 8
REL_OT_IN      = 10
REL_OT_OUT     = 12
REL_BIO_NUM    = 9
REL_NAME       = 9

PERIOD_ROW     = 1   # 0-indexed row that holds the date range string
PERIOD_COL     = 3   # column within row 1 that holds "2025-04-01 ~ 2025-04-30"
ID_ROW         = 3   # 0-indexed row that holds "ID | {number}"
DATA_START_ROW = 11  # rows 0-10 are headers; rows 11-40 are the 30 attendance days

TIME_RE    = re.compile(r'^(\d{1,2}):(\d{2})$')
TIME_RE_SS = re.compile(r'^(\d{1,2}:\d{2}):\d{2}$')   # "HH:MM:SS" → strip seconds

# Machine prefix → Bio type letter
BIO_TYPE_MAP: dict[str, str] = {'10': 'I', '12': 'II', 'K': 'III'}

# ── Low-level helpers ─────────────────────────────────────────────────────────

def detect_machine(filepath: str) -> str | None:
    """Return '10', '12', or 'K' from a filename like '10_Report.xls'."""
    base = os.path.basename(filepath)
    m = re.match(r'^(10|12|K)_', base, re.IGNORECASE)
    if not m:
        return None
    v = m.group(1)
    return 'K' if v.upper() == 'K' else v


def cell_str(sheet, row: int, col: int) -> str:
    """Read a cell safely; convert float 35.0 → '35'."""
    try:
        v = sheet.cell(row, col).value
    except IndexError:
        return ''
    if v is None:
        return ''
    if isinstance(v, float) and v == int(v):
        return str(int(v))
    return str(v).strip()


def read_time_cell(sheet, row: int, col: int) -> str:
    """
    Read a cell that is expected to contain a time value or the text 'Absent'.

    Biometric machine XLS files store times in several ways depending on the
    machine model and Excel version:
      1. XL_CELL_TEXT  "09:28"          → returned as-is
      2. XL_CELL_TEXT  "09:28:00"       → seconds stripped → "09:28"
      3. XL_CELL_DATE  0.394444…        → xlrd fraction-of-day → "09:28"
      4. XL_CELL_NUMBER 0.394444…       → treated as fraction-of-day → "09:28"
      5. XL_CELL_TEXT  "Absent" / "A"   → returned as-is (for absent detection)
      6. Empty / blank                   → ''
    """
    try:
        cell = sheet.cell(row, col)
    except IndexError:
        return ''

    ctype, val = cell.ctype, cell.value

    # ── Empty ──────────────────────────────────────────────────────────────
    if ctype in (xlrd.XL_CELL_EMPTY, xlrd.XL_CELL_BLANK) or val is None:
        return ''

    # ── Excel date/time cell (most common biometric export format) ─────────
    if ctype == xlrd.XL_CELL_DATE:
        try:
            dt = xlrd.xldate_as_datetime(val, sheet.book.datemode)
            return dt.strftime('%H:%M')
        except Exception:
            return ''

    # ── Plain number that looks like a time fraction (0 ≤ v < 1) ──────────
    if ctype == xlrd.XL_CELL_NUMBER:
        if isinstance(val, float) and 0.0 <= val < 1.0:
            try:
                dt = xlrd.xldate_as_datetime(val, sheet.book.datemode)
                return dt.strftime('%H:%M')
            except Exception:
                return ''
        return ''   # any other number is not a time

    # ── Text cell ──────────────────────────────────────────────────────────
    if ctype == xlrd.XL_CELL_TEXT:
        s = str(val).strip()
        # Strip trailing seconds "09:28:00" → "09:28"
        m = TIME_RE_SS.match(s)
        if m:
            return m.group(1)
        return s   # "09:28", "Absent", "A", etc. — returned as-is

    # ── Fallback ───────────────────────────────────────────────────────────
    return str(val).strip()


def parse_date_range(raw: str) -> tuple[int, int]:
    """'2025-04-01 ~ 2025-04-30' → (2025, 4). Raises ValueError on failure."""
    m = re.search(r'(\d{4})-(\d{2})-\d{2}', raw.strip())
    if not m:
        raise ValueError(f"Unrecognised date range: {repr(raw)}")
    return int(m.group(1)), int(m.group(2))


def parse_weekdate(cell: str, year: int, month: int) -> date | None:
    """'01 TUE' → date(2025, 4, 1). Returns None if unparseable."""
    m = re.match(r'^(\d{1,2})\s+\w+$', cell.strip())
    if not m:
        return None
    try:
        return date(year, month, int(m.group(1)))
    except ValueError:
        return None


def parse_time(val: str) -> time | None:
    """'09:28' → time(9, 28).  Empty / unrecognised → None."""
    s = val.strip()
    if not s:
        return None
    m = TIME_RE.match(s)
    if m:
        h, mi = int(m.group(1)), int(m.group(2))
        if 0 <= h <= 23 and 0 <= mi <= 59:
            return time(h, mi)
    return None


def is_bad_time(val: str) -> bool:
    """True when val is non-empty AND cannot be parsed as HH:MM."""
    return bool(val.strip()) and parse_time(val) is None


def fmt_time(t: time | None) -> str | None:
    return t.strftime('%H:%M') if t else None


def resolve_check_in(fi: str, si: str, oti: str) -> time | None:
    """First TZ → Second TZ → Overtime (first non-null wins)."""
    return parse_time(fi) or parse_time(si) or parse_time(oti)


def resolve_check_out(fo: str, so: str, oto: str) -> time | None:
    """First TZ → Second TZ → Overtime (first non-null wins)."""
    return parse_time(fo) or parse_time(so) or parse_time(oto)


def is_blank_day(fi: str, fo: str, si: str, so: str, oti: str, oto: str) -> bool:
    """No punches at all in any time zone → Sunday / holiday row, skip it."""
    return all(not v.strip() for v in [fi, fo, si, so, oti, oto])


# ── DB helpers ────────────────────────────────────────────────────────────────

def load_bio_map(cursor, bio_numbers: list[int], machine_name: str) -> dict:
    """
    Look up employee_bio_ids by bio_number + machine_name only.
    Returns { bio_number: {'employee_id': int, 'bio_id_full': str} }
    Bio numbers not registered in the system are absent from the dict → caller skips them.
    """
    if not bio_numbers:
        return {}
    placeholders = ','.join(['%s'] * len(bio_numbers))
    cursor.execute(
        f"""
        SELECT bio_number, employee_id, bio_id_full
        FROM   employee_bio_ids
        WHERE  bio_number   IN ({placeholders})
          AND  machine_name  = %s
          AND  is_active     = 1
        """,
        bio_numbers + [machine_name]
    )
    return {
        int(row[0]): {'employee_id': int(row[1]), 'bio_id_full': row[2]}
        for row in cursor.fetchall()
    }


# ── Bio ID auto-registration helpers ─────────────────────────────────────────

def extract_bio_ids_from_files(file_machine_list: list[tuple[str, str]]) -> list[dict]:
    """
    Scan every employee block in every uploaded XLS sheet and extract
    Bio ID entries.  Called only when employee_bio_ids is empty — we pull
    the name→bio mapping directly from the machine reports.

    Returns a list of unique dicts keyed by bio_id_full:
      { bio_number, bio_type, bio_id_full, machine_name,
        employee_name, source_file, sheet }
    """
    seen    : set[str]  = set()
    entries : list[dict] = []

    for filepath, machine in file_machine_list:
        filename = os.path.basename(filepath)
        bio_type = BIO_TYPE_MAP.get(machine, 'I')

        try:
            wb = xlrd.open_workbook(filepath)
        except Exception:
            continue

        for sheet in wb.sheets():
            sheet_name = sheet.name.strip()
            if not BIO_SHEET_RE.match(sheet_name):
                continue

            for off in BLOCK_OFFSETS:
                raw_num = cell_str(sheet, ID_ROW, off + REL_BIO_NUM)
                if not raw_num:
                    continue
                try:
                    bio_num = int(float(raw_num))
                except (ValueError, OverflowError):
                    continue

                emp_name = cell_str(sheet, 2, off + REL_NAME).strip()
                if not emp_name:
                    continue

                bio_id_full = f"{bio_type}-{bio_num}"
                if bio_id_full in seen:
                    continue          # same Bio ID on another sheet — skip duplicate
                seen.add(bio_id_full)

                entries.append({
                    'bio_number':    bio_num,
                    'bio_type':      bio_type,
                    'bio_id_full':   bio_id_full,
                    'machine_name':  machine,
                    'employee_name': emp_name,
                    'source_file':   filename,
                    'sheet':         sheet_name,
                })

    return entries


def _normalise_name(raw: str) -> str:
    """
    Normalise a name from a biometric machine for matching:
      • Replace ~ and . with spaces (machine uses these as separators)
      • Collapse multiple spaces to one
      • Strip leading/trailing whitespace
    """
    import re as _re
    s = _re.sub(r'[~.]+', ' ', raw)
    return ' '.join(s.split())


def _find_employee_by_name(cursor, raw_name: str) -> int | None:
    """
    Multi-stage name matching for biometric machine names that may be:
      • Truncated to ~24 chars  ("Joshi Dhananjay Nareshk" → "Nareshkumar")
      • Use ~ separators        ("Parmar~Dharti~Narendrab")
      • Single word only        ("BHUMI", "Sufiyan")
      • Contain an initial      ("Jaldeep J. Joshi")

    Stages tried in order — first hit wins:
      1. Exact normalised full name  (first [middle] last)
      2. Normalised first+last only  (drop middle from both sides)
      3. Single-word → match first_name only
      4. Prefix match for truncated  (name starts with the stored value or vice-versa, ≥15 chars)
    """
    name = _normalise_name(raw_name)
    if not name:
        return None

    words = name.split()

    # ── Stage 1: exact full-name match ───────────────────────────────────────
    cursor.execute(
        """
        SELECT id FROM employees
        WHERE  LOWER(TRIM(CONCAT_WS(' ', first_name,
                   NULLIF(TRIM(COALESCE(middle_name, '')), ''),
                   last_name))) = LOWER(%s)
          AND  deleted_at IS NULL
        LIMIT 1
        """,
        (name,),
    )
    row = cursor.fetchone()
    if row:
        return int(row[0])

    # ── Stage 2: first + last name only (ignore middle from both sides) ──────
    if len(words) >= 2:
        # Try "first last" (first word + last word) and also "last first"
        for first, last in [(words[0], words[-1]), (words[-1], words[0])]:
            cursor.execute(
                """
                SELECT id FROM employees
                WHERE  LOWER(first_name) = LOWER(%s)
                  AND  LOWER(last_name)  = LOWER(%s)
                  AND  deleted_at IS NULL
                LIMIT 1
                """,
                (first, last),
            )
            row = cursor.fetchone()
            if row:
                return int(row[0])

    # ── Stage 3: single word → match first_name only ─────────────────────────
    if len(words) == 1:
        cursor.execute(
            """
            SELECT id FROM employees
            WHERE  LOWER(first_name) = LOWER(%s)
              AND  deleted_at IS NULL
            LIMIT 1
            """,
            (words[0],),
        )
        row = cursor.fetchone()
        if row:
            return int(row[0])

    # ── Stage 4: prefix match for truncated machine names (≥15 chars safe) ───
    if len(name) >= 15:
        cursor.execute(
            """
            SELECT id FROM employees
            WHERE (
                LOWER(TRIM(CONCAT_WS(' ', first_name,
                           NULLIF(TRIM(COALESCE(middle_name, '')), ''),
                           last_name))) LIKE LOWER(%s)
              OR  LOWER(%s) LIKE CONCAT(LOWER(TRIM(CONCAT_WS(' ', first_name,
                           NULLIF(TRIM(COALESCE(middle_name, '')), ''),
                           last_name))), '%%')
            )
              AND  deleted_at IS NULL
            LIMIT 1
            """,
            (name + '%', name),
        )
        row = cursor.fetchone()
        if row:
            return int(row[0])

    return None


def auto_register_bio_ids(
    cursor,
    bio_entries: list[dict],
) -> tuple[int, list[dict]]:
    """
    Match each bio entry to an employee by name (multi-stage fuzzy match),
    then INSERT into employee_bio_ids.

    Matching stages (see _find_employee_by_name for details):
      1. Exact normalised full name
      2. First + last name only
      3. Single-word first name
      4. Prefix match for truncated machine names

    Returns (registered_count, unmatched_entries).
    """
    registered = 0
    unmatched  = []

    for entry in bio_entries:
        employee_id = _find_employee_by_name(cursor, entry['employee_name'])

        if employee_id is None:
            unmatched.append(entry)
            continue

        cursor.execute(
            """
            INSERT INTO employee_bio_ids
                (employee_id, bio_id_full, bio_type, bio_number, machine_name, is_active)
            VALUES (%s, %s, %s, %s, %s, 1)
            ON DUPLICATE KEY UPDATE
                employee_id  = VALUES(employee_id),
                bio_type     = VALUES(bio_type),
                bio_number   = VALUES(bio_number),
                machine_name = VALUES(machine_name),
                is_active    = 1
            """,
            (employee_id, entry['bio_id_full'], entry['bio_type'],
             entry['bio_number'], entry['machine_name']),
        )
        registered += 1

    return registered, unmatched


# ── Core file parser ──────────────────────────────────────────────────────────

def parse_file(
    filepath: str,
    machine: str,
    cursor,
) -> tuple[list[dict], list[dict]]:
    """
    Parse one XLS file.
    Returns (records, errors).
    records — dicts ready for DB insert.
    errors  — dicts for the error report Excel.
    """
    records  = []
    errors   = []
    filename = os.path.basename(filepath)

    try:
        wb = xlrd.open_workbook(filepath)
    except Exception as exc:
        return [], [{'file': filename, 'sheet': '', 'bio_id': '', 'date': '',
                     'error': f"Cannot open file: {exc}"}]

    for sheet in wb.sheets():
        sheet_name = sheet.name.strip()
        if not BIO_SHEET_RE.match(sheet_name):
            continue   # skip Schedule Infor., Att. Stat., Att.log report, etc.

        # ── Date range ─────────────────────────────────────────────────────
        range_raw = cell_str(sheet, PERIOD_ROW, PERIOD_COL)
        try:
            year, month = parse_date_range(range_raw)
        except ValueError as exc:
            errors.append({'file': filename, 'sheet': sheet_name,
                           'bio_id': '', 'date': '',
                           'error': f"Cannot read date range: {exc}"})
            continue

        # ── Bio numbers for each block ─────────────────────────────────────
        block_bio_nums = []
        for off in BLOCK_OFFSETS:
            raw = cell_str(sheet, ID_ROW, off + REL_BIO_NUM)
            try:
                block_bio_nums.append(int(float(raw)) if raw else None)
            except (ValueError, OverflowError):
                block_bio_nums.append(None)

        # Batch look-up in one query
        known = [n for n in block_bio_nums if n is not None]
        bio_map = load_bio_map(cursor, known, machine)

        # ── Per-block, per-day parsing ─────────────────────────────────────
        for blk_idx, off in enumerate(BLOCK_OFFSETS):
            bio_num = block_bio_nums[blk_idx]
            if bio_num is None or bio_num not in bio_map:
                continue   # bio ID not in our system — silent skip

            emp  = bio_map[bio_num]
            name = cell_str(sheet, 2, off + REL_NAME)  # for readable errors

            for row_idx in range(DATA_START_ROW, sheet.nrows):
                date_raw = cell_str(sheet, row_idx, off + REL_DATE)
                if not date_raw:
                    break   # past the 30-day data; trailing empty rows

                att_date = parse_weekdate(date_raw, year, month)
                if att_date is None:
                    errors.append({
                        'file': filename, 'sheet': sheet_name,
                        'bio_id': emp['bio_id_full'], 'date': date_raw,
                        'error': f"Cannot parse date '{date_raw}' (employee: {name})"
                    })
                    continue

                fi  = read_time_cell(sheet, row_idx, off + REL_FIRST_IN)
                fo  = read_time_cell(sheet, row_idx, off + REL_FIRST_OUT)
                si  = read_time_cell(sheet, row_idx, off + REL_SECOND_IN)
                so  = read_time_cell(sheet, row_idx, off + REL_SECOND_OUT)
                oti = read_time_cell(sheet, row_idx, off + REL_OT_IN)
                oto = read_time_cell(sheet, row_idx, off + REL_OT_OUT)

                # "Absent" always appears in the First TZ On-duty column
                is_absent = fi.strip().lower() == 'absent'

                # Skip Sunday / holiday rows — no data in ANY time zone
                if not is_absent and is_blank_day(fi, fo, si, so, oti, oto):
                    continue

                # Validate that every non-empty time string is a valid HH:MM
                if not is_absent:
                    bad_fields = []
                    for fname, fval in [
                        ('check_in  (first zone)',   fi),
                        ('check_out (first zone)',   fo),
                        ('check_in  (second zone)',  si),
                        ('check_out (second zone)',  so),
                        ('check_in  (overtime)',     oti),
                        ('check_out (overtime)',     oto),
                    ]:
                        if is_bad_time(fval):
                            bad_fields.append(f"{fname}='{fval}'")

                    if bad_fields:
                        errors.append({
                            'file': filename, 'sheet': sheet_name,
                            'bio_id': emp['bio_id_full'],
                            'date': str(att_date),
                            'error': (
                                f"Unrecognised time for {name}: "
                                + '; '.join(bad_fields)
                            )
                        })
                        continue

                records.append({
                    'employee_id':     emp['employee_id'],
                    'bio_id_full':     emp['bio_id_full'],
                    'bio_number':      bio_num,
                    'machine_name':    machine,
                    'attendance_date': str(att_date),
                    'check_in':        None if is_absent else fmt_time(resolve_check_in(fi, si, oti)),
                    'check_out':       None if is_absent else fmt_time(resolve_check_out(fo, so, oto)),
                    'is_absent':       1 if is_absent else 0,
                    'source_file':     filename,
                    'uploaded_by':     None,   # filled just before insert
                })

    return records, errors


# ── Error Excel writer ────────────────────────────────────────────────────────

def write_error_excel(errors: list[dict], output_path: str) -> None:
    wb  = xlsxwriter.Workbook(output_path)
    ws  = wb.add_worksheet('Errors')

    hdr_fmt = wb.add_format({
        'bold': True, 'bg_color': '#C0392B',
        'font_color': '#FFFFFF', 'border': 1,
    })
    row_fmt = wb.add_format({'bg_color': '#FDE8E8', 'border': 1})

    cols   = ['File', 'Sheet', 'Bio ID', 'Date', 'Error']
    widths = [35,     20,      15,       15,      80]
    for c, (h, w) in enumerate(zip(cols, widths)):
        ws.write(0, c, h, hdr_fmt)
        ws.set_column(c, c, w)

    for r, e in enumerate(errors, 1):
        ws.write(r, 0, e.get('file',   ''), row_fmt)
        ws.write(r, 1, e.get('sheet',  ''), row_fmt)
        ws.write(r, 2, e.get('bio_id', ''), row_fmt)
        ws.write(r, 3, e.get('date',   ''), row_fmt)
        ws.write(r, 4, e.get('error',  ''), row_fmt)

    wb.close()


def open_file(path: str) -> None:
    """Auto-open a file using the OS default application."""
    try:
        if sys.platform == 'win32':
            os.startfile(path)
        elif sys.platform == 'darwin':
            subprocess.call(['open', path])
        else:
            subprocess.call(['xdg-open', path])
    except Exception:
        pass   # auto-open is best-effort


# ── DB insert ─────────────────────────────────────────────────────────────────

INSERT_SQL = """
    INSERT INTO attendance_raw_punches
        (employee_id, bio_id_full, bio_number, machine_name, attendance_date,
         check_in, check_out, is_absent,
         source_file, uploaded_by, created_at, updated_at)
    VALUES
        (%(employee_id)s, %(bio_id_full)s, %(bio_number)s, %(machine_name)s,
         %(attendance_date)s,
         %(check_in)s, %(check_out)s, %(is_absent)s,
         %(source_file)s, %(uploaded_by)s, NOW(), NOW())
    ON DUPLICATE KEY UPDATE
        check_in    = VALUES(check_in),
        check_out   = VALUES(check_out),
        is_absent   = VALUES(is_absent),
        source_file = VALUES(source_file),
        uploaded_by = VALUES(uploaded_by),
        updated_at  = NOW()
"""


def insert_records(cursor, records: list[dict], uploaded_by: int | None) -> int:
    for r in records:
        r['uploaded_by'] = uploaded_by
    cursor.executemany(INSERT_SQL, records)
    return cursor.rowcount


# ── Entry point ───────────────────────────────────────────────────────────────

def main():
    if len(sys.argv) < 2:
        print(__doc__)
        sys.exit(1)

    filepaths   = sys.argv[1:]
    uploaded_by = None   # set to a user_id if launching from a web context

    print(f"\n{'='*65}")
    print(f"  HRMS Attendance Import")
    print(f"{'='*65}")

    # ── Validate filenames & detect machines ──────────────────────────
    file_machine: list[tuple[str, str]] = []
    for fp in filepaths:
        if not os.path.isfile(fp):
            print(f"[ERROR] File not found: {fp}")
            sys.exit(1)
        machine = detect_machine(fp)
        if machine is None:
            print(f"[ERROR] Cannot detect machine from '{os.path.basename(fp)}'.")
            print("        Filename must start with:  10_  /  12_  /  K_")
            sys.exit(1)
        file_machine.append((fp, machine))
        print(f"  Queued: {os.path.basename(fp):<45}  Machine {machine}")

    # ── Connect to DB ─────────────────────────────────────────────────
    try:
        conn   = mysql.connector.connect(**DB_CONFIG)
        cursor = conn.cursor()
    except Exception as exc:
        print(f"\n[ERROR] DB connection failed: {exc}")
        sys.exit(1)

    # ── Pre-flight: scan uploaded files for Bio IDs; register any that are new ──
    #
    # Rules:
    #  • A bio ID only needs to appear in ONE file/sheet to be registered & stored.
    #  • A bio ID appearing in multiple files (different machines) is stored once
    #    per machine — each machine produces its own (employee, date, machine) row.
    #  • New bio IDs (not yet in employee_bio_ids) are auto-registered by name match.
    #  • If a name match fails the bio ID is SKIPPED with a warning — the rest of
    #    the import continues unaffected.  There is no hard abort for unmatched names.
    #  • Already-registered bio IDs are never re-registered; the query only looks
    #    for bio_id_full values absent from employee_bio_ids.

    print(f"\n  Scanning uploaded file(s) for Bio IDs ...")
    all_bio_entries = extract_bio_ids_from_files(file_machine)

    if not all_bio_entries:
        print(f"\n{'='*65}")
        print(f"  [BLOCKED] No employee blocks found in the uploaded XLS files.")
        print(f"  Check that the files are valid biometric machine exports.")
        print(f"{'='*65}\n")
        cursor.close(); conn.close(); sys.exit(1)

    print(f"  Found {len(all_bio_entries)} unique Bio ID(s) in the uploaded file(s).")

    # Find which bio_id_fulls are NOT yet in employee_bio_ids
    all_fulls    = [e['bio_id_full'] for e in all_bio_entries]
    placeholders = ','.join(['%s'] * len(all_fulls))
    cursor.execute(
        f"SELECT bio_id_full FROM employee_bio_ids WHERE bio_id_full IN ({placeholders}) AND is_active = 1",
        all_fulls,
    )
    already_registered = {row[0] for row in cursor.fetchall()}
    new_entries = [e for e in all_bio_entries if e['bio_id_full'] not in already_registered]

    if new_entries:
        print(f"  {len(new_entries)} new Bio ID(s) found — auto-registering by name match ...")
        registered_count, unmatched = auto_register_bio_ids(cursor, new_entries)
        conn.commit()

        if registered_count:
            print(f"  Auto-registered {registered_count} new Bio ID(s).")

        if unmatched:
            # Warn but do NOT abort — unmatched bio IDs are simply skipped.
            # Their rows will not appear in this import, but everything else proceeds.
            print(f"\n  [WARN] {len(unmatched)} Bio ID(s) could not be matched to any employee:")
            for e in unmatched:
                print(f"    {e['bio_id_full']:<10} \"{e['employee_name']}\"  "
                      f"(file: {e['source_file']}, sheet: {e['sheet']})")
            print(f"  These employees will be skipped this import.")
            print(f"  To fix: correct the employee name in the system to match the sheet,")
            print(f"  or upload Bio IDs manually via the Bio IDs Excel template.")
            print()
    else:
        cursor.execute("SELECT COUNT(*) FROM employee_bio_ids WHERE is_active = 1")
        total = cursor.fetchone()[0]
        print(f"  All Bio IDs already registered ({total} active on record).")

    # ── Parse all files — collect records and errors ──────────────────
    all_records: list[dict] = []
    all_errors:  list[dict] = []

    for filepath, machine in file_machine:
        print(f"\n  Parsing {os.path.basename(filepath)}  (machine={machine}) ...")
        recs, errs = parse_file(filepath, machine, cursor)
        all_records.extend(recs)
        all_errors.extend(errs)
        print(f"    {len(recs):4d} records parsed   |   {len(errs):4d} errors found")

    cursor.close()

    # ── Abort on any error — generate error Excel ─────────────────────
    if all_errors:
        err_dir  = os.path.join(PROJECT_ROOT, 'storage', 'app', 'error_exports')
        os.makedirs(err_dir, exist_ok=True)
        ts       = datetime.now().strftime('%Y%m%d_%H%M%S')
        err_path = os.path.join(err_dir, f'attendance_errors_{ts}.xlsx')
        write_error_excel(all_errors, err_path)

        print(f"\n{'='*65}")
        print(f"  IMPORT ABORTED")
        print(f"  Total records parsed : {len(all_records)}")
        print(f"  Errors found         : {len(all_errors)}")
        print(f"  Error file saved to  : {err_path}")
        print(f"{'='*65}\n")

        open_file(err_path)   # auto-open in Excel
        conn.close()
        sys.exit(1)

    if not all_records:
        print(f"\n{'='*65}")
        print("  No matching Bio IDs found in any uploaded file.")
        print("  Nothing was imported.")
        print(f"{'='*65}\n")
        conn.close()
        sys.exit(0)

    # ── All valid — insert in a single transaction ────────────────────
    print(f"\n  Inserting {len(all_records)} records ...")
    cursor = conn.cursor()
    try:
        affected = insert_records(cursor, all_records, uploaded_by)
        conn.commit()
        print(f"\n{'='*65}")
        print(f"  SUCCESS")
        print(f"  Records processed  : {len(all_records)}")
        print(f"  DB rows affected   : {affected}")
        print(f"{'='*65}\n")
    except Exception as exc:
        conn.rollback()
        print(f"\n[ERROR] DB insert failed: {exc}")
        cursor.close()
        conn.close()
        sys.exit(1)

    cursor.close()
    conn.close()


if __name__ == '__main__':
    main()
