<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRegularization;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\Attendance;
use App\Models\LeaveTrackerRecord;
use App\Models\HelpdeskTicket;
use App\Models\PayrollRun;
use App\Models\ReimbursementTrackerRow;
use App\Services\LeaveEntitlementService;
use Illuminate\Http\Request;

class WebDashboardController extends Controller
{
    public function __construct(protected LeaveEntitlementService $leaveEntitlementService) {}

    public function index(Request $request)
    {
        // Employee-role holders (including managers — Manager isn't a
        // separate role here) get their own personal dashboard: their leave
        // balance, their attendance, their payslip, their team's approvals
        // if they have direct reports. Company-wide figures are an HR/Admin
        // concern only.
        if ($request->user()?->isRestrictedToLeaveOnly()) {
            return $this->myDashboard($request);
        }

        // A user linked to their own employee record always sees just their
        // company. Someone without one (System Admin) has no natural
        // company to default to — rather than silently picking an
        // arbitrary "first company with employees" (the old bug), default
        // them to 0 = "All Firms" combined, same convention as the Salary
        // Report page, with an explicit selector to narrow to one.
        $userCompanyId = auth()->user()?->employee?->company_id;
        $companyId = $userCompanyId
            ? (int) $userCompanyId
            : ($request->has('company_id') ? (int) $request->get('company_id') : 0);

        $companies = $userCompanyId ? collect() : Company::orderBy('name')->get(['id', 'name', 'short_name']);

        $month = $request->get('month') ?: now()->format('Y-m');
        [$selYear, $selMonth] = array_map('intval', explode('-', $month));

        $today = now()->toDateString();

        $totalActive   = Employee::when($companyId, fn($q) => $q->where('company_id', $companyId))
                            ->where('employee_status', 'active')->count();
        $newJoiners    = Employee::when($companyId, fn($q) => $q->where('company_id', $companyId))
                            ->whereYear('date_of_joining', $selYear)
                            ->whereMonth('date_of_joining', $selMonth)->count();
        $pendingLeaves = LeaveTrackerRecord::whereHas('employee', fn($q) => $q->when($companyId, fn($q2) => $q2->where('company_id', $companyId)))
                            ->where('is_active', true)->where('is_approved', false)->count();
        $openTickets   = HelpdeskTicket::when($companyId, fn($q) => $q->where('company_id', $companyId))
                            ->whereNotIn('status', ['closed', 'resolved'])->count();

        $todayAttendance = Attendance::whereHas('employee', fn($q) => $q->when($companyId, fn($q2) => $q2->where('company_id', $companyId)))
                            ->where('attendance_date', $today)
                            ->selectRaw('status, COUNT(*) as count')
                            ->groupBy('status')->pluck('count', 'status');

        $recentEmployees = Employee::with(['department', 'designation'])
                            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
                            ->orderByDesc('created_at')->limit(6)->get();

        // Payroll for the selected month specifically (not just "whatever
        // finalized run happened to be most recent") — summed across
        // companies when viewing "All Firms" so it's one coherent number.
        $monthRuns = PayrollRun::whereHas('period', fn($q) => $q->where('year', $selYear)->where('month', $selMonth))
                            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
                            ->whereIn('status', ['finalized', 'paid'])
                            ->get();
        $monthPayroll = $monthRuns->isNotEmpty() ? [
            'total_net'       => $monthRuns->sum('total_net'),
            'total_employees' => $monthRuns->sum('total_employees'),
            'status'          => $monthRuns->pluck('status')->unique()->count() === 1 ? $monthRuns->first()->status : 'mixed',
        ] : null;

        $pendingLoans = EmployeeLoan::whereHas('employee', fn($q) => $q->when($companyId, fn($q2) => $q2->where('company_id', $companyId)))
                            ->where('status', 'pending')->count();

        $pendingReimbursements = ReimbursementTrackerRow::when($companyId, fn($q) => $q->where('company_id', $companyId))
                            ->where('status', 'pending')->count();

        // Last 6 months of payroll cost, summed across companies when
        // viewing "All Firms" so the trend still means one number per month.
        $payrollTrend = PayrollRun::with('period:id,year,month')
                            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
                            ->whereIn('status', ['finalized', 'paid'])
                            ->get()
                            ->filter(fn($run) => $run->period)
                            ->groupBy(fn($run) => sprintf('%04d-%02d', $run->period->year, $run->period->month))
                            ->map(fn($runs, $key) => [
                                'label'     => \Carbon\Carbon::createFromFormat('Y-m', $key)->format('M Y'),
                                'total_net' => $runs->sum('total_net'),
                            ])
                            ->sortKeys()
                            ->slice(-6)
                            ->values();

        return view('dashboard.index', compact(
            'totalActive', 'newJoiners', 'pendingLeaves', 'openTickets',
            'todayAttendance', 'recentEmployees', 'monthPayroll',
            'pendingLoans', 'pendingReimbursements',
            'payrollTrend', 'companyId', 'companies', 'month'
        ));
    }

    protected function myDashboard(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 404, 'No employee profile linked to your account.');

        // Real, live CL/SL/Comp-Off standing — same computation the Leave
        // Management page itself uses (see LeaveEntitlementService). The
        // leave_balances table this used to read from is unused for CL/SL
        // and always showed 0/duplicated rows.
        $leaveBalance = collect($this->leaveEntitlementService->currentCLSL($employee))
                            ->merge(['Compensation Off' => $this->leaveEntitlementService->currentCompOff($employee)])
                            ->map(fn($available, $name) => (object) ['leaveType' => (object) ['name' => $name], 'available' => $available])
                            ->values();

        $monthAttendance = Attendance::where('employee_id', $employee->id)
                            ->whereYear('attendance_date', now()->year)
                            ->whereMonth('attendance_date', now()->month)
                            ->selectRaw('status, COUNT(*) as count')
                            ->groupBy('status')->pluck('count', 'status');

        $pendingReimbursements = ReimbursementTrackerRow::where('employee_id', $employee->id)
                            ->where('status', 'pending')->count();

        $activeLoan = EmployeeLoan::where('employee_id', $employee->id)
                            ->whereIn('status', ['approved', 'active'])
                            ->orderByDesc('id')->first();

        $isManager = $employee->subordinates()->exists();
        $teamPendingLeaves = 0;
        $teamPendingAttendance = 0;
        if ($isManager) {
            $subordinateIds = $employee->subordinates()->pluck('id');
            $teamPendingLeaves = LeaveTrackerRecord::whereIn('employee_id', $subordinateIds)
                                    ->where('is_active', true)->where('is_approved', false)->count();
            $teamPendingAttendance = AttendanceRegularization::whereIn('employee_id', $subordinateIds)
                                    ->where('status', 'pending')->count();
        }

        return view('dashboard.my', compact(
            'employee', 'leaveBalance', 'monthAttendance',
            'pendingReimbursements', 'activeLoan',
            'isManager', 'teamPendingLeaves', 'teamPendingAttendance'
        ));
    }
}
