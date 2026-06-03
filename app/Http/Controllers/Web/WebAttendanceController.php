<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Imports\AttendanceImporter;
use App\Models\Attendance;
use App\Models\AttendanceRawPunch;
use App\Models\Employee;
use App\Models\Entity;
use App\Services\AttendanceService;
use App\Services\ExcelImportService;
use App\Http\Controllers\Concerns\ImportsExcel;
use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Http\Request;

class WebAttendanceController extends Controller
{
    use ImportsExcel;

    public function __construct(
        protected AttendanceService $attendanceService,
        protected ExcelImportService $excelImportService
    ) {}

    public function index(Request $request)
    { 
        // ── Entity resolution ─────────────────────────────────────────────
        // Admin users have no linked employee — let them pick entity from the
        // URL param.  Fall back to the first entity that has active employees.
        $userEntityId = auth()->user()->employee?->company_id;

        $companies = Company::orderBy('name')->get(['id', 'name']);

        if ($request->filled('company_id')) {
            $companyId = (int) $request->get('company_id');
        } elseif ($userEntityId) {
            $companyId = $userEntityId;
        } else {
            // Pick the first entity that actually has active employees
            $companyId = Employee::where('employee_status', 'active')
                ->whereNotNull('company_id')
                ->value('company_id') ?? ($entities->first()?->id ?? 1);
        }

        $year     = (int) $request->get('year', now()->year);
        $month    = (int) $request->get('month', now()->month);

        $from = Carbon::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
        $to   = Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

        $employees = Employee::query()
            ->currentlyEmployed()
            ->with(['department'])
            ->orderBy('employee_id')
            ->paginate(30)
            ->withQueryString();

        // Scope attendance data to only the current page's employees for efficiency
        $pageEmpIds = $employees->pluck('id');

        $records = Attendance::whereIn('employee_id', $pageEmpIds)
            ->whereBetween('attendance_date', [$from, $to])
            ->get()
            ->groupBy('employee_id');

        $todaySummary = Attendance::whereHas('employee', fn($q) => $q->currentlyEmployed())
            ->where('attendance_date', now()->toDateString())
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // Grouped collection — used by the attendance grid for day-cell tooltips
        $rawPunches = AttendanceRawPunch::whereBetween('attendance_date', [$from, $to])
            ->whereIn('employee_id', $pageEmpIds)
            ->orderBy('attendance_date')
            ->orderBy('employee_id')
            ->get()
            ->groupBy('employee_id');

        // Paginated flat list — used by the Biometric Punch Records table.
        // Restricted to currently active employees so left employees' punches
        // don't inflate the count.
        $rawPunchesPaginated = AttendanceRawPunch::whereBetween('attendance_date', [$from, $to])
            ->whereHas('employee', fn($q) => $q->currentlyEmployed())
            ->with(['employee:id,first_name,middle_name,last_name'])
            ->orderBy('attendance_date')
            ->orderBy('employee_id')
            ->paginate(50, ['*'], 'punch_page')
            ->withQueryString();

        // What date ranges actually have biometric data for this entity?
        // Used to show a hint in the UI when no data found.
        $biometricDataRange = AttendanceRawPunch::whereHas('employee', fn($q) => $q)
            ->selectRaw('MIN(attendance_date) as min_date, MAX(attendance_date) as max_date')
            ->first();

        return view('attendance.index', compact(
            'employees', 'records', 'todaySummary',
            'year', 'month', 'from', 'to', 'companyId', 'rawPunches', 'rawPunchesPaginated', 'companies',
            'biometricDataRange'
        ));
    }

    public function mark(Request $request)
    {
        $validated = $request->validate([
            'attendance_date'        => 'required|date',
            'records'                => 'required|array',
            'records.*.emp_id'       => 'required|exists:employees,id',
            'records.*.status'       => 'required|string',
            'records.*.work_type'    => 'nullable|string',
            'records.*.remarks'      => 'nullable|string|max:200',
        ]);

        foreach ($validated['records'] as $rec) {
            $this->attendanceService->markAttendance(
                $rec['emp_id'],
                $validated['attendance_date'],
                [
                    'status'    => $rec['status'],
                    'work_type' => $rec['work_type'] ?? 'wfo',
                    'remarks'   => $rec['remarks'] ?? null,
                    'source'    => 'manual',
                ]
            );
        }

        return redirect()->route('attendance')->with('success', 'Attendance saved successfully.');
    }

