<?php

namespace App\Services;

use App\Models\EmployeeDocument;
use Illuminate\Support\Facades\Http;

/**
 * Fetches the actual file for an EmployeeDocument that was bulk-imported as
 * a OneDrive share link (source_url) rather than an uploaded file — see
 * EmployeeImporter's document-link handling, which deliberately left
 * file_path null pending this. Needs Files.Read.All / Sites.Read.All
 * (Application permissions) on the same Graph app registration
 * OutlookMailerService uses for mail — confirmed granted 2026-08-25.
 *
 * Uses the same client-credentials token as mail sending (reused via
 * OutlookMailerService::getAccessToken()) — this is app-only auth, no
 * individual user's OneDrive session is involved, so it can resolve any
 * share link the tenant's Graph app has been granted access to.
 */
class EmployeeDocumentDownloadService
{
    public function __construct(
        protected OutlookMailerService $mailer,
        protected PublicFileStore $files,
    ) {}

    /**
     * Downloads one document from its OneDrive share link and saves it via
     * PublicFileStore. Returns true on success. On failure, records the
     * reason in the row's remarks (rather than throwing) since this runs
     * unattended over many rows and one bad link shouldn't stop the batch.
     */
    public function download(EmployeeDocument $document): bool
    {
        if (!$document->source_url || $document->file_path) {
            return false;
        }

        try {
            $token = $this->mailer->getAccessToken();
        } catch (\Throwable $e) {
            $document->update(['remarks' => 'OneDrive fetch failed: could not acquire Graph token — ' . $e->getMessage()]);
            return false;
        }

        $shareId = $this->encodeShareUrl($document->source_url);

        $metaResponse = Http::withToken($token)
            ->get("https://graph.microsoft.com/v1.0/shares/{$shareId}/driveItem", [
                '$select' => 'id,name,size,file',
            ]);

        if (!$metaResponse->successful()) {
            $document->update([
                'remarks' => 'OneDrive fetch failed (metadata): HTTP ' . $metaResponse->status() . ' — ' . $this->shortError($metaResponse),
            ]);
            return false;
        }

        $meta = $metaResponse->json();
        $originalName = $meta['name'] ?? ($document->document_name ?: 'document');

        $contentResponse = Http::withToken($token)
            ->timeout(60)
            ->get("https://graph.microsoft.com/v1.0/shares/{$shareId}/driveItem/content");

        if (!$contentResponse->successful()) {
            $document->update([
                'remarks' => 'OneDrive fetch failed (download): HTTP ' . $contentResponse->status(),
            ]);
            return false;
        }

        $relativePath = $this->files->storeContent($contentResponse->body(), 'employee_documents', $originalName);

        $document->update([
            'file_path' => $relativePath,
            'file_name' => $originalName,
            'file_type' => pathinfo($originalName, PATHINFO_EXTENSION) ?: null,
            'remarks'   => null,
        ]);

        return true;
    }

    /**
     * Graph's documented scheme for resolving a share link:
     * base64-encode the URL, then convert to unpadded base64url and
     * prefix with "u!". https://learn.microsoft.com/en-us/onedrive/developer/rest-api/api/shares_get
     */
    protected function encodeShareUrl(string $url): string
    {
        $base64 = base64_encode($url);
        $urlSafe = rtrim(strtr($base64, '/+', '_-'), '=');
        return 'u!' . $urlSafe;
    }

    protected function shortError($response): string
    {
        $body = $response->json();
        return $body['error']['message'] ?? substr($response->body(), 0, 200);
    }
}
