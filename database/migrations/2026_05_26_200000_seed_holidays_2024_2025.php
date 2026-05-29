<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Holiday data: [date, name, type] */
    private function holidays(): array
    {
        return [
            // ── Festivals 2024 ─────────────────────────────────────────────
            ['2024-08-15', 'Independence Day',  'national'],
            ['2024-08-19', 'Raksha Bandhan',    'national'],
            ['2024-08-26', 'Janmashtami',       'national'],
            ['2024-10-12', 'Dussehra',          'national'],
            ['2024-11-01', 'Diwali',            'national'],
            ['2024-11-02', 'Diwali',            'national'],
            ['2024-11-03', 'Diwali',            'national'],
            ['2024-11-04', 'Diwali',            'national'],
            ['2024-11-05', 'Diwali',            'national'],
            // ── Sundays & Second Saturdays 2024 ────────────────────────────
            ['2024-05-05', 'Sunday',            'optional'],
            ['2024-05-11', 'Second Saturday',   'optional'],
            ['2024-05-12', 'Sunday',            'optional'],
            ['2024-05-19', 'Sunday',            'optional'],
            ['2024-05-26', 'Sunday',            'optional'],
            ['2024-06-02', 'Sunday',            'optional'],
            ['2024-06-08', 'Second Saturday',   'optional'],
            ['2024-06-09', 'Sunday',            'optional'],
            ['2024-06-16', 'Sunday',            'optional'],
            ['2024-06-22', 'Saturday',          'optional'],
            ['2024-06-23', 'Sunday',            'optional'],
            ['2024-06-30', 'Sunday',            'optional'],
            ['2024-07-07', 'Sunday',            'optional'],
            ['2024-07-13', 'Second Saturday',   'optional'],
            ['2024-07-14', 'Sunday',            'optional'],
            ['2024-07-21', 'Sunday',            'optional'],
            ['2024-07-28', 'Sunday',            'optional'],
            ['2024-08-04', 'Sunday',            'optional'],
            ['2024-08-10', 'Second Saturday',   'optional'],
            ['2024-08-11', 'Sunday',            'optional'],
            ['2024-08-18', 'Sunday',            'optional'],
            ['2024-08-25', 'Sunday',            'optional'],
            ['2024-09-01', 'Sunday',            'optional'],
            ['2024-09-08', 'Sunday',            'optional'],
            ['2024-09-14', 'Second Saturday',   'optional'],
            ['2024-09-15', 'Sunday',            'optional'],
            ['2024-09-22', 'Sunday',            'optional'],
            ['2024-09-29', 'Sunday',            'optional'],
            ['2024-10-06', 'Sunday',            'optional'],
            ['2024-10-13', 'Sunday',            'optional'],
            ['2024-10-20', 'Sunday',            'optional'],
            ['2024-10-27', 'Sunday',            'optional'],
            ['2024-11-09', 'Second Saturday',   'optional'],
            ['2024-11-10', 'Sunday',            'optional'],
            ['2024-11-17', 'Sunday',            'optional'],
            ['2024-11-24', 'Sunday',            'optional'],
            ['2024-12-01', 'Sunday',            'optional'],
            ['2024-12-08', 'Sunday',            'optional'],
            ['2024-12-14', 'Second Saturday',   'optional'],
            ['2024-12-15', 'Sunday',            'optional'],
            ['2024-12-22', 'Sunday',            'optional'],
            ['2024-12-29', 'Sunday',            'optional'],
            // ── Sundays & Second Saturdays 2025 (Jan–Dec) ──────────────────
            ['2025-01-05', 'Sunday',            'optional'],
            ['2025-01-11', 'Second Saturday',   'optional'],
            ['2025-01-12', 'Sunday',            'optional'],
            ['2025-01-19', 'Sunday',            'optional'],
            ['2025-01-26', 'Sunday',            'optional'],
            ['2025-02-02', 'Sunday',            'optional'],
            ['2025-02-08', 'Second Saturday',   'optional'],
            ['2025-02-09', 'Sunday',            'optional'],
            ['2025-02-16', 'Sunday',            'optional'],
            ['2025-02-23', 'Sunday',            'optional'],
            ['2025-03-02', 'Sunday',            'optional'],
            ['2025-03-08', 'Second Saturday',   'optional'],
            ['2025-03-09', 'Sunday',            'optional'],
            ['2025-03-16', 'Sunday',            'optional'],
            ['2025-03-23', 'Sunday',            'optional'],
            ['2025-03-30', 'Sunday',            'optional'],
            ['2025-04-06', 'Sunday',            'optional'],
            ['2025-04-12', 'Second Saturday',   'optional'],
            ['2025-04-13', 'Sunday',            'optional'],
            ['2025-04-20', 'Sunday',            'optional'],
            ['2025-04-27', 'Sunday',            'optional'],
            ['2025-05-04', 'Sunday',            'optional'],
            ['2025-05-10', 'Second Saturday',   'optional'],
            ['2025-05-11', 'Sunday',            'optional'],
            ['2025-05-18', 'Sunday',            'optional'],
            ['2025-05-25', 'Sunday',            'optional'],
            ['2025-06-01', 'Sunday',            'optional'],
            ['2025-06-08', 'Sunday',            'optional'],
            ['2025-06-14', 'Second Saturday',   'optional'],
            ['2025-06-15', 'Sunday',            'optional'],
            ['2025-06-22', 'Sunday',            'optional'],
            ['2025-06-29', 'Sunday',            'optional'],
            ['2025-07-06', 'Sunday',            'optional'],
            ['2025-07-12', 'Second Saturday',   'optional'],
            ['2025-07-13', 'Sunday',            'optional'],
            ['2025-07-20', 'Sunday',            'optional'],
            ['2025-07-27', 'Sunday',            'optional'],
            ['2025-08-03', 'Sunday',            'optional'],
            ['2025-08-09', 'Second Saturday',   'optional'],
            ['2025-08-10', 'Sunday',            'optional'],
            ['2025-08-17', 'Sunday',            'optional'],
            ['2025-08-24', 'Sunday',            'optional'],
            ['2025-08-31', 'Sunday',            'optional'],
            ['2025-09-07', 'Sunday',            'optional'],
            ['2025-09-13', 'Second Saturday',   'optional'],
            ['2025-09-14', 'Sunday',            'optional'],
            ['2025-09-21', 'Sunday',            'optional'],
            ['2025-09-28', 'Sunday',            'optional'],
            ['2025-10-05', 'Sunday',            'optional'],
            ['2025-10-11', 'Second Saturday',   'optional'],
            ['2025-10-12', 'Sunday',            'optional'],
            ['2025-10-19', 'Sunday',            'optional'],
            ['2025-10-26', 'Sunday',            'optional'],
            ['2025-11-02', 'Sunday',            'optional'],
            ['2025-11-08', 'Second Saturday',   'optional'],
            ['2025-11-09', 'Sunday',            'optional'],
            ['2025-11-16', 'Sunday',            'optional'],
            ['2025-11-23', 'Sunday',            'optional'],
            ['2025-11-30', 'Sunday',            'optional'],
            ['2025-12-07', 'Sunday',            'optional'],
            ['2025-12-13', 'Second Saturday',   'optional'],
            ['2025-12-14', 'Sunday',            'optional'],
            ['2025-12-21', 'Sunday',            'optional'],
            ['2025-12-28', 'Sunday',            'optional'],
            // ── Festivals 2025 ─────────────────────────────────────────────
            ['2025-01-14', 'Uttarayan I',       'national'],
            ['2025-01-15', 'Uttarayan II',      'national'],
            ['2025-03-14', 'Holi',              'national'],
            ['2025-08-09', 'Raksha Bandhan',    'national'],
            ['2025-08-15', 'Independence Day',  'national'],
            ['2025-08-16', 'Janmashtami',       'national'],
            ['2025-10-02', 'Dussehra',          'national'],
            ['2025-10-21', 'Diwali & New Year', 'national'],
            ['2025-10-22', 'Diwali & New Year', 'national'],
            ['2025-10-23', 'Diwali & New Year', 'national'],
            ['2025-10-24', 'Diwali & New Year', 'national'],
            ['2025-10-25', 'Diwali & New Year', 'national'],
            // ── Company Outing ─────────────────────────────────────────────
            ['2025-02-01', 'Outing',            'optional'],
        ];
    }

    public function up(): void
    {
        $now = now()->toDateTimeString();

        // Ensure every entity that has employees also has a holiday calendar.
        $entityIds = DB::table('employees')
            ->distinct()
            ->pluck('entity_id')
            ->toArray();

        foreach ($entityIds as $entityId) {
            $calId = DB::table('holiday_calendars')
                ->where('entity_id', $entityId)
                ->value('id');

            if (!$calId) {
                $calId = DB::table('holiday_calendars')->insertGetId([
                    'entity_id'  => $entityId,
                    'name'       => 'Company Holidays 2024-25',
                    'year'       => 2025,
                    'is_active'  => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // Upsert holidays into this calendar (skip duplicates by date+name)
            foreach ($this->holidays() as [$date, $name, $type]) {
                $exists = DB::table('holidays')
                    ->where('holiday_calendar_id', $calId)
                    ->where('date', $date)
                    ->where('name', $name)
                    ->exists();

                if (!$exists) {
                    DB::table('holidays')->insert([
                        'holiday_calendar_id' => $calId,
                        'date'                => $date,
                        'name'                => $name,
                        'type'                => $type,
                        'description'         => null,
                        'created_at'          => $now,
                        'updated_at'          => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Remove only the holidays we inserted — leave pre-existing ones
        $dates = array_column($this->holidays(), 0);
        DB::table('holidays')->whereIn('date', $dates)->delete();
    }
};
