<?php

namespace App\Services\PaymentGateway\DTO;

class PaymentVerificationResult
{
    public function __construct(
        public readonly bool $successful,
        public readonly ?string $referenceNumber = null,
        public readonly ?string $cardPan = null,
        public readonly ?string $errorMessage = null,
        public readonly array $raw = []
    ) {}

    public static function success(string $referenceNumber, ?string $cardPan = null, array $raw = []): self
    {
        return new self(
            successful: true,
            referenceNumber: $referenceNumber,
            cardPan: $cardPan,
            raw: $raw
        );
    }

    public static function failure(string $errorMessage, array $raw = []): self
    {
        return new self(
            successful: false,
            errorMessage: $errorMessage,
            raw: $raw
        );
    }
}
