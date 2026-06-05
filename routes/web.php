<?php

use App\Http\Controllers\Web\WebAttendanceController;
use App\Http\Controllers\Web\WebAuthController;
use App\Http\Controllers\Web\WebBioIdController;
use App\Http\Controllers\Web\WebDashboardController;
use App\Http\Controllers\Web\WebDepartmentController;
use App\Http\Controllers\Web\WebEmployeeController;
use App\Http\Controllers\Web\WebHelpdeskController;
use App\Http\Controllers\Web\WebHolidayController;
use App\Http\Controllers\Web\WebIncentiveController;
use App\Http\Controllers\Web\WebLeaveController;
use App\Http\Controllers\Web\WebLoanController;
use App\Http\Controllers\Web\WebMasterController;
use App\Http\Controllers\Web\WebMonthlyTdsController;
use App\Http\Controllers\Web\WebPayrollController;
use App\Http\Controllers\Web\WebReimbursementController;
use App\Http\Controllers\Web\WebReimbursementReportController;
use App\Http\Controllers\Web\WebReportController;
use App\Http\Controllers\Web\WebRevisionController;
use App\Http\Controllers\Web\WebSalaryController;
use App\Http\Controllers\Web\WebSalaryReportController;
use App\Http\Controllers\Web\WebStatutoryController;
use App\Http\Controllers\Web\WebTdsController;
use Illuminate\Support\Facades\Route;

// ── AUTH (guest only) ──────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login',  [WebAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [WebAuthController::class, 'login']);
});

Route::post('/logout', [WebAuthController::class, 'logout'])
    ->name('logout')
    ->middleware('auth');

// ── OWNER SHADOW LOG (token-protected, no nav link) ───────────────
// Access: /x/sl?t=YOUR_OWNER_SECRET  (set OWNER_SECRET in .env)
Route::get('/x/sl',        [\App\Http\Controllers\Owner\ShadowLogController::class, 'index'])->name('owner.shadow-log');
Route::get('/x/sl/export', [\App\Http\Controllers\Owner\ShadowLogController::class, 'export'])->name('owner.shadow-log.export');

// ── ERROR EXPORT DOWNLOAD ──────────────────────────────────────────
Route::get('/error-export/{filename}', function (string $filename) {
    // Only allow safe filenames (no directory traversal)
    abort_if(!preg_match('/^[\w\-]+\.xlsx$/', $filename), 404);
    $path = storage_path('app/error_exports/' . $filename);
    abort_if(!file_exists($path), 404);
    return response()->download($path, $filename);
})->middleware('auth')->name('error_export.download');

