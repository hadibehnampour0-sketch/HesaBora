<?php

namespace App\Services\Hardware\Pos\DTO;

class PosTransactionResult
{
    public function __construct(
        public bool $success,
        public string $status,
        public ?string $rrn = null,
        public ?string $terminalTraceNo = null,
        public ?string $maskedCardNumber = null,
        public ?string $cardHolderName = null,
        public ?string $responseCode = null,
        public ?string $message = null,
        public array $rawResponse = []
    ) {}

    public static function success(
        string $rrn,
        ?string $terminalTraceNo = null,
        ?string $maskedCardNumber = null,
        ?string $cardHolderName = null,
        string $responseCode = '00',
        string $message = 'تراکنش با موفقیت انجام شد.',
        array $rawResponse = []
    ): self {
        return new self(
            success: true,
            status: 'success',
            rrn: $rrn,
            terminalTraceNo: $terminalTraceNo,
            maskedCardNumber: $maskedCardNumber,
            cardHolderName: $cardHolderName,
            responseCode: $responseCode,
            message: $message,
            rawResponse: $rawResponse
        );
    }

    public static function pending(
        string $message = 'دستگاه در انتظار کارت کشیدن و ورود رمز مشتری است.',
        array $rawResponse = []
    ): self {
        return new self(
            success: false,
            status: 'pending',
            responseCode: 'WAITING',
            message: $message,
            rawResponse: $rawResponse
        );
    }

    public static function failure(
        string $message,
        string $responseCode = '99',
        string $status = 'failed',
        array $rawResponse = []
    ): self {
        return new self(
            success: false,
            status: $status,
            responseCode: $responseCode,
            message: $message,
            rawResponse: $rawResponse
        );
    }

    public static function cancelled(
        string $message = 'تراکنش توسط کاربر یا مشتری لغو گردید.',
        array $rawResponse = []
    ): self {
        return new self(
            success: false,
            status: 'cancelled',
            responseCode: 'EX',
            message: $message,
            rawResponse: $rawResponse
        );
    }

    public static function timeout(
        string $message = 'زمان انتظار برای کشیدن کارت به پایان رسید (Timeout).',
        array $rawResponse = []
    ): self {
        return new self(
            success: false,
            status: 'timeout',
            responseCode: 'TO',
            message: $message,
            rawResponse: $rawResponse
        );
    }
}
