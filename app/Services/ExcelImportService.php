<?php

namespace App\Services;

use App\Models\ExcelUploadLog;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelImportService
{
    protected array $errors = [];
    protected array $warnings = [];
    protected array $errorRows = [];
    protected ?string $errorFile = null;
    protected int $successRows = 0;
    protected int $totalRows = 0;

    /** Rows skipped with a reportable reason (e.g. EMP ID not found) */
    protected array $reportableSkips = [];
    protected ?string $skippedFile = null;
    protected int $skippedCount = 0;

    public function import(
        UploadedFile $file,
        string $module,
        string $importerClass,
        array $meta = []
    ): array {
        $this->resetImportState();

        $headers = [];
        $rows = [];

        $filePath = $file->store("uploads/excel/{$module}", 'local');
        $log = ExcelUploadLog::create([
            'company_id' => $meta['company_id'] ?? null,
            'user_id' => auth()->id(),
            'module' => $module,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $filePath,
            'status' => 'processing',
            'meta' => $meta,
        ]);

        try {
            $spreadsheet = IOFactory::load(storage_path("app/{$filePath}"));

            // Allow callers to request a specific sheet by name via meta['sheet_name'].
            // If the file has only ONE sheet, skip the name check — the user uploaded a
            // simple file (e.g. our own template) so just use it and let header validation
            // catch any mismatch.
            if (!empty($meta['sheet_name']) && $spreadsheet->getSheetCount() > 1) {
                $sheet = $spreadsheet->getSheetByName($meta['sheet_name']);
                if (!$sheet) {
                    $available = implode(', ', $spreadsheet->getSheetNames());
                    throw new \RuntimeException(
                        "Sheet \"{$meta['sheet_name']}\" not found in the uploaded file. " .
                        "Available sheets: {$available}"
                    );
                }
            } else {
                $sheet = $spreadsheet->getActiveSheet();
            }

            $rows = $this->readSheetCells($sheet);

            if (empty($rows)) {
                throw new \RuntimeException('The uploaded file is empty.');
            }

            $headers = array_map('trim', $rows[1] ?? []);
            $dataRows = array_slice($rows, 1, null, true);

            $importer = new $importerClass($meta);
            $importer->setHeaders($headers);

            $headerErrors = $importer->validateHeaders($headers);
            if (!empty($headerErrors)) {
                throw new \RuntimeException('Header mismatch: ' . implode(', ', $headerErrors));
            }

            $validRows = [];
            $this->totalRows = count($dataRows);

            foreach ($dataRows as $rowIndex => $row) {
                $rowNumber = $rowIndex + 1;

                if ($this->isRowEmpty($row)) {
                    $this->totalRows--;
                    continue;
                }

                $mapped = $importer->mapRow($row, $headers);

                // Allow the importer to skip rows without adding them to the error file.
                // shouldSkipRow() may return:
                //   false  → do not skip (normal row)
                //   true   → skip silently (e.g. blank row)
                //   string → skip AND record this reason in the skipped-rows report
                if (method_exists($importer, 'shouldSkipRow')) {
                    $skipResult = $importer->shouldSkipRow($mapped);
                    if ($skipResult !== false) {
                        $this->totalRows--;
                        if (is_string($skipResult) && $skipResult !== '') {
                            $this->reportableSkips[] = [
                                'row_number' => $rowNumber,
                                'data'       => $row,
                                'error'      => $skipResult,
                            ];
                        }
                        continue;
                    }
                }

                $rowErrors = $importer->validate($mapped, $rowNumber);

                if (!empty($rowErrors)) {
                    $errorMsg = implode('; ', $rowErrors);
                    foreach ($rowErrors as $error) {
                        $this->errors[] = ['row' => $rowNumber, 'message' => $error];
                    }
                    $this->errorRows[] = ['row_number' => $rowNumber, 'data' => $row, 'error' => $errorMsg];
                    continue;
                }

                $validRows[] = [
                    'row_number' => $rowNumber,
                    'data' => $row,
                    'mapped' => $mapped,
                ];
            }

            if (!empty($this->errorRows)) {
                $this->errorFile = $this->generateErrorFile($module, $this->errorRows, $headers);

                $this->markLogFailed($log);

                return $this->buildResult(
                    $log,
                    false,
                    'Import failed. No rows were imported because the file contains errors.'
                );
            }

            \DB::beginTransaction();

            try {
                foreach ($validRows as $validRow) {
                    $importer->process($validRow['mapped'], $validRow['row_number']);
                    $this->successRows++;
                }

                if (method_exists($importer, 'afterImport')) {
                    $importer->afterImport();
                }

                \DB::commit();
            } catch (\Throwable $e) {
                \DB::rollBack();
                $this->successRows = 0;

                $failedRow = $validRow ?? ['row_number' => 0, 'data' => []];
                $this->errors[] = ['row' => $failedRow['row_number'], 'message' => $e->getMessage()];
                $this->errorRows[] = [
                    'row_number' => $failedRow['row_number'],
                    'data' => $failedRow['data'],
                    'error' => $e->getMessage(),
                ];
                $this->errorFile = $this->generateErrorFile($module, $this->errorRows, $headers);

                $this->markLogFailed($log);

                return $this->buildResult(
                    $log,
                    false,
                    'Import failed. No rows were imported because one or more rows could not be saved.'
                );
            }
        } catch (\Throwable $e) {
            $this->successRows = 0;
            $this->errors[] = ['row' => 0, 'message' => $e->getMessage()];

            if (!empty($rows)) {
                if (empty($headers)) {
                    $headers = array_map('trim', $rows[1] ?? []);
                }

                $this->errorFile = $this->generateImportFailureFile($module, $rows, $headers, $e->getMessage());
            }

            $this->markLogFailed($log);

            return $this->buildResult($log, false, $e->getMessage());
        }

        // Generate a skipped-rows report if any rows were reportably skipped
        if (!empty($this->reportableSkips)) {
            $this->skippedFile  = $this->generateErrorFile($module, $this->reportableSkips, $headers);
            $this->skippedCount = count($this->reportableSkips);
        }

        $log->update([
            'status' => 'completed',
            'total_rows' => $this->totalRows,
            'success_rows' => $this->successRows,
            'error_rows' => 0,
            'errors' => [],
            'warnings' => $this->warnings,
        ]);

        return $this->buildResult($log, true);
    }

    /**
     * Generate a downloadable import template.
     *
     * @param array $dropdowns  column-header → [option1, option2, ...] map.
     *                          Columns listed here get an Excel dropdown (data validation).
     *                          The validation is WARNING style — users can still type new
     *                          values not in the list (new firms / depts / designations
     *                          will be created automatically on import).
     */
    public function generateTemplate(string $importerClass, array $dropdowns = []): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $importer    = new $importerClass([]);
        $headers     = $importer->getTemplateHeaders();
        $sampleRows  = $importer->getSampleRows();
        $validations = $importer->getColumnValidations();

        $spreadsheet = new Spreadsheet();

        // ── "Lists" sheet — hidden sheet that holds dropdown values ─────────────
        // Excel data-validation formula references must point to a named range or
        // a sheet range; inline comma-separated lists break for >255 chars.
        $listSheet = $spreadsheet->createSheet(0);
        $listSheet->setTitle('Lists');
        $listSheet->getSheetState();   // ensure sheet is created

        // Write each dropdown column's values into a separate column on the Lists sheet
        $listColMap = []; // header → "Lists!$X$2:$X$N"
        $listCol    = 1;
        foreach ($dropdowns as $header => $options) {
            if (empty($options)) continue;
            $colLetter = Coordinate::stringFromColumnIndex($listCol);
            // Header label in row 1
            $listSheet->getCell("{$colLetter}1")->setValue($header);
            foreach ($options as $i => $opt) {
                $listSheet->getCell("{$colLetter}" . ($i + 2))->setValue($opt);
            }
            $lastRow          = count($options) + 1;
            $listColMap[$header] = "Lists!\${$colLetter}\$2:\${$colLetter}\${$lastRow}";
            $listCol++;
        }

        // Hide the Lists sheet so it doesn't clutter the template
        $listSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

        // ── "Data" sheet ─────────────────────────────────────────────────────────
        $sheet = $spreadsheet->createSheet(1);
        $sheet->setTitle('Data');
        $spreadsheet->setActiveSheetIndex(1);

        $col = 1;
        foreach ($headers as $header) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $sheet->getCell("{$colLetter}1")->setValue($header);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
            $sheet->getStyle("{$colLetter}1")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF4472C4']],
            ]);

            // Add dropdown validation if this column has list data
            if (isset($listColMap[$header])) {
                $this->applyDropdownValidation($sheet, $colLetter, $listColMap[$header], $header);
            }

            $col++;
        }

        foreach ($sampleRows as $rowIdx => $sampleRow) {
            $col = 1;
            foreach ($sampleRow as $value) {
                $sheet->getCell(Coordinate::stringFromColumnIndex($col) . ($rowIdx + 2))->setValue($value);
                $col++;
            }
        }

        // ── "Instructions" sheet ─────────────────────────────────────────────────
        $infoSheet = $spreadsheet->createSheet(2);
        $infoSheet->setTitle('Instructions');
        $infoSheet->setCellValue('A1', 'Column');
        $infoSheet->setCellValue('B1', 'Required');
        $infoSheet->setCellValue('C1', 'Format / Allowed Values');
        $infoSheet->setCellValue('D1', 'Example');

        $row = 2;
        foreach ($validations as $col => $info) {
            $infoSheet->setCellValue("A{$row}", $col);
            $infoSheet->setCellValue("B{$row}", $info['required'] ? 'Yes' : 'No');
            $infoSheet->setCellValue("C{$row}", $info['format'] ?? '');
            $infoSheet->setCellValue("D{$row}", $info['example'] ?? '');
            $row++;
        }
        foreach (['A', 'B', 'C', 'D'] as $col) {
            $infoSheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Activate the Data sheet for the user
        $spreadsheet->setActiveSheetIndex(1);

        $writer   = new Xlsx($spreadsheet);
        $filename = strtolower(class_basename($importerClass)) . '_template.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Apply a WARNING-style dropdown to a whole column (rows 2–1000).
     * WARNING style: shows the dropdown arrow and a caution message, but
     * still ALLOWS the user to type any value not in the list — so new
     * firms / departments / designations can be entered freely.
     */
    protected function applyDropdownValidation(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        string $colLetter,
        string $listFormula,
        string $columnLabel
    ): void {
        $validation = new \PhpOffice\PhpSpreadsheet\Cell\DataValidation();
        $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_WARNING);
        $validation->setAllowBlank(true);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setShowDropDown(false);   // false = show the dropdown arrow in Excel
        $validation->setPromptTitle('Select or type');
        $validation->setPrompt("Choose an existing {$columnLabel} from the list, or type a new one to create it.");
        $validation->setErrorTitle('New value');
        $validation->setError("This {$columnLabel} is not in the existing list. It will be created automatically on import.");
        $validation->setFormula1($listFormula);

        // Apply to rows 2–1000
        $sheet->setDataValidation(
            "{$colLetter}2:{$colLetter}1000",
            $validation
        );
    }

    public function export(array $data, array $headers, string $filename): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $col = 1;
        foreach ($headers as $header) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $sheet->getCell($colLetter . '1')->setValue($header);
            $sheet->getStyle($colLetter . '1')->getFont()->setBold(true);
            $col++;
        }

        $rowNum = 2;
        foreach ($data as $row) {
            $col = 1;
            foreach ($row as $value) {
                $sheet->getCell(Coordinate::stringFromColumnIndex($col) . $rowNum)->setValue($value);
                $col++;
            }
            $rowNum++;
        }

        foreach (range(1, count($headers)) as $col) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Read all cells from a worksheet, indexed as $rows[rowNum][colLetter].
     *
     * Key behaviour vs toArray(null,true,true,true):
     *   • Date-formatted cells → returned as 'YYYY-MM-DD' string (unambiguous, locale-safe)
     *   • Formula cells        → cached/pre-computed value (no re-evaluation, avoids Calculation\Exception)
     *   • Unresolved formulas  → null (formula strings starting with '=' are suppressed)
     *   • Everything else      → raw cell value unchanged
     *
     * This prevents PhpSpreadsheet from applying Excel's number-format code (e.g. 'mm-dd-yy')
     * to date serials, which would produce locale-dependent strings like "12-19-17" that
     * parseDate() cannot reliably decode.
     */
    protected function readSheetCells(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
    {
        $highestRow = $sheet->getHighestDataRow();
        $highestCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString(
            $sheet->getHighestDataColumn()
        );

        $rows = [];
        for ($r = 1; $r <= $highestRow; $r++) {
            $rowData = [];
            for ($c = 1; $c <= $highestCol; $c++) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
                $coord     = $colLetter . $r;

                if (!$sheet->cellExists($coord)) {
                    $rowData[$colLetter] = null;
                    continue;
                }

                $xlCell = $sheet->getCell($coord);

                // Use cached value for formula cells to avoid re-evaluation exceptions
                $value = ($xlCell->getDataType() === \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA)
                    ? $xlCell->getOldCalculatedValue()
                    : $xlCell->getValue();

                // Date-formatted numeric cell → convert to unambiguous Y-m-d string
                if (is_numeric($value) && \PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($xlCell)) {
                    try {
                        $value = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)
                            ->format('Y-m-d');
                    } catch (\Throwable) {
                        // leave as numeric — parseDate handles serial fallback
                    }
                }

                // Suppress unresolved formula strings
                if (is_string($value) && str_starts_with(ltrim($value), '=')) {
                    $value = null;
                }

                $rowData[$colLetter] = $value;
            }
            $rows[$r] = $rowData;
        }

        return $rows;
    }

    protected function isRowEmpty(array $row): bool
    {
        return empty(array_filter($row, fn($v) => $v !== null && $v !== ''));
    }

    protected function resetImportState(): void
    {
        $this->errors = [];
        $this->warnings = [];
        $this->errorRows = [];
        $this->errorFile = null;
        $this->successRows = 0;
        $this->totalRows = 0;
        $this->reportableSkips = [];
        $this->skippedFile = null;
        $this->skippedCount = 0;
    }

    protected function markLogFailed(ExcelUploadLog $log): void
    {
        $log->update([
            'status' => 'failed',
            'total_rows' => $this->totalRows,
            'success_rows' => 0,
            'error_rows' => count($this->errors),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ]);
    }

    protected function buildResult(ExcelUploadLog $log, bool $success, string $message = ''): array
    {
        return [
            'log_id'        => $log->id,
            'success'       => $success,
            'total_rows'    => $this->totalRows,
            'success_rows'  => $this->successRows,
            'error_rows'    => count($this->errors),
            'errors'        => $this->errors,
            'warnings'      => $this->warnings,
            'error_file'    => $this->errorFile,
            'skipped_file'  => $this->skippedFile,
            'skipped_count' => $this->skippedCount,
            'message'       => $message ?: ($success ? 'Import completed.' : 'Import failed.'),
        ];
    }

    protected function generateErrorFile(string $module, array $errorRows, array $colHeaders): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Errors');

        $colLetters = array_keys($colHeaders);
        $headerNames = array_values($colHeaders);
        $totalCols = count($headerNames) + 2;
        $lastColLetter = Coordinate::stringFromColumnIndex($totalCols);

        $sheet->getCell('A1')->setValue('Row #');
        foreach ($headerNames as $i => $name) {
            $sheet->getCell(Coordinate::stringFromColumnIndex($i + 2) . '1')->setValue($name);
        }
        $sheet->getCell($lastColLetter . '1')->setValue('Error');

        $sheet->getStyle('A1:' . $lastColLetter . '1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFC0392B']],
        ]);

        $rowNum = 2;
        foreach ($errorRows as $errRow) {
            $sheet->getCell('A' . $rowNum)->setValue($errRow['row_number']);

            foreach ($colLetters as $i => $letter) {
                $sheet->getCell(Coordinate::stringFromColumnIndex($i + 2) . $rowNum)
                    ->setValue($errRow['data'][$letter] ?? '');
            }

            $sheet->getCell($lastColLetter . $rowNum)->setValue($errRow['error']);

            $sheet->getStyle('A' . $rowNum . ':' . $lastColLetter . $rowNum)
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB('FFFDE8E8');

            $rowNum++;
        }

        for ($c = 1; $c <= $totalCols; $c++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }

        $dir = storage_path('app/error_exports');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = sprintf('error_%s_%s_%s.xlsx', $module, now()->format('Ymd_His'), substr(uniqid(), -6));
        (new Xlsx($spreadsheet))->save($dir . '/' . $filename);

        return 'error_exports/' . $filename;
    }

    protected function generateImportFailureFile(string $module, array $rows, array $headers, string $error): string
    {
        $dataRows = array_slice($rows, 1, null, true);

        if (empty($headers)) {
            $firstRow = $rows[1] ?? [];
            foreach (array_keys($firstRow) as $letter) {
                $headers[$letter] = $letter;
            }
        }

        if (empty($dataRows)) {
            $dataRows = [2 => []];
        }

        $errorRows = [];
        foreach ($dataRows as $rowNumber => $row) {
            if ($this->isRowEmpty($row)) {
                continue;
            }

            $errorRows[] = [
                'row_number' => $rowNumber,
                'data' => $row,
                'error' => $error,
            ];
        }

        if (empty($errorRows)) {
            $errorRows[] = [
                'row_number' => 0,
                'data' => [],
                'error' => $error,
            ];
        }

        return $this->generateErrorFile($module, $errorRows, $headers);
    }
}