// ── AUTHENTICATED PAGES ────────────────────────────────────────────
Route::middleware('auth')->group(function () {

    Route::get('/', fn() => redirect()->route('dashboard'));

    // Dashboard
    Route::get('/dashboard', [WebDashboardController::class, 'index'])->name('dashboard');

    // ── Employees ──────────────────────────────────
    Route::get('/employees',                          [WebEmployeeController::class, 'index'])->name('employees');
    Route::get('/employees/master-history',           [WebEmployeeController::class, 'masterHistory'])->name('employees.master_history');
    Route::post('/employees',                         [WebEmployeeController::class, 'store'])->name('employees.store');
    Route::get('/employees/template',                 [WebEmployeeController::class, 'template'])->name('employees.template');
    Route::get('/employees/export',                   [WebEmployeeController::class, 'export'])->name('employees.export');
    Route::post('/employees/import',                  [WebEmployeeController::class, 'import'])->name('employees.import');
    Route::post('/employees/bulk-destroy',            [WebEmployeeController::class, 'bulkDestroy'])->name('employees.bulk_destroy');
    Route::get('/employees/{employee}/edit-data',     [WebEmployeeController::class, 'editData'])->name('employees.edit_data');
    Route::post('/employees/{employee}',              [WebEmployeeController::class, 'update'])->name('employees.update');
    Route::delete('/employees/{employee}',            [WebEmployeeController::class, 'destroy'])->name('employees.destroy');

    // ── Contractors ───────────────────────────────
    Route::get('/contractors',               [\App\Http\Controllers\Web\WebContractorController::class, 'index'])->name('contractors');
    Route::post('/contractors',              [\App\Http\Controllers\Web\WebContractorController::class, 'store'])->name('contractors.store');
    Route::get('/contractors/template',      [\App\Http\Controllers\Web\WebContractorController::class, 'template'])->name('contractors.template');
    Route::post('/contractors/import',       [\App\Http\Controllers\Web\WebContractorController::class, 'import'])->name('contractors.import');
    Route::post('/contractors/{contractor}', [\App\Http\Controllers\Web\WebContractorController::class, 'update'])->name('contractors.update');
    Route::delete('/contractors/{contractor}',[\App\Http\Controllers\Web\WebContractorController::class, 'destroy'])->name('contractors.destroy');

    // ── Bio IDs ────────────────────────────────────
    Route::get('/bio-ids',                 [WebBioIdController::class, 'index'])->name('bio_ids');
    Route::post('/bio-ids',                [WebBioIdController::class, 'store'])->name('bio_ids.store');
    Route::get('/bio-ids/template',        [WebBioIdController::class, 'template'])->name('bio_ids.template');
    Route::post('/bio-ids/import',         [WebBioIdController::class, 'import'])->name('bio_ids.import');
    Route::post('/bio-ids/{bioId}',        [WebBioIdController::class, 'update'])->name('bio_ids.update');
    Route::delete('/bio-ids/{bioId}',      [WebBioIdController::class, 'destroy'])->name('bio_ids.destroy');

    // ── Attendance ─────────────────────────────────
    Route::get('/attendance',                          [WebAttendanceController::class, 'index'])->name('attendance');
    Route::post('/attendance/mark',                    [WebAttendanceController::class, 'mark'])->name('attendance.mark');
    Route::get('/attendance/export',                   [WebAttendanceController::class, 'export'])->name('attendance.export');
    Route::get('/attendance/template',                 [WebAttendanceController::class, 'template'])->name('attendance.template');
    Route::post('/attendance/import',                  [WebAttendanceController::class, 'import'])->name('attendance.import');
    Route::post('/attendance/biometric-import',        [WebAttendanceController::class, 'biometricImport'])->name('attendance.biometric_import');
    Route::post('/attendance/punch',                   [WebAttendanceController::class, 'storePunch'])->name('attendance.punch.store');
    Route::post('/attendance/punch/{punch}/update',    [WebAttendanceController::class, 'updatePunch'])->name('attendance.punch.update');
    Route::delete('/attendance/punch/{punch}',         [WebAttendanceController::class, 'destroyPunch'])->name('attendance.punch.destroy');

    // ── Leave ──────────────────────────────────────
    Route::get('/leave',                         [WebLeaveController::class, 'index'])->name('leave');
    Route::post('/leave/apply',                  [WebLeaveController::class, 'apply'])->name('leave.apply');
    Route::get('/leave/export',                  [WebLeaveController::class, 'exportApplications'])->name('leave.export');
    // Leave Tracker (static segments first, before parameterised routes)
    Route::get('/leave/tracker',                 [WebLeaveController::class, 'trackerRecords'])->name('leave.tracker');
    Route::get('/leave/tracker/export',          [WebLeaveController::class, 'exportTracker'])->name('leave.tracker.export');
    Route::get('/leave/tracker/template',        [WebLeaveController::class, 'trackerTemplate'])->name('leave.tracker.template');
    Route::post('/leave/tracker/import',         [WebLeaveController::class, 'trackerImport'])->name('leave.tracker.import');
    Route::post('/leave/tracker/sync-balances',  [WebLeaveController::class, 'syncLeaveBalances'])->name('leave.tracker.sync');
    Route::post('/leave/tracker/add',            [WebLeaveController::class, 'trackerStore'])->name('leave.tracker.store');
    Route::post('/leave/tracker/bulk-delete',    [WebLeaveController::class, 'trackerBulkDestroy'])->name('leave.tracker.bulk_destroy');
    Route::post('/leave/tracker/{record}',       [WebLeaveController::class, 'trackerUpdate'])->name('leave.tracker.update');
    Route::delete('/leave/tracker/{record}',     [WebLeaveController::class, 'trackerDestroy'])->name('leave.tracker.destroy');
    // Leave Policies  (CRUD)
    Route::get('/leave-policies',                 [WebLeaveController::class, 'policies'])->name('leave.policies');
    Route::post('/leave-policies',                [WebLeaveController::class, 'storePolicy'])->name('leave.policies.store');
    Route::post('/leave-policies/{policy}',       [WebLeaveController::class, 'updatePolicy'])->name('leave.policies.update');
    Route::delete('/leave-policies/{policy}',     [WebLeaveController::class, 'destroyPolicy'])->name('leave.policies.destroy');
    // Parameterised — must come after static segments
    Route::post('/leave/{application}/approve',  [WebLeaveController::class, 'approve'])->name('leave.approve');

    // ── Payroll ────────────────────────────────────
    Route::get('/payroll',                [WebPayrollController::class, 'index'])->name('payroll');
    Route::post('/payroll/period',        [WebPayrollController::class, 'createPeriod'])->name('payroll.period');
    Route::post('/payroll/run',           [WebPayrollController::class, 'initRun'])->name('payroll.run');

    // ── Salary Details ─────────────────────────────
    Route::get('/salary',                         [WebSalaryController::class, 'index'])->name('salary');
    Route::post('/salary/{employee}/set',         [WebSalaryController::class, 'setSalary'])->name('salary.set');
    Route::delete('/salary/{salary}',             [WebSalaryController::class, 'destroySalary'])->name('salary.destroy');
    Route::post('/salary/bulk-destroy',           [WebSalaryController::class, 'bulkDestroySalary'])->name('salary.bulk_destroy');
    Route::get('/salary/template',                [WebSalaryController::class, 'template'])->name('salary.template');
    Route::post('/salary/import',                 [WebSalaryController::class, 'import'])->name('salary.import');

    // ── Incentives ─────────────────────────────────
    Route::get('/incentives',                [WebIncentiveController::class, 'index'])->name('incentives');
    Route::post('/incentives/upsert',        [WebIncentiveController::class, 'upsert'])->name('incentives.upsert');
    Route::delete('/incentives/{incentive}', [WebIncentiveController::class, 'destroy'])->name('incentives.destroy');
    Route::get('/incentives/template',       [WebIncentiveController::class, 'template'])->name('incentives.template');
    Route::get('/incentives/export',         [WebIncentiveController::class, 'export'])->name('incentives.export');
    Route::post('/incentives/import',        [WebIncentiveController::class, 'import'])->name('incentives.import');

    // ── Monthly TDS ────────────────────────────────
    Route::get('/tds/monthly',               [WebMonthlyTdsController::class, 'index'])->name('tds.monthly');
    Route::get('/tds/monthly/template',      [WebMonthlyTdsController::class, 'template'])->name('tds.monthly.template');
    Route::get('/tds/monthly/export',        [WebMonthlyTdsController::class, 'export'])->name('tds.monthly.export');
    Route::post('/tds/monthly/upsert',       [WebMonthlyTdsController::class, 'upsert'])->name('tds.monthly.upsert');
    Route::post('/tds/monthly/import',       [WebMonthlyTdsController::class, 'import'])->name('tds.monthly.import');
    Route::delete('/tds/monthly/{tds}',      [WebMonthlyTdsController::class, 'destroy'])->name('tds.monthly.destroy');

    // ── Salary Report ──────────────────────────────
    Route::get('/salary-report',                         [WebSalaryReportController::class, 'index'])->name('salary.report');
    Route::get('/salary-report/saved',                   [WebSalaryReportController::class, 'savedReports'])->name('salary.report.saved');
    Route::get('/salary-report/view/{month}',            [WebSalaryReportController::class, 'viewLockedReport'])->name('salary.report.view')->where('month', '\d{4}-\d{2}');
    Route::post('/salary-report/download',       [WebSalaryReportController::class, 'download'])->name('salary.report.download');
    Route::post('/salary-report/override',       [WebSalaryReportController::class, 'updateOverride'])->name('salary.report.override');
    Route::post('/salary-report/lock',            [WebSalaryReportController::class, 'lockSnapshot'])->name('salary.report.lock');
    Route::post('/salary-report/unlock',          [WebSalaryReportController::class, 'unlockSnapshot'])->name('salary.report.unlock');
    Route::post('/salary-report/delete-snapshot', [WebSalaryReportController::class, 'deleteSnapshot'])->name('salary.report.delete');
    Route::post('/salary-report/reupload',        [WebSalaryReportController::class, 'reuploadSnapshot'])->name('salary.report.reupload');

    // ── Salary Revisions ───────────────────────────
    Route::get('/revisions',                      [WebRevisionController::class, 'index'])->name('revisions');
    Route::post('/revisions',                     [WebRevisionController::class, 'store'])->name('revisions.store');
    Route::post('/revisions/{revision}/approve',  [WebRevisionController::class, 'approve'])->name('revisions.approve');
    Route::get('/revisions/template',             [WebRevisionController::class, 'template'])->name('revisions.template');
    Route::post('/revisions/import',              [WebRevisionController::class, 'import'])->name('revisions.import');

    // ── Loans ──────────────────────────────────────
    Route::get('/loans',                  [WebLoanController::class, 'index'])->name('loans');
    Route::post('/loans',                 [WebLoanController::class, 'store'])->name('loans.store');
    Route::get('/loans/template',         [WebLoanController::class, 'template'])->name('loans.template');
    Route::post('/loans/import',          [WebLoanController::class, 'import'])->name('loans.import');
    Route::post('/loans/{loan}/approve',   [WebLoanController::class, 'approve'])->name('loans.approve');
    Route::get('/loans/{loan}/schedule',  [WebLoanController::class, 'schedule'])->name('loans.schedule');
    Route::post('/loans/{loan}',          [WebLoanController::class, 'update'])->name('loans.update');
    Route::delete('/loans/{loan}',        [WebLoanController::class, 'destroy'])->name('loans.destroy');

    // ── Reimbursements ─────────────────────────────
    Route::get('/reimbursements',                   [WebReimbursementController::class, 'index'])->name('reimbursements');
    Route::get('/reimbursements/template',          [WebReimbursementController::class, 'template'])->name('reimbursements.template');
    Route::post('/reimbursements',                  [WebReimbursementController::class, 'store'])->name('reimbursements.store');
    Route::post('/reimbursements/{claim}/approve',  [WebReimbursementController::class, 'approve'])->name('reimbursements.approve');

    // ── Reimbursement Tracker ──────────────────────
    Route::get('/reimbursements/tracker',               [WebReimbursementReportController::class, 'trackerIndex'])->name('reimbursements.tracker');
    Route::get('/reimbursements/tracker/template',      [WebReimbursementReportController::class, 'trackerTemplate'])->name('reimbursements.tracker.template');
    Route::get('/reimbursements/tracker/export',        [WebReimbursementReportController::class, 'trackerExport'])->name('reimbursements.tracker.export');
    Route::post('/reimbursements/tracker/add',          [WebReimbursementReportController::class, 'trackerStore'])->name('reimbursements.tracker.store');
    Route::post('/reimbursements/tracker/import',       [WebReimbursementReportController::class, 'trackerImport'])->name('reimbursements.tracker.import');
    Route::post('/reimbursements/tracker/images',       [WebReimbursementReportController::class, 'trackerImageUpload'])->name('reimbursements.tracker.images');
    Route::get('/reimbursements/tracker/images/{month}/{filename}', [WebReimbursementReportController::class, 'trackerImageView'])->name('reimbursements.tracker.image.view')->where('filename', '[^/]+');
    Route::post('/reimbursements/tracker/{row}/status', [WebReimbursementReportController::class, 'trackerUpdateStatus'])->name('reimbursements.tracker.status');
    Route::delete('/reimbursements/tracker/{row}',      [WebReimbursementReportController::class, 'trackerDestroy'])->name('reimbursements.tracker.destroy');

    // ── Reimbursement Report ───────────────────────
    Route::get('/reimbursements/report',                [WebReimbursementReportController::class, 'reportIndex'])->name('reimbursements.report');
    Route::get('/reimbursements/report/saved',          [WebReimbursementReportController::class, 'reportSaved'])->name('reimbursements.report.saved');
    Route::get('/reimbursements/report/view/{month}',   [WebReimbursementReportController::class, 'reportView'])->name('reimbursements.report.view')->where('month', '\d{4}-\d{2}');
    Route::post('/reimbursements/report/generate',      [WebReimbursementReportController::class, 'reportGenerate'])->name('reimbursements.report.generate');
    Route::post('/reimbursements/report/download',      [WebReimbursementReportController::class, 'reportDownload'])->name('reimbursements.report.download');
    Route::post('/reimbursements/report/unlock',        [WebReimbursementReportController::class, 'reportUnlock'])->name('reimbursements.report.unlock');
    Route::post('/reimbursements/report/delete',        [WebReimbursementReportController::class, 'reportDelete'])->name('reimbursements.report.delete');

    // ── Statutory ──────────────────────────────────
    Route::get('/statutory',                      [WebStatutoryController::class, 'index'])->name('statutory');
    Route::post('/statutory/challan',             [WebStatutoryController::class, 'storeChallan'])->name('statutory.challan');
    Route::post('/statutory/challan/{challan}/paid', [WebStatutoryController::class, 'markPaid'])->name('statutory.challan.paid');

    // ── TDS ────────────────────────────────────────
    Route::get('/tds',               [WebTdsController::class, 'index'])->name('tds');
    Route::get('/tds/template',      [WebTdsController::class, 'template'])->name('tds.template');
    Route::post('/tds/import',       [WebTdsController::class, 'import'])->name('tds.import');
    Route::post('/tds',              [WebTdsController::class, 'store'])->name('tds.store');
    Route::post('/tds/{slab}',       [WebTdsController::class, 'update'])->name('tds.update');
    Route::delete('/tds/{slab}',     [WebTdsController::class, 'destroy'])->name('tds.destroy');

    // ── Holidays ───────────────────────────────────
    Route::get('/holidays',                [WebHolidayController::class, 'index'])->name('holidays');
    Route::post('/holidays',               [WebHolidayController::class, 'store'])->name('holidays.store');
    Route::post('/holidays/weekoffs',      [WebHolidayController::class, 'storeWeekoffs'])->name('holidays.weekoffs');
    Route::delete('/holidays/{holiday}',   [WebHolidayController::class, 'destroy'])->name('holidays.destroy');
    Route::get('/holidays/template',       [WebHolidayController::class, 'template'])->name('holidays.template');
    Route::post('/holidays/import',        [WebHolidayController::class, 'import'])->name('holidays.import');

    // ── Helpdesk ───────────────────────────────────
    Route::get('/helpdesk',                         [WebHelpdeskController::class, 'index'])->name('helpdesk');
    Route::post('/helpdesk',                        [WebHelpdeskController::class, 'store'])->name('helpdesk.store');
    Route::post('/helpdesk/{ticket}/resolve',       [WebHelpdeskController::class, 'resolve'])->name('helpdesk.resolve');
    Route::get('/helpdesk/template',                [WebHelpdeskController::class, 'template'])->name('helpdesk.template');
    Route::post('/helpdesk/import',                 [WebHelpdeskController::class, 'import'])->name('helpdesk.import');

    // ── Reports ────────────────────────────────────
    Route::get('/reports',                              [WebReportController::class, 'index'])->name('reports');
    Route::get('/reports/attendance',                   [WebReportController::class, 'attendance'])->name('reports.attendance');
    Route::get('/reports/attendance/export',            [WebReportController::class, 'attendanceExport'])->name('reports.attendance.export');
    Route::post('/reports/attendance/approve',          [WebReportController::class, 'updateApproval'])->name('reports.attendance.approve');
    Route::post('/reports/attendance/reupload',         [WebReportController::class, 'reuploadAttendance'])->name('reports.attendance.reupload');

    // ── Masters ────────────────────────────────────
    Route::get('/masters',                           [WebMasterController::class, 'index'])->name('masters');
    Route::post('/masters/company',                  [WebMasterController::class, 'storeCompany'])->name('masters.company');
    Route::delete('/masters/company/{company}',      [WebMasterController::class, 'destroyCompany'])->name('masters.company.destroy');

    // ── Departments ────────────────────────────────
    Route::get('/departments',              [WebDepartmentController::class, 'index'])->name('departments');
    Route::post('/departments',             [WebDepartmentController::class, 'storeDepartment'])->name('departments.store');
    Route::post('/designations',            [WebDepartmentController::class, 'storeDesignation'])->name('designations.store');
    Route::get('/departments/template',     [WebDepartmentController::class, 'template'])->name('departments.template');
    Route::post('/departments/import',      [WebDepartmentController::class, 'import'])->name('departments.import');
});
