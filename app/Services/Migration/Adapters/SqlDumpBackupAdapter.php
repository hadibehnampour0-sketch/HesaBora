<?php

namespace App\Services\Migration\Adapters;

use App\Services\Migration\Contracts\MigrationAdapterInterface;
use App\Services\Migration\DTO\MigrationPayload;

class SqlDumpBackupAdapter implements MigrationAdapterInterface
{
    public function __construct(
        protected AutoUnknownDatabaseAdapter $autoAdapter = new AutoUnknownDatabaseAdapter
    ) {}

    public function getName(): string
    {
        return 'sql_dump';
    }

    public function supports(string $sourceType, array|string $data): bool
    {
        if ($sourceType === 'sql_dump' || $sourceType === 'sql') {
            return true;
        }

        if (is_string($data) && (stripos($data, 'INSERT INTO') !== false || stripos($data, 'CREATE TABLE') !== false)) {
            return true;
        }

        return false;
    }

    public function parse(array|string $data): MigrationPayload
    {
        $payload = $this->autoAdapter->parse($data);
        $payload->metadata['source'] = 'sql_dump';
        $payload->metadata['adapter'] = 'SqlDumpBackupAdapter';

        return $payload;
    }
}
