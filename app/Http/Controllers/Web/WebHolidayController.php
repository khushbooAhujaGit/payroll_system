<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Holiday;
use App\Models\HolidayCalendar;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class WebHolidayController extends Controller
{
    public function index(Request $request)
    {
        $year       = (int) $request->get('year', now()->year);
        $companyFilter = $request->get('company_id'); // null = all companies

        // All active companies (for filter bar)
        $companies = Company::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        // Load all calendars for the selected year, optionally filtered by company
        $calendarQuery = HolidayCalendar::where('year', $year)->with('holidays');
        if ($companyFilter) {
            $calendarQuery->where('company_id', $companyFilter);
        }
        $calendars = $calendarQuery->get();

        // Company id → name map for display
        $companyNames = $companies->pluck('name', 'id');

        // Load all holidays for these calendars, ordered by date
        // Grouped as: date → [company_id => holiday]  so the table shows all companies per date
        $allHolidays = Holiday::whereIn('holiday_calendar_id', $calendars->pluck('id'))
            ->whereYear('date', $year)
            ->orderBy('date')
            ->get()
            ->map(function ($h) use ($calendars, $companyNames) {
                $cal = $calendars->firstWhere('id', $h->holiday_calendar_id);
                $h->company_id   = $cal?->company_id;
                $h->company_name = $companyNames[$cal?->company_id] ?? '—';
                return $h;
            });

        // Group by date for the date-wise grid view
        $byDate = $allHolidays->groupBy(fn($h) => \Carbon\Carbon::parse($h->date)->format('Y-m-d'));

        // Which companies actually have data (for column headers)
        $activeCompanyIds = $allHolidays->pluck('company_id')->unique()->values();
        $activeCompanies  = $companies->whereIn('id', $activeCompanyIds)->values();

        // Calendar map: company_id → calendar (for Add Holiday modal)
        $calendarByCompany = $calendars->keyBy('company_id');

        return view('holidays.index', compact(
            'year', 'companies', 'companyFilter',
            'byDate', 'activeCompanies', 'calendarByCompany',
            'allHolidays'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id'  => 'required|exists:companies,id',
            'date'        => 'required|date',
            'name'        => 'required|string|max:200',
            'type'        => 'required|in:national,regional,optional,company',
            'description' => 'nullable|string',
        ]);

        $year = (int) \Carbon\Carbon::parse($validated['date'])->format('Y');

        // Auto-create calendar for this company+year if not exists
        $company  = Company::find($validated['company_id']);
        $calendar = HolidayCalendar::firstOrCreate(
            ['company_id' => $validated['company_id'], 'year' => $year],
            ['name' => $company->name . ' ' . $year, 'is_active' => true]
        );

        Holiday::updateOrCreate(
            ['holiday_calendar_id' => $calendar->id, 'date' => $validated['date']],
            ['name' => $validated['name'], 'type' => $validated['type'], 'description' => $validated['description'] ?? null]
        );

        return redirect()->route('holidays', ['year' => $year])->with('success', 'Holiday added for ' . $company->name . '.');
    }

    public function destroy(Holiday $holiday)
    {
        $calendarId = $holiday->holiday_calendar_id;
        $holiday->delete();
        return redirect()->route('holidays', ['calendar_id' => $calendarId])->with('success', 'Holiday deleted.');
    }

    public function template()
    {
        $companies = Company::where('is_active', true)->orderBy('name')->pluck('name')->all();

        $spreadsheet = new Spreadsheet();

        // ── Lists sheet (hidden) — company dropdown values ────────────────
        $listSheet = $spreadsheet->createSheet(0);
        $listSheet->setTitle('Lists');
        foreach ($companies as $i => $name) {
            $listSheet->setCellValue('A' . ($i + 1), $name);
        }
        $listSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

        // ── Data sheet ────────────────────────────────────────────────────
        $sheet = $spreadsheet->createSheet(1);
        $sheet->setTitle('Holidays');
        $spreadsheet->setActiveSheetIndex(1);

        $headers = ['Company', 'Date (YYYY-MM-DD)', 'Name', 'Type (national/regional/optional/company)', 'Description'];
        foreach ($headers as $i => $h) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $cell = $sheet->getCell("{$colLetter}1");
            $cell->setValue($h);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
            $sheet->getStyle("{$colLetter}1")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF4472C4']],
            ]);
        }

        // Company dropdown on column A (rows 2–500)
        if (!empty($companies)) {
            $lastListRow = count($companies);
            $validation  = new DataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_WARNING);
            $validation->setAllowBlank(false);
            $validation->setShowDropDown(false);
            $validation->setShowInputMessage(true);
            $validation->setPromptTitle('Select Company');
            $validation->setPrompt('Choose the company this holiday applies to.');
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Invalid company');
            $validation->setError('Please select a company from the dropdown.');
            $validation->setFormula1("Lists!\$A\$1:\$A\${$lastListRow}");
            $sheet->setDataValidation('A2:A500', $validation);
        }

        // Sample rows
        $firstCompany = $companies[0] ?? 'Company Name';
        $sheet->fromArray([$firstCompany, '2025-01-26', 'Republic Day',    'national', ''], null, 'A2');
        $sheet->fromArray([$firstCompany, '2025-08-15', 'Independence Day', 'national', ''], null, 'A3');

        $writer   = new Xlsx($spreadsheet);
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'holidays_template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file'        => 'required|file|mimes:xlsx,xls,csv|max:5120',
            'calendar_id' => 'nullable|exists:holiday_calendars,id',
        ]);

        $filePath = $request->file('file')->getPathname();

        // ── Read raw rows without PhpSpreadsheet formula evaluation ─────────
        // (same ZipArchive approach used by ExcelImportService to avoid
        //  CalculationException on files with Excel Tables)
        try {
            $rows = $this->readHolidayXlsx($filePath);
        } catch (\Throwable $e) {
            return redirect()->route('holidays')
                ->with('error', 'Could not read the file: ' . $e->getMessage());
        }

        if (empty($rows)) {
            return redirect()->route('holidays')
                ->with('error', 'The uploaded file is empty.');
        }

        // ── Header validation ─────────────────────────────────────────────
        $headers      = array_map('trim', array_values($rows[0]));
        $hasCompanyCol = false;
        $headerErrors  = [];

        foreach (['Date', 'Name'] as $req) {
            $found = false;
            foreach ($headers as $h) {
                if (stripos($h, $req) === 0) { $found = true; break; }
            }
            if (!$found) $headerErrors[] = "\"{$req}\" column not found.";
        }
        if ($headerErrors) {
            return redirect()->route('holidays')
                ->with('error', 'Invalid template headers: ' . implode(' ', $headerErrors)
                    . ' Expected: Company (optional), Date (YYYY-MM-DD), Name, Type, Description.');
        }

        // ── Identify column positions ─────────────────────────────────────
        $colCompany = $colDate = $colName = $colType = $colDesc = null;
        foreach ($headers as $idx => $h) {
            $lower = strtolower($h);
            if (str_starts_with($lower, 'company'))  { $colCompany = $idx; $hasCompanyCol = true; }
            elseif (str_starts_with($lower, 'date')) $colDate = $idx;
            elseif (str_starts_with($lower, 'name')) $colName = $idx;
            elseif (str_starts_with($lower, 'type')) $colType = $idx;
            elseif (str_starts_with($lower, 'desc')) $colDesc = $idx;
        }

        // Pre-load company name → id map for fast lookup
        $companyMap  = Company::where('is_active', true)->pluck('id', 'name')->all();
        $validTypes  = ['national', 'regional', 'optional', 'company'];

        // Default calendar_id from the form (used when file has no Company column)
        $defaultCalendarId = $request->calendar_id ?: null;

        // ── Process data rows ─────────────────────────────────────────────
        $dataRows  = array_slice($rows, 1);
        $errors    = [];
        $validRows = [];

        foreach ($dataRows as $i => $row) {
            $rowNum = $i + 2;
            $vals   = array_values($row);

            $rawCompany = ($colCompany !== null && isset($vals[$colCompany]))
                ? trim((string) $vals[$colCompany]) : '';
            $rawDate    = $colDate !== null && isset($vals[$colDate]) ? $vals[$colDate] : null;
            $rawName    = $colName !== null && isset($vals[$colName]) ? trim((string) $vals[$colName]) : '';
            $rawType    = $colType !== null && isset($vals[$colType]) ? strtolower(trim((string) $vals[$colType])) : '';
            $rawDesc    = $colDesc !== null && isset($vals[$colDesc]) ? trim((string) $vals[$colDesc]) : null;

            if ($rawDate === null && $rawName === '' && $rawCompany === '') continue;

            $rowErrors = [];

            // Resolve company → calendar
            $calendarId = $defaultCalendarId;
            if ($hasCompanyCol && $rawCompany !== '') {
                $companyId = $companyMap[$rawCompany] ?? null;
                if (!$companyId) {
                    $rowErrors[] = "Company \"{$rawCompany}\" not found. Available: " . implode(', ', array_keys($companyMap)) . ".";
                } else {
                    // Auto-create calendar for this company+year if missing
                    $year       = $this->parseHolidayDate($rawDate) ? substr($this->parseHolidayDate($rawDate), 0, 4) : date('Y');
                    $calendar   = HolidayCalendar::firstOrCreate(
                        ['company_id' => $companyId, 'year' => (int) $year],
                        ['name' => $rawCompany . ' ' . $year, 'is_active' => true]
                    );
                    $calendarId = $calendar->id;
                }
            }

            if (!$calendarId) {
                $rowErrors[] = 'No company selected. Either add a Company column or select a calendar.';
            }

            if ($rawName === '') $rowErrors[] = 'Name is required.';

            $parsedDate = $this->parseHolidayDate($rawDate);
            if ($parsedDate === null) {
                $rowErrors[] = "Date \"{$rawDate}\" could not be parsed. Use YYYY-MM-DD, DD-MM-YYYY, or DD/MM/YYYY.";
            }

            if ($rawType !== '' && !in_array($rawType, $validTypes)) {
                $rowErrors[] = "Type \"{$rawType}\" is invalid. Use: " . implode(', ', $validTypes) . ".";
            }
            $type = in_array($rawType, $validTypes) ? $rawType : 'national';

            if ($rowErrors) {
                $errors[] = 'Row ' . $rowNum . ': ' . implode(' ', $rowErrors);
                continue;
            }

            $validRows[] = [
                'calendar_id' => $calendarId,
                'date'        => $parsedDate,
                'name'        => $rawName,
                'type'        => $type,
                'desc'        => $rawDesc ?: null,
            ];
        }

        if ($errors) {
            $msg = count($errors) . ' row(s) have errors:<br>' . implode('<br>', $errors);
            return redirect()->route('holidays', ['calendar_id' => $defaultCalendarId])
                ->with('error', $msg);
        }

        if (empty($validRows)) {
            return redirect()->route('holidays', ['calendar_id' => $defaultCalendarId])
                ->with('error', 'No valid holiday rows found in the file.');
        }

        // ── Import — update-or-create per (calendar_id + date) ───────────
        $count = 0;
        DB::beginTransaction();
        try {
            foreach ($validRows as $r) {
                Holiday::updateOrCreate(
                    ['holiday_calendar_id' => $r['calendar_id'], 'date' => $r['date']],
                    ['name' => $r['name'], 'type' => $r['type'], 'description' => $r['desc']]
                );
                $count++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return redirect()->route('holidays', ['calendar_id' => $defaultCalendarId])
                ->with('error', 'Import failed while saving: ' . $e->getMessage());
        }

        return redirect()->route('holidays', ['calendar_id' => $defaultCalendarId])
            ->with('success', "{$count} holiday(s) imported successfully.");
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Read an xlsx file as a plain array without formula evaluation.
     * Returns [ [col0=>val, col1=>val, ...], ... ] — one entry per non-empty row.
     */
    private function readHolidayXlsx(string $filePath): array
    {
        // CSV fallback
        if (str_ends_with(strtolower($filePath), '.csv')) {
            $rows = [];
            if (($fh = fopen($filePath, 'r')) !== false) {
                while (($row = fgetcsv($fh)) !== false) {
                    $rows[] = array_values($row);
                }
                fclose($fh);
            }
            return $rows;
        }

        // xlsx / xls — read via ZipArchive to avoid formula evaluation
        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new \RuntimeException('Cannot open the uploaded file.');
        }

        // Shared strings
        $strings = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml) {
            $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
            $ss = new \SimpleXMLElement($ssXml);
            foreach ($ss->children($ns)->si as $si) {
                $children = $si->children($ns);
                if (isset($children->t)) {
                    $strings[] = (string) $children->t;
                } else {
                    $text = '';
                    foreach ($children->r as $run) {
                        $text .= (string) $run->children($ns)->t;
                    }
                    $strings[] = $text;
                }
            }
        }

        // ── Find the sheet that contains holiday data ─────────────────────────
        // Strategy: check each sheet XML for the string "Date" or "Name" in its
        // raw shared-string references. This avoids SimpleXML namespace issues.
        // Our template has Lists (hidden) as sheet1 and Holidays data as sheet2.
        $wsXml = null;

        // Build a set of shared-string indices that contain header keywords
        $headerIndices = [];
        foreach ($strings as $idx => $str) {
            $lower = strtolower($str);
            if (str_starts_with($lower, 'date') || str_starts_with($lower, 'name')
                || str_starts_with($lower, 'company') || str_starts_with($lower, 'occasion')) {
                $headerIndices[$idx] = true;
            }
        }

        for ($i = 1; $i <= 20; $i++) {
            $sheetXml = $zip->getFromName("xl/worksheets/sheet{$i}.xml");
            if ($sheetXml === false) break;

            // Simple heuristic: the header row cells all have t="s" (shared string).
            // Look for r="1" rows that reference our header-keyword string indices.
            // We use regex on the raw XML to avoid namespace complexity.
            preg_match_all('/<row[^>]+r="1"[^>]*>(.*?)<\/row>/s', $sheetXml, $rowMatch);
            if (empty($rowMatch[1])) {
                // Try without row number attribute (some writers omit it)
                preg_match('/<row\b[^>]*>(.*?)<\/row>/s', $sheetXml, $rowMatch2);
                $rowContent = $rowMatch2[1] ?? '';
            } else {
                $rowContent = $rowMatch[1][0];
            }

            // Count how many cells in the first row reference a header-keyword string
            preg_match_all('/<v>(\d+)<\/v>/', $rowContent, $vMatches);
            $hits = 0;
            foreach ($vMatches[1] as $idx) {
                if (isset($headerIndices[(int)$idx])) $hits++;
            }

            if ($hits >= 2) {
                $wsXml = $sheetXml;
                break;
            }
        }

        // Fallback: use sheet1 if no data sheet found
        if ($wsXml === null) {
            $wsXml = $zip->getFromName('xl/worksheets/sheet1.xml') ?: '';
        }

        $zip->close();

        if (!$wsXml) {
            throw new \RuntimeException('Could not read worksheet from file.');
        }

        // ── Parse rows using regex — avoids SimpleXML namespace issues entirely ─
        // Each <row> contains <c> cells; each <c> may have t="s" (shared string),
        // t="b" (bool), or no t (numeric/date serial). Value is in <v>.
        $rows = [];

        // Extract all <row ...>...</row> blocks
        preg_match_all('/<row\b[^>]*>(.*?)<\/row>/s', $wsXml, $rowMatches);

        foreach ($rowMatches[1] as $rowContent) {
            $rowData = [];

            // Extract all <c ...>...</c> blocks within this row
            preg_match_all('/<c\b([^>]*)>(.*?)<\/c>/s', $rowContent, $cellMatches, PREG_SET_ORDER);

            foreach ($cellMatches as $cellMatch) {
                $attrs   = $cellMatch[1];   // e.g. ' r="A1" s="1" t="s"'
                $inner   = $cellMatch[2];   // e.g. '<v>3</v>'

                // Get cell type
                preg_match('/\bt="([^"]*)"/', $attrs, $tMatch);
                $type = $tMatch[1] ?? '';

                // Get value inside <v>...</v>
                preg_match('/<v>(.*?)<\/v>/s', $inner, $vMatch);
                $rawVal = isset($vMatch[1]) ? $vMatch[1] : null;

                switch ($type) {
                    case 's':   // shared string index
                        $rowData[] = $rawVal !== null ? ($strings[(int)$rawVal] ?? '') : '';
                        break;
                    case 'b':   // boolean
                        $rowData[] = $rawVal === '1' ? true : false;
                        break;
                    case 'inlineStr':
                        preg_match('/<t>(.*?)<\/t>/s', $inner, $tInline);
                        $rowData[] = $tInline[1] ?? '';
                        break;
                    default:    // numeric or date serial — return as-is for parseHolidayDate
                        $rowData[] = ($rawVal !== null && $rawVal !== '')
                            ? (is_numeric($rawVal) ? (float)$rawVal : $rawVal)
                            : null;
                }
            }

            if (!empty(array_filter($rowData, fn($v) => $v !== null && $v !== ''))) {
                $rows[] = $rowData;
            }
        }

        return $rows;
    }

    /**
     * Parse a date value that may arrive as:
     *  • YYYY-MM-DD string            → stored as-is
     *  • DD-MM-YYYY / DD/MM/YYYY      → converted to Y-m-d
     *  • Excel serial (float/int)     → converted via epoch calculation
     *  • DateTimeInterface            → formatted
     *
     * Returns "Y-m-d" string or null on failure.
     */
    private function parseHolidayDate(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;

        // DateTime object
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->format('Y-m-d');
        }

        // Excel serial number
        if (is_numeric($value)) {
            $serial = (float) $value;
            if ($serial >= 1 && $serial <= 2958465) {
                try {
                    return Carbon::createFromTimestampUTC((int)(($serial - 25569) * 86400))->format('Y-m-d');
                } catch (\Throwable) {
                    return null;
                }
            }
        }

        $str = trim((string) $value);
        if ($str === '') return null;

        // Try formats from most specific to least
        $formats = ['Y-m-d', 'd-m-Y', 'd/m/Y', 'd.m.Y', 'm/d/Y', 'Y/m/d', 'd-m-y', 'd/m/y'];
        foreach ($formats as $fmt) {
            try {
                $parsed = Carbon::createFromFormat($fmt, $str);
                if ($parsed && $parsed->year >= 1900 && $parsed->year <= 2100) {
                    return $parsed->format('Y-m-d');
                }
            } catch (\Throwable) { }
        }

        // Last resort: Carbon::parse
        try {
            $parsed = Carbon::parse($str);
            if ($parsed->year >= 1900 && $parsed->year <= 2100) {
                return $parsed->format('Y-m-d');
            }
        } catch (\Throwable) { }

        return null;
    }
}
