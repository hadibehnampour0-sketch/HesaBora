<?php

namespace App\Services\Sms\DTO;

class SmsSendResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $messageId = null,
        public readonly ?string $error = null,
        public readonly array $rawResponse = []
    ) {}

    public static function success(string $messageId, array $rawResponse = []): self
    {
        return new self(
            success: true,
            messageId: $messageId,
            error: null,
            rawResponse: $rawResponse
        );
    }

    public static function failure(string $error, array $rawResponse = []): self
    {
        return new self(
            success: false,
            messageId: null,
            error: $error,
            rawResponse: $rawResponse
        );
    }
}
