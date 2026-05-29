<?php
// Re-seeds holidays for calendar IDs 2, 3, 4 (entities 3, 4, 5)
// from the Holidays.xlsx data (128 rows: Sundays + Second Saturdays + public holidays)

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$holidays = [
    ['2024-08-15', 'Independence Day'],
    ['2024-08-19', 'Raksha Bandhan'],
    ['2024-08-26', 'Janmashtami'],
    ['2024-10-12', 'Dussehra'],
    ['2024-11-01', 'Diwali'],
    ['2024-11-02', 'Diwali'],
    ['2024-11-03', 'Diwali'],
    ['2024-11-04', 'Diwali'],
    ['2024-11-05', 'Diwali'],
    ['2024-05-05', 'Sunday'],
    ['2024-05-11', 'Second Saturday'],
    ['2024-05-12', 'Sunday'],
    ['2024-05-19', 'Sunday'],
    ['2024-05-26', 'Sunday'],
    ['2024-06-02', 'Sunday'],
    ['2024-06-08', 'Second Saturday'],
    ['2024-06-09', 'Sunday'],
    ['2024-06-16', 'Sunday'],
    ['2024-06-23', 'Sunday'],
    ['2024-06-30', 'Sunday'],
    ['2024-07-07', 'Sunday'],
    ['2024-07-13', 'Second Saturday'],
    ['2024-07-14', 'Sunday'],
    ['2024-07-21', 'Sunday'],
    ['2024-07-28', 'Sunday'],
    ['2024-08-04', 'Sunday'],
    ['2024-08-10', 'Second Saturday'],
    ['2024-08-11', 'Sunday'],
    ['2024-08-18', 'Sunday'],
    ['2024-08-25', 'Sunday'],
    ['2024-09-01', 'Sunday'],
    ['2024-09-08', 'Sunday'],
    ['2024-09-14', 'Second Saturday'],
    ['2024-09-15', 'Sunday'],
    ['2024-09-22', 'Sunday'],
    ['2024-09-29', 'Sunday'],
    ['2024-10-06', 'Sunday'],
    ['2024-10-13', 'Sunday'],
    ['2024-10-20', 'Sunday'],
    ['2024-10-27', 'Sunday'],
    ['2024-11-09', 'Second Saturday'],
    ['2024-11-10', 'Sunday'],
    ['2024-11-17', 'Sunday'],
    ['2024-11-24', 'Sunday'],
    ['2024-12-01', 'Sunday'],
    ['2024-12-08', 'Sunday'],
    ['2024-12-14', 'Second Saturday'],
    ['2024-12-15', 'Sunday'],
    ['2024-12-22', 'Sunday'],
    ['2024-12-29', 'Sunday'],
    ['2025-01-05', 'Sunday'],
    ['2025-01-11', 'Second Saturday'],
    ['2025-01-12', 'Sunday'],
    ['2025-01-19', 'Sunday'],
    ['2025-01-26', 'Sunday'],
    ['2025-02-02', 'Sunday'],
    ['2025-02-08', 'Second Saturday'],
    ['2025-02-09', 'Sunday'],
    ['2025-02-16', 'Sunday'],
    ['2025-02-23', 'Sunday'],
    ['2025-03-02', 'Sunday'],
    ['2025-03-08', 'Second Saturday'],
    ['2025-03-09', 'Sunday'],
    ['2025-03-16', 'Sunday'],
    ['2025-03-23', 'Sunday'],
    ['2025-03-30', 'Sunday'],
    ['2024-06-22', 'Saturday'],
    ['2025-04-06', 'Sunday'],
    ['2025-04-12', 'Second Saturday'],
    ['2025-04-13', 'Sunday'],
    ['2025-04-20', 'Sunday'],
    ['2025-04-27', 'Sunday'],
    ['2025-05-04', 'Sunday'],
    ['2025-05-10', 'Second Saturday'],
    ['2025-05-11', 'Sunday'],
    ['2025-05-18', 'Sunday'],
    ['2025-05-25', 'Sunday'],
    ['2025-06-01', 'Sunday'],
    ['2025-06-08', 'Sunday'],
    ['2025-06-14', 'Second Saturday'],
    ['2025-06-15', 'Sunday'],
    ['2025-06-22', 'Sunday'],
    ['2025-06-29', 'Sunday'],
    ['2025-07-06', 'Sunday'],
    ['2025-07-12', 'Second Saturday'],
    ['2025-07-13', 'Sunday'],
    ['2025-07-20', 'Sunday'],
    ['2025-07-27', 'Sunday'],
    ['2025-08-03', 'Sunday'],
    ['2025-08-09', 'Second Saturday'],
    ['2025-08-10', 'Sunday'],
    ['2025-08-17', 'Sunday'],
    ['2025-08-24', 'Sunday'],
    ['2025-08-31', 'Sunday'],
    ['2025-09-07', 'Sunday'],
    ['2025-09-13', 'Second Saturday'],
    ['2025-09-14', 'Sunday'],
    ['2025-09-21', 'Sunday'],
    ['2025-09-28', 'Sunday'],
    ['2025-10-05', 'Sunday'],
    ['2025-10-11', 'Second Saturday'],
    ['2025-10-12', 'Sunday'],
    ['2025-10-19', 'Sunday'],
    ['2025-10-26', 'Sunday'],
    ['2025-11-02', 'Sunday'],
    ['2025-11-08', 'Second Saturday'],
    ['2025-11-09', 'Sunday'],
    ['2025-11-16', 'Sunday'],
    ['2025-11-23', 'Sunday'],
    ['2025-11-30', 'Sunday'],
    ['2025-12-07', 'Sunday'],
    ['2025-12-13', 'Second Saturday'],
    ['2025-12-14', 'Sunday'],
    ['2025-12-21', 'Sunday'],
    ['2025-12-28', 'Sunday'],
    ['2025-01-14', 'Uttarayan I'],
    ['2025-01-15', 'Uttarayan II'],
    ['2025-03-14', 'Holi'],
    ['2025-08-09', 'Raksha Bandhan'],
    ['2025-08-15', 'Independence Day'],
    ['2025-08-16', 'Janmashtami'],
    ['2025-10-02', 'Dussehra'],
    ['2025-10-21', 'Diwali & New Year'],
    ['2025-10-22', 'Diwali & New Year'],
    ['2025-10-23', 'Diwali & New Year'],
    ['2025-10-24', 'Diwali & New Year'],
    ['2025-10-25', 'Diwali & New Year'],
    ['2025-02-01', 'Outing'],
];

