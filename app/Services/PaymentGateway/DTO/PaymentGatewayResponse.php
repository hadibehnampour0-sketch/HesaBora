<?php

namespace App\Services\PaymentGateway\DTO;

class PaymentGatewayResponse
{
    public function __construct(
        public readonly bool $successful,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $gatewayToken = null,
        public readonly ?string $errorMessage = null,
        public readonly array $raw = []
    ) {}

    public static function success(string $redirectUrl, string $gatewayToken, array $raw = []): self
    {
        return new self(
            successful: true,
            redirectUrl: $redirectUrl,
            gatewayToken: $gatewayToken,
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
