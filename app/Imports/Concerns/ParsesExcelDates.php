<?php

namespace App\Imports\Concerns;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Shared date-parsing logic for all Excel importers and import controllers.
 *
 * Handles every form a date can arrive in after reading an Excel file:
 *   • DateTimeInterface   — PhpSpreadsheet date-formatted cell (formatData=false path)
 *   • Numeric serial      — cell stored as plain number, no date format applied
 *   • "Y-m-d" string      — output of ExcelImportService::readSheetCells() (preferred)
 *   • "DD-MM-YYYY"        — Indian standard text date
 *   • Any other string    — broad format list + Carbon::parse fallback
 */
trait ParsesExcelDates
{
    protected function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;

        // ── DateTimeInterface ────────────────────────────────────────────────
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->format('Y-m-d');
        }

        // ── Excel serial number ──────────────────────────────────────────────
        // Uses PhpSpreadsheet's own converter which correctly handles Excel's
        // 1900 leap-year bug (Excel serial 60 = fake Feb 29, 1900).
        if (is_numeric($value)) {
            $serial = (float) $value;
            if ($serial < 1 || $serial > 2958465) return null;
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject($serial))->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        $str = trim((string) $value);

        // Fast bail: empty, "Working" sentinel, or no digits (e.g. "Jay Dave")
        if ($str === '' || strtolower($str) === 'working') return null;
        if (!preg_match('/\d/', $str)) return null;

        // ── Explicit format list — Indian standard (DD-MM-YYYY) first ────────
        $formats = [
            'd-m-Y',         // 19-12-2017   DD-MM-YYYY  ← Indian standard
            'd/m/Y',         // 19/12/2017   DD/MM/YYYY
            'd.m.Y',         // 19.12.2017   DD.MM.YYYY
            'Y-m-d',         // 2017-12-19   ISO 8601    ← ExcelImportService output
            'Y/m/d',         // 2017/12/19
            'Y-m-d H:i:s',   // 2017-12-19 00:00:00
            'Y-m-d\TH:i:s',  // 2017-12-19T00:00:00
            'd-m-y',         // 19-12-17     two-digit year
            'd/m/y',         // 19/12/17
            'd.m.y',         // 19.12.17
            'j-n-Y',         // 1-2-2019     no leading zeros
            'j/n/Y',         // 1/2/2019
            'j.n.Y',         // 1.2.2019
            'd-M-Y',         // 19-Dec-2017  text month abbreviation
            'd/M/Y',         // 19/Dec/2017
            'd M Y',         // 19 Dec 2017
            'j M Y',         // 1 Dec 2017
            'd F Y',         // 19 December 2017
            'm/d/Y',         // 12/19/2017   US format (last — ambiguous with DD/MM)
        ];

        foreach ($formats as $fmt) {
            try {
                $parsed = Carbon::createFromFormat($fmt, $str);
                if ($parsed && $parsed->year >= 1900 && $parsed->year <= 2100) {
                    return $parsed->format('Y-m-d');
                }
            } catch (\Throwable) {
                // try next
            }
        }

        // ── Carbon::parse as final fallback ──────────────────────────────────
        try {
            $parsed = Carbon::parse($str);
            if ($parsed->year >= 1900 && $parsed->year <= 2100) {
                return $parsed->format('Y-m-d');
            }
        } catch (\Throwable) {}

        return null;
    }
}
