<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AttendanceApproval;
use App\Models\AttendanceRawPunch;
use App\Models\Employee;
use App\Models\LeaveTrackerRecord;
use App\Models\PayrollEmployeeRecord;
use App\Models\PayrollRun;
use App\Services\FastXlsxWriter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

class WebReportController extends Controller
{
    /** Punch-in is "Late" if after this time (HH:MM:SS string compare) */
    private const LATE_IN_CUTOFF = '10:00:00';

    /** Clocked hours threshold for ">9:00 Hours" remark */
    private const MIN_WORK_HOURS = 9;

    // ─────────────────────────────────────────────────────────────────────────
    // Reports Dashboard
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $companyId = $this->resolveCompanyId($request);

        $headcount = Employee::query()
            ->selectRaw('employee_status, COUNT(*) as count')
            ->groupBy('employee_status')
            ->pluck('count', 'employee_status');

        $deptBreakdown = Employee::query()
            ->where('employee_status', 'active')
            ->selectRaw('department_id, COUNT(*) as count')
            ->with('department:id,name')
            ->groupBy('department_id')
            ->orderByDesc('count')
            ->get();

        $lastPayroll = PayrollRun::where('company_id', $companyId)
            ->whereIn('status', ['finalized', 'paid'])
            ->with('period')
            ->orderByDesc('id')->first();

        $lastRunRecords = $lastPayroll
            ? PayrollEmployeeRecord::where('payroll_run_id', $lastPayroll->id)
                ->selectRaw('SUM(gross_salary) as total_gross, SUM(net_salary) as total_net, COUNT(*) as count')
                ->first()
            : null;

