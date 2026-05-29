<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\LeaveApplication;
use App\Models\HelpdeskTicket;
use App\Models\PayrollRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrAnalyticsDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $entityId = $request->entity_id;
        $today    = now()->toDateString();
        $totalActive    = Employee::where('entity_id', $entityId)->where('employee_status', 'active')->count();
        $totalOnNotice  = Employee::where('entity_id', $entityId)->where('employee_status', 'on_notice')->count();
        $newJoiners = Employee::where('entity_id', $entityId)->whereYear('date_of_joining', now()->year)->whereMonth('date_of_joining', now()->month)->count();
        $todayAttendance = Attendance::whereHas('employee', fn($q) => $q->where('entity_id', $entityId))->where('attendance_date', $today)->selectRaw('status, COUNT(*) as count')->groupBy('status')->pluck('count', 'status');
        $leaveThisMonth = LeaveApplication::whereHas('employee', fn($q) => $q->where('entity_id', $entityId))->whereYear('from_date', now()->year)->whereMonth('from_date', now()->month)->selectRaw('status, COUNT(*) as count')->groupBy('status')->pluck('count', 'status');
        $deptStrength = Employee::where('entity_id', $entityId)->where('employee_status', 'active')->selectRaw('department_id, COUNT(*) as count')->with('department:id,name')->groupBy('department_id')->orderByDesc('count')->get();
        $genderRatio = Employee::where('entity_id', $entityId)->where('employee_status', 'active')->selectRaw('gender, COUNT(*) as count')->groupBy('gender')->pluck('count', 'gender');
        $pendingActions = [
            'leave_approvals' => LeaveApplication::whereHas('employee', fn($q) => $q->where('entity_id', $entityId))->where('status', 'pending')->count(),
            'open_tickets' => HelpdeskTicket::where('entity_id', $entityId)->whereNotIn('status', ['closed', 'resolved'])->count(),
        ];
        $hiringTrend = Employee::where('entity_id', $entityId)->where('date_of_joining', '>=', now()->subMonths(12)->startOfMonth())->selectRaw('YEAR(date_of_joining) as year, MONTH(date_of_joining) as month, COUNT(*) as count')->groupBy('year', 'month')->orderBy('year')->orderBy('month')->get();
        $lastRun = PayrollRun::where('entity_id', $entityId)->whereIn('status', ['finalized', 'paid'])->orderByDesc('id')->first();
        $payrollSummary = $lastRun ? ['total_employees' => $lastRun->total_employees, 'total_gross' => $lastRun->total_gross, 'total_net' => $lastRun->total_net] : null;
        return response()->json(['total_active' => $totalActive, 'total_on_notice' => $totalOnNotice, 'new_joiners' => $newJoiners, 'today_attendance' => $todayAttendance, 'leave_this_month' => $leaveThisMonth, 'dept_strength' => $deptStrength, 'gender_ratio' => $genderRatio, 'pending_actions' => $pendingActions, 'hiring_trend' => $hiringTrend, 'payroll_summary' => $payrollSummary]);
    }

    public function payrollTrend(Request $request): JsonResponse { return response()->json(['data' => []]); }
    public function attritionTrend(Request $request): JsonResponse { return response()->json(['data' => []]); }
    public function leaveUtilisation(Request $request): JsonResponse { return response()->json(['data' => []]); }
}
