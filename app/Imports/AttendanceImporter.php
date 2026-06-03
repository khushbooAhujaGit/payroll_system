<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\Attendance;

class AttendanceImporter extends BaseImporter
{
    protected function getRequiredHeaders(): array
    {
        return ['Employee ID', 'Attendance Date', 'Status'];
    }


    public static function getTemplateHeaders(): array
    {
        return [
            'Employee ID',
            'Attendance Date (DD-MM-YYYY)',
            'Check In (HH:MM)',
            'Check Out (HH:MM)',
            'Status',
            'Work Type',
            'Remarks',
        ];
    }

    public static function getSampleRows(): array
    {
        return [
            ['EMP0001', '01-05-2025', '09:00', '18:00', 'present', 'wfo', ''],
            ['EMP0002', '01-05-2025', '09:30', '18:30', 'present', 'wfh', 'Work from home'],
            ['EMP0003', '01-05-2025', '',      '',      'absent',  '',    'No information'],
        ];
    }

    public static function getColumnValidations(): array
    {
        return [
            'Employee ID'        => ['required' => true,  'type' => 'string'],
            'Attendance Date'    => ['required' => true,  'type' => 'date'],
            'Check In'           => ['required' => false, 'type' => 'time'],
            'Check Out'          => ['required' => false, 'type' => 'time'],
            'Status'             => ['required' => true,  'type' => 'string'],
            'Work Type'          => ['required' => false, 'type' => 'string'],
            'Remarks'            => ['required' => false, 'type' => 'string'],
        ];
    }

    protected function validate(array $row, int $rowNum): array
    {
        $errors = [];
        $validStatuses = ['present', 'absent', 'half_day', 'late', 'leave', 'holiday', 'weekly_off', 'work_from_home', 'on_duty', 'tour', 'lop'];

        $status = strtolower(trim($row['Status'] ?? ''));
        if (!in_array($status, $validStatuses)) {
            $errors[] = "Row {$rowNum}: Invalid status '{$status}'. Must be one of: " . implode(', ', $validStatuses);
        }

        return $errors;
    }

    protected function mapRow(array $row, array $context): array
    {
        $dateRaw = trim($row['Attendance Date (DD-MM-YYYY)'] ?? $row['Attendance Date'] ?? '');
        $date    = $this->parseDate($dateRaw);

        return [
            'employee_code'   => trim($row['Employee ID'] ?? ''),
            'attendance_date' => $date,
            'check_in'        => $this->parseTime($row['Check In (HH:MM)'] ?? $row['Check In'] ?? null),
            'check_out'       => $this->parseTime($row['Check Out (HH:MM)'] ?? $row['Check Out'] ?? null),
            'status'          => strtolower(trim($row['Status'] ?? 'present')),
            'work_type'       => strtolower(trim($row['Work Type'] ?? 'wfo')) ?: 'wfo',
            'remarks'         => trim($row['Remarks'] ?? ''),
            'source'          => 'excel',
        ];
    }

    protected function process(array $rows, array $context): array
    {
        $success = 0;
        $errors  = [];

        // $companyId = $context['company_id'];
        $year     = $context['year'];
        $month    = $context['month'];

        // Pre-load employee ID map
        $employeeMap = Employee::whereNotNull('employee_id')
            ->pluck('id', 'employee_id');

        foreach ($rows as $idx => $row) {
            $rowNum = $idx + 2;
            $code   = $row['employee_code'];

            if (!isset($employeeMap[$code])) {
                $errors[] = "Row {$rowNum}: Employee ID '{$code}' not found.";
                continue;
            }

            $date = $row['attendance_date'];
            if (!$date) {
                $errors[] = "Row {$rowNum}: Invalid date format.";
                continue;
            }

            // Check date is within the expected month
            [$d_year, $d_month] = explode('-', substr($date, 0, 7));
            if ((int)$d_year !== (int)$year || (int)$d_month !== (int)$month) {
                $errors[] = "Row {$rowNum}: Date {$date} is outside the selected month {$year}-{$month}.";
                continue;
            }

            try {
                Attendance::updateOrCreate(
                    ['employee_id' => $employeeMap[$code], 'attendance_date' => $date],
                    [
                        'check_in'    => $row['check_in'],
                        'check_out'   => $row['check_out'],
                        'status'      => $row['status'],
                        'work_type'   => $row['work_type'],
                        'remarks'     => $row['remarks'],
                        'source'      => 'excel',
                    ]
                );
                $success++;
            } catch (\Throwable $e) {
                $errors[] = "Row {$rowNum}: " . $e->getMessage();
            }
        }

        return ['success' => $success, 'errors' => $errors];
    }

    private function parseDate(string $raw): ?string
    {
        if (empty($raw)) return null;

        // Try DD-MM-YYYY first
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $raw, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        // Try YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
        }

        try {
            return \Carbon\Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseTime(?string $raw): ?string
    {
        if (empty($raw)) return null;
        try {
            return \Carbon\Carbon::parse($raw)->format('H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }
}
