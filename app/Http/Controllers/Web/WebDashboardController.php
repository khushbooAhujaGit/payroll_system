<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\LeaveApplication;
use App\Models\HelpdeskTicket;
use App\Models\PayrollRun;
use Illuminate\Http\Request;

class WebDashboardController extends Controller
{
    public function index(Request $request)
    {
        $entityId = $this->resolveEntityId($request);

        $today = now()->toDateString();

        $totalActive   = Employee::where('entity_id', $entityId)->where('employee_status', 'active')->count();
        $newJoiners    = Employee::where('entity_id', $entityId)
                            ->whereYear('date_of_joining', now()->year)
                            ->whereMonth('date_of_joining', now()->month)->count();
        $pendingLeaves = LeaveApplication::whereHas('employee', fn($q) => $q->where('entity_id', $entityId))
                            ->where('status', 'pending')->count();
        $openTickets   = HelpdeskTicket::where('entity_id', $entityId)
                            ->whereNotIn('status', ['closed', 'resolved'])->count();

        $todayAttendance = Attendance::whereHas('employee', fn($q) => $q->where('entity_id', $entityId))
                            ->where('attendance_date', $today)
                            ->selectRaw('status, COUNT(*) as count')
                            ->groupBy('status')->pluck('count', 'status');

        $deptStrength = Employee::where('entity_id', $entityId)
                            ->where('employee_status', 'active')
                            ->selectRaw('department_id, COUNT(*) as count')
                            ->with('department:id,name')
                            ->groupBy('department_id')
                            ->orderByDesc('count')->get();

        $recentEmployees = Employee::with(['department', 'designation'])
                            ->where('entity_id', $entityId)
                            ->orderByDesc('created_at')->limit(6)->get();

        $lastRun = PayrollRun::where('entity_id', $entityId)
                    ->whereIn('status', ['finalized', 'paid'])->orderByDesc('id')->first();

        return view('dashboard.index', compact(
            'totalActive', 'newJoiners', 'pendingLeaves', 'openTickets',
            'todayAttendance', 'deptStrength', 'recentEmployees', 'lastRun'
        ));
    }
}
