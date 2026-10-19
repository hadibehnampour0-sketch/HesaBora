<?php

namespace App\Services\PaymentGateway\Gateways;

use App\Models\PaymentLink;
use App\Services\PaymentGateway\Contracts\PaymentGatewayInterface;
use App\Services\PaymentGateway\DTO\PaymentGatewayResponse;
use App\Services\PaymentGateway\DTO\PaymentVerificationResult;
use Illuminate\Support\Str;

class SandboxGateway implements PaymentGatewayInterface
{
    public function getName(): string
    {
        return 'sandbox';
    }

    public function requestPayment(PaymentLink $link, string $callbackUrl): PaymentGatewayResponse
    {
        $token = 'SBX-' . strtoupper(Str::random(16));
        $separator = str_contains($callbackUrl, '?') ? '&' : '?';
        $redirectUrl = $callbackUrl . $separator . http_build_query([
            'status' => 'OK',
            'authority' => $token,
        ]);

        return PaymentGatewayResponse::success(
            redirectUrl: $redirectUrl,
            gatewayToken: $token,
            raw: ['status' => 'OK', 'authority' => $token]
        );
    }

    public function verifyPayment(PaymentLink $link, array $callbackData): PaymentVerificationResult
    {
        $status = $callbackData['status'] ?? $callbackData['Status'] ?? null;
        if ($status !== 'OK' && $status !== '100' && $status !== 'success') {
            return PaymentVerificationResult::failure(
                errorMessage: 'پرداخت توسط کاربر در درگاه آزمایشی لغو شد.',
                raw: $callbackData
            );
        }

        $reference = 'REF-' . strtoupper(Str::random(10));
        $cardPan = '603799******' . mt_rand(1000, 9999);

        return PaymentVerificationResult::success(
            referenceNumber: $reference,
            cardPan: $cardPan,
            raw: $callbackData
        );
    }
}
