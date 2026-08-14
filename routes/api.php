<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\HR\EmployeeController;
use App\Http\Controllers\Attendance\AttendanceController;
use App\Http\Controllers\Leave\LeaveController;
use App\Http\Controllers\Payroll\PayrollController;
use App\Http\Controllers\Payroll\SalaryRevisionController;
use App\Http\Controllers\Finance\LoanController;
use App\Http\Controllers\Statutory\StatutoryController;
use App\Http\Controllers\Statutory\InvestmentDeclarationController;
use App\Http\Controllers\Statutory\PoiController;
use App\Http\Controllers\Reimbursement\ReimbursementController;
use App\Http\Controllers\Contractor\ContractorController;
use App\Http\Controllers\Helpdesk\HelpdeskController;
use App\Http\Controllers\Reports\ManagerDashboardController;
use App\Http\Controllers\Reports\HrAnalyticsDashboardController;

// ╔══════════════════════════════════════════════════════════════╗
// ║  PUBLIC ROUTES  (no auth required)                         ║
// ╚══════════════════════════════════════════════════════════════╝

Route::post('/auth/login',  [AuthController::class, 'login']);
Route::post('/auth/forgot-password', fn() => response()->json(['message' => 'Password reset link sent.']));

// ╔══════════════════════════════════════════════════════════════╗
// ║  AUTHENTICATED ROUTES                                      ║
// ╚══════════════════════════════════════════════════════════════╝

