<?php

namespace App\Services\Integration;

/** A platform-neutral event. `payload` is already normalised by the adapter; the hub never sees raw platform JSON. */
final class CanonicalEvent
{
    public function __construct(
        public readonly string $source,
        public readonly string $externalId,
        public readonly IntegrationEventType $type,
        public readonly array $payload,
    ) {}

    /** Stable hash used to tell a harmless redelivery from a conflicting one. */
    public function payloadHash(): string
    {
        return hash('sha256', json_encode($this->canonicalise($this->payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function canonicalise(array $value): array
    {
        ksort($value);

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonicalise($item);
            }
        }

        return $value;
    }
}
