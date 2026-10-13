<?php

namespace App\Console\Commands;

use App\Models\AttendanceMailRecipient;
use App\Models\AttendanceRawPunch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveTrackerRecord;
use App\Services\FastXlsxWriter;
use App\Services\OutlookMailerService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Item 56: "Daily at 10:30 AM, management will get email of all employees
 * who have not yet arrived/on leave/etc. and their reasons thereof... Also,
 * include excel of attendance of all employees."
 *
 * One combined email covering every active company — not grouped into
 * per-company sections, but a Company column on two flat tables ("Not Yet
 * Arrived" and "Other Status" i.e. On Leave etc.) — sent to a single shared
 * recipient list (see AttendanceMailRecipient, a singleton settings row
 * edited from the Attendance page). If no recipients are configured, or
 * there are no active companies, nothing is sent.
 */
class SendDailyAttendanceExceptionMail extends Command
{
    protected $signature = 'attendance:daily-exception-mail {--date= : Date (Y-m-d) to report on instead of today, for testing}';
    protected $description = 'Email the configured recipients one combined daily attendance exception report (all companies) with an Excel attachment';

    public function handle(FastXlsxWriter $xlsxWriter): int
    {
        $date    = $this->option('date') ? Carbon::parse($this->option('date')) : now();
        $dateStr = $date->toDateString();

        $recipient = AttendanceMailRecipient::first();
        $toList    = $recipient?->toList() ?? [];

        if (!$recipient || empty($toList)) {
            $this->info('No exception-mail recipients configured — nothing to send.');
            return self::SUCCESS;
        }

        $companies = Company::where('is_active', true)->orderBy('name')->get();

        if ($companies->isEmpty()) {
            $this->info('No active companies — nothing to send.');
            return self::SUCCESS;
        }

        $notArrivedRows   = collect();
        $otherStatusRows  = collect();
        $fullRows         = []; // combined across all companies, with a Company column, for the Excel attachment
        $totalActive      = 0;
        $totalPresent     = 0;
        $totalOnLeave     = 0;

        foreach ($companies as $company) {
            $result = $this->collectCompanyRows($company, $dateStr);

            $notArrivedRows  = $notArrivedRows->concat($result['notArrived']);
            $otherStatusRows = $otherStatusRows->concat($result['otherStatus']);
            array_push($fullRows, ...$result['fullRows']);

            $totalActive  += $result['totalActive'];
            $totalPresent += $result['presentCount'];
            $totalOnLeave += $result['onLeaveCount'];
        }

        $body = view('emails.attendance-daily-exception', [
            'today'            => $date,
            'notArrivedRows'   => $notArrivedRows,
            'otherStatusRows'  => $otherStatusRows,
            'totalActive'      => $totalActive,
            'totalPresent'     => $totalPresent,
            'totalOnLeave'     => $totalOnLeave,
            'totalNotArrived'  => $totalActive - $totalPresent - $totalOnLeave,
        ])->render();

        $xlsxPath = $xlsxWriter->write(
            'Attendance ' . $date->format('d-m-Y'),
            ['Company', 'Emp ID', 'Name', 'Department', 'Status', 'Time In', 'Time Out', 'Leave Type'],
            $fullRows,
            [20, 14, 24, 20, 16, 10, 10, 14]
        );

        try {
            $result = app(OutlookMailerService::class)->sendMail(
                $toList,
                "Daily Attendance Exceptions - {$date->format('d M Y')}",
                $body,
                $recipient->ccList(),
                [[
                    'filename'  => "attendance_{$dateStr}.xlsx",
                    'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'content'   => file_get_contents($xlsxPath),
                ]],
                useRealRecipient: true
            );

            if (!str_starts_with($result, 'Email sent successfully')) {
                Log::error('Daily attendance exception mail failed: ' . $result, ['date' => $dateStr]);
                $this->error('Send failed: ' . $result);
                return self::FAILURE;
            }

            Log::info('Daily attendance exception mail sent.', [
                'date'       => $dateStr,
                'recipients' => count($toList),
                'companies'  => $companies->count(),
            ]);
            $this->info("Sent combined daily exception mail covering {$companies->count()} compan" . ($companies->count() === 1 ? 'y' : 'ies') . " — {$dateStr}.");
        } catch (\Throwable $e) {
            Log::error('Failed to send daily attendance exception mail: ' . $e->getMessage(), ['date' => $dateStr]);
            $this->error($e->getMessage());
            return self::FAILURE;
        } finally {
            @unlink($xlsxPath);
        }

        return self::SUCCESS;
    }

    /**
     * Build one company's rows for the shared "Not Yet Arrived" / "Other
     * Status" flat cross-company tables (each row carries its own Company
     * name) plus its Excel rows and status counts — returned rather than
     * mutated by reference, so each company's slice is independently easy
     * to test.
     *
     * @return array{notArrived: \Illuminate\Support\Collection, otherStatus: \Illuminate\Support\Collection, fullRows: array, totalActive: int, presentCount: int, onLeaveCount: int}
     */
    private function collectCompanyRows(Company $company, string $dateStr): array
    {
        $employees = Employee::currentlyEmployed()
            ->where('company_id', $company->id)
            ->with('department:id,name')
            ->orderBy('employee_id')
            ->get();

        $employeeIds = $employees->pluck('id');

        $punchesToday = AttendanceRawPunch::whereIn('employee_id', $employeeIds)
            ->where('attendance_date', $dateStr)
            ->get()
            ->groupBy('employee_id');

        $leavesToday = LeaveTrackerRecord::whereIn('employee_id', $employeeIds)
            ->where('leave_date', $dateStr)
            ->where('is_active', true)
            ->where('is_approved', true)
            ->get()
            ->keyBy('employee_id');

        $notArrived   = collect();
        $otherStatus  = collect();
        $fullRows     = [];
        $presentCount = 0;
        $onLeaveCount = 0;

        foreach ($employees as $emp) {
            $punches  = $punchesToday->get($emp->id, collect());
            $checkIn  = $punches->filter(fn($p) => $p->check_in  !== null)->min('check_in');
            $checkOut = $punches->filter(fn($p) => $p->check_out !== null)->max('check_out');
            $leave    = $leavesToday->get($emp->id);

            if ($leave) {
                $status = 'On Leave';
                $reason = $leave->leave_type;
                $onLeaveCount++;
            } elseif ($checkIn) {
                $status = 'Present';
                $reason = '';
                $presentCount++;
            } else {
                $status = 'Not Yet Arrived';
                $reason = '';
            }

            $row = [
                'company'    => $company->name,
                'emp_id'     => $emp->employee_id,
                'name'       => $emp->full_name,
                'department' => $emp->department?->name,
                'status'     => $status,
                'reason'     => $reason,
            ];

            if ($status === 'Not Yet Arrived') {
                $notArrived->push($row);
            } elseif ($status === 'On Leave') {
                $otherStatus->push($row);
            }

            $fullRows[] = [
                $company->name,
                $emp->employee_id,
                $emp->full_name,
                $emp->department?->name,
                $status,
                $checkIn ? substr($checkIn, 0, 5) : '',
                $checkOut ? substr($checkOut, 0, 5) : '',
                $reason,
            ];
        }

        return [
            'notArrived'   => $notArrived,
            'otherStatus'  => $otherStatus,
            'fullRows'     => $fullRows,
            'totalActive'  => $employees->count(),
            'presentCount' => $presentCount,
            'onLeaveCount' => $onLeaveCount,
        ];
    }
}