        return view('reports.index', compact(
            'headcount', 'deptBreakdown', 'lastPayroll', 'lastRunRecords', 'companyId'
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Attendance Report — Web Preview
    // ─────────────────────────────────────────────────────────────────────────

    public function attendance(Request $request)
    {
        $companyId  = $this->resolveCompanyId($request);
        $monthStr  = $request->get('month');
        $empFilter = $request->get('emp_id');

        $allEmployees = Employee::query()
            ->where('employee_status', 'active')
            ->orderBy('employee_id')
            ->get(['id', 'employee_id', 'first_name', 'middle_name', 'last_name']);

        // No month selected yet — show empty state
        if (!$monthStr || !preg_match('/^\d{4}-\d{2}$/', $monthStr)) {
            return view('reports.attendance', [
                'rows'        => [],
                'monthStr'    => null,
                'from'        => null,
                'to'          => null,
                'companyId'    => $companyId,
                'allEmployees'=> $allEmployees,
                'empFilter'   => $empFilter,
            ]);
        }

        $from = Carbon::parse($monthStr . '-01')->startOfMonth();
        $to   = $from->copy()->endOfMonth();

        $rows = $this->buildAttendanceRows($companyId, $from, $to, $empFilter);

        return view('reports.attendance', compact(
            'rows', 'monthStr', 'from', 'to', 'companyId', 'allEmployees', 'empFilter'
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Attendance Report — Excel Export  (FastXlsxWriter — no PhpSpreadsheet)
    // ─────────────────────────────────────────────────────────────────────────

    public function attendanceExport(Request $request)
    {
        $companyId = $this->resolveCompanyId($request);
        $monthStr  = $request->get('month');
        $empFilter = $request->get('emp_id');

        if (!$monthStr || !preg_match('/^\d{4}-\d{2}$/', $monthStr)) {
            return redirect()->route('reports.attendance');
        }

        $from = Carbon::parse($monthStr . '-01')->startOfMonth();
        $to   = $from->copy()->endOfMonth();
        $rows = $this->buildAttendanceRows($companyId, $from, $to, $empFilter);

        $headers = [
            'Month', 'Date', 'Day Name', 'New Emp ID', 'Employee Name',
            'TimeIn', 'TimeOut', 'Punch In Type', 'Punch Out Type',
            'Clocked Time', 'CT_Remark', 'Leave Type', 'Reason For Leave',
            'Punch Deviation', 'Punch In Approval', 'Punch Out Approval',
        ];

        $approvalLabel = fn(?string $v): string => match($v) {
            'approved'     => 'Approved',
            'not_approved' => 'Not Approved',
            default        => 'Approved',   // default shown when no record exists
        };

        // ── Build Excel with PhpSpreadsheet so we can add dropdown validation ──
        $wb = new Spreadsheet();
        $ws = $wb->getActiveSheet();
        $ws->setTitle('Attendance');

        // Header row
        $ws->fromArray($headers, null, 'A1');
        $lastCol = count($headers);
        $ws->getStyle('A1:' . Coordinate::stringFromColumnIndex($lastCol) . '1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3b7de8']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Approval column letters (O = Punch In Approval, P = Punch Out Approval)
        $piApprColLetter = Coordinate::stringFromColumnIndex(15); // O
        $poApprColLetter = Coordinate::stringFromColumnIndex(16); // P

        // Data rows
        $ri = 2;
        foreach ($rows as $r) {
            $ws->fromArray([
                $r['month'],          $r['date'],           $r['day_name'],
                $r['emp_id'],         $r['emp_name'],
                $r['time_in'],        $r['time_out'],
                $r['punch_in_type'],  $r['punch_out_type'],
                $r['clocked_time'],   $r['ct_remark'],
                $r['leave_type'],     $r['reason_for_leave'],
                $r['punch_deviation'],
                $approvalLabel($r['punch_in_approval']),
                $approvalLabel($r['punch_out_approval']),
            ], null, 'A' . $ri);
            $ri++;
        }

        $lastRow = $ri - 1;

        // ── Dropdown validation on approval columns ────────────────────────────
        $approvalOptions = '"Approved,Not Approved"';
        foreach ([$piApprColLetter, $poApprColLetter] as $colLetter) {
            $validation = new \PhpOffice\PhpSpreadsheet\Cell\DataValidation();
            $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
            $validation->setAllowBlank(false);
            $validation->setShowDropDown(false);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Invalid value');
            $validation->setError('Please select "Approved" or "Not Approved".');
            $validation->setFormula1($approvalOptions);
            if ($lastRow >= 2) {
                $ws->setDataValidation("{$colLetter}2:{$colLetter}{$lastRow}", $validation);
            }
        }

        // Column widths
        foreach ([14,13,12,14,24,8,8,14,14,14,14,12,24,14,18,18] as $ci => $w) {
            $ws->getColumnDimension(Coordinate::stringFromColumnIndex($ci + 1))->setWidth($w);
        }
        $ws->freezePane('A2');

        $filename = 'Attendance_' . $from->format('Y_m') . '.xlsx';
        $writer   = new XlsxWriter($wb);

        return response()->streamDownload(fn() => $writer->save('php://output'), $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Re-upload edited attendance report — updates ONLY Punch In/Out Approval
    // ─────────────────────────────────────────────────────────────────────────

    public function reuploadAttendance(Request $request)
    {
        $request->validate([
            'file'           => 'required|file|mimes:xlsx,xls|max:20480',
            'expected_month' => 'required|date_format:Y-m',
        ]);

        $expectedMonth = $request->get('expected_month');
        $path          = $request->file('file')->store('tmp');

        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile(
                \Illuminate\Support\Facades\Storage::path($path)
            );
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load(\Illuminate\Support\Facades\Storage::path($path));
            $ws          = $spreadsheet->getActiveSheet();
            $data        = $ws->toArray(null, false, false, false);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Storage::delete($path);
            return back()->with('error', 'Could not read file: ' . $e->getMessage());
        } finally {
            \Illuminate\Support\Facades\Storage::delete($path);
        }

        if (count($data) < 2) {
            return back()->with('error', 'File has no data rows.');
        }

        // Map header → column index (0-based)
        $headerRow = array_map(fn($v) => strtolower(trim((string)$v)), $data[0]);
        $colEmpId  = array_search('new emp id', $headerRow);
        $colDate   = array_search('date', $headerRow);
        $colPiAppr = array_search('punch in approval', $headerRow);
        $colPoAppr = array_search('punch out approval', $headerRow);

        if ($colEmpId === false || $colDate === false || $colPiAppr === false || $colPoAppr === false) {
            return back()->with('error', 'Header row not recognised. Use the file downloaded from this page.');
        }

        // Pre-load employee emp_id → DB id map
        $empMap = Employee::pluck('id', 'employee_id')->all();

        $normalize = fn(?string $v): ?string => match(strtolower(trim((string)$v))) {
            'approved'     => 'approved',
            'not approved' => 'not_approved',
            default        => null,
        };

        $updated = 0;
        $skipped = 0;

        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            foreach (array_slice($data, 1) as $row) {
                if (empty(array_filter($row, fn($v) => $v !== null && $v !== ''))) continue;

                $empIdStr = trim((string)($row[$colEmpId] ?? ''));
                $dateRaw  = trim((string)($row[$colDate]  ?? ''));

                if (!$empIdStr || !$dateRaw) continue;

                // Parse date — format in the file is "dd-mm-yyyy"
                $date = null;
                foreach (['d-m-Y', 'Y-m-d', 'd/m/Y'] as $fmt) {
                    try {
                        $date = Carbon::createFromFormat($fmt, $dateRaw)->format('Y-m-d');
                        break;
                    } catch (\Throwable) {}
                }
                if (!$date) { $skipped++; continue; }

                // Month cross-check
                if (substr($date, 0, 7) !== $expectedMonth) { $skipped++; continue; }

                $empDbId = $empMap[$empIdStr] ?? null;
                if (!$empDbId) { $skipped++; continue; }

                $piAppr = $normalize($row[$colPiAppr] ?? null);
                $poAppr = $normalize($row[$colPoAppr] ?? null);

                if ($piAppr === null && $poAppr === null) { $skipped++; continue; }

                $update = ['updated_by' => auth()->id()];
                if ($piAppr !== null) $update['punch_in_approval']  = $piAppr;
                if ($poAppr !== null) $update['punch_out_approval'] = $poAppr;

                AttendanceApproval::updateOrCreate(
                    ['employee_id' => $empDbId, 'attendance_date' => $date],
                    $update
                );
                $updated++;
            }
            \Illuminate\Support\Facades\DB::commit();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return back()->with('error', 'Re-upload failed: ' . $e->getMessage());
        }

        $label = Carbon::createFromFormat('Y-m', $expectedMonth)->format('F Y');
        $msg   = "Re-upload complete — {$updated} attendance approval(s) updated for {$label}.";
        if ($skipped > 0) $msg .= " {$skipped} rows skipped.";

        return redirect()->route('reports.attendance', ['month' => $expectedMonth])
            ->with('success', $msg);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Approval — AJAX / form POST: save punch_in / punch_out approval for one
    //            employee + date.  Returns JSON for fetch() callers.
    // ─────────────────────────────────────────────────────────────────────────

    public function updateApproval(Request $request)
    {
        $validated = $request->validate([
            'employee_id'        => 'required|exists:employees,id',
            'attendance_date'    => 'required|date',
            'punch_in_approval'  => 'nullable|in:approved,not_approved',
            'punch_out_approval' => 'nullable|in:approved,not_approved',
        ]);

        // Only update the field(s) that were actually sent — the AJAX sends
        // one dropdown at a time, so the other key is absent from the request.
        // Using updateOrCreate with a partial update set preserves the other field.
        $updateData = ['updated_by' => auth()->id()];

        if ($request->has('punch_in_approval')) {
            $updateData['punch_in_approval'] = $validated['punch_in_approval'] ?? null;
        }
        if ($request->has('punch_out_approval')) {
            $updateData['punch_out_approval'] = $validated['punch_out_approval'] ?? null;
        }

        AttendanceApproval::updateOrCreate(
            [
                'employee_id'     => $validated['employee_id'],
                'attendance_date' => $validated['attendance_date'],
            ],
            $updateData
        );

        return response()->json(['ok' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Shared: Build flat attendance rows
    // ─────────────────────────────────────────────────────────────────────────

    private function buildAttendanceRows(int $companyId, Carbon $from, Carbon $to, ?string $empFilter): array
    {
        $employees = Employee::query()
            ->where('employee_status', 'active')
            ->when($empFilter, fn($q) => $q->where(fn($sq) =>
                $sq->where('employee_id', $empFilter)
                   ->orWhere('first_name', 'like', "%{$empFilter}%")
                   ->orWhere('last_name', 'like', "%{$empFilter}%")
            ))
            ->orderBy('employee_id')
            ->get();

        $employeeIds = $employees->pluck('id')->all();

        // All punches grouped by "employee_id_date"
        $allPunches = AttendanceRawPunch::whereIn('employee_id', $employeeIds)
            ->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->groupBy(fn($p) => $p->employee_id . '_' . $p->attendance_date->format('Y-m-d'));

        // All leave records grouped by "employee_id_date"
        $allLeaves = LeaveTrackerRecord::whereIn('employee_id', $employeeIds)
            ->whereBetween('leave_date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->groupBy(fn($r) => $r->employee_id . '_' . $r->leave_date->format('Y-m-d'));

        // Admin-saved approvals keyed by "employee_id_date"
        $allApprovals = AttendanceApproval::whereIn('employee_id', $employeeIds)
            ->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->keyBy(fn($a) => $a->employee_id . '_' . $a->attendance_date->format('Y-m-d'));

        // ── Pre-compute all day metadata ONCE (not per employee × per day) ───
        // Avoids N×E Carbon::parse() calls; uses PHP's lightweight date()

        // FIX: Month column = human-readable "Apr 2025", not period-end date
        $periodMonthLabel = $from->format('M Y');   // e.g. "Apr 2025"

        $dayMeta    = [];                  // 'Y-m-d' => [date, day_name, is_weekend]
        $minWorkSec = self::MIN_WORK_HOURS * 3600;
        $cutoffHHMM = substr(self::LATE_IN_CUTOFF, 0, 5); // "10:00"

        // FIX: Earliest valid check-in time. Punches before 05:30 are biometric
        // artifacts (previous day's late-night punch bled into the next day's date
        // by the device). Treating them as check-in would give 20+ hour clocked times.
        $minCheckInHHMM = '05:30';

        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $dateStr           = $d->format('Y-m-d');
            $ts                = mktime(0, 0, 0, $d->month, $d->day, $d->year);
            $dow               = (int) date('N', $ts); // 1=Mon … 7=Sun
            $dayMeta[$dateStr] = [
                'date'       => date('d-m-Y', $ts),
                'day_name'   => date('l', $ts),
                'is_weekend' => $dow >= 6,
            ];
        }

        $rows = [];

        foreach ($employees as $emp) {
            $empId   = $emp->id;
            $empName = $emp->full_name;

            foreach ($dayMeta as $date => $meta) {
                $key      = $empId . '_' . $date;
                $punches  = $allPunches->get($key, collect());
                $leaves   = $allLeaves->get($key, collect());
                $approval = $allApprovals->get($key);

                // Earliest check-in, latest check-out across multi-machine punches
                $checkIn  = $punches->filter(fn($p) => $p->check_in  !== null)->min('check_in');
                $checkOut = $punches->filter(fn($p) => $p->check_out !== null)->max('check_out');

                // FIX: Discard midnight/early-morning check-ins that are biometric
                // artifacts (prev-day late punch carried over to next day by the device).
                // Any check-in before 05:30 is treated as Punch Missing.
                if ($checkIn !== null && substr($checkIn, 0, 5) < $minCheckInHHMM) {
                    $checkIn = null;
                }

                // Punch In Type — string comparison is fine (HH:MM:SS is lexicographically orderable)
                if (!$checkIn) {
                    $punchInType = 'Punch Missing';
                } elseif (substr($checkIn, 0, 5) <= $cutoffHHMM) {
                    $punchInType = 'On Time';
                } else {
                    $punchInType = 'LateTimeIn';
                }

                // Punch Out Type
                $punchOutType = $checkOut ? 'On Time' : 'Punch Missing';

                // Clocked Time (D.HH.MM.SS) — use strtotime instead of Carbon (10× faster)
                $clockedTime = '';
                $ctRemark    = 'NA';
                if ($checkIn && $checkOut) {
                    $inTs  = strtotime($date . ' ' . $checkIn);
                    $outTs = strtotime($date . ' ' . $checkOut);
                    if ($outTs > $inTs) {
                        $totalSec    = $outTs - $inTs;
                        $days2       = (int) ($totalSec / 86400);
                        $hrs         = (int) (($totalSec % 86400) / 3600);
                        $mins        = (int) (($totalSec % 3600) / 60);
                        $secs        = $totalSec % 60;
                        $clockedTime = sprintf('%d.%02d.%02d.%02d', $days2, $hrs, $mins, $secs);
                        $ctRemark    = $totalSec >= $minWorkSec ? '>9:00 Hours' : '< 9:00 Hours';
                    }
                }

                // Leave info
                $leaveType   = '';
                $leaveReason = '';
                if ($leaves->isNotEmpty()) {
                    $leaveType   = $leaves->first()->leave_type ?? '';
                    $leaveReason = $leaves->first()->reason      ?? '';
                }

                // Punch Deviation (minutes late past 10:00)
                $deviation = '';
                if ($checkIn && $punchInType === 'LateTimeIn') {
                    $cutoffTs  = strtotime($date . ' ' . self::LATE_IN_CUTOFF);
                    $actualTs  = strtotime($date . ' ' . $checkIn);
                    $diffMin   = (int) (($actualTs - $cutoffTs) / 60);
                    $deviation = '+' . $diffMin . ' min';
                }

                $rows[] = [
                    'month'              => $periodMonthLabel,
                    'date'               => $meta['date'],
                    'date_ymd'           => $date,           // YYYY-MM-DD for JS/AJAX
                    'day_name'           => $meta['day_name'],
                    'internal_emp_id'    => $empId,          // DB employees.id for AJAX
                    'emp_id'             => $emp->employee_id,
                    'emp_name'           => $empName,
                    'time_in'            => $checkIn  ? substr($checkIn,  0, 5) : '',
                    'time_out'           => $checkOut ? substr($checkOut, 0, 5) : '',
                    'punch_in_type'      => $punchInType,
                    'punch_out_type'     => $punchOutType,
                    'clocked_time'       => $clockedTime,
                    'ct_remark'          => $ctRemark,
                    'leave_type'         => $leaveType,
                    'reason_for_leave'   => $leaveReason,
                    'punch_deviation'    => $deviation,
                    // Approval values: DB record wins; if no record exists and there IS
                    // a punch, default to 'approved' (presume approval, flag exceptions).
                    'punch_in_approval'  => $approval?->punch_in_approval  ?? ($checkIn  ? 'approved' : null),
                    'punch_out_approval' => $approval?->punch_out_approval ?? ($checkOut ? 'approved' : null),
                    'has_punch'          => $punches->isNotEmpty(),
                    'is_weekend'         => $meta['is_weekend'],
                    'has_leave'          => $leaveType !== '',
                ];
            }
        }

        return $rows;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Shared: Build styled Excel
    // ─────────────────────────────────────────────────────────────────────────

    private function buildAttendanceExcel(array $rows, Carbon $month): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Attendance');

        $headers = [
            'Month', 'Date', 'Day Name', 'New Emp ID', 'Employee Name',
            'TimeIn', 'TimeOut', 'Punch In Type', 'Punch Out Type',
            'Clocked Time', 'CT_Remark', 'Leave Type', 'Reason For Leave',
            'Punch Deviation', 'Punch In Approval', 'Punch Out Approval',
        ];

        $col       = fn(int $c): string => Coordinate::stringFromColumnIndex($c);
        $lastColLtr = $col(count($headers)); // 'P'

        // ── Write ALL data in one shot (fastest bulk write) ──────────────────
        $allData = [$headers];
        foreach ($rows as $row) {
            $allData[] = [
                $row['month'],  $row['date'],            $row['day_name'],
                $row['emp_id'], $row['emp_name'],
                $row['time_in'],     $row['time_out'],
                $row['punch_in_type'],  $row['punch_out_type'],
                $row['clocked_time'],   $row['ct_remark'],
                $row['leave_type'],     $row['reason_for_leave'],
                $row['punch_deviation'],
                $row['punch_in_approval'], $row['punch_out_approval'],
            ];
        }
        $sheet->fromArray($allData, null, 'A1', true);

        // ── Header row styling (one applyFromArray call) ─────────────────────
        $sheet->getStyle("A1:{$lastColLtr}1")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'name' => 'Arial', 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF4E7C00']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCCCCCC']]],
        ]);
        // Punch In/Out Approval column headers → red text
        $sheet->getStyle("O1:P1")->getFont()->getColor()->setARGB('FFFF0000');
        $sheet->getRowDimension(1)->setRowHeight(18);

        $lastDataRow = count($rows) + 1;

        if ($lastDataRow >= 2) {
            // ── Base data range: borders + font — one call for ALL rows ──────
            $sheet->getStyle("A2:{$lastColLtr}{$lastDataRow}")->applyFromArray([
                'font'    => ['name' => 'Arial', 'size' => 9],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFDDDDDD']]],
            ]);

            // ── Bucket rows by their fill colour ─────────────────────────────
            $weekendRows = [];
            $leaveRows   = [];
            $altRows     = [];          // every even-indexed non-weekend non-leave row
            $pitGreen    = [];          // On Time
            $pitOrange   = [];          // LateTimeIn
            $pitRed      = [];          // Punch Missing
            $potRed      = [];          // Punch Out Missing

            foreach ($rows as $ri => $row) {
                $rowNum = $ri + 2;
                if ($row['is_weekend'])    { $weekendRows[] = $rowNum; }
                elseif ($row['has_leave']) { $leaveRows[]   = $rowNum; }
                elseif ($ri % 2 === 0)    { $altRows[]      = $rowNum; }

                match ($row['punch_in_type']) {
                    'On Time'    => $pitGreen[]  = $rowNum,
                    'LateTimeIn' => $pitOrange[] = $rowNum,
                    default      => $pitRed[]    = $rowNum,
                };
                if ($row['punch_out_type'] === 'Punch Missing') {
                    $potRed[] = $rowNum;
                }
            }

            // ── Apply fills by group (contiguous rows collapsed into ranges) ─
            $this->applyFillToRows($sheet, $weekendRows, 'FFE8E8E8', $lastColLtr);
            $this->applyFillToRows($sheet, $leaveRows,   'FFE8F4FF', $lastColLtr);
            $this->applyFillToRows($sheet, $altRows,     'FFF4FCE8', $lastColLtr);

            // ── Punch type font colours applied per column in runs ───────────
            $this->applyFontColorToRows($sheet, $pitGreen,  8, 'FF22863A', true);
            $this->applyFontColorToRows($sheet, $pitOrange, 8, 'FFC05621', true);
            $this->applyFontColorToRows($sheet, $pitRed,    8, 'FFD93025', true);
            $this->applyFontColorToRows($sheet, $potRed,    9, 'FFD93025', true);
        }

        // ── Column widths ─────────────────────────────────────────────────────
        $widths = [14, 13, 12, 14, 24, 8, 8, 14, 14, 14, 14, 12, 24, 14, 18, 18];
        foreach ($widths as $ci => $w) {
            $sheet->getColumnDimension($col($ci + 1))->setWidth($w);
        }

        $sheet->freezePane('A2');

        if ($lastDataRow > 1) {
            $sheet->setAutoFilter("A1:{$lastColLtr}{$lastDataRow}");
        }

        return $spreadsheet;
    }

    /**
     * Apply a solid fill colour to a list of full-row ranges.
     * Collapses consecutive row numbers into single range calls (fewer style objects).
     */
    private function applyFillToRows(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        array $rows,
        string $argb,
        string $lastCol
    ): void {
        foreach ($this->toRuns($rows) as [$s, $e]) {
            $range = "A{$s}:{$lastCol}" . ($s === $e ? $s : $e);
            $sheet->getStyle($range)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB($argb);
        }
    }

    /**
     * Apply font colour (and optional bold) to a single column across listed rows.
     * Collapses consecutive rows into range calls.
     */
    private function applyFontColorToRows(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        array $rows,
        int $colIdx,
        string $argb,
        bool $bold = false
    ): void {
        $colLtr = Coordinate::stringFromColumnIndex($colIdx);
        foreach ($this->toRuns($rows) as [$s, $e]) {
            $range = $s === $e ? "{$colLtr}{$s}" : "{$colLtr}{$s}:{$colLtr}{$e}";
            $style = $sheet->getStyle($range);
            $style->getFont()->getColor()->setARGB($argb);
            if ($bold) $style->getFont()->setBold(true);
        }
    }

    /**
     * Convert an unsorted list of row numbers into contiguous runs.
     * e.g. [2,3,4,7,8,10] → [[2,4],[7,8],[10,10]]
     */
    private function toRuns(array $rows): array
    {
        if (empty($rows)) return [];
        sort($rows);
        $runs  = [];
        $start = $end = $rows[0];
        foreach (array_slice($rows, 1) as $r) {
            if ($r === $end + 1) { $end = $r; }
            else { $runs[] = [$start, $end]; $start = $end = $r; }
        }
        $runs[] = [$start, $end];
        return $runs;
    }
}
