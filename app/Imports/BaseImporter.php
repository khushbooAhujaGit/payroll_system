<?php

namespace App\Imports;

abstract class BaseImporter
{
    protected array $meta    = [];
    protected array $headers = [];

    public function __construct(array $meta)
    {
        $this->meta = $meta;
    }

    public function setHeaders(array $headers): void
    {
        $this->headers = $headers;
    }

    public function validateHeaders(array $headers): array
    {
        $required = $this->getRequiredHeaders();
        $missing  = [];
        foreach ($required as $req) {
            if (!in_array($req, array_values($headers))) {
                $missing[] = $req;
            }
        }
        return $missing ? ["Missing columns: " . implode(', ', $missing)] : [];
    }

    protected function getRequiredHeaders(): array
    {
        // Override in subclass if needed
        return [];
    }

    abstract public function getTemplateHeaders(): array;
    abstract public function getSampleRows(): array;
    abstract public function getColumnValidations(): array;
    abstract public function validate(array $data, int $rowNumber): array;
    abstract public function mapRow(array $row, array $headers): array;
    abstract public function process(array $data, int $rowNumber): void;
}
