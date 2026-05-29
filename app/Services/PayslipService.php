<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeHistory;
use App\Models\EmployeeSalary;
use App\Models\EmployeeSalaryComponent;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\PayrollRun;
use App\Models\PayrollEmployeeRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PayslipService
{
  /**
   * Generate payslips for all employees in a payroll run.
   * Returns count of payslips generated.
   */
  public function generateBulk(PayrollRun $payrollRun): int
  {
    $records = PayrollEmployeeRecord::where('payroll_run_id', $payrollRun->id)
      ->where('status', '!=', 'blocked')
      ->with([
        'employee.entity',
        'employee.department',
        'employee.designation',
        'employee.grade',
        'employee.primaryBank',
        'employee.statutoryDetails',
        'components.salaryComponent',
      ])
      ->get();

    $count = 0;
    foreach ($records as $record) {
      try {
        $this->generateSingle($record, $payrollRun);
        $count++;
      } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error("Payslip error for {$record->employee->employee_id}: " . $e->getMessage());
      }
    }

    return $count;
  }

  public function generateSingle(PayrollEmployeeRecord $record, PayrollRun $payrollRun): string
  {
    $employee  = $record->employee;
    $period    = $payrollRun->period;
    $entity    = $employee->entity;

    // Build payslip data
    $earnings  = $record->components->where('salaryComponent.component_type', 'earning');
    $deductions = $record->components->where('salaryComponent.component_type', 'deduction');

    $data = [
      'employee'        => $employee,
      'record'          => $record,
      'period'          => $period,
      'entity'          => $entity,
      'earnings'        => $earnings,
      'deductions'      => $deductions,
      'month_name'      => \Carbon\Carbon::createFromDate($period->year, $period->month, 1)->format('F Y'),
      'generated_at'    => now()->format('d M Y H:i'),
    ];

    // Generate HTML payslip (can switch to PDF library)
    $html = $this->renderPayslipHtml($data);

    // Store as HTML (in production, use dompdf/puppeteer to convert to PDF)
    // $path = "payslips/{$period->year}/{$period->month}/{$employee->employee_id}.html";
    // Storage::put($path, $html);

    $folder   = public_path("uploads/payslips/{$period->year}/{$period->month}");
    $filename = "{$employee->employee_id}.html";

    if (!file_exists($folder)) {
      mkdir($folder, 0755, true);
    }

    file_put_contents($folder . '/' . $filename, $html);

    $path = "uploads/payslips/{$period->year}/{$period->month}/{$filename}";
    $record->update(['payslip_path' => $path]);

    return $path;
  }

  protected function renderPayslipHtml(array $data): string
  {
    $e         = $data['employee'];
    $r         = $data['record'];
    $entity    = $data['entity'];
    $monthName = $data['month_name'];

    // Pre-compute ALL values — no logic allowed inside heredoc
    $employeeId      = $e->employee_id;
    $fullName        = $e->full_name;
    $designation     = $e->designation?->name ?? '-';
    $department      = $e->department?->name ?? '-';
    $doj             = $e->date_of_joining->format('d M Y');
    $pan             = $e->statutoryDetails?->pan_number ?? 'NA';
    $uan             = $e->statutoryDetails?->uan_number ?? 'NA';
    $bankAccount     = $e->primaryBank?->account_number ?? 'NA';
    $entityName      = $entity->name;
    $generatedAt     = $data['generated_at'];

    $workingDays     = $r->working_days;
    $presentDays     = $r->present_days;
    $absentDays      = $r->absent_days;
    $lopDays         = $r->lop_days;
    $leaveDays       = $r->leave_days;

    $totalEarnings   = number_format($r->total_earnings, 2);
    $totalDeductions = number_format($r->total_deductions, 2);
    $netSalary       = number_format($r->manual_net_override ?? $r->net_salary, 2);

    $earningsRows  = '';
    foreach ($data['earnings'] as $comp) {
      $name   = $comp->salaryComponent->name;
      $amount = number_format($comp->final_amount, 2);
      $earningsRows .= "<tr><td>{$name}</td><td style='text-align:right'>₹ {$amount}</td></tr>";
    }

    $deductionRows = '';
    foreach ($data['deductions'] as $comp) {
      $name   = $comp->salaryComponent->name;
      $amount = number_format($comp->final_amount, 2);
      $deductionRows .= "<tr><td>{$name}</td><td style='text-align:right'>₹ {$amount}</td></tr>";
    }

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Payslip - {$employeeId} - {$monthName}</title>
<style>
  body { font-family: Arial, sans-serif; font-size: 12px; color: #333; }
  .header { background: #1a3a5c; color: white; padding: 15px 20px; }
  .header h2 { margin: 0; font-size: 18px; }
  .header p  { margin: 3px 0; font-size: 11px; }
  .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 10px 20px; }
  .info-box  { background: #f5f7fa; padding: 10px; border-radius: 4px; }
  .info-box label { font-weight: bold; color: #555; display: block; margin-bottom: 3px; font-size: 10px; }
  table { width: 100%; border-collapse: collapse; margin: 5px 0; }
  th    { background: #1a3a5c; color: white; padding: 7px 10px; text-align: left; font-size: 11px; }
  td    { padding: 6px 10px; border-bottom: 1px solid #eee; }
  .totals   { background: #f5f7fa; font-weight: bold; }
  .net-pay  { background: #1a3a5c; color: white; text-align: center; padding: 12px; font-size: 16px; margin: 10px 20px; border-radius: 4px; }
  .footer   { text-align: center; font-size: 10px; color: #888; margin: 20px; }
</style>
</head>
<body>

<div class="header">
  <h2>{$entityName}</h2>
  <p>SALARY SLIP FOR THE MONTH OF {$monthName}</p>
</div>

<div class="info-grid">
  <div class="info-box">
    <label>Employee ID</label>{$employeeId}
    <label>Employee Name</label>{$fullName}
    <label>Designation</label>{$designation}
    <label>Department</label>{$department}
  </div>
  <div class="info-box">
    <label>Date of Joining</label>{$doj}
    <label>PAN</label>{$pan}
    <label>UAN</label>{$uan}
    <label>Bank Account</label>{$bankAccount}
  </div>
</div>

<div style="margin: 5px 20px">
  <table>
    <tr>
      <th>Working Days</th><th>Present</th><th>Absent</th><th>LOP Days</th><th>Leave</th>
    </tr>
    <tr>
      <td>{$workingDays}</td>
      <td>{$presentDays}</td>
      <td>{$absentDays}</td>
      <td>{$lopDays}</td>
      <td>{$leaveDays}</td>
    </tr>
  </table>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin: 5px 20px">
  <div>
    <table>
      <tr><th colspan="2">EARNINGS</th></tr>
      {$earningsRows}
      <tr class="totals"><td>Total Earnings</td><td style="text-align:right">₹ {$totalEarnings}</td></tr>
    </table>
  </div>
  <div>
    <table>
      <tr><th colspan="2">DEDUCTIONS</th></tr>
      {$deductionRows}
      <tr class="totals"><td>Total Deductions</td><td style="text-align:right">₹ {$totalDeductions}</td></tr>
    </table>
  </div>
</div>

<div class="net-pay">NET SALARY: ₹ {$netSalary}</div>

<div class="footer">
  This is a computer-generated payslip and does not require a signature.<br>
  Generated on {$generatedAt}
</div>

</body>
</html>
HTML;
  }
}
