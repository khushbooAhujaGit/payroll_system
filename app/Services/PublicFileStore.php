<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Stores user-uploaded files (ID documents, bills, receipts, attachments)
 * under public_path() instead of the authenticated storage/app disk, per
 * explicit instruction — see PayslipPdfService's docblock for the
 * background on why these are public at all.
 *
 * Unlike payslips/Form16 (keyed by employee_id, fine to be guessable since
 * they're computed summaries), these are scans of PAN cards, Aadhaar
 * cards, cheques etc. — so each file gets a random, unguessable folder
 * token instead of a predictable employee/entity ID, so a file is only
 * reachable by someone who already has the exact link, not by enumerating
 * sequential IDs in the URL.
 */
class PublicFileStore
{
    /** Moves an uploaded file under public_path("{category}/{random token}/filename") and returns that relative path. */
    public function store(UploadedFile $file, string $category): string
    {
        $token = Str::random(40);
        $filename = $file->hashName();
        $dir = public_path("{$category}/{$token}");

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file->move($dir, $filename);

        return "{$category}/{$token}/{$filename}";
    }

    /** Same scheme as store(), for bytes fetched from elsewhere (e.g. a OneDrive download) rather than a real HTTP upload. */
    public function storeContent(string $content, string $category, string $originalFilename): string
    {
        $token = Str::random(40);
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $filename = Str::random(40) . ($extension ? ".{$extension}" : '');
        $dir = public_path("{$category}/{$token}");

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents("{$dir}/{$filename}", $content);

        return "{$category}/{$token}/{$filename}";
    }

    public function exists(?string $relativePath): bool
    {
        return $relativePath && is_file(public_path($relativePath));
    }

    public function absolutePath(string $relativePath): string
    {
        return public_path($relativePath);
    }

    public function delete(?string $relativePath): void
    {
        if ($this->exists($relativePath)) {
            unlink(public_path($relativePath));
        }
    }
}