    public function export(Request $request)
    {
        $userEntityId = auth()->user()->employee?->company_id;
        if ($request->filled('company_id')) {
            $companyId = (int) $request->get('company_id');
        } elseif ($userEntityId) {
            $companyId = $userEntityId;
        } else {
            $companyId = Employee::where('employee_status', 'active')->whereNotNull('company_id')->value('company_id') ?? 1;
        }

        $year  = (int) $request->get('year',  now()->year);
        $month = (int) $request->get('month', now()->month);
        $from  = Carbon::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
        $to    = Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

        $records = Attendance::whereHas('employee', fn($q) => $q)
            ->with(['employee:id,first_name,middle_name,last_name,employee_id,department_id', 'employee.department:id,name'])
            ->whereBetween('attendance_date', [$from, $to])
            ->orderBy('attendance_date')
            ->orderBy('employee_id')
            ->get();

        $headers = [
            'Employee ID', 'Employee Name', 'Department', 'Date',
            'Status', 'Work Type', 'Check In', 'Check Out', 'Remarks',
        ];

        $rows = $records->map(fn($r) => [
            $r->employee?->employee_id,
            $r->employee ? ($r->employee->full_name) : null,
            $r->employee?->department?->name,
            $r->attendance_date?->format('d-m-Y'),
            $r->status,
            $r->work_type,
            $r->check_in  ? substr($r->check_in,  0, 5) : null,
            $r->check_out ? substr($r->check_out, 0, 5) : null,
            $r->remarks,
        ]);

        return $this->streamXlsx('attendance_' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT), $headers, $rows);
    }

    public function template()
    {
        return $this->excelImportService->generateTemplate(AttendanceImporter::class);
    }

    public function import(Request $request)
    {
        $companyId = $this->resolveCompanyId($request);

        $request->validate([
            'file'  => 'required|file|mimes:xlsx,xls|max:20480',
        ]);

        $result = $this->excelImportService->import(
            $request->file('file'),
            'attendance',
            AttendanceImporter::class,
            ['company_id' => $companyId]
        );

        return $this->handleImportResult($result, 'attendance', 'attendance record');
    }

    public function storePunch(Request $request)
    {
        $validated = $request->validate([
            'employee_id'     => 'required|exists:employees,id',
            'attendance_date' => 'required|date',
            'check_in'        => 'nullable|date_format:H:i',
            'check_out'       => 'nullable|date_format:H:i',
            'machine_name'    => 'nullable|string|max:10',
        ]);

        if (empty($validated['check_in']) && empty($validated['check_out'])) {
            return redirect()->back()->withErrors(['check_in' => 'At least one of Check In or Check Out is required.']);
        }

        // Prevent duplicate punch for same employee + date + machine
        $existing = AttendanceRawPunch::where('employee_id', $validated['employee_id'])
            ->where('attendance_date', $validated['attendance_date'])
            ->when($validated['machine_name'] ?? null, fn($q, $m) => $q->where('machine_name', $m))
            ->first();

        if ($existing) {
            // Update the existing record instead of creating duplicate
            $existing->update([
                'check_in'    => $validated['check_in']   ? $validated['check_in'] . ':00'  : $existing->check_in,
                'check_out'   => $validated['check_out']  ? $validated['check_out'] . ':00' : $existing->check_out,
                'uploaded_by' => auth()->id(),
            ]);
            return redirect()
            ->route('attendance', $request->only('year', 'month', 'company_id'))
                ->with('success', 'Punch record updated (existing record found for this date).');
        }

        AttendanceRawPunch::create([
            'employee_id'     => $validated['employee_id'],
            'attendance_date' => $validated['attendance_date'],
            'check_in'        => $validated['check_in']  ? $validated['check_in'] . ':00'  : null,
            'check_out'       => $validated['check_out'] ? $validated['check_out'] . ':00' : null,
            'machine_name'    => $validated['machine_name'] ?? 'manual',
            'source_file'     => 'manual',
            'uploaded_by'     => auth()->id(),
        ]);

        return redirect()->route('attendance', $request->only('year', 'month', 'company_id',))
            ->with('success', 'Punch record added.');
    }

    public function updatePunch(Request $request, AttendanceRawPunch $punch)
    {
        $validated = $request->validate([
            'check_in'     => 'nullable|date_format:H:i',
            'check_out'    => 'nullable|date_format:H:i',
            'machine_name' => 'nullable|string|max:10',
        ]);

        $punch->update([
            'check_in'    => $validated['check_in']  ? $validated['check_in'] . ':00'  : null,
            'check_out'   => $validated['check_out'] ? $validated['check_out'] . ':00' : null,
            'machine_name'=> $validated['machine_name'] ?? $punch->machine_name,
            'uploaded_by' => auth()->id(),
        ]);

        return redirect()->route('attendance', $request->only('year', 'month', 'company_id',))
            ->with('success', 'Punch updated.');
    }

    public function destroyPunch(AttendanceRawPunch $punch)
    {
        $punch->delete();
        return redirect()->route('attendance')->with('success', 'Raw punch deleted.');
    }

    // ─────────────────────────────────────────────
    // BIOMETRIC XLS IMPORT  (runs attendance_import.py)
    // ─────────────────────────────────────────────

    /**
     * Accept one or more raw biometric machine XLS files from the browser,
     * save them to a temp directory (preserving original filenames so the
     * Python script can detect the machine from the name prefix), run
     * attendance_import.py, then redirect with success or error.
     *
     * Supports: single file  →  python script attendance_import.py 3 10_April.xls
     *           multi files  →  python script attendance_import.py 3 10_April.xls 12_April.xls K_April.xls
     */
    public function biometricImport(Request $request)
    {
        $companyId = $this->resolveCompanyId($request);

        $request->validate([
            'files'   => 'required|array|min:1|max:3',
            'files.*' => 'required|file|max:51200',   // 50 MB per file
        ]);

        // ── Save uploads preserving the original filename ─────────────────
        // The Python script detects machine from prefix: 10_ / 12_ / K_
        $tempDir = storage_path('app/temp_attendance');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $savedPaths = [];
        foreach ($request->file('files') as $file) {
            $name = $file->getClientOriginalName();
            $file->move($tempDir, $name);
            $savedPaths[] = $tempDir . DIRECTORY_SEPARATOR . $name;
        }

        // ── Locate Python interpreter ────────────────────────────────────
        $python = $this->findPython();
        if ($python === null) {
            $this->cleanupTempFiles($savedPaths);
            return redirect()->route('attendance')
                ->with('error', 'Python is not available on this server. Run the import manually: '
                    . "python scripts/attendance_import.py {$companyId} <file(s)>");
        }

        // ── Execute the script ────────────────────────────────────────────
        $script = base_path('scripts' . DIRECTORY_SEPARATOR . 'attendance_import.py');
        $args   = array_merge([$python, $script], $savedPaths);
        $cmd    = implode(' ', array_map('escapeshellarg', $args));

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($cmd, $descriptors, $pipes, base_path());

        if (!is_resource($process)) {
            $this->cleanupTempFiles($savedPaths);
            return redirect()->route('attendance')
                ->with('error', 'Failed to start the attendance import process. Check server configuration.');
        }

        fclose($pipes[0]);
        $stdout   = stream_get_contents($pipes[1]); fclose($pipes[1]);
        $stderr   = stream_get_contents($pipes[2]); fclose($pipes[2]);
        $exitCode = proc_close($process);
        $output   = $stdout . $stderr;

        // ── Cleanup temp files ─────────────────────────────────────────────
        $this->cleanupTempFiles($savedPaths);
        $msg  = $this->summariseScriptOutput($output);

        // ── Success ───────────────────────────────────────────────────────
        if ($exitCode === 0) {
            preg_match('/Records processed\s*:\s*(\d+)/i', $output, $m);
            $count = isset($m[1]) ? (int) $m[1] : '?';
            return redirect()->route('attendance')
                ->with('success', "Biometric import complete. {$count} attendance record(s) stored.");
        }

        // ── Failure — find the error Excel written by the script ──────────
        $errorFile = null;
        if (preg_match('/Error file saved to\s*:\s*(.+\.xlsx)/i', $output, $m)) {
            $full = trim($m[1]);
            if (file_exists($full)) {
                $errorFile = basename($full);
            }
        }

        // Fallback: pick newest attendance_errors_*.xlsx in error_exports/
        if (!$errorFile) {
            $pattern = storage_path('app/error_exports/attendance_errors_*.xlsx');
            $matches = glob($pattern);
            if ($matches) {
                usort($matches, fn($a, $b) => filemtime($b) - filemtime($a));
                $newest = $matches[0];
                if (filemtime($newest) >= (time() - 30)) {   // created in last 30 s
                    $errorFile = basename($newest);
                }
            }
        }

        $redirect = redirect()->route('attendance')->with('error', 'Biometric import failed. ' . $msg);

        if ($errorFile) {
            $redirect = $redirect->with('error_file', $errorFile);
        }

        return $redirect;
    }

    // ─────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────

    /**
     * Try common Python binary names (cross-platform).
     * Returns the first one that responds to --version, or null.
     *
     * Uses proc_open() instead of exec() because exec() is commonly
     * disabled on shared hosting (Hostinger, cPanel, etc.).
     */
    private function findPython(): ?string
    {
        foreach (['python3', 'python', 'py'] as $bin) {
            $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $process = @proc_open(
                escapeshellcmd($bin) . ' --version',
                $descriptors,
                $pipes
            );
            if (is_resource($process)) {
                fclose($pipes[0]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                if (proc_close($process) === 0) {
                    return $bin;
                }
            }
        }
        return null;
    }

    /**
     * Extract a short human-readable summary from the script's stdout/stderr
     * to show in the flash error (strip separator lines, keep last 3 lines).
     */
    private function summariseScriptOutput(string $output): string
    {
        $lines = array_values(array_filter(
            array_map('trim', explode("\n", $output)),
            fn($l) => $l !== '' && !preg_match('/^=+$/', $l) && !str_starts_with($l, 'Error file saved')
        ));

        $tail = array_slice($lines, -3);
        return implode(' | ', $tail);
    }

    /** Delete a list of file paths, ignoring failures. */
    private function cleanupTempFiles(array $paths): void
    {
        foreach ($paths as $path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        }
    }
}
