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
        $this->authorize('reimbursment_tab');

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
        $this->authorize('reimbursment_add');

        $v = $request->validate([
            'year_month'           => 'required|date_format:Y-m',
            'expense_date'         => 'nullable|date',
            'emp_id'               => 'nullable|string|max:50',
            'employee_name'        => 'required|string|max:200',
            'amount'               => 'required|numeric|min:0.01',
            'category'             => 'nullable|string|in:' . implode(',', ReimbursementTrackerRow::CATEGORIES),
            'other_category_text'  => 'required_if:category,Other|nullable|string|max:150',
            'remarks'              => 'nullable|string|max:500',
        ]);

        ReimbursementTrackerRow::create([
            'year_month'            => $v['year_month'],
            'expense_date'          => $v['expense_date'] ?? null,
            'emp_id'                => $v['emp_id'] ? trim($v['emp_id']) : null,
            'employee_name'         => trim($v['employee_name']),
            'amount'                => $v['amount'],
            'category'              => $v['category'] ?? null,
            'other_category_text'   => ($v['category'] ?? null) === 'Other' ? ($v['other_category_text'] ?? null) : null,
            'remarks'               => $v['remarks'] ?? null,
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
        $this->authorize('reimbursment_add');

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
        $colCategory = array_search('category', $headerRow);
        $colOtherCategory = array_search('other category', $headerRow);
        $colRemarks = array_search('remarks', $headerRow);
        $colImage   = array_search('image', $headerRow);

        // These only appear in a re-uploaded Export file (see trackerExport()),
        // never in the blank create-only Template — present together with a
        // non-blank ID column, they turn a row into an UPDATE of an existing
        // row instead of a new INSERT, so HR can bulk-edit (approve/reject,
        // record payment, fix a typo) many rows at once instead of one by one.
        $colId               = array_search('id', $headerRow);
        $colApprovalStatus   = array_search('approval status', $headerRow);
        $colApprovalRemarks  = array_search('approval remarks', $headerRow);
        $colPaymentStatus    = array_search('payment status', $headerRow);
        $colPaymentDate      = array_search('payment date', $headerRow);
        $colPaymentReference = array_search('payment reference', $headerRow);
        $colPaidAmount       = array_search('paid amount', $headerRow);

        // Case-insensitive lookup so "travel & conveyance" still matches
        // the canonical "Travel & Conveyance" from CATEGORIES.
        $categoryByLower = collect(ReimbursementTrackerRow::CATEGORIES)
            ->mapWithKeys(fn($c) => [strtolower($c) => $c]);

        if ($colEmpName === false || $colAmount === false) {
            return redirect()->route('reimbursements.tracker', ['month' => $month])
                ->with('error', 'Template mismatch: "Employee Name" and "Amount" columns are required.');
        }

        // Original display headers (for error file column labels)
        $displayHeaders = $rows[0];

        // Image filenames already in DB for this month — used for duplicate
        // check. Keyed to the owning row id (not just a boolean) so an update
        // row re-uploading its OWN existing filename unchanged isn't flagged
        // as a clash against itself.
        $existingImages = ReimbursementTrackerRow::where('year_month', $month)
            ->whereNotNull('image_filename')
            ->get(['id', 'image_filename'])
            ->keyBy(fn($r) => strtolower(trim($r->image_filename)))
            ->map(fn($r) => $r->id);

        // ── PASS 1 — validate every data row ─────────────────────────────────
        $validRows   = []; // new rows to INSERT (no ID column / blank ID)
        $updateRows  = []; // existing rows to UPDATE (ID column present & found)
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

            // ── ID column present & non-blank → this row updates an existing
            // record instead of creating a new one (round-trip bulk edit). ──
            $rawId = $colId !== false ? trim((string) ($clean($row[$colId] ?? '') ?? '')) : '';
            $existingRow = null;
            if ($rawId !== '') {
                if (!ctype_digit($rawId) || !($existingRow = ReimbursementTrackerRow::find((int) $rawId))) {
                    $errorRows[] = ['row_number' => $rowNum, 'data' => $row, 'error' => "Row ID \"{$rawId}\" was not found — it may have been deleted. Leave ID blank to add this as a new row instead."];
                    continue;
                }
            }

            // Pre-parse the date early so it can be used in image name suggestions
            $rawDateEarly = $colDate !== false ? $clean($row[$colDate] ?? null) : null;
            $expDateEarly = null;
            if ($rawDateEarly !== null && $rawDateEarly !== '') {
                $s = trim((string) $rawDateEarly);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
                    $expDateEarly = $s;
                } elseif (is_numeric($rawDateEarly)) {
                    try { $expDateEarly = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$rawDateEarly)->format('Y-m-d'); } catch (\Throwable) {}
                } else {
                    try { $expDateEarly = \Carbon\Carbon::parse($s)->format('Y-m-d'); } catch (\Throwable) {}
                }
            }
            // Image filenames use date-time of upload for uniqueness (not the expense date from the template).
            $dateLabelForSuggestion = now()->format('d-m-Y_H-i');

            $errs = [];

            if ($empName === '') {
                $errs[] = 'Employee Name is required';
            }

            if ($amount === null || $amount === '' || !is_numeric($amount) || (float) $amount <= 0) {
                $errs[] = 'Amount must be a positive number';
            }

            // ── Category validation (optional column — blank is fine) ──────────
            $rawCategory = $colCategory !== false ? trim((string) ($clean($row[$colCategory] ?? '') ?? '')) : '';
            $resolvedCategory = null;
            if ($rawCategory !== '') {
                $resolvedCategory = $categoryByLower->get(strtolower($rawCategory));
                if ($resolvedCategory === null) {
                    $errs[] = "Category \"{$rawCategory}\" is not in the allowed list — pick one from the Category column's dropdown";
                }
            }

            $rawOtherCategory = $colOtherCategory !== false ? trim((string) ($clean($row[$colOtherCategory] ?? '') ?? '')) : '';
            if ($resolvedCategory === 'Other' && $rawOtherCategory === '') {
                $errs[] = 'Other Category is required when Category is "Other"';
            }

            // ── Payment Status / Paid Amount (only meaningful on an update row,
            // but validated whenever the columns are filled in at all) ────────
            $rawPaymentStatus = $colPaymentStatus !== false ? strtolower(trim((string) ($clean($row[$colPaymentStatus] ?? '') ?? ''))) : '';
            $resolvedPaymentStatus = null;
            if ($rawPaymentStatus !== '') {
                if (!in_array($rawPaymentStatus, ['unpaid', 'paid', 'partial'], true)) {
                    $errs[] = "Payment Status \"{$rawPaymentStatus}\" must be Unpaid, Paid, or Partial";
                } else {
                    $resolvedPaymentStatus = $rawPaymentStatus;
                }
            }
            $rawPaidAmount = $colPaidAmount !== false ? $clean($row[$colPaidAmount] ?? null) : null;
            if ($resolvedPaymentStatus === 'partial'
                && (!is_numeric($rawPaidAmount) || (float) $rawPaidAmount <= 0 || (float) $rawPaidAmount >= (float) $amount)) {
                $errs[] = 'Paid Amount must be a positive number less than Amount when Payment Status is Partial';
            }

            // ── Approval Status (update rows only — ignored for new rows,
            // which are always created pre-approved like manual Add Reimbursement) ──
            $rawApprovalStatus = $colApprovalStatus !== false ? strtolower(trim((string) ($clean($row[$colApprovalStatus] ?? '') ?? ''))) : '';
            $resolvedApprovalStatus = null;
            if ($rawApprovalStatus !== '') {
                if (!in_array($rawApprovalStatus, ['pending', 'approved', 'rejected'], true)) {
                    $errs[] = "Approval Status \"{$rawApprovalStatus}\" must be Pending, Approved, or Rejected";
                } else {
                    $resolvedApprovalStatus = $rawApprovalStatus;
                }
            }

            // ── Image filename validation ─────────────────────────────────────
            // The Export file puts a clickable URL (not a filename) in this
            // column — re-uploading it unchanged should leave the existing
            // attachment alone, not get validated/stored as a bogus filename.
            $rawImage = $colImage !== false ? trim((string) ($clean($row[$colImage] ?? '') ?? '')) : '';
            $imageIsUrl = (bool) preg_match('#^https?://#i', $rawImage);
            if ($imageIsUrl) {
                $rawImage = '';
            }
            if ($rawImage !== '') {

                // 1. No spaces allowed — use underscores (e.g. VADHER_NEHA_DIPAKBHAI_03-06-2026)
                if (preg_match('/\s/', $rawImage)) {
                    $suggested = preg_replace('/\s+/', '_', strtoupper(trim($rawImage)));
                    $errs[] = "Image filename \"{$rawImage}\" must not contain spaces. "
                            . "Use underscores instead (e.g. \"{$suggested}\")";
                } else {
                    // 2. Filename must include the employee name (any part of it, underscore-separated)
                    //    and a date segment so different records for the same employee are distinguishable.
                    $imageUpper = strtoupper($rawImage);

                    // Break employee name into parts and check at least one part exists in the filename
                    $nameParts = array_filter(
                        preg_split('/\s+/', strtoupper(trim($empName))),
                        fn($p) => strlen($p) >= 3   // skip very short parts like initials
                    );

                    $nameFound = false;
                    foreach ($nameParts as $part) {
                        if (str_contains($imageUpper, $part)) {
                            $nameFound = true;
                            break;
                        }
                    }

                    if (!$nameFound && !empty($nameParts)) {
                        // Also accept emp_id as an alternative to name
                        $empIdRaw = $colEmpId !== false ? trim((string) ($clean($row[$colEmpId] ?? '') ?? '')) : '';
                        $nameFound = $empIdRaw !== '' && str_contains($imageUpper, strtoupper($empIdRaw));
                    }

                    // Build example once — used in both error messages below.
                    // Extension is intentionally omitted from the pattern; any extension is accepted
                    // (.jpg, .jpeg, .png, .pdf, .webp, etc.).
                    $exampleBase = strtoupper(preg_replace('/\s+/', '_', trim($empName)))
                                 . '_' . $dateLabelForSuggestion;

                    if (!$nameFound && !empty($nameParts)) {
                        $errs[] = "Image filename \"{$rawImage}\" must contain the employee name. "
                                . "Format: EMPLOYEE_NAME_DD-MM-YYYY_HH-MM.<ext> "
                                . "(e.g. \"{$exampleBase}.jpg\" or \"{$exampleBase}.png\" etc.)";
                    }

                    // 3. Must contain a date-TIME pattern so that multiple records for the same
                    //    employee are always unique (date alone is not enough).
                    //    Accepted formats (any file extension is fine):
                    //      DD-MM-YYYY_HH-MM        e.g. 04-06-2026_10-30
                    //      DD-MM-YYYY_HH-MM-SS     e.g. 04-06-2026_10-30-45
                    //      YYYY-MM-DD_HH-MM        e.g. 2026-06-04_10-30
                    //      YYYYMMDD_HHMMSS         e.g. 20260604_103045
                    //      YYYYMMDDHHMMSS          e.g. 20260604103045
                    $hasDateTime = (bool) preg_match(
                        '/\d{2}[-_]\d{2}[-_]\d{4}[-_T]\d{2}[-:_]\d{2}'   // DD-MM-YYYY_HH-MM or variants
                        . '|\d{4}[-_]\d{2}[-_]\d{2}[-_T]\d{2}[-:_]\d{2}' // YYYY-MM-DD_HH-MM
                        . '|\d{8}[-_]\d{4,6}'                              // YYYYMMDD_HHMM or YYYYMMDD_HHMMSS
                        . '|\d{14}/',                                       // YYYYMMDDHHmmSS
                        $rawImage
                    );
                    if (!$hasDateTime) {
                        $errs[] = "Image filename \"{$rawImage}\" must include a date AND time "
                                . "(e.g. \"{$exampleBase}.jpg\") — any extension is fine (.jpg/.png/.pdf etc.), "
                                . "but date alone is not enough to keep multiple records for the same employee unique";
                    }

                    // 4. Uniqueness — within this file and against the database.
                    // An update row re-stating its OWN current filename unchanged
                    // doesn't count as a clash against itself.
                    if (empty($errs)) {      // only check uniqueness if format is valid
                        $imageKey = strtolower($rawImage);
                        $ownsExistingImage = $existingRow && (int) ($existingImages[$imageKey] ?? 0) === $existingRow->id;
                        if (isset($seenImages[$imageKey])) {
                            $errs[] = "Image \"{$rawImage}\" is duplicated in this file "
                                    . "(first seen on row {$seenImages[$imageKey]})";
                        } elseif (isset($existingImages[$imageKey]) && !$ownsExistingImage) {
                            $errs[] = "Image \"{$rawImage}\" already exists for {$month} in the database";
                        } else {
                            $seenImages[$imageKey] = $rowNum;
                        }
                    }
                }
            }

            // ── Lock-down: only Payment Status/Date/Reference/Paid Amount may
            // be changed via this sheet (see trackerExport(), which protects
            // every other column in Excel) — this is the server-side
            // backstop for someone unprotecting the sheet or editing it with
            // a different tool. Any other column coming back different from
            // the current DB value rejects the whole import with an error,
            // same as any other validation failure here. New rows ($rawId
            // blank, $existingRow null) are unaffected — this only applies
            // once a row is identified as an update to an existing one.
            if ($existingRow) {
                $immutableChanges = [];

                $existingDateStr = $existingRow->expense_date
                    ? (is_string($existingRow->expense_date) ? $existingRow->expense_date : $existingRow->expense_date->toDateString())
                    : null;
                if (($expDateEarly ?: null) !== $existingDateStr) {
                    $immutableChanges[] = 'Date';
                }

                $rawEmpIdCheck = $colEmpId !== false ? trim((string) ($clean($row[$colEmpId] ?? '') ?? '')) : ($existingRow->emp_id ?? '');
                if ($rawEmpIdCheck !== (string) ($existingRow->emp_id ?? '')) {
                    $immutableChanges[] = 'Emp ID';
                }

                if ($empName !== $existingRow->employee_name) {
                    $immutableChanges[] = 'Employee Name';
                }

                if (is_numeric($amount) && abs((float) $amount - (float) $existingRow->amount) > 0.001) {
                    $immutableChanges[] = 'Amount';
                }

                if (($resolvedCategory ?: null) !== ($existingRow->category ?: null)) {
                    $immutableChanges[] = 'Category';
                }

                if (($rawOtherCategory ?: null) !== ($existingRow->other_category_text ?: null)) {
                    $immutableChanges[] = 'Other Category';
                }

                $rawRemarksCheck = $colRemarks !== false
                    ? (trim((string) ($clean($row[$colRemarks] ?? '') ?? '')) ?: null)
                    : $existingRow->remarks;
                if ($rawRemarksCheck !== $existingRow->remarks) {
                    $immutableChanges[] = 'Remarks';
                }

                if (!$imageIsUrl && $rawImage !== '' && $rawImage !== $existingRow->image_filename) {
                    $immutableChanges[] = 'Image';
                }

                if ($rawApprovalStatus !== '' && $resolvedApprovalStatus !== $existingRow->status) {
                    $immutableChanges[] = 'Approval Status';
                }

                $rawApprovalRemarksCheck = $colApprovalRemarks !== false
                    ? (trim((string) ($clean($row[$colApprovalRemarks] ?? '') ?? '')) ?: null)
                    : $existingRow->approval_remarks;
                if ($rawApprovalRemarksCheck !== $existingRow->approval_remarks) {
                    $immutableChanges[] = 'Approval Remarks';
                }

                if (!empty($immutableChanges)) {
                    $errs[] = 'Only Payment Status, Payment Date, Payment Reference, and Paid Amount can be edited in this sheet — '
                        . implode(', ', $immutableChanges) . ' ' . (count($immutableChanges) > 1 ? 'were' : 'was') . ' changed from the current record.';
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

            $commonData = [
                'expense_date'         => $expDate,
                'emp_id'               => $rawEmpId !== null ? trim((string) $rawEmpId) ?: null : null,
                'employee_name'        => $empName,
                'amount'               => (float) $amount,
                'category'             => $resolvedCategory,
                'other_category_text'  => $resolvedCategory === 'Other' ? $rawOtherCategory : null,
                'remarks'              => $colRemarks !== false ? (trim((string) ($clean($row[$colRemarks] ?? '') ?? '')) ?: null) : null,
            ];

            if ($existingRow) {
                // Update row — only touch the image if a non-blank filename was given,
                // so leaving the cell blank doesn't wipe out an already-attached receipt.
                $updateData = $commonData;
                if ($rawImage !== '') {
                    $updateData['image_filename'] = $rawImage;
                }
                if ($resolvedApprovalStatus !== null) {
                    $updateData['status'] = $resolvedApprovalStatus;
                }
                if ($colApprovalRemarks !== false) {
                    $updateData['approval_remarks'] = trim((string) ($clean($row[$colApprovalRemarks] ?? '') ?? '')) ?: null;
                }

                $payment = null;
                if ($resolvedPaymentStatus !== null) {
                    $rawPaymentDate = $colPaymentDate !== false ? $clean($row[$colPaymentDate] ?? null) : null;
                    $payment = [
                        'status'      => $resolvedPaymentStatus,
                        'date'        => $rawPaymentDate ? (string) $rawPaymentDate : null,
                        'reference'   => $colPaymentReference !== false ? (trim((string) ($clean($row[$colPaymentReference] ?? '') ?? '')) ?: null) : null,
                        'paid_amount' => $resolvedPaymentStatus === 'partial' ? (float) $rawPaidAmount : null,
                    ];
                }

                $updateRows[] = ['row' => $existingRow, 'data' => $updateData, 'payment' => $payment];
            } else {
                $validRows[] = array_merge($commonData, [
                    'year_month'     => $month,
                    'image_filename' => $rawImage ?: null,
                    'status'         => 'approved',
                    'created_by'     => auth()->id(),
                ]);
            }
        }

        // ── Any errors → generate error Excel, change NOTHING ────────────────
        if (!empty($errorRows)) {
            $errorFile = $this->buildImportErrorFile($month, $errorRows, $displayHeaders);
            $count     = count($errorRows);
            return redirect()->route('reimbursements.tracker', ['month' => $month])
                ->with('error', "{$count} row(s) have validation errors. No data was imported or updated. Download the error report to fix and re-upload.")
                ->with('error_file', basename($errorFile));
        }

        // ── PASS 2 — all rows valid: create + update in a single transaction ──
        $actorId = auth()->id();
        \Illuminate\Support\Facades\DB::transaction(function () use ($validRows, $updateRows, $actorId) {
            foreach ($validRows as $row) {
                ReimbursementTrackerRow::create($row);
            }
            foreach ($updateRows as $u) {
                $u['row']->update($u['data']);
                if ($u['payment'] !== null) {
                    $u['row']->applyPayment(
                        $u['payment']['status'],
                        $u['payment']['date'],
                        $u['payment']['reference'],
                        $u['payment']['paid_amount'],
                        $actorId
                    );
                }
            }
        });

        $parts = [];
        if (count($validRows))  $parts[] = count($validRows) . ' row(s) added';
        if (count($updateRows)) $parts[] = count($updateRows) . ' row(s) updated';

        return redirect()->route('reimbursements.tracker', ['month' => $month])
            ->with('success', implode(', ', $parts) . '.');
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
        $this->authorize('reimbursment_tab');

        $wb = new Spreadsheet();
        $wb->removeSheetByIndex(0); // drop the blank default sheet — Lists + Template replace it below

        // ── Hidden "Lists" sheet — holds the fixed Category list so the Data
        // sheet's dropdown can reference a range (an inline comma list breaks
        // past 255 chars, and this list is well over that). ────────────────
        $listSheet = $wb->createSheet(0);
        $listSheet->setTitle('Lists');
        foreach (ReimbursementTrackerRow::CATEGORIES as $i => $cat) {
            $listSheet->getCell('A' . ($i + 1))->setValue($cat);
        }
        $listSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);
        $categoryListFormula = 'Lists!$A$1:$A$' . count(ReimbursementTrackerRow::CATEGORIES);

        $ws = $wb->createSheet(1);
        $wb->setActiveSheetIndex(1);
        $ws->setTitle('Template');

        $headers = ['Date', 'Emp ID', 'Employee Name', 'Amount', 'Category', 'Other Category', 'Remarks', 'Image'];
        $ws->fromArray($headers, null, 'A1');

        $lastCol = count($headers);
        $range   = 'A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastCol) . '1';

        $ws->getStyle($range)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        foreach ([14, 14, 28, 14, 28, 32, 40, 28] as $ci => $w) {
            $ws->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 1))->setWidth($w);
        }

        // Category column (E) — dropdown of the fixed list, incl. "Other".
        $validation = new \PhpOffice\PhpSpreadsheet\Cell\DataValidation();
        $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_WARNING);
        $validation->setAllowBlank(true);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setShowDropDown(true);
        $validation->setPromptTitle('Select a category');
        $validation->setPrompt('Choose from the list. Pick "Other" if nothing fits.');
        $validation->setFormula1($categoryListFormula);
        $ws->setDataValidation('E2:E1000', $validation);

        // Other Category column (F) — only used, and required, when Category is "Other".
        $ws->getComment('F1')->getText()->createTextRun(
            'Fill this in only if you picked "Other" as the Category — say what the expense actually was.'
        );

        // Sample row — image name: NAME_DD-MM-YYYY_HH-MM.<any-ext> — no spaces, includes datetime for uniqueness
        $ws->fromArray([now()->toDateString(), 'EMP001', 'John Doe', 1800, 'Travel & Conveyance', '', 'Petrol & Recharge', 'JOHN_DOE_' . now()->format('d-m-Y_H-i') . '.png'], null, 'A2');

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
        $this->authorize('reimbursment_add');

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
        $this->authorize('reimbursment_tab');

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
        $this->authorize('reimbursment_edit');

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
        $this->authorize('reimbursment_del');

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
        $this->authorize('reimbursment_tab');

        $request->validate(['month' => 'required|date_format:Y-m']);
        $month = $request->get('month');

        $rows = ReimbursementTrackerRow::where('year_month', $month)
            ->orderBy('id')
            ->get();

        $carbonMonth = Carbon::createFromFormat('Y-m', $month);
        $filename    = 'Reimbursement_Tracker_' . $carbonMonth->format('M_Y') . '.xlsx';

        $wb = new Spreadsheet();
        $wb->removeSheetByIndex(0); // drop the blank default sheet — Lists + Data replace it below

        // ── Hidden "Lists" sheet — same Category dropdown as the blank
        // Template, so HR can fix a wrong/missing category directly in the
        // exported file (picking from the list, not free-typing it) before
        // re-uploading via Import to bulk-update these rows. ───────────────
        $listSheet = $wb->createSheet(0);
        $listSheet->setTitle('Lists');
        foreach (ReimbursementTrackerRow::CATEGORIES as $i => $cat) {
            $listSheet->getCell('A' . ($i + 1))->setValue($cat);
        }
        $listSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);
        $categoryListFormula = 'Lists!$A$1:$A$' . count(ReimbursementTrackerRow::CATEGORIES);

        $ws = $wb->createSheet(1);
        $wb->setActiveSheetIndex(1);
        $ws->setTitle($carbonMonth->format('M Y'));

        // Same header names trackerImport() looks for (case-insensitive), so
        // this file — edited in Excel — can be re-uploaded via the same
        // Import button to bulk-update these rows (matched by ID) instead of
        // HR editing each one by hand. The blank "Template" download (for
        // brand-new rows) intentionally omits ID/Approval/Payment columns.
        // "Image" holds a clickable URL to the attached file (when there is
        // one) rather than the raw filename — trackerImport() recognises a
        // URL in this column on re-upload and leaves the existing attachment
        // untouched instead of trying to validate it as a new filename.
        $headers = [
            'ID', 'Date', 'Emp ID', 'Employee Name', 'Amount', 'Category', 'Other Category',
            'Remarks', 'Image', 'Approval Status', 'Approval Remarks',
            'Payment Status', 'Payment Date', 'Payment Reference', 'Paid Amount',
        ];
        $ws->fromArray($headers, null, 'A1');

        $lastCol = count($headers);
        $hRange  = 'A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastCol) . '1';
        $ws->getStyle($hRange)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'AAAAAA']]],
        ]);

        // Category column (F) — same dropdown as the blank Template.
        $validation = new \PhpOffice\PhpSpreadsheet\Cell\DataValidation();
        $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_WARNING);
        $validation->setAllowBlank(true);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setShowDropDown(true);
        $validation->setPromptTitle('Select a category');
        $validation->setPrompt('Choose from the list. Pick "Other" if nothing fits.');
        $validation->setFormula1($categoryListFormula);
        $ws->setDataValidation('F2:F1000', $validation);

        foreach ($rows->values() as $i => $r) {
            $imageUrl = null;
            if ($r->image_filename) {
                $imageUrl = route('reimbursements.tracker.image.view', ['month' => $r->year_month, 'filename' => $r->image_filename]);
            } elseif ($r->document_path) {
                $imageUrl = route('reimbursements.tracker.document', $r);
            }

            $rowNum = $i + 2;
            $ws->fromArray([
                $r->id,
                $r->expense_date ?? '',
                $r->emp_id ?? '',
                $r->employee_name,
                (float) $r->amount,
                $r->category ?? '',
                $r->other_category_text ?? '',
                $r->remarks ?? '',
                $imageUrl ?? '',
                ucfirst($r->status),
                $r->approval_remarks ?? '',
                $r->status === 'approved' ? ucfirst($r->payment_status) : '',
                $r->payment_date?->toDateString() ?? '',
                $r->payment_reference ?? '',
                $r->payment_status === 'partial' ? (float) $r->paid_amount : '',
            ], null, 'A' . $rowNum);

            if ($imageUrl) {
                $cell = $ws->getCell('I' . $rowNum);
                $cell->getHyperlink()->setUrl($imageUrl);
                $cell->getStyle()->getFont()->setUnderline(true)->getColor()->setRGB('2563EB');
            }
        }

        foreach ([8, 14, 14, 32, 14, 28, 32, 40, 40, 14, 30, 14, 14, 20, 14] as $ci => $w) {
            $ws->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 1))->setWidth($w);
        }

        $ws->getStyle('E2:E' . (count($rows) + 1))->getNumberFormat()->setFormatCode('#,##0.00');
        $ws->getStyle('O2:O' . (count($rows) + 1))->getNumberFormat()->setFormatCode('#,##0.00');
        $ws->freezePane('B2');

        // ── Lock every column except Payment Status/Date/Reference/Paid
        // Amount (L-O) — those four are the only fields HR should edit in
        // this sheet before re-uploading. All cells are locked by default;
        // sheet protection only takes effect once enabled below, and these
        // four columns are explicitly unlocked first. No password — this is
        // a guardrail against accidental edits, not a security boundary
        // (trackerImport() is the real enforcement, rejecting any row where
        // a locked column came back changed anyway).
        $protectionRange = 'L2:O' . max(count($rows) + 1, 1000);
        $ws->getStyle($protectionRange)->getProtection()
            ->setLocked(\PhpOffice\PhpSpreadsheet\Style\Protection::PROTECTION_UNPROTECTED);
        $ws->getProtection()->setSheet(true);

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
        $this->authorize('reimb_report');

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

        // amount is encrypted at rest (see ReimbursementTrackerRow's
        // EncryptedDecimal cast) — SQL can't SUM() ciphertext, so this has
        // to fetch rows and total them in PHP, where Eloquent decrypts each
        // one on access.
        $totalAmount = ReimbursementTrackerRow::where('year_month', $month)
            ->where('status', 'approved')
            ->get(['amount'])
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
        $this->authorize('reimb_report');

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
        $this->authorize('reimb_report');

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
                    'category'       => $r->category,
                    'remarks'        => $r->remarks,
                    'image_filename' => $r->image_filename,
                ])->toArray();
        }

        $carbonMonth = Carbon::createFromFormat('Y-m', $month);
        $filename    = 'Reimbursement_Report_' . $carbonMonth->format('M_Y') . '.xlsx';

        $wb = new Spreadsheet();
        $ws = $wb->getActiveSheet();
        $ws->setTitle($carbonMonth->format('M Y') . ' Report');

        $headers = ['#', 'Date', 'Emp ID', 'Employee Name', 'Amount (₹)', 'Category', 'Remarks', 'Image File'];
        $ws->fromArray($headers, null, 'A1');

        $hRange = 'A1:H1';
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
                $r['category'] ?? '',
                $r['remarks'] ?? '',
                $r['image_filename'] ?? '',
            ], null, 'A' . ($i + 2));
        }

        $lastRow = count($rows) + 1;
        if ($lastRow >= 2) {
            $ws->getStyle('E2:E' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
            $ws->getStyle('A2:H' . $lastRow)->applyFromArray([
                'font'    => ['size' => 9],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]],
            ]);
            for ($r = 2; $r <= $lastRow; $r++) {
                if ($r % 2 === 0) {
                    $ws->getStyle("A{$r}:H{$r}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F5F7FA');
                }
            }
        }

        foreach ([6, 14, 14, 32, 14, 26, 44, 28] as $ci => $w) {
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
        $this->authorize('reimb_report');

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
        $this->authorize('reimb_report');

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
        $this->authorize('reimb_report');

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
        $this->authorize('reimb_report');

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
                'category'       => $r->category,
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
