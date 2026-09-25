<?php

namespace App\Services\Integration;

/**
 * Outcome of handling one canonical event. Mirrors the sync engine's three outcome classes
 * (D-076): a Business Rejection is final and visible; PendingDependency waits for another
 * event (never dropped); a thrown exception is a Technical Retry (see IntegrationHub).
 */
final class HandlerResult
{
    private function __construct(public readonly string $status, public readonly ?string $message = null) {}

    public static function processed(?string $message = null): self
    {
        return new self(IntegrationHub::PROCESSED, $message);
    }

    public static function pendingDependency(string $message): self
    {
        return new self(IntegrationHub::PENDING_DEPENDENCY, $message);
    }

    public static function rejected(string $message): self
    {
        return new self(IntegrationHub::REJECTED, $message);
    }
}
