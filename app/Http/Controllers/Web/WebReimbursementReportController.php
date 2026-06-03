<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ReimbursementTrackerRow;
use App\Models\ReimbursementReportSnapshot;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class WebReimbursementReportController extends Controller
{
    // ─────────────────────────────────────────────
    // TRACKER INDEX
    // ─────────────────────────────────────────────

    public function trackerIndex(Request $request)
    {
        $month   = $request->get('month', now()->format('Y-m'));
        $search  = trim((string) $request->get('search', ''));

        // Totals always from ALL rows for that month (unfiltered)
        $allRows       = ReimbursementTrackerRow::where('year_month', $month)->get(['amount', 'status']);
        $totalRows     = $allRows->count();
        $totalApproved = $allRows->where('status', 'approved')->sum('amount');
        $totalAll      = $allRows->sum('amount');

        // Paginated + optionally filtered rows
        $rows = ReimbursementTrackerRow::where('year_month', $month)
            ->when($search !== '', fn($q) => $q->where(fn($q2) =>
                $q2->where('employee_name', 'like', "%{$search}%")
                   ->orWhere('emp_id', 'like', "%{$search}%")
            ))
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        $snapshot  = ReimbursementReportSnapshot::where('year_month', $month)->first();
        $isLocked  = $snapshot !== null;
        $lockedAt  = $snapshot?->locked_at;

        return view('reimbursements.tracker', compact(
            'month', 'rows', 'isLocked', 'lockedAt',
            'totalApproved', 'totalAll', 'totalRows', 'search'
        ));
    }

    // ─────────────────────────────────────────────
    // MANUAL ADD ROW
    // ─────────────────────────────────────────────

    public function trackerStore(Request $request)
    {
        $v = $request->validate([
            'year_month'    => 'required|date_format:Y-m',
            'expense_date'  => 'nullable|date',
            'emp_id'        => 'nullable|string|max:50',
            'employee_name' => 'required|string|max:200',
            'amount'        => 'required|numeric|min:0.01',
            'remarks'       => 'nullable|string|max:500',
        ]);

        ReimbursementTrackerRow::create([
            'year_month'    => $v['year_month'],
            'expense_date'  => $v['expense_date'] ?? null,
            'emp_id'        => $v['emp_id'] ? trim($v['emp_id']) : null,
            'employee_name' => trim($v['employee_name']),
            'amount'        => $v['amount'],
            'remarks'       => $v['remarks'] ?? null,
            'status'        => 'approved',
            'created_by'    => auth()->id(),
        ]);

        return redirect()->route('reimbursements.tracker', ['month' => $v['year_month']])
            ->with('success', 'Row added.');
    }

    // ─────────────────────────────────────────────
    // EXCEL IMPORT  (two-pass: validate all → insert all or reject all)
    // ─────────────────────────────────────────────

    public function trackerImport(Request $request)
    {
        $request->validate([
            'month' => 'required|date_format:Y-m',
            'file'  => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $month = $request->get('month');
        $path  = $request->file('file')->store('tmp');

        // ── Read cells (formula-safe + date-aware) ────────────────────────────
        try {
            $spreadsheet = IOFactory::load(Storage::path($path));
            $ws          = $spreadsheet->getActiveSheet();

            $highestRow      = $ws->getHighestDataRow();
            $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString(
                $ws->getHighestDataColumn()
            );

            $rows = [];
            for ($r = 1; $r <= $highestRow; $r++) {
                $rowData = [];
                for ($c = 1; $c <= $highestColIndex; $c++) {
                    $coord  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c) . $r;
                    $xlCell = $ws->cellExists($coord) ? $ws->getCell($coord) : null;

                    if ($xlCell === null) {
                        $rowData[] = null;
                        continue;
                    }

                    $value = ($xlCell->getDataType() === \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA)
                        ? $xlCell->getOldCalculatedValue()
                        : $xlCell->getValue();

                    // Auto-convert date-formatted numeric serials to Y-m-d
                    if (is_numeric($value) && \PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($xlCell)) {
                        try {
                            $value = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)
                                ->format('Y-m-d');
                        } catch (\Throwable) {}
                    }

                    $rowData[] = $value;
                }
                $rows[] = $rowData;
            }
        } catch (\PhpOffice\PhpSpreadsheet\Calculation\Exception) {
            $reader = IOFactory::createReaderForFile(Storage::path($path));
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load(Storage::path($path));
            $rows        = $spreadsheet->getActiveSheet()->toArray(null, false, false, false);
        } finally {
            Storage::delete($path);
        }

        if (empty($rows) || count($rows) < 2) {
            return redirect()->route('reimbursements.tracker', ['month' => $month])
                ->with('error', 'File is empty or has no data rows.');
        }

        // Sanitize: unresolved formula strings (start with '=') → null
        $clean = fn($v) => (is_string($v) && str_starts_with(ltrim((string) $v), '=')) ? null : $v;

        // ── Map headers (0-based) ─────────────────────────────────────────────
        $headerRow  = array_map(fn($v) => strtolower(trim((string) $v)), $rows[0]);
        $colDate    = array_search('date', $headerRow);
        $colEmpId   = array_search('emp id', $headerRow);
        $colEmpName = array_search('employee name', $headerRow);
        $colAmount  = array_search('amount', $headerRow);
        $colRemarks = array_search('remarks', $headerRow);
        $colImage   = array_search('image', $headerRow);

        if ($colEmpName === false || $colAmount === false) {
            return redirect()->route('reimbursements.tracker', ['month' => $month])
                ->with('error', 'Template mismatch: "Employee Name" and "Amount" columns are required.');
        }

        // Original display headers (for error file column labels)
        $displayHeaders = $rows[0];

        // Image filenames already in DB for this month — used for duplicate check
        $existingImages = ReimbursementTrackerRow::where('year_month', $month)
            ->whereNotNull('image_filename')
            ->pluck('image_filename')
            ->map(fn($f) => strtolower(trim($f)))
            ->flip()          // use as a hash-set: O(1) lookup
            ->toArray();

        // ── PASS 1 — validate every data row ─────────────────────────────────
        $validRows   = [];
        $errorRows   = [];
        $seenImages  = []; // track image names seen within this file (lower-cased)

        foreach (array_slice($rows, 1) as $i => $row) {
            $rowNum  = $i + 2;
            $empName = trim((string) ($clean($row[$colEmpName] ?? '') ?? ''));
            $amount  = $clean($row[$colAmount] ?? null);

            // Skip entirely blank rows (no error added)
            if ($empName === '' && ($amount === null || $amount === '')) {
                continue;
            }

            $errs = [];

            if ($empName === '') {
                $errs[] = 'Employee Name is required';
            }

            if ($amount === null || $amount === '' || !is_numeric($amount) || (float) $amount <= 0) {
                $errs[] = 'Amount must be a positive number';
            }

            // Image uniqueness
            $rawImage = $colImage !== false ? trim((string) ($clean($row[$colImage] ?? '') ?? '')) : '';
            if ($rawImage !== '') {
                $imageKey = strtolower($rawImage);
                if (isset($seenImages[$imageKey])) {
                    $errs[] = "Image \"{$rawImage}\" is duplicated in this file (first seen on row {$seenImages[$imageKey]})";
                } elseif (isset($existingImages[$imageKey])) {
                    $errs[] = "Image \"{$rawImage}\" already exists for {$month} in the database";
                } else {
                    $seenImages[$imageKey] = $rowNum;
                }
            }

            if (!empty($errs)) {
                $errorRows[] = ['row_number' => $rowNum, 'data' => $row, 'error' => implode('; ', $errs)];
                continue;
            }

            // Parse date
            $rawDate = $colDate !== false ? $clean($row[$colDate] ?? null) : null;
            $expDate = null;
            if ($rawDate !== null && $rawDate !== '') {
                $rawStr = trim((string) $rawDate);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawStr)) {
                    $expDate = $rawStr;
                } elseif (is_numeric($rawDate)) {
                    try { $expDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $rawDate)->format('Y-m-d'); } catch (\Throwable) {}
                } else {
                    try { $expDate = Carbon::parse($rawStr)->format('Y-m-d'); } catch (\Throwable) {}
                }
            }

            $rawEmpId = $colEmpId !== false ? $clean($row[$colEmpId] ?? null) : null;

            $validRows[] = [
                'year_month'     => $month,
                'expense_date'   => $expDate,
                'emp_id'         => $rawEmpId !== null ? trim((string) $rawEmpId) ?: null : null,
                'employee_name'  => $empName,
                'amount'         => (float) $amount,
                'remarks'        => $colRemarks !== false ? (trim((string) ($clean($row[$colRemarks] ?? '') ?? '')) ?: null) : null,
                'image_filename' => $rawImage ?: null,
                'status'         => 'approved',
                'created_by'     => auth()->id(),
            ];
        }

        // ── Any errors → generate error Excel, insert NOTHING ────────────────
        if (!empty($errorRows)) {
            $errorFile = $this->buildImportErrorFile($month, $errorRows, $displayHeaders);
            $count     = count($errorRows);
            return redirect()->route('reimbursements.tracker', ['month' => $month])
                ->with('error', "{$count} row(s) have validation errors. No data was imported. Download the error report to fix and re-upload.")
                ->with('error_file', basename($errorFile));
        }

        // ── PASS 2 — all rows valid: insert in a single transaction ───────────
        \Illuminate\Support\Facades\DB::transaction(function () use ($validRows) {
            foreach ($validRows as $row) {
                ReimbursementTrackerRow::create($row);
            }
        });

        $count = count($validRows);
        return redirect()->route('reimbursements.tracker', ['month' => $month])
            ->with('success', "{$count} rows imported successfully.");
    }

    // ─────────────────────────────────────────────
    // PRIVATE: build import error Excel file
    // ─────────────────────────────────────────────

    private function buildImportErrorFile(string $month, array $errorRows, array $displayHeaders): string
    {
        $wb = new Spreadsheet();
        $ws = $wb->getActiveSheet();
        $ws->setTitle('Errors');

        $colCount = count($displayHeaders);

        // Header row: original columns + Error column
        $ws->fromArray(array_merge(array_values($displayHeaders), ['Error']), null, 'A1');
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount + 1);

        $ws->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C0392B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $r = 2;
        foreach ($errorRows as $err) {
            for ($c = 0; $c < $colCount; $c++) {
                $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c + 1) . $r;
                $ws->getCell($coord)->setValue($err['data'][$c] ?? '');
            }
            $ws->getCell($lastCol . $r)->setValue($err['error']);

            $ws->getStyle("A{$r}:{$lastCol}{$r}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FDE8E8');

            $r++;
        }

        for ($c = 1; $c <= $colCount + 1; $c++) {
            $ws->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }

        $dir = storage_path('app/error_exports');
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $filename = sprintf('reimb_import_error_%s_%s.xlsx', str_replace('-', '_', $month), now()->format('Ymd_His'));
        (new Xlsx($wb))->save($dir . '/' . $filename);

        return $filename;
    }

    // ─────────────────────────────────────────────
    // PRIVATE: paginate a plain PHP array
    // ─────────────────────────────────────────────

    private function filterRows(array $rows, string $search): array
    {
        if ($search === '') return $rows;

        $lower = strtolower($search);
        return array_values(array_filter($rows, fn($r) =>
            str_contains(strtolower((string) ($r['employee_name'] ?? '')), $lower) ||
            str_contains(strtolower((string) ($r['emp_id']        ?? '')), $lower)
        ));
    }

    private function paginateArray(array $items, int $perPage = 25): \Illuminate\Pagination\LengthAwarePaginator
    {
        $page    = request()->get('page', 1);
        $offset  = ((int) $page - 1) * $perPage;
        $slice   = array_slice($items, $offset, $perPage);

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $slice,
            count($items),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    // ─────────────────────────────────────────────
    // TEMPLATE DOWNLOAD
    // ─────────────────────────────────────────────

    public function trackerTemplate()
    {
        $wb = new Spreadsheet();
        $ws = $wb->getActiveSheet();
        $ws->setTitle('Template');

        $headers = ['Date', 'Emp ID', 'Employee Name', 'Amount', 'Remarks', 'Image'];
        $ws->fromArray($headers, null, 'A1');

        $lastCol = count($headers);
        $range   = 'A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastCol) . '1';

        $ws->getStyle($range)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        foreach ([14, 14, 28, 14, 40, 28] as $ci => $w) {
            $ws->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 1))->setWidth($w);
        }

        // Sample row
        $ws->fromArray([now()->toDateString(), 'EMP001', 'John Doe', 1800, 'Petrol & Recharge', 'receipt_john_jan.jpg'], null, 'A2');

        $writer = new Xlsx($wb);
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'Reimbursement_Tracker_Template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ─────────────────────────────────────────────
    // BULK IMAGE UPLOAD
    // ─────────────────────────────────────────────

    public function trackerImageUpload(Request $request)
    {
        $request->validate([
            'month'    => 'required|date_format:Y-m',
            'images'   => 'required|array|min:1',
            'images.*' => 'required|file|mimes:jpg,jpeg,png,gif,webp,pdf|max:5120',
        ]);

        $month   = $request->get('month');
        $dir     = 'reimb-images/' . $month;
        $count   = 0;

        foreach ($request->file('images') as $file) {
            $originalName = $file->getClientOriginalName();
            // sanitize: keep only safe characters
            $safeName = preg_replace('/[^a-zA-Z0-9_.\-]/', '_', $originalName);
            Storage::disk('public')->putFileAs($dir, $file, $safeName);
            $count++;
        }

        return redirect()->route('reimbursements.tracker', ['month' => $month])
            ->with('success', "{$count} image(s) uploaded for {$month}.");
    }

    // ─────────────────────────────────────────────
    // VIEW A SINGLE IMAGE
    // ─────────────────────────────────────────────

    public function trackerImageView(Request $request, string $month, string $filename)
    {
        // Guard against path traversal
        $filename = basename($filename);
        $path     = 'reimb-images/' . $month . '/' . $filename;

        if (!Storage::disk('public')->exists($path)) {
            abort(404, 'Image not found.');
        }

        $mime = Storage::disk('public')->mimeType($path);
        return response()->file(Storage::disk('public')->path($path), ['Content-Type' => $mime]);
    }

    // ─────────────────────────────────────────────
    // UPDATE ROW STATUS
    // ─────────────────────────────────────────────

    public function trackerUpdateStatus(Request $request, ReimbursementTrackerRow $row)
    {
        $request->validate(['status' => 'required|in:approved,rejected']);

        $row->update(['status' => $request->status]);

        // Do NOT auto-regenerate the locked snapshot — the saved report remains frozen
        // until the user explicitly clicks "Re-save Report" on the report page.
        $hasSnapshot = ReimbursementReportSnapshot::where('year_month', $row->year_month)->exists();

        $msg = 'Status updated.';
        if ($hasSnapshot) {
            $msg .= ' The saved report for this month is unchanged — click Re-save Report to update it.';
        }

        return redirect()->route('reimbursements.tracker', ['month' => $row->year_month])
            ->with('success', $msg);
    }

    // ─────────────────────────────────────────────
    // DELETE ROW
    // ─────────────────────────────────────────────

    public function trackerDestroy(ReimbursementTrackerRow $row)
    {
        $month = $row->year_month;
        $row->delete();
        return redirect()->route('reimbursements.tracker', ['month' => $month])
            ->with('success', 'Row deleted.');
    }

    // ─────────────────────────────────────────────
    // EXPORT TABLE (all rows for month)
    // ─────────────────────────────────────────────

    public function trackerExport(Request $request)
    {
        $request->validate(['month' => 'required|date_format:Y-m']);
        $month = $request->get('month');

        $rows = ReimbursementTrackerRow::where('year_month', $month)
            ->orderBy('id')
            ->get();

        $carbonMonth = Carbon::createFromFormat('Y-m', $month);
        $filename    = 'Reimbursement_Tracker_' . $carbonMonth->format('M_Y') . '.xlsx';

        $wb = new Spreadsheet();
        $ws = $wb->getActiveSheet();
        $ws->setTitle($carbonMonth->format('M Y'));

        $headers = ['#', 'Date', 'Emp ID', 'Employee Name', 'Amount (₹)', 'Remarks', 'Image File', 'Status'];
        $ws->fromArray($headers, null, 'A1');

        $hRange = 'A1:H1';
        $ws->getStyle($hRange)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'AAAAAA']]],
        ]);

        foreach ($rows->values() as $i => $r) {
            $ws->fromArray([
                $i + 1,
                $r->expense_date ?? '',
                $r->emp_id ?? '',
                $r->employee_name,
                (float) $r->amount,
                $r->remarks ?? '',
                $r->image_filename ?? '',
                ucfirst($r->status),
            ], null, 'A' . ($i + 2));
        }

        foreach ([6, 14, 14, 32, 14, 40, 28, 12] as $ci => $w) {
            $ws->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 1))->setWidth($w);
        }

        $ws->getStyle('E2:E' . (count($rows) + 1))->getNumberFormat()->setFormatCode('#,##0.00');
        $ws->freezePane('A2');

        $writer = new Xlsx($wb);
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ─────────────────────────────────────────────
    // REPORT INDEX (working report page)
    // ─────────────────────────────────────────────

    public function reportIndex(Request $request)
    {
        $month  = $request->get('month', now()->format('Y-m'));
        $search = trim((string) $request->get('search', ''));

        // Snapshot metadata only — used for status display and button logic.
        // This page always shows LIVE approved rows, never the frozen snapshot.
        $snapshot = ReimbursementReportSnapshot::where('year_month', $month)->first();
        $isLocked = $snapshot !== null;
        $lockedAt = $snapshot?->locked_at;

        // Always query live approved rows from the tracker
        $liveQuery = ReimbursementTrackerRow::where('year_month', $month)
            ->where('status', 'approved')
            ->when($search !== '', fn($q) => $q->where(fn($q2) =>
                $q2->where('employee_name', 'like', "%{$search}%")
                   ->orWhere('emp_id', 'like', "%{$search}%")
            ))
            ->orderBy('id');

        $totalAmount = ReimbursementTrackerRow::where('year_month', $month)
            ->where('status', 'approved')
            ->sum('amount');

        $rows     = $liveQuery->paginate(25)->withQueryString();
        $rowCount = ReimbursementTrackerRow::where('year_month', $month)
            ->where('status', 'approved')
            ->count();

        return view('reimbursements.report', compact(
            'month', 'rows', 'isLocked', 'lockedAt', 'totalAmount', 'rowCount', 'search'
        ));
    }

    // ─────────────────────────────────────────────
    // GENERATE / LOCK REPORT
    // ─────────────────────────────────────────────

    public function reportGenerate(Request $request)
    {
        $request->validate(['month' => 'required|date_format:Y-m']);
        $month = $request->get('month');

        $snapshot = ReimbursementReportSnapshot::firstOrNew(['year_month' => $month]);
        $this->performLock($month, $snapshot);

        $count = $snapshot->row_count;
        return redirect()->route('reimbursements.report', ['month' => $month])
            ->with('success', "Report locked — {$count} approved rows frozen.");
    }

    // ─────────────────────────────────────────────
    // DOWNLOAD LOCKED REPORT AS EXCEL
    // ─────────────────────────────────────────────

    public function reportDownload(Request $request)
    {
        $request->validate(['month' => 'required|date_format:Y-m']);
        $month = $request->get('month');

        $snapshot = ReimbursementReportSnapshot::where('year_month', $month)->first();

        if ($snapshot) {
            $rows = $snapshot->snapshot_data;
        } else {
            $rows = ReimbursementTrackerRow::where('year_month', $month)
                ->where('status', 'approved')
                ->orderBy('id')
                ->get()
                ->map(fn($r) => [
                    'expense_date'   => $r->expense_date,
                    'emp_id'         => $r->emp_id,
                    'employee_name'  => $r->employee_name,
                    'amount'         => (float) $r->amount,
                    'remarks'        => $r->remarks,
                    'image_filename' => $r->image_filename,
                ])->toArray();
        }

        $carbonMonth = Carbon::createFromFormat('Y-m', $month);
        $filename    = 'Reimbursement_Report_' . $carbonMonth->format('M_Y') . '.xlsx';

        $wb = new Spreadsheet();
        $ws = $wb->getActiveSheet();
        $ws->setTitle($carbonMonth->format('M Y') . ' Report');

        $headers = ['#', 'Date', 'Emp ID', 'Employee Name', 'Amount (₹)', 'Remarks', 'Image File'];
        $ws->fromArray($headers, null, 'A1');

        $hRange = 'A1:G1';
        $ws->getStyle($hRange)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'AAAAAA']]],
        ]);

        foreach ($rows as $i => $r) {
            $ws->fromArray([
                $i + 1,
                $r['expense_date'] ?? '',
                $r['emp_id'] ?? '',
                $r['employee_name'] ?? '',
                (float) ($r['amount'] ?? 0),
                $r['remarks'] ?? '',
                $r['image_filename'] ?? '',
            ], null, 'A' . ($i + 2));
        }

        $lastRow = count($rows) + 1;
        if ($lastRow >= 2) {
            $ws->getStyle('E2:E' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
            $ws->getStyle('A2:G' . $lastRow)->applyFromArray([
                'font'    => ['size' => 9],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]],
            ]);
            for ($r = 2; $r <= $lastRow; $r++) {
                if ($r % 2 === 0) {
                    $ws->getStyle("A{$r}:G{$r}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F5F7FA');
                }
            }
        }

        foreach ([6, 14, 14, 32, 14, 44, 28] as $ci => $w) {
            $ws->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 1))->setWidth($w);
        }
        $ws->freezePane('A2');

        $writer = new Xlsx($wb);
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ─────────────────────────────────────────────
    // UNLOCK REPORT (delete snapshot)
    // ─────────────────────────────────────────────

    public function reportUnlock(Request $request)
    {
        $request->validate(['month' => 'required|date_format:Y-m']);
        $month = $request->get('month');

        ReimbursementReportSnapshot::where('year_month', $month)->delete();

        return redirect()->route('reimbursements.report', ['month' => $month])
            ->with('success', 'Report unlocked — snapshot removed. Data will now reflect live approved rows.');
    }

    // ─────────────────────────────────────────────
    // DELETE SNAPSHOT (from saved reports page)
    // ─────────────────────────────────────────────

    public function reportDelete(Request $request)
    {
        $request->validate(['month' => 'required|date_format:Y-m']);
        $month = $request->get('month');
        $label = Carbon::createFromFormat('Y-m', $month)->format('F Y');

        ReimbursementReportSnapshot::where('year_month', $month)->delete();

        return redirect()->route('reimbursements.report.saved')
            ->with('success', "Snapshot for {$label} deleted.");
    }

    // ─────────────────────────────────────────────
    // SAVED REPORTS LIST
    // ─────────────────────────────────────────────

    public function reportSaved()
    {
        $snapshots = ReimbursementReportSnapshot::orderByDesc('year_month')->get();

        $userIds = $snapshots->pluck('locked_by')->filter()->unique()->all();
        $users   = \App\Models\User::whereIn('id', $userIds)->pluck('name', 'id');

        return view('reimbursements.report-saved', compact('snapshots', 'users'));
    }

    // ─────────────────────────────────────────────
    // VIEW LOCKED REPORT (read-only)
    // ─────────────────────────────────────────────

    public function reportView(string $month)
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            return redirect()->route('reimbursements.report.saved')->with('error', 'Invalid month format.');
        }

        $snapshot = ReimbursementReportSnapshot::where('year_month', $month)->first();

        if (!$snapshot) {
            return redirect()->route('reimbursements.report.saved')
                ->with('error', 'No locked report found for ' . $month . '.');
        }

        $search      = trim((string) request()->get('search', ''));
        $allRows     = $snapshot->snapshot_data ?? [];
        $lockedAt    = $snapshot->locked_at;
        $totalAmount = $snapshot->total_amount;
        $rowCount    = $snapshot->row_count;

        $filteredRows = $this->filterRows($allRows, $search);
        $rows         = $this->paginateArray($filteredRows, 25);

        return view('reimbursements.report-view', compact(
            'month', 'rows', 'lockedAt', 'totalAmount', 'rowCount', 'search'
        ));
    }

    // ─────────────────────────────────────────────
    // PRIVATE: build + save snapshot
    // ─────────────────────────────────────────────

    private function performLock(string $yearMonth, ReimbursementReportSnapshot $snapshot): void
    {
        $approvedRows = ReimbursementTrackerRow::where('year_month', $yearMonth)
            ->where('status', 'approved')
            ->orderBy('id')
            ->get()
            ->map(fn($r) => [
                'id'             => $r->id,
                'expense_date'   => $r->expense_date,
                'emp_id'         => $r->emp_id,
                'employee_name'  => $r->employee_name,
                'amount'         => (float) $r->amount,
                'remarks'        => $r->remarks,
                'image_filename' => $r->image_filename,
                'status'         => $r->status,
            ])->toArray();

        $total = array_sum(array_column($approvedRows, 'amount'));

        $snapshot->year_month    = $yearMonth;
        $snapshot->snapshot_data = $approvedRows;
        $snapshot->row_count     = count($approvedRows);
        $snapshot->total_amount  = $total;
        $snapshot->locked_at     = now();
        $snapshot->locked_by     = auth()->id();
        $snapshot->save();
    }
}
