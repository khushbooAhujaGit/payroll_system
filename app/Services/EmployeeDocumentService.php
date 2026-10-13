<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use Illuminate\Http\UploadedFile;

class EmployeeDocumentService
{
    public function __construct(protected PublicFileStore $files)
    {
    }

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

            $path = $this->files->store($file, 'employee_documents');

            $existing = EmployeeDocument::where('employee_id', $employee->id)
                ->where('document_type', $type)
                ->first();

            if ($existing) {
                $this->files->delete($existing->file_path);
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
