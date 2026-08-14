<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Deletes leftover files in storage/app/error_exports older than the given age.
 *
 * Error/skipped-row export files are meant to be downloaded once and discarded
 * (the download route already deletes them on successful download). This is a
 * safety net for files that were generated but never downloaded — e.g. the user
 * closed the tab, or fixed the import a different way.
 */
class PruneErrorExports extends Command
{
    protected $signature = 'storage:prune-error-exports {--hours=24 : Delete files older than this many hours}';
    protected $description = 'Delete stale files from storage/app/error_exports';

    public function handle(): int
    {
        $dir = storage_path('app/error_exports');
        if (!is_dir($dir)) {
            $this->info('Nothing to prune — error_exports directory does not exist.');
            return self::SUCCESS;
        }

        $cutoff  = now()->subHours((int) $this->option('hours'))->getTimestamp();
        $deleted = 0;

        foreach (File::files($dir) as $file) {
            if ($file->getMTime() < $cutoff) {
                File::delete($file->getPathname());
                $deleted++;
            }
        }

        $this->info("Deleted {$deleted} stale file(s) from error_exports.");
        return self::SUCCESS;
    }
}