Route::middleware('auth:sanctum')->group(function () {

    // ─── AUTH ────────────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('logout',          [AuthController::class, 'logout']);
        Route::get('me',               [AuthController::class, 'profile']);
        Route::put('profile',          [AuthController::class, 'updateProfile']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
        Route::get('permissions',      [AuthController::class, 'permissions']);
    });

    // ─── EMPLOYEES ───────────────────────────────────────────────
    Route::prefix('employees')->group(function () {
        Route::get('/',                  [EmployeeController::class, 'index']);
        Route::post('/',                 [EmployeeController::class, 'store']);
        Route::get('/export',            [EmployeeController::class, 'export']);
        Route::get('/template',          [EmployeeController::class, 'uploadTemplate']);
        Route::post('/import',           [EmployeeController::class, 'import']);

        Route::get('/{employee}',        [EmployeeController::class, 'show']);
        Route::put('/{employee}',        [EmployeeController::class, 'update']);
        Route::post('/{employee}/confirm',   [EmployeeController::class, 'confirm']);
        Route::post('/{employee}/transfer',  [EmployeeController::class, 'transfer']);
        Route::get('/{employee}/history',    [EmployeeController::class, 'history']);
        Route::post('/{employee}/documents', [EmployeeController::class, 'uploadDocument']);

        // Salary history (nested under employee)
        Route::get('/{employee}/salary-history', [SalaryRevisionController::class, 'employeeSalaryHistory']);
    });

    // ─── ATTENDANCE ──────────────────────────────────────────────
    Route::prefix('attendance')->group(function () {
        Route::get('/today',                [AttendanceController::class, 'today']);
        Route::get('/monthly',              [AttendanceController::class, 'monthly']);
        Route::get('/employee/{id}/monthly',[AttendanceController::class, 'employeeMonthly']);
        Route::post('/mark',                [AttendanceController::class, 'mark']);
        Route::post('/bulk-mark',           [AttendanceController::class, 'bulkMark']);
        Route::get('/export',               [AttendanceController::class, 'export']);
        Route::get('/template',             [AttendanceController::class, 'uploadTemplate']);
        Route::post('/import',              [AttendanceController::class, 'import']);

        // Regularization
        Route::get('/regularizations',              [AttendanceController::class, 'pendingRegularizations']);
        Route::post('/regularizations',             [AttendanceController::class, 'storeRegularization']);
        Route::post('/regularizations/{reg}/action',[AttendanceController::class, 'approveRegularization']);

        // Overtime
        Route::get('/overtime',                 [AttendanceController::class, 'overtimeRequests']);
        Route::post('/overtime/{ot}/action',    [AttendanceController::class, 'approveOvertime']);
    });

    // ─── LEAVE ───────────────────────────────────────────────────
    Route::prefix('leave')->group(function () {
        // Leave types
        Route::get('/types',         [LeaveController::class, 'leaveTypes']);
        Route::post('/types',        [LeaveController::class, 'storeLeaveType']);

        // Balances
        Route::get('/balances/me',   [LeaveController::class, 'myBalances']);
        Route::get('/balances',      [LeaveController::class, 'teamBalances']);
        Route::post('/balances/adjust', [LeaveController::class, 'adjustBalance']);

        // Applications
        Route::get('/my-applications',   [LeaveController::class, 'myApplications']);
        Route::post('/apply',            [LeaveController::class, 'apply']);
        Route::post('/{application}/approve', [LeaveController::class, 'approve']);
        Route::post('/{application}/cancel',  [LeaveController::class, 'cancel']);

        // Team / HR view
        Route::get('/applications',          [LeaveController::class, 'teamApplications']);
        Route::get('/pending-my-approvals',  [LeaveController::class, 'pendingMyApprovals']);
        Route::get('/calendar',              [LeaveController::class, 'leaveCalendar']);

        // Comp-off
        Route::post('/comp-off',             [LeaveController::class, 'applyCompOff']);
        Route::post('/comp-off/{co}/action', [LeaveController::class, 'approveCompOff']);

        // Year-end
        Route::post('/carry-forward',        [LeaveController::class, 'runCarryForward']);
    });

    // ─── PAYROLL ─────────────────────────────────────────────────
    Route::prefix('payroll')->group(function () {
        // Periods
        Route::get('/periods',           [PayrollController::class, 'periods']);
        Route::post('/periods',          [PayrollController::class, 'createPeriod']);
        Route::post('/periods/{period}/lock',   [PayrollController::class, 'lockPeriod']);
        Route::post('/periods/{period}/unlock', [PayrollController::class, 'unlockPeriod']);

        // Runs
        Route::post('/runs',             [PayrollController::class, 'initRun']);
        Route::post('/runs/{run}/calculate', [PayrollController::class, 'calculate']);
        Route::post('/runs/{run}/calculate-single', [PayrollController::class, 'calculateSingleEmployee']);
        Route::post('/runs/{run}/finalize', [PayrollController::class, 'finalize']);
        Route::get('/runs/{run}/salary-sheet', [PayrollController::class, 'salarySheet']);
        Route::get('/runs/{run}/reconciliation', [PayrollController::class, 'reconciliation']);
        Route::get('/runs/{run}/register/export', [PayrollController::class, 'payrollRegister']);

        // Payslips
        Route::post('/runs/{run}/payslips', [PayrollController::class, 'generatePayslips']);
        Route::get('/records/{record}/payslip', [PayrollController::class, 'downloadPayslip']);

        // Bank file
        Route::post('/runs/{run}/bank-file', [PayrollController::class, 'generateBankFile']);
        Route::get('/bank-files/{bankFile}/download', [PayrollController::class, 'downloadBankFile']);

        // Admin overrides
        Route::post('/records/{record}/override-net',  [PayrollController::class, 'overrideNetSalary']);
        Route::post('/components/{component}/override', [PayrollController::class, 'overrideComponent']);
        Route::post('/records/{record}/block',   [PayrollController::class, 'blockEmployee']);
        Route::post('/records/{record}/unblock', [PayrollController::class, 'unblockEmployee']);

        // Salary structure master
        Route::apiResource('/salary-components', \App\Http\Controllers\Payroll\SalaryComponentController::class);
        Route::apiResource('/salary-structures',  \App\Http\Controllers\Payroll\SalaryStructureController::class);
    });

    // ─── SALARY REVISIONS ────────────────────────────────────────
    Route::prefix('salary-revisions')->group(function () {
        Route::get('/',                    [SalaryRevisionController::class, 'index']);
        Route::post('/',                   [SalaryRevisionController::class, 'store']);
        Route::post('/{revision}/approve', [SalaryRevisionController::class, 'approve']);
        Route::post('/{revision}/reject',  [SalaryRevisionController::class, 'reject']);
        Route::get('/template',            [SalaryRevisionController::class, 'uploadTemplate']);
        Route::post('/import',             [SalaryRevisionController::class, 'import']);
    });

    // ─── LOANS & ADVANCES ────────────────────────────────────────
    Route::prefix('loans')->group(function () {
        Route::get('/types',           [LoanController::class, 'loanTypes']);
        Route::post('/types',          [LoanController::class, 'storeLoanType']);
        Route::get('/',                [LoanController::class, 'index']);
        Route::post('/',               [LoanController::class, 'store']);
        Route::get('/{loan}',          [LoanController::class, 'show']);
        Route::post('/{loan}/approve', [LoanController::class, 'approve']);
        Route::post('/{loan}/reject',  [LoanController::class, 'reject']);
        Route::post('/{loan}/foreclose', [LoanController::class, 'foreclose']);
        Route::get('/{loan}/emi-schedule', [LoanController::class, 'emiSchedule']);
        Route::post('/installments/{installment}/waive', [LoanController::class, 'waiveInstallment']);
        Route::get('/template', [LoanController::class, 'uploadTemplate']);
        Route::post('/import',  [LoanController::class, 'import']);
    });

    // ─── REIMBURSEMENTS ──────────────────────────────────────────
    Route::prefix('reimbursements')->group(function () {
        Route::get('/types',       [ReimbursementController::class, 'types']);
        Route::post('/types',      [ReimbursementController::class, 'storeType']);
        Route::get('/mine',        [ReimbursementController::class, 'myClaims']);
        Route::get('/all',         [ReimbursementController::class, 'allClaims']);
        Route::get('/report',      [ReimbursementController::class, 'report']);
        Route::post('/',           [ReimbursementController::class, 'store']);
        Route::post('/{claim}/items',   [ReimbursementController::class, 'addItem']);
        Route::post('/{claim}/approve', [ReimbursementController::class, 'approveClaim']);
        Route::post('/{claim}/paid',    [ReimbursementController::class, 'markPaid']);
    });

    // ─── STATUTORY ───────────────────────────────────────────────
    Route::prefix('statutory')->group(function () {
        Route::get('/pf-settings',         [StatutoryController::class, 'pfSettings']);
        Route::post('/pf-settings',        [StatutoryController::class, 'savePfSettings']);
        Route::get('/esic-settings',       [StatutoryController::class, 'esicSettings']);
        Route::post('/esic-settings',      [StatutoryController::class, 'saveEsicSettings']);
        Route::get('/pt-slabs',            [StatutoryController::class, 'ptSlabs']);
        Route::post('/pt-slabs',           [StatutoryController::class, 'savePtSlabs']);
        Route::get('/tds-slabs',           [StatutoryController::class, 'tdsSlabs']);
        Route::get('/challans',            [StatutoryController::class, 'challans']);
        Route::post('/challans',           [StatutoryController::class, 'storeChallan']);
        Route::post('/challans/{challan}/paid', [StatutoryController::class, 'markChallanPaid']);
        Route::get('/compliance-dashboard', [StatutoryController::class, 'complianceDashboard']);

        // ECR
        Route::post('/ecr/{run}/generate', [StatutoryController::class, 'generateEcr']);
        Route::get('/ecr/{ecr}/download',  [StatutoryController::class, 'downloadEcr']);
    });

    // ─── INVESTMENT DECLARATION (Form 12BB) ──────────────────────
    Route::prefix('investment-declarations')->group(function () {
        Route::get('/mine',          [InvestmentDeclarationController::class, 'myDeclaration']);
        Route::post('/save',         [InvestmentDeclarationController::class, 'saveDeclaration']);
        Route::post('/submit',       [InvestmentDeclarationController::class, 'submitDeclaration']);
        Route::get('/section-limits',[InvestmentDeclarationController::class, 'sectionLimits']);
        Route::get('/all',           [InvestmentDeclarationController::class, 'allDeclarations']);
        Route::post('/{declaration}/approve', [InvestmentDeclarationController::class, 'approveDeclaration']);
        Route::post('/{declaration}/reject',  [InvestmentDeclarationController::class, 'rejectDeclaration']);
    });

    // ─── POI WORKFLOW ────────────────────────────────────────────
    Route::prefix('poi')->group(function () {
        Route::post('/upload',     [PoiController::class, 'uploadProof']);
        Route::get('/mine',        [PoiController::class, 'mySubmissions']);
        Route::get('/pending',     [PoiController::class, 'pendingReview']);
        Route::get('/summary',     [PoiController::class, 'summary']);
        Route::post('/{poi}/review', [PoiController::class, 'review']);
        Route::post('/bulk-review',  [PoiController::class, 'bulkReview']);
        Route::get('/{poi}/download', [PoiController::class, 'downloadProof']);
    });

    // ─── CONTRACTORS ─────────────────────────────────────────────
    Route::prefix('contractors')->group(function () {
        Route::get('/',              [ContractorController::class, 'index']);
        Route::post('/',             [ContractorController::class, 'store']);
        Route::get('/template',      [ContractorController::class, 'uploadTemplate']);
        Route::post('/import',       [ContractorController::class, 'import']);
        Route::get('/{contractor}',  [ContractorController::class, 'show']);
        Route::put('/{contractor}',  [ContractorController::class, 'update']);

        Route::get('/{contractor}/invoices',         [ContractorController::class, 'invoices']);
        Route::post('/{contractor}/invoices',        [ContractorController::class, 'storeInvoice']);
        Route::post('/invoices/{invoice}/approve',   [ContractorController::class, 'approveInvoice']);
        Route::post('/invoices/{invoice}/paid',      [ContractorController::class, 'markPaid']);
        Route::get('/tds-report',                    [ContractorController::class, 'tdsReport']);
    });

    // ─── HELPDESK ────────────────────────────────────────────────
    Route::prefix('helpdesk')->group(function () {
        Route::get('/categories',     [HelpdeskController::class, 'categories']);
        Route::post('/categories',    [HelpdeskController::class, 'storeCategory']);

        Route::get('/tickets/mine',   [HelpdeskController::class, 'myTickets']);
        Route::get('/tickets',        [HelpdeskController::class, 'agentTickets']);
        Route::post('/tickets',       [HelpdeskController::class, 'store']);
        Route::get('/tickets/{ticket}',  [HelpdeskController::class, 'show']);

        Route::post('/tickets/{ticket}/comments', [HelpdeskController::class, 'addComment']);
        Route::post('/tickets/{ticket}/assign',   [HelpdeskController::class, 'assign']);
        Route::post('/tickets/{ticket}/resolve',  [HelpdeskController::class, 'resolve']);
        Route::post('/tickets/{ticket}/close',    [HelpdeskController::class, 'close']);
        Route::post('/tickets/{ticket}/reopen',   [HelpdeskController::class, 'reopen']);

        Route::get('/analytics',      [HelpdeskController::class, 'analytics']);
    });

    // ─── DASHBOARDS ──────────────────────────────────────────────
    Route::prefix('dashboards')->group(function () {
        Route::get('/manager',              [ManagerDashboardController::class, 'index']);
        Route::get('/manager/attendance-trends', [ManagerDashboardController::class, 'attendanceTrends']);

        Route::get('/hr-analytics',         [HrAnalyticsDashboardController::class, 'index']);
        Route::get('/hr-analytics/payroll-trend',  [HrAnalyticsDashboardController::class, 'payrollTrend']);
        Route::get('/hr-analytics/attrition-trend',[HrAnalyticsDashboardController::class, 'attritionTrend']);
        Route::get('/hr-analytics/leave-utilisation',[HrAnalyticsDashboardController::class, 'leaveUtilisation']);
    });

    // ─── MASTER DATA ROUTES ──────────────────────────────────────
    Route::prefix('master')->group(function () {
        Route::apiResource('companies',    \App\Http\Controllers\HR\CompanyController::class);
        Route::apiResource('entities',     \App\Http\Controllers\HR\EntityController::class);
        Route::apiResource('branches',     \App\Http\Controllers\HR\BranchController::class);
        Route::apiResource('departments',  \App\Http\Controllers\HR\DepartmentController::class);
        Route::apiResource('designations', \App\Http\Controllers\HR\DesignationController::class);
        Route::apiResource('grades',       \App\Http\Controllers\HR\GradeController::class);
        Route::apiResource('cost-centers', \App\Http\Controllers\HR\CostCenterController::class);
        Route::apiResource('shifts',       \App\Http\Controllers\Attendance\ShiftController::class);
        Route::apiResource('holiday-calendars', \App\Http\Controllers\HR\HolidayCalendarController::class);
        Route::apiResource('holiday-calendars.holidays', \App\Http\Controllers\HR\HolidayController::class);
        Route::apiResource('bonus-rules',  \App\Http\Controllers\Finance\BonusController::class);
        Route::apiResource('employee-bonus', \App\Http\Controllers\Finance\EmployeeBonusController::class);
    });

    // ─── REPORTS ─────────────────────────────────────────────────
    Route::prefix('reports')->group(function () {
        Route::get('/payroll-register',         [PayrollController::class, 'payrollRegister']);
        Route::get('/payroll-reconciliation',   [PayrollController::class, 'reconciliation']);
        Route::get('/attendance-report',        [AttendanceController::class, 'export']);
        Route::get('/leave-report',             [LeaveController::class, 'teamApplications']);
        Route::get('/loan-outstanding',         [LoanController::class, 'index']);
        Route::get('/reimbursement-report',     [ReimbursementController::class, 'report']);
        Route::get('/compliance-dashboard',     [StatutoryController::class, 'complianceDashboard']);
        Route::get('/tds-computation',          [StatutoryController::class, 'complianceDashboard']); // TDS
        Route::get('/contractor-tds',           [ContractorController::class, 'tdsReport']);
    });

    // ─── AUDIT LOGS ──────────────────────────────────────────────
    Route::get('/audit-logs', function (\Illuminate\Http\Request $request) {
        $logs = \App\Models\AuditLog::with('user:id,name')
            ->when($request->module, fn($q) => $q->where('module', $request->module))
            ->when($request->user_id, fn($q) => $q->where('user_id', $request->user_id))
            ->orderByDesc('created_at')
            ->paginate(50);
        return response()->json($logs);
    })->middleware('permission:audit_view');

    // ─── NOTIFICATIONS ───────────────────────────────────────────
    Route::get('/notifications', function (\Illuminate\Http\Request $request) {
        return response()->json(auth()->user()->notifications()->paginate(20));
    });
    Route::post('/notifications/{id}/read', function (string $id) {
        auth()->user()->notifications()->where('id', $id)->update(['read_at' => now()]);
        return response()->json(['message' => 'Marked as read.']);
    });
    Route::post('/notifications/read-all', function () {
        auth()->user()->unreadNotifications->markAsRead();
        return response()->json(['message' => 'All notifications marked as read.']);
    });

    // ─── SYSTEM SETTINGS ─────────────────────────────────────────
    Route::prefix('settings')->group(function () {
        Route::get('/', function (\Illuminate\Http\Request $req) {
            return response()->json(\App\Models\SystemSetting::where('entity_id', $req->entity_id)->get()->pluck('value', 'key'));
        });
        Route::post('/', function (\Illuminate\Http\Request $req) {
            foreach ($req->settings as $key => $value) {
                \App\Models\SystemSetting::setValue($key, $value, $req->entity_id);
            }
            return response()->json(['message' => 'Settings saved.']);
        });
    });

    // ─── RBAC MANAGEMENT (Super Admin) ───────────────────────────
    Route::prefix('rbac')->middleware('permission:rbac_manage')->group(function () {
        Route::apiResource('roles', \App\Http\Controllers\Auth\RoleController::class);
        Route::get('/permissions',  fn() => response()->json(\App\Models\Permission::all()->groupBy('module')));
        Route::post('/roles/{role}/permissions', [\App\Http\Controllers\Auth\RoleController::class, 'syncPermissions']);
        Route::get('/roles/{role}/users',         [\App\Http\Controllers\Auth\RoleController::class, 'users']);
        Route::post('/users/{user}/roles',        [\App\Http\Controllers\Auth\RoleController::class, 'assignToUser']);
        Route::delete('/users/{user}/roles/{role}', [\App\Http\Controllers\Auth\RoleController::class, 'removeFromUser']);
    });

}); // end auth:sanctum
