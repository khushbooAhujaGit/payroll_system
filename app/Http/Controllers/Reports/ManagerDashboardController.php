<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\LeaveApplication;
use App\Models\HelpdeskTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManagerDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->company_id ?? 1;
        $today = now()->toDateString();
        $headcount = Employee::selectRaw('employee_status, COUNT(*) as count')->groupBy('employee_status')->pluck('count', 'employee_status');
        $todayAttendance = Attendance::whereHas('employee', fn($q) => $q)->where('attendance_date', $today)->selectRaw('status, COUNT(*) as count')->groupBy('status')->pluck('count', 'status');
        $pendingLeaves = LeaveApplication::whereHas('employee', fn($q) => $q)->where('status', 'pending')->with(['employee:id,employee_id,first_name,middle_name,last_name', 'leaveType:id,name,code'])->limit(10)->get();
        $onLeaveToday = LeaveApplication::whereHas('employee', fn($q) => $q)->where('status', 'approved')->where('from_date', '<=', $today)->where('to_date', '>=', $today)->with(['employee:id,employee_id,first_name,middle_name,last_name', 'leaveType:id,name,code'])->get();
        $openTickets = HelpdeskTicket::where('company_id', $companyId)->whereNotIn('status', ['closed'])->count();
        return response()->json(['headcount' => $headcount, 'today_attendance' => $todayAttendance, 'pending_leaves' => $pendingLeaves, 'on_leave_today' => $onLeaveToday, 'open_tickets' => $openTickets]);
    }

    public function attendanceTrends(Request $request): JsonResponse
    {
        return response()->json(['data' => []]);
    }
}
