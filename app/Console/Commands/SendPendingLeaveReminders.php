<?php

namespace App\Console\Commands;

use App\Models\LeaveTrackerRecord;
use App\Models\User;
use App\Services\OutlookMailerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Daily morning digest: for every leave request still awaiting a decision
 * (leave_tracker_records rows created by the Apply Leave modal — see
 * WebLeaveController::apply() — that are still is_active/!is_approved),
 * emails the applicant's reporting manager one list of just their team's
 * pending items, and every HR user one list of every pending item across
 * whichever companies they're scoped to. Deliberately excludes bulk-import/
 * manual tracker rows (source_file != 'leave_application') — those were
 * never "sent by the employee" and HR/managers already review that data on
 * the dedicated Leave Tracker Records page.
 */
class SendPendingLeaveReminders extends Command
{
    protected $signature = 'leave:send-pending-reminders';
    protected $description = "Email each reporting manager and every HR user a daily digest of their team's / the company's pending leave applications";

    public function handle(OutlookMailerService $mailer): int
    {
        Log::info('leave:send-pending-reminders started.');

        $pending = LeaveTrackerRecord::query()
            ->where('is_active', true)
            ->where('is_approved', false)
            ->where('source_file', 'leave_application')
            ->with('employee.reportingManager.user')
            ->orderBy('leave_date')
            ->get()
            ->filter(fn($r) => $r->employee);

        if ($pending->isEmpty()) {
            $this->info('No pending leave applications — nothing to send.');
            Log::info('leave:send-pending-reminders finished: no pending leave applications, nothing sent.');
            return self::SUCCESS;
        }

        $sent = 0;
        $unsent = 0;

        // ── Reporting managers: one digest each, scoped to their own team ──
        // Employees with no reporting manager (or a manager with no
        // resolvable email) are filtered out here — HR still sees their
        // pending leave in the company-wide digest below, but nobody would
        // ever be able to act as "the manager" for them, so it's not counted
        // as a failed send, just not applicable.
        $byManager = $pending
            ->filter(fn($r) => $this->resolveEmployeeEmail($r->employee->reportingManager))
            ->groupBy(fn($r) => $r->employee->reportingManager->id);

        foreach ($byManager as $records) {
            $manager = $records->first()->employee->reportingManager;
            $email   = $this->resolveEmployeeEmail($manager);

            if ($this->sendDigest($mailer, $email, $manager->full_name, $records, 'your team')) {
                $sent++;
            } else {
                $unsent++;
            }
        }

        // ── HR: one digest each, scoped to whichever companies they cover ──
        $recordsByHrEmail = collect();
        $hrNames = [];
        foreach ($pending->groupBy(fn($r) => $r->employee->company_id) as $companyId => $companyRecords) {
            foreach ($this->hrUsersForCompany($companyId) as $hrUser) {
                $recordsByHrEmail->put(
                    $hrUser->email,
                    $recordsByHrEmail->get($hrUser->email, collect())->concat($companyRecords)
                );
                $hrNames[$hrUser->email] = $hrUser->name;
            }
        }

        foreach ($recordsByHrEmail as $email => $records) {
            if ($this->sendDigest($mailer, $email, $hrNames[$email] ?? 'HR', $records->unique('id'), 'the company')) {
                $sent++;
            } else {
                $unsent++;
            }
        }

        $summary = "Sent {$sent} reminder email(s) for {$pending->count()} pending leave record(s) (unsent: {$unsent}).";
        $this->info($summary);
        Log::info("leave:send-pending-reminders finished: {$summary}");

        return self::SUCCESS;
    }

    protected function sendDigest(OutlookMailerService $mailer, ?string $email, string $recipientName, $records, string $scopeLabel): bool
    {
        if (!$email) {
            Log::warning("Pending-leave reminder skipped for \"{$recipientName}\": no resolvable email address.");

            return false;
        }

        $body = view('emails.pending-leave-reminder', [
            'recipientName' => $recipientName,
            'records'       => $records->sortBy('leave_date')->values(),
            'scopeLabel'    => $scopeLabel,
        ])->render();

        try {
            $mailer->sendMail(
                $email,
                'Pending Leave Approvals - ' . now()->format('d M Y'),
                $body,
                [],
                [],
                useRealRecipient: true
            );

            Log::info("Pending-leave reminder sent to {$email} ({$records->count()} record(s), scope: {$scopeLabel}).");

            return true;
        } catch (\Throwable $e) {
            Log::error("Failed to send pending-leave reminder to {$email}: " . $e->getMessage());

            return false;
        }
    }

    /** Same priority as WebLeaveController::resolveEmployeeEmail() — official, then personal, then login email. */
    protected function resolveEmployeeEmail(?\App\Models\Employee $employee): ?string
    {
        if (!$employee) {
            return null;
        }

        return $employee->official_email ?: $employee->personal_email ?: $employee->user?->email;
    }

    /** Same convention as WebLeaveController::hrEmailsForCompany() — company-scoped HR plus global (null company_id) HR. */
    protected function hrUsersForCompany(?int $companyId)
    {
        return User::role('HR')
            ->where(fn($q) => $q->whereNull('company_id')->orWhere('company_id', $companyId))
            ->whereNotNull('email')
            ->get(['id', 'name', 'email']);
    }
}
