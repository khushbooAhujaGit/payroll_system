<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Resolve the current company ID for the logged-in user.
     *
     * Priority:
     *   1. The company the user's own employee record belongs to.
     *   2. An explicit company_id from the current request (admin selector).
     *   3. The first company that has at least one employee in the DB.
     *   4. Hard fallback: 1.
     */
    protected function resolveCompanyId(?\Illuminate\Http\Request $request = null): int
    {
        // 1. User's own employee company
        $userCompanyId = auth()->user()?->employee?->company_id;
        if ($userCompanyId) {
            return (int) $userCompanyId;
        }

        // 2. Explicit request param
        if ($request?->filled('company_id')) {
            return (int) $request->get('company_id');
        }

        // 3. First company that has employees
        $first = Employee::whereNotNull('company_id')->value('company_id');
        if ($first) {
            return (int) $first;
        }

        return 1;
    }

    /**
     * @deprecated  Use resolveCompanyId() — kept only to avoid breaking any
     *              external calls during transition. Will be removed.
     */
    protected function resolveEntityId(?\Illuminate\Http\Request $request = null): int
    {
        return $this->resolveCompanyId($request);
    }

    /** Stream an array/collection of rows as an xlsx download. */
    protected function streamXlsx(string $filename, array $headers, iterable $rows): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $ws          = $spreadsheet->getActiveSheet();

        foreach ($headers as $i => $h) {
            $ws->setCellValue([$i + 1, 1], $h);
            $ws->getStyle([$i + 1, 1])->getFont()->setBold(true);
        }

        $ri = 2;
        foreach ($rows as $row) {
            $row = array_values((array) $row);
            foreach ($row as $ci => $val) {
                $cell = $ws->getCell([$ci + 1, $ri]);
                if ($val instanceof \DateTimeInterface) {
                    // Write as a proper Excel date serial so Excel can sort/filter by date.
                    // Null is left as an empty cell (handled by the else branch below).
                    // Use date+time format only when the time component is non-midnight.
                    $excelSerial = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($val);
                    $cell->setValue($excelSerial);
                    $hasTime = ((int) $val->format('His')) !== 0;
                    $cell->getStyle()->getNumberFormat()
                        ->setFormatCode($hasTime ? 'DD-MM-YYYY HH:MM' : 'DD-MM-YYYY');
                } elseif (is_string($val) && $val !== '' && preg_match('/^\d+$/', $val)) {
                    $cell->setValueExplicit(
                        $val,
                        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                    );
                } else {
                    $cell->setValue($val ?? '');
                }
            }
            $ri++;
        }

        foreach (range(1, count($headers)) as $col) {
            $ws->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $date   = now()->format('Y-m-d');
        return response()->streamDownload(
            fn() => $writer->save('php://output'),
            "{$filename}_{$date}.xlsx",
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }
}
