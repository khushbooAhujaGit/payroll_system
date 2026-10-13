<?php

namespace App\Jobs;

use App\Models\SalaryReportSnapshot;
use App\Services\OutlookMailerService;
use App\Services\PayslipPdfService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/** 
 * Generates and emails one employee's payslip PDF — queued so locking a
 * report (up to 50+ employees, one Graph API round-trip each) doesn't hold
 * the "Save / Re-lock" request open for a minute or more. Dispatched once
 * per snapshot from WebSalaryReportController::deliverPayslipEmails().
 */
class SendPayslipEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(protected int $snapshotId)
    {
    }
 
    public function handle(PayslipPdfService $payslipService, OutlookMailerService $mailer): void
    {
        $snapshot = SalaryReportSnapshot::with('employee')->find($this->snapshotId);
        if (!$snapshot || $snapshot->payslip_sent_at) {
            return;
        }

        $employee = $snapshot->employee;
        if (!$employee) {
            Log::error("Payslip email skipped: snapshot #{$this->snapshotId} has no linked employee.");
            return;
        }

        $email = $employee->official_email ?: $employee->personal_email;
        if (!$email) {
            Log::error("Payslip email skipped for employee {$employee->id}: no email on file.");
            return;
        }

        $month = $snapshot->year_month;
        $monthLabel = Carbon::createFromFormat('Y-m', $month)->format('F Y');

        $path = $payslipService->ensureGenerated($employee, $month, $snapshot->snapshot_data);
        $pdfContent = file_get_contents($path);

        $body = view('emails.payslip', [
            'employee'   => $employee,
            'row'        => $snapshot->snapshot_data,
            'monthLabel' => $monthLabel,
        ])->render();

        $mailer->sendMail(
            $email,
            "Payslip - {$monthLabel} (Test Mail)",
            $body,
            [],
            [[
                'filename'  => "Payslip_{$employee->employee_id}_{$month}.pdf",
                'mime_type' => 'application/pdf',
                'content'   => $pdfContent,
            ]],
            useRealRecipient: true
        );

        $snapshot->update(['payslip_sent_at' => now()]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error("Payslip email job failed for snapshot #{$this->snapshotId}: " . $e->getMessage());
    }
}
