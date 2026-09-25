<?php

namespace App\Services\Integration;

/** One adapter per external platform: verifies and translates raw platform payloads into canonical events. */
interface IntegrationAdapter
{
    /** Stable source id, e.g. "woocommerce". Used in the idempotency key. */
    public function source(): string;

    /**
     * @param  array<string, mixed>  $rawPayload
     * @param  array<string, string>  $headers
     * @return list<CanonicalEvent>
     *
     * @throws \InvalidArgumentException when the payload/signature is not acceptable
     */
    public function normalize(array $rawPayload, array $headers): array;
}
