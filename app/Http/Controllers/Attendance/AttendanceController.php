<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceRegularization;
use App\Models\OvertimeRequest;
use App\Services\AttendanceService;
use App\Services\ExcelImportService;
use App\Imports\AttendanceImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    public function __construct(
        protected AttendanceService $attendanceService,
        protected ExcelImportService $excelImportService
    ) {}

    // ─────────────────────────────────────────────
    // MONTHLY REGISTER
    // ─────────────────────────────────────────────

    public function monthly(Request $request): JsonResponse
    {
        $request->validate([
            'entity_id' => 'required|exists:entities,id',
            'year'      => 'required|integer',
            'month'     => 'required|integer|min:1|max:12',
        ]);

        $from = Carbon::createFromDate($request->year, $request->month, 1)->startOfMonth()->toDateString();
        $to   = Carbon::createFromDate($request->year, $request->month, 1)->endOfMonth()->toDateString();

        $records = Attendance::with('employee:id,employee_id,first_name,last_name,department_id')
            ->whereHas('employee', fn($q) => $q->where('entity_id', $request->entity_id))
            ->whereBetween('attendance_date', [$from, $to])
            ->when($request->department_id, fn($q) => $q->whereHas('employee', fn($q2) => $q2->where('department_id', $request->department_id)))
            ->when($request->employee_id, fn($q) => $q->where('employee_id', $request->employee_id))
            ->get()
            ->groupBy('employee_id');

        return response()->json(['data' => $records, 'period' => ['from' => $from, 'to' => $to]]);
    }

    // Single employee attendance for a month with summary
    public function employeeMonthly(Request $request, int $employeeId): JsonResponse
    {
        $request->validate(['year' => 'required|integer', 'month' => 'required|integer|min:1|max:12']);

        $from    = Carbon::createFromDate($request->year, $request->month, 1)->startOfMonth()->toDateString();
        $to      = Carbon::createFromDate($request->year, $request->month, 1)->endOfMonth()->toDateString();
        $records = Attendance::where('employee_id', $employeeId)->whereBetween('attendance_date', [$from, $to])->orderBy('attendance_date')->get();
        $summary = $this->attendanceService->getMonthlySummary($employeeId, $from, $to);

        return response()->json(['attendance' => $records, 'summary' => $summary]);
    }

    // ─────────────────────────────────────────────
    // MARK ATTENDANCE (Manual / Biometric sync)
    // ─────────────────────────────────────────────

    public function mark(Request $request): JsonResponse
    {
        $this->authorize('attendance.mark');

        $validated = $request->validate([
            'employee_id'     => 'required|exists:employees,id',
            'attendance_date' => 'required|date',
            'check_in'        => 'nullable|date_format:H:i',
            'check_out'       => 'nullable|date_format:H:i',
            'status'          => 'required|in:present,absent,half_day,late,leave,holiday,weekly_off,work_from_home,on_duty,tour,lop',
            'work_type'       => 'nullable|in:wfo,wfh,client_location,hybrid',
            'remarks'         => 'nullable|string',
        ]);

        $attendance = $this->attendanceService->markAttendance(
            $validated['employee_id'],
            $validated['attendance_date'],
            array_merge($validated, ['source' => 'manual'])
        );

        return response()->json(['message' => 'Attendance marked.', 'data' => $attendance]);
    }

    // Bulk manual attendance for a day
    public function bulkMark(Request $request): JsonResponse
    {
        $this->authorize('attendance.mark');

        $request->validate([
            'attendance_date' => 'required|date',
            'records'         => 'required|array',
            'records.*.employee_id' => 'required|exists:employees,id',
            'records.*.status'      => 'required|string',
        ]);

        $count = 0;
        foreach ($request->records as $rec) {
            $this->attendanceService->markAttendance(
                $rec['employee_id'],
                $request->attendance_date,
                ['status' => $rec['status'], 'check_in' => $rec['check_in'] ?? null, 'check_out' => $rec['check_out'] ?? null, 'source' => 'manual']
            );
            $count++;
        }

        return response()->json(['message' => "{$count} attendance records saved."]);
    }

    // ─────────────────────────────────────────────
    // TODAY'S SNAPSHOT
    // ─────────────────────────────────────────────

    public function today(Request $request): JsonResponse
    {
        $today = now()->toDateString();

        $summary = Attendance::whereHas('employee', fn($q) => $q->where('entity_id', $request->entity_id)->where('employee_status', 'active'))
            ->where('attendance_date', $today)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $totalActive = \App\Models\Employee::where('entity_id', $request->entity_id)->active()->count();
        $markedCount = Attendance::whereHas('employee', fn($q) => $q->where('entity_id', $request->entity_id))->where('attendance_date', $today)->count();

        return response()->json([
            'date'         => $today,
            'total_active' => $totalActive,
            'marked_count' => $markedCount,
            'not_marked'   => $totalActive - $markedCount,
            'summary'      => $summary,
        ]);
    }

    // ─────────────────────────────────────────────
    // REGULARIZATION
    // ─────────────────────────────────────────────

    public function storeRegularization(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'attendance_date'      => 'required|date',
            'requested_check_in'   => 'nullable|date_format:H:i',
            'requested_check_out'  => 'nullable|date_format:H:i',
            'reason'               => 'required|in:forgot_punch,system_error,client_visit,wfh,travel,other',
            'remarks'              => 'nullable|string|max:500',
        ]);

        $employee = auth()->user()->employee;
        if (!$employee) return response()->json(['message' => 'No employee profile found.'], 422);

        $attendance = Attendance::where('employee_id', $employee->id)
            ->where('attendance_date', $validated['attendance_date'])
            ->first();

        $reg = AttendanceRegularization::create(array_merge($validated, [
            'employee_id'   => $employee->id,
            'attendance_id' => $attendance?->id,
            'status'        => 'pending',
        ]));

        return response()->json(['message' => 'Regularization request submitted.', 'data' => $reg], 201);
    }

    public function pendingRegularizations(Request $request): JsonResponse
    {
        $this->authorize('attendance.approve');

        $regs = AttendanceRegularization::with(['employee:id,employee_id,first_name,last_name', 'employee.department:id,name'])
            ->whereHas('employee', fn($q) => $q->where('entity_id', $request->entity_id))
            ->where('status', 'pending')
            ->orderBy('attendance_date')
            ->paginate(50);

        return response()->json($regs);
    }

    public function approveRegularization(Request $request, AttendanceRegularization $regularization): JsonResponse
    {
        $this->authorize('attendance.approve');

        $request->validate(['action' => 'required|in:approved,rejected', 'remarks' => 'nullable|string']);

        if ($request->action === 'approved') {
            $this->attendanceService->approveRegularization($regularization);
        } else {
            $regularization->update([
                'status'           => 'rejected',
                'approved_by'      => auth()->id(),
                'approved_at'      => now(),
                'approval_remarks' => $request->remarks,
            ]);
        }

        return response()->json(['message' => "Regularization {$request->action}."]);
    }

    // ─────────────────────────────────────────────
    // OVERTIME
    // ─────────────────────────────────────────────

    public function overtimeRequests(Request $request): JsonResponse
    {
        $query = OvertimeRequest::with('employee:id,employee_id,first_name,last_name')
            ->whereHas('employee', fn($q) => $q->where('entity_id', $request->entity_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderByDesc('overtime_date');

        return response()->json($query->paginate(50));
    }

    public function approveOvertime(Request $request, OvertimeRequest $overtime): JsonResponse
    {
        $this->authorize('attendance.approve');

        $request->validate(['action' => 'required|in:approved,rejected', 'overtime_amount' => 'required_if:action,approved|numeric|min:0']);

        $overtime->update([
            'status'          => $request->action,
            'overtime_amount' => $request->action === 'approved' ? $request->overtime_amount : 0,
            'approved_by'     => auth()->id(),
        ]);

        return response()->json(['message' => "Overtime {$request->action}."]);
    }

    // ─────────────────────────────────────────────
    // EXCEL IMPORT
    // ─────────────────────────────────────────────

    public function uploadTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $this->excelImportService->generateTemplate(AttendanceImporter::class);
    }

    public function import(Request $request): JsonResponse
    {
        $this->authorize('attendance.mark');

        $request->validate([
            'file'      => 'required|file|mimes:xlsx,xls|max:20480',
            'entity_id' => 'required|exists:entities,id',
            'year'      => 'required|integer',
            'month'     => 'required|integer|min:1|max:12',
        ]);

        $result = $this->excelImportService->import(
            $request->file('file'),
            'attendance',
            AttendanceImporter::class,
            ['entity_id' => $request->entity_id, 'year' => $request->year, 'month' => $request->month]
        );

        return response()->json($result);
    }

    // ─────────────────────────────────────────────
    // EXPORT
    // ─────────────────────────────────────────────

    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $request->validate(['entity_id' => 'required', 'year' => 'required|integer', 'month' => 'required|integer']);

        $from = Carbon::createFromDate($request->year, $request->month, 1)->startOfMonth()->toDateString();
        $to   = Carbon::createFromDate($request->year, $request->month, 1)->endOfMonth()->toDateString();

        $records = Attendance::with('employee:id,employee_id,first_name,last_name,department_id', 'employee.department:id,name')
            ->whereHas('employee', fn($q) => $q->where('entity_id', $request->entity_id))
            ->whereBetween('attendance_date', [$from, $to])
            ->orderBy('attendance_date')
            ->get();

        $headers = ['Employee ID', 'Name', 'Department', 'Date', 'Check In', 'Check Out', 'Status', 'Working Minutes', 'OT Minutes', 'Remarks'];
        $data    = $records->map(fn($r) => [
            $r->employee->employee_id,
            $r->employee->full_name,
            $r->employee->department?->name,
            $r->attendance_date->format('d-m-Y'),
            $r->check_in,
            $r->check_out,
            $r->status,
            $r->working_minutes,
            $r->overtime_minutes,
            $r->remarks,
        ])->toArray();

        return $this->excelImportService->export($data, $headers, "Attendance_{$request->year}_{$request->month}");
    }
}
