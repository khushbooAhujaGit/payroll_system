<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Entity;
use App\Models\Holiday;
use App\Models\HolidayCalendar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class WebHolidayController extends Controller
{
    public function index(Request $request)
    {
        $entityId = $this->resolveEntityId($request);
        $year     = $request->get('year', now()->year);

        $calendars = HolidayCalendar::where('entity_id', $entityId)->get();
        $calendarId = $request->get('calendar_id', $calendars->first()?->id);

        $holidays = $calendarId
            ? Holiday::where('holiday_calendar_id', $calendarId)
                ->whereYear('date', $year)
                ->orderBy('date')->get()
            : collect();

        return view('holidays.index', compact('calendars', 'holidays', 'calendarId', 'year', 'entityId'));
    }

    public function store(Request $request)
    {
        $entityId = $this->resolveEntityId($request);

        $validated = $request->validate([
            'calendar_id' => 'required|exists:holiday_calendars,id',
            'date'        => 'required|date',
            'name'        => 'required|string|max:200',
            'type'        => 'required|in:national,regional,optional',
            'description' => 'nullable|string',
        ]);

        Holiday::create([
            'holiday_calendar_id' => $validated['calendar_id'],
            'date'                => $validated['date'],
            'name'                => $validated['name'],
            'type'                => $validated['type'],
            'description'         => $validated['description'] ?? null,
        ]);

        return redirect()->route('holidays', ['calendar_id' => $validated['calendar_id']])->with('success', 'Holiday added.');
    }

    public function destroy(Holiday $holiday)
    {
        $calendarId = $holiday->holiday_calendar_id;
        $holiday->delete();
        return redirect()->route('holidays', ['calendar_id' => $calendarId])->with('success', 'Holiday deleted.');
    }

    public function template()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Holidays');
        foreach (['Date (YYYY-MM-DD)', 'Name', 'Type (national/regional/optional)', 'Description'] as $i => $h) {
            $sheet->setCellValueByColumnAndRow($i + 1, 1, $h);
        }
        $sheet->fromArray(['2025-01-26', 'Republic Day', 'national', ''], null, 'A2');
        $sheet->fromArray(['2025-08-15', 'Independence Day', 'national', ''], null, 'A3');

        $writer = new Xlsx($spreadsheet);
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'holidays_template.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file'        => 'required|file|mimes:xlsx,xls,csv',
            'calendar_id' => 'required|exists:holiday_calendars,id',
        ]);

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($request->file('file')->getPathname());
            $rows        = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
            array_shift($rows);

            $count = 0;
            DB::beginTransaction();
            foreach ($rows as $row) {
                if (empty(array_filter($row))) continue;
                [$date, $name, $type, $desc] = array_pad($row, 4, null);
                if (!$date || !$name) continue;
                Holiday::updateOrCreate(
                    ['holiday_calendar_id' => $request->calendar_id, 'date' => $date],
                    ['name' => trim($name), 'type' => strtolower(trim($type ?? 'national')), 'description' => $desc ?? null]
                );
                $count++;
            }
            DB::commit();
            return redirect()->route('holidays', ['calendar_id' => $request->calendar_id])
                ->with('success', "{$count} holiday(s) imported.");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('holidays')->with('error', 'Import failed: ' . $e->getMessage());
        }
    }
}
