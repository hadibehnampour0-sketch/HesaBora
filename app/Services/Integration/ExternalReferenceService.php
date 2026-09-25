<?php

namespace App\Services\Integration;

use App\Models\ExternalReference;
use Illuminate\Validation\ValidationException;

/** Maps an external system's ids to local entity ids (shared by the Integration Hub and the future Migration platform). */
class ExternalReferenceService
{
    /** Idempotent link: same mapping again is a no-op; a different local entity for the same external id is rejected. */
    public function link(string $source, string $externalId, string $entityType, int $entityId): ExternalReference
    {
        $existing = ExternalReference::where('source_system', $source)
            ->where('external_id', $externalId)
            ->where('entity_type', $entityType)
            ->first();

        if ($existing) {
            if ($existing->entity_id !== $entityId) {
                throw ValidationException::withMessages([
                    'external_id' => [__('This external id is already linked to a different record.')],
                ]);
            }

            return $existing;
        }

        return ExternalReference::create([
            'source_system' => $source,
            'external_id' => $externalId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);
    }

    public function resolve(string $source, string $externalId, string $entityType): ?int
    {
        return ExternalReference::where('source_system', $source)
            ->where('external_id', $externalId)
            ->where('entity_type', $entityType)
            ->value('entity_id');
    }
}
