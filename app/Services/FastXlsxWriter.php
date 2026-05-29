<?php

namespace App\Services;

/**
 * Lightweight, fast XLSX writer — bypasses PhpSpreadsheet entirely.
 *
 * Uses ZipArchive (built-in PHP extension) + direct XML string building.
 * Typically 10–20× faster than PhpSpreadsheet for pure data exports.
 *
 * Features:
 *  - Header row  : bold, white text, dark-green background, centered
 *  - Data rows   : thin borders, Arial 9pt — NO per-cell fancy colouring
 *  - Frozen pane at A2
 *  - Auto-filter on row 1
 *  - Custom column widths
 *  - Inline strings (no shared-strings table to build)
 */
class FastXlsxWriter
{
    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Write an XLSX to a temp file and return its absolute path.
     * Caller is responsible for deleting the file (or use ->deleteFileAfterSend()).
     *
     * @param  string   $sheetTitle  Tab name shown in Excel
     * @param  string[] $headers     Column header labels
     * @param  array[]  $rows        Data rows — each an indexed array matching $headers count
     * @param  float[]  $colWidths   Column widths in characters (falls back to 14 if missing)
     * @param  string   $headerColor ARGB hex for the header background (default dark-green)
     */
    public function write(
        string $sheetTitle,
        array  $headers,
        array  $rows,
        array  $colWidths   = [],
        string $headerColor = 'FF4E7C00'
    ): string {
        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_') . '.xlsx';

        $zip = new \ZipArchive();
        $zip->open($tmpFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $colCount = count($headers);
        $lastCol  = $colCount > 0 ? $this->colLetter($colCount) : 'A';

        $zip->addFromString('[Content_Types].xml',        $this->buildContentTypes());
        $zip->addFromString('_rels/.rels',                $this->buildRels());
        $zip->addFromString('xl/workbook.xml',            $this->buildWorkbook($sheetTitle));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->buildWorkbookRels());
        $zip->addFromString('xl/styles.xml',              $this->buildStyles($headerColor));
        $zip->addFromString(
            'xl/worksheets/sheet1.xml',
            $this->buildSheet($headers, $rows, $colWidths, $lastCol)
        );

        $zip->close();

        return $tmpFile;
    }

    // ── XLSX structure XML ────────────────────────────────────────────────────

    private function buildContentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function buildRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function buildWorkbook(string $title): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>'
            . '<sheet name="' . $this->esc($title) . '" sheetId="1" r:id="rId1"/>'
            . '</sheets>'
            . '</workbook>';
    }

    private function buildWorkbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function buildStyles(string $headerBgArgb): string
    {
        // 3 styles:
        //   s=0  default  (no formatting)
        //   s=1  header   (bold white text, colored background, centered)
        //   s=2  data     (thin borders, Arial 9)
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="3">'
            . '<font><sz val="9"/><name val="Arial"/></font>'                                          // 0 default
            . '<font><b/><sz val="10"/><name val="Arial"/><color rgb="FFFFFFFF"/></font>'             // 1 header
            . '<font><sz val="9"/><name val="Arial"/></font>'                                          // 2 data
            . '</fonts>'
            . '<fills count="3">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="' . $headerBgArgb . '"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'                             // 0 none
            . '<border>'
            . '<left style="thin"><color rgb="FFCCCCCC"/></left>'
            . '<right style="thin"><color rgb="FFCCCCCC"/></right>'
            . '<top style="thin"><color rgb="FFCCCCCC"/></top>'
            . '<bottom style="thin"><color rgb="FFCCCCCC"/></bottom>'
            . '<diagonal/>'
            . '</border>'                                                                               // 1 thin
            . '</borders>'
            . '<cellStyleXfs count="1">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>'
            . '</cellStyleXfs>'
            . '<cellXfs count="3">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'                       // s=0
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1">'
            . '<alignment horizontal="center" vertical="center" wrapText="1"/>'
            . '</xf>'                                                                                   // s=1 header
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/>' // s=2 data
            . '</cellXfs>'
            . '<cellStyles count="1">'
            . '<cellStyle name="Normal" xfId="0" builtinId="0"/>'
            . '</cellStyles>'
            . '</styleSheet>';
    }

    private function buildSheet(array $headers, array $rows, array $colWidths, string $lastCol): string
    {
        $colCount   = count($headers);
        $filterRef  = $colCount > 0 ? 'A1:' . $lastCol . '1' : 'A1';

        // Column definitions
        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        // sheetViews MUST come before cols (OOXML schema order)
        $xml .= '<sheetViews>'
            . '<sheetView workbookViewId="0">'
            . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
            . '</sheetView>'
            . '</sheetViews>';

        // Column widths
        $xml .= '<cols>';
        for ($c = 1; $c <= $colCount; $c++) {
            $w    = $colWidths[$c - 1] ?? 14;
            $xml .= '<col min="' . $c . '" max="' . $c . '" width="' . $w . '" customWidth="1"/>';
        }
        $xml .= '</cols>';

        $xml .= '<sheetData>';

        // ── Header row (s=1) ─────────────────────────────────────────────────
        $xml .= '<row r="1" ht="18" customHeight="1">';
        foreach ($headers as $ci => $header) {
            $ref  = $this->colLetter($ci + 1) . '1';
            $xml .= '<c r="' . $ref . '" t="inlineStr" s="1"><is><t>' . $this->esc((string) $header) . '</t></is></c>';
        }
        $xml .= '</row>';

        // ── Data rows (s=2) ──────────────────────────────────────────────────
        foreach ($rows as $ri => $row) {
            $rowNum = $ri + 2;
            $xml   .= '<row r="' . $rowNum . '">';
            foreach ($row as $ci => $val) {
                $ref = $this->colLetter($ci + 1) . $rowNum;
                if (is_numeric($val) && $val !== '' && $val !== null) {
                    $xml .= '<c r="' . $ref . '" s="2"><v>' . $val . '</v></c>';
                } else {
                    $xml .= '<c r="' . $ref . '" t="inlineStr" s="2"><is><t>' . $this->esc((string) ($val ?? '')) . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData>';
        $xml .= '<autoFilter ref="' . $filterRef . '"/>';
        $xml .= '</worksheet>';

        return $xml;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Convert 1-based column index to Excel letter (1→A, 27→AA, …) */
    private function colLetter(int $n): string
    {
        $s = '';
        while ($n > 0) {
            $n--;
            $s  = chr(65 + ($n % 26)) . $s;
            $n  = (int)($n / 26);
        }
        return $s;
    }

    /** XML-safe string escaping */
    private function esc(string $v): string
    {
        return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
