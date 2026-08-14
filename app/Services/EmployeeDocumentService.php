<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class EmployeeDocumentService
{
    /**
     * Stores one uploaded file per document type — replacing whatever was
     * previously uploaded for that type/employee, so there's always at most
     * one current file per type rather than an unbounded history.
     *
     * @param array<string, UploadedFile|null> $files keyed by EmployeeDocument::UPLOAD_TYPES key
     */
    public function storeUploads(Employee $employee, array $files, int $uploadedBy): void
    {
        foreach ($files as $type => $file) {
            if (!$file instanceof UploadedFile || !array_key_exists($type, EmployeeDocument::UPLOAD_TYPES)) {
                continue;
            }

            $path = $file->store("employee_documents/{$employee->id}", 'local');

            $existing = EmployeeDocument::where('employee_id', $employee->id)
                ->where('document_type', $type)
                ->first();

            if ($existing) {
                Storage::disk('local')->delete($existing->file_path);
                $existing->delete();
            }

            EmployeeDocument::create([
                'employee_id'   => $employee->id,
                'document_type' => $type,
                'file_path'     => $path,
                'file_name'     => $file->getClientOriginalName(),
                'file_type'     => $file->getClientOriginalExtension(),
                'uploaded_by'   => $uploadedBy,
            ]);
        }
    }
}
