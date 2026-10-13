<?php

namespace App\Console\Commands;

use App\Models\EmployeeDocument;
use App\Services\EmployeeDocumentDownloadService;
use Illuminate\Console\Command;

/**
 * Sweeps EmployeeDocument rows left pending by the bulk-upload's OneDrive
 * document-link columns (source_url set, file_path still null) and
 * downloads each one via Microsoft Graph now that Files.Read.All /
 * Sites.Read.All are granted.
 */
class FetchOneDriveDocuments extends Command
{
    protected $signature = 'documents:fetch-onedrive
        {--employee-id= : Only process documents for this employee}
        {--dry-run : List what would be fetched without downloading anything}';

    protected $description = 'Download employee documents that were bulk-imported as OneDrive share links but not yet fetched.';

    public function handle(EmployeeDocumentDownloadService $service)
    {
        $query = EmployeeDocument::whereNotNull('source_url')->whereNull('file_path');

        if ($employeeId = $this->option('employee-id')) {
            $query->where('employee_id', $employeeId);
        }

        $documents = $query->get();

        if ($documents->isEmpty()) {
            $this->info('No pending OneDrive document links to fetch.');
            return 0;
        }

        $this->info("Found {$documents->count()} pending document(s).");

        if ($this->option('dry-run')) {
            foreach ($documents as $doc) {
                $this->line("[DRY RUN] employee #{$doc->employee_id} / {$doc->document_type} <- {$doc->source_url}");
            }
            return 0;
        }

        $ok = 0;
        $failed = 0;
        foreach ($documents as $doc) {
            if ($service->download($doc)) {
                $this->info("✓ {$doc->document_type} for employee #{$doc->employee_id}");
                $ok++;
            } else {
                $this->error("✗ {$doc->document_type} for employee #{$doc->employee_id}: " . ($doc->fresh()->remarks ?? 'unknown error'));
                $failed++;
            }
        }

        $this->info("Done. {$ok} downloaded, {$failed} failed.");

        return 0;
    }
}
