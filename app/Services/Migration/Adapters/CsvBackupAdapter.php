<?php

namespace App\Services\Migration\Adapters;

use App\Services\Migration\Contracts\MigrationAdapterInterface;
use App\Services\Migration\DTO\MigrationPayload;

class CsvBackupAdapter implements MigrationAdapterInterface
{
    public function __construct(
        protected AutoUnknownDatabaseAdapter $autoAdapter = new AutoUnknownDatabaseAdapter
    ) {}

    public function getName(): string
    {
        return 'csv';
    }

    public function supports(string $sourceType, array|string $data): bool
    {
        if ($sourceType === 'csv') {
            return true;
        }

        if (is_string($data) && str_contains($data, "\n") && (str_contains($data, ',') || str_contains($data, ';') || str_contains($data, "\t"))) {
            // If it's not JSON and not SQL, it might be CSV
            $trimmed = trim($data);
            if (! str_starts_with($trimmed, '{') && ! str_starts_with($trimmed, '[') && stripos($trimmed, 'INSERT INTO') === false) {
                return true;
            }
        }

        return false;
    }

    public function parse(array|string $data): MigrationPayload
    {
        $payload = $this->autoAdapter->parse($data);
        $payload->metadata['source'] = 'csv';
        $payload->metadata['adapter'] = 'CsvBackupAdapter';

        return $payload;
    }
}
