<?php

namespace App\Services\Pos\DTO;

use App\Models\Invoice;

class PosCheckoutResult
{
    public function __construct(
        public readonly bool $successful,
        public readonly ?Invoice $invoice = null,
        public readonly ?string $receiptBase64 = null,
        public readonly ?string $rawEscPos = null,
        public readonly ?string $errorMessage = null,
        public readonly array $metadata = []
    ) {}

    public static function success(Invoice $invoice, string $receiptBase64, string $rawEscPos, array $metadata = []): self
    {
        return new self(
            successful: true,
            invoice: $invoice,
            receiptBase64: $receiptBase64,
            rawEscPos: $rawEscPos,
            metadata: $metadata
        );
    }

    public static function failure(string $errorMessage): self
    {
        return new self(
            successful: false,
            errorMessage: $errorMessage
        );
    }
}
