<?php

namespace App\Support\Moadian;

/**
 * Clean-Room Moadian API Response Wrapper.
 *
 * License: MIT
 */
class MoadianResponse
{
    public function __construct(
        private array $body,
        private int $statusCode = 200,
        private bool $success = true,
    ) {}

    public function getBody(): array
    {
        return $this->body;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function isSuccessful(): bool
    {
        return $this->success && $this->statusCode >= 200 && $this->statusCode < 300;
    }
}
