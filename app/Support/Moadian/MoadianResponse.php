<?php

namespace App\Support\Moadian;

/**
 * Encapsulated Gateway HTTP response wrapper.
 */
class MoadianResponse
{
    public function __construct(
        private readonly array $body,
        private readonly int $statusCode = 200
    ) {}

    public function getBody(): array
    {
        return $this->body;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function isSuccess(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }
}
