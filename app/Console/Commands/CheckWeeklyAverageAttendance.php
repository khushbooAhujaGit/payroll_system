<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Notifications\WeeklyAttendanceShortfall;
use App\Services\AttendanceWeeklyAverageService;
use App\Services\OutlookMailerService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Item #21: "If an Employee hasn't met Weekly Average of 9 hours, they'll
 * be notified for exception." Runs weekly (see routes/console.php) for the
 * week just completed; checks every currently-employed employee and emails
 * anyone under the threshold.
 */
class CheckWeeklyAverageAttendance extends Command
{
    protected $signature = 'attendance:check-weekly-average {--week= : ISO week start date (Y-m-d) to check instead of last week, for testing}';
    protected $description = 'Email employees whose average clocked hours fell below 9/day for the past week';

    public function handle(AttendanceWeeklyAverageService $service): int
    {
        $weekStart = $this->option('week')
            ? Carbon::parse($this->option('week'))->startOfWeek(Carbon::MONDAY)
            : now()->subWeek()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

        $employees = Employee::currentlyEmployed()->get();
        $notified  = 0;

        foreach ($employees as $employee) {
            $result = $service->forEmployeeDateRange($employee->id, $weekStart, $weekEnd);
            if (!$service->isShortfall($result)) {
                continue;
            }

            $employee->user?->notify(new WeeklyAttendanceShortfall($weekStart, $weekEnd, $result['average_hours']));

            $email = $employee->official_email ?: $employee->personal_email;
            if (!$email) {
                continue;
            }

            $body = view('emails.attendance-weekly-shortfall', [
                'employee'     => $employee,
                'weekStart'    => $weekStart,
                'weekEnd'      => $weekEnd,
                'averageHours' => $result['average_hours'],
                'dayCount'     => $result['day_count'],
            ])->render();

            try {
                app(OutlookMailerService::class)->sendMail(
                    $email,
                    "Attendance Notice - Week of {$weekStart->format('d M Y')}",
                    $body
                );
                $notified++;
            } catch (\Throwable $e) {
                Log::error('Failed to send weekly attendance shortfall email: ' . $e->getMessage());
            }
        }

        $this->info("Checked {$employees->count()} employee(s), notified {$notified} for the week of {$weekStart->format('d M Y')}.");

        return self::SUCCESS;
    }
}