$calendarIds = [2, 3, 4];
$now = now()->toDateTimeString();

// Clear existing rows for these calendars
$deleted = DB::table('holidays')->whereIn('holiday_calendar_id', $calendarIds)->delete();
echo "Cleared $deleted existing rows\n";

// Batch-insert
// Determine type from occasion name
$typeMap = fn(string $occ): string => match(true) {
    in_array($occ, ['Independence Day', 'Raksha Bandhan', 'Janmashtami', 'Dussehra', 'Holi']) => 'national',
    str_contains($occ, 'Diwali') || str_contains($occ, 'Uttarayan') || $occ === 'New Year' => 'regional',
    default => 'company',  // Sunday, Second Saturday, Saturday, Outing, etc.
};

$rows = [];
foreach ($calendarIds as $calId) {
    foreach ($holidays as [$date, $occ]) {
        $rows[] = [
            'holiday_calendar_id' => $calId,
            'date'                => $date,
            'name'                => $occ,
            'type'                => $typeMap($occ),
            'description'         => '',
            'created_at'          => $now,
            'updated_at'          => $now,
        ];
    }
}
DB::table('holidays')->insert($rows);
echo 'Inserted ' . count($rows) . " rows (" . count($holidays) . " holidays × " . count($calendarIds) . " calendars)\n";

// Verify
foreach ($calendarIds as $calId) {
    $total = DB::table('holidays')->where('holiday_calendar_id', $calId)->count();
    $apr25 = DB::table('holidays')->where('holiday_calendar_id', $calId)
                ->whereYear('date', 2025)->whereMonth('date', 4)->count();
    $aug25 = DB::table('holidays')->where('holiday_calendar_id', $calId)
                ->whereYear('date', 2025)->whereMonth('date', 8)->count();
    echo "Cal $calId: total=$total  Apr2025=$apr25  Aug2025=$aug25\n";
}
