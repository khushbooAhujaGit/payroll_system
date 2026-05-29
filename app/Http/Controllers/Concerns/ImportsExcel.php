<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;

/**
 * Shared import-result handling for all Excel upload controllers.
 *
 * Usage:
 *   use App\Http\Controllers\Concerns\ImportsExcel;
 *
 *   class WebFooController extends Controller {
 *       use ImportsExcel;
 *
 *       public function import(Request $request) {
 *           $result = $this->excelImportService->import(...);
 *           return $this->handleImportResult($result, 'foo.index', 'record');
 *       }
 *   }
 */
trait ImportsExcel
{
    /**
     * Build a redirect with flash messages (and optional error-file download link)
     * from an ExcelImportService result array.
     *
     * @param  array  $result       Return value of ExcelImportService::import()
     * @param  string $route        Named route to redirect to
     * @param  string $noun         Singular noun for the success message, e.g. "row", "loan"
     * @param  array  $routeParams  Optional route parameters
     */
    protected function handleImportResult(
        array  $result,
        string $route,
        string $noun = 'row',
        array  $routeParams = []
    ): RedirectResponse {
        $count = $result['success_rows'] ?? 0;
        $success = (bool) ($result['success'] ?? false);
        $msg = $success
            ? "Imported {$count} {$noun}" . ($count === 1 ? '' : 's') . '.'
            : ($result['message'] ?? 'Import failed. No rows were imported.');

        if (!empty($result['errors'])) {
            $total    = count($result['errors']);
            $snippets = array_map(
                fn($e) => is_array($e) ? "Row {$e['row']}: {$e['message']}" : (string) $e,
                array_slice($result['errors'], 0, 3)
            );
            $msg .= ' ' . $total . ' error' . ($total === 1 ? '' : 's') . ': '
                  . implode('; ', $snippets)
                  . ($total > 3 ? ' …' : '');

            $redirect = redirect()->route($route, $routeParams)->with('error', $msg);

            if (!empty($result['error_file'])) {
                $redirect = $redirect->with('error_file', basename($result['error_file']));
            }

            return $redirect;
        }

        $redirect = redirect()->route($route, $routeParams)->with('success', $msg);

        // If some rows were reportably skipped (e.g. EMP ID not found), surface a
        // warning alongside the success so the user can download the skipped-rows report.
        if (!empty($result['skipped_file'])) {
            $skipped = (int) ($result['skipped_count'] ?? 0);
            $redirect = $redirect
                ->with('skipped_file', basename($result['skipped_file']))
                ->with('skipped_warning', "{$skipped} row(s) were skipped because the EMP ID was not found in the employees table. Download the report to see which records were skipped.");
        }

        return $redirect;
    }
}
