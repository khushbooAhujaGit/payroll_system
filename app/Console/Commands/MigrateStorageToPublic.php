<?php

namespace App\Console\Commands;

use App\Models\ComplianceChallan;
use App\Models\ContractorInvoice;
use App\Models\EmployeeDocument;
use App\Models\HelpdeskComment;
use App\Models\HelpdeskTicket;
use App\Models\LeaveApplication;
use App\Models\PoiSubmission;
use App\Models\ReimbursementItem;
use App\Models\ReimbursementTrackerRow;
use App\Services\PublicFileStore;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * One-time move of every file already uploaded to the private storage/app
 * ('local') disk over to public_path(), matching how new uploads are now
 * written (see PublicFileStore). Each file gets a fresh random-token
 * folder — same "public but unguessable" scheme as new uploads — the old
 * predictable employee/entity-ID-keyed path is not reused.
 *
 * Idempotent: a row already migrated (its stored path already resolves
 * under public_path(), or its column is empty) is skipped, so this can be
 * re-run safely if it's interrupted partway.
 */
class MigrateStorageToPublic extends Command
{
    protected $signature = 'storage:migrate-to-public {--dry-run : List what would move without touching any files or the database}';

    protected $description = 'Move existing uploaded documents from storage/app to public_path(), matching the new public-upload storage scheme.';

    public function handle(PublicFileStore $files)
    {
        $dryRun = (bool) $this->option('dry-run');

        $jobs = [
            ['model' => EmployeeDocument::class, 'column' => 'file_path', 'category' => 'employee_documents'],
            ['model' => ReimbursementTrackerRow::class, 'column' => 'document_path', 'category' => 'reimbursement_docs'],
            ['model' => ReimbursementItem::class, 'column' => 'bill_path', 'category' => 'reimbursements'],
            ['model' => LeaveApplication::class, 'column' => 'document_path', 'category' => 'leave_docs'],
            ['model' => ComplianceChallan::class, 'column' => 'receipt_path', 'category' => 'challans'],
            ['model' => HelpdeskTicket::class, 'column' => 'attachment_path', 'category' => 'helpdesk'],
            ['model' => HelpdeskComment::class, 'column' => 'attachment_path', 'category' => 'helpdesk/comments'],
            ['model' => ContractorInvoice::class, 'column' => 'invoice_path', 'category' => 'contractor_invoices'],
            ['model' => PoiSubmission::class, 'column' => 'file_path', 'category' => 'poi'],
        ];

        $totalMoved = 0;
        $totalSkipped = 0;

        foreach ($jobs as $job) {
            [$moved, $skipped] = $this->migrateColumn($job['model'], $job['column'], $job['category'], $files, $dryRun);
            $totalMoved += $moved;
            $totalSkipped += $skipped;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Done. Moved {$totalMoved} file(s), skipped {$totalSkipped} (already migrated, empty, or missing on disk).");

        return 0;
    }

    protected function migrateColumn(string $modelClass, string $column, string $category, PublicFileStore $files, bool $dryRun): array
    {
        $moved = 0;
        $skipped = 0;

        $rows = $modelClass::query()->whereNotNull($column)->where($column, '!=', '')->get();

        foreach ($rows as $row) {
            $oldRelative = $row->{$column};
            $oldAbsolute = storage_path("app/{$oldRelative}");

            if (!is_file($oldAbsolute)) {
                $skipped++;
                continue;
            }

            $token = Str::random(40);
            $filename = basename($oldRelative);
            $newRelative = "{$category}/{$token}/{$filename}";
            $newAbsolute = public_path($newRelative);

            $this->line(($dryRun ? '[DRY RUN] ' : '') . "{$modelClass}#{$row->id}: storage/app/{$oldRelative} -> public/{$newRelative}");

            if (!$dryRun) {
                $dir = dirname($newAbsolute);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                rename($oldAbsolute, $newAbsolute);
                $row->{$column} = $newRelative;
                $row->save();
            }

            $moved++;
        }

        return [$moved, $skipped];
    }
}
