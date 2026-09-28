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
     * @param  string  $rawBody  The exact undecoded request body, needed to verify an HMAC
     *                           signature (re-encoding JSON is not guaranteed byte-identical).
     *                           Optional so existing/simple adapters that don't sign need not
     *                           pass it.
     * @return list<CanonicalEvent>
     *
     * @throws \InvalidArgumentException when the payload/signature is not acceptable
     */
    public function normalize(array $rawPayload, array $headers, string $rawBody = ''): array;

    /**
     * Push this company's current available quantity for one mapped product to the platform
     * (design doc "outbound half" of the two-way stock sync). Implementations should be
     * idempotent (safe to call repeatedly with the same quantity).
     */
    public function pushInventory(string $externalProductId, float $quantity): void;
}
