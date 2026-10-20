<?php

namespace App\Services\Migration\Contracts;

use App\Services\Migration\DTO\MigrationPayload;

interface MigrationAdapterInterface
{
    /**
     * Check if this adapter supports the source payload or format.
     */
    public function supports(string $sourceType, array|string $data): bool;

    /**
     * Parse raw competitor backup data into normalized MigrationPayload.
     */
    public function parse(array|string $data): MigrationPayload;

    /**
     * Software name / format identifier.
     */
    public function getName(): string;
}
