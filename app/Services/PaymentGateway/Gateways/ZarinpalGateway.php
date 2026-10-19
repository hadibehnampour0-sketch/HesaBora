<?php

namespace App\Services\PaymentGateway\Gateways;

use App\Models\PaymentLink;
use App\Services\PaymentGateway\Contracts\PaymentGatewayInterface;
use App\Services\PaymentGateway\DTO\PaymentGatewayResponse;
use App\Services\PaymentGateway\DTO\PaymentVerificationResult;
use Illuminate\Support\Facades\Http;

class ZarinpalGateway implements PaymentGatewayInterface
{
    public function __construct(
        protected ?string $merchantId = null,
        protected bool $isSandbox = false
    ) {
        $this->merchantId = $merchantId ?: config('services.zarinpal.merchant_id', 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx');
    }

    public function getName(): string
    {
        return 'zarinpal';
    }

    public function requestPayment(PaymentLink $link, string $callbackUrl): PaymentGatewayResponse
    {
        $baseUrl = $this->isSandbox
            ? 'https://sandbox.zarinpal.com/pg/v4/payment/request.json'
            : 'https://payment.zarinpal.com/pg/v4/payment/request.json';

        $payUrl = $this->isSandbox
            ? 'https://sandbox.zarinpal.com/pg/StartPay/'
            : 'https://payment.zarinpal.com/pg/StartPay/';

        // Convert Rials to Tomans or Rials as required (Zarinpal v4 expects Tomans or Rials depending on currency; standard is Rials)
        $amount = (int) $link->amount;

        try {
            $response = Http::timeout(10)->post($baseUrl, [
                'merchant_id' => $this->merchantId,
                'amount' => $amount,
                'description' => 'پرداخت فاکتور ' . ($link->invoice?->number ?? $link->id),
                'callback_url' => $callbackUrl,
                'metadata' => [
                    'mobile' => $link->customer?->phone,
                    'order_id' => (string) $link->id,
                ],
            ]);

            $json = $response->json();
            $code = $json['data']['code'] ?? null;
            $authority = $json['data']['authority'] ?? null;

            if ($code === 100 && $authority) {
                return PaymentGatewayResponse::success(
                    redirectUrl: $payUrl . $authority,
                    gatewayToken: $authority,
                    raw: $json
                );
            }

            $errorMessage = $json['errors']['message'] ?? 'خطا در ارتباط با درگاه زرین‌پال (کد: ' . ($code ?? 'نامشخص') . ')';
            return PaymentGatewayResponse::failure($errorMessage, $json ?? []);
        } catch (\Throwable $e) {
            return PaymentGatewayResponse::failure('خطای سرور در اتصال به زرین‌پال: ' . $e->getMessage());
        }
    }

    public function verifyPayment(PaymentLink $link, array $callbackData): PaymentVerificationResult
    {
        $authority = $callbackData['Authority'] ?? $callbackData['authority'] ?? $link->gateway_token;
        $status = $callbackData['Status'] ?? $callbackData['status'] ?? null;

        if ($status !== 'OK') {
            return PaymentVerificationResult::failure('تراکنش توسط کاربر لغو شد.', $callbackData);
        }

        $baseUrl = $this->isSandbox
            ? 'https://sandbox.zarinpal.com/pg/v4/payment/verify.json'
            : 'https://payment.zarinpal.com/pg/v4/payment/verify.json';

        try {
            $response = Http::timeout(10)->post($baseUrl, [
                'merchant_id' => $this->merchantId,
                'amount' => (int) $link->amount,
                'authority' => $authority,
            ]);

            $json = $response->json();
            $code = $json['data']['code'] ?? null;
            $refId = $json['data']['ref_id'] ?? null;
            $cardPan = $json['data']['card_pan'] ?? null;

            if (in_array($code, [100, 101], true)) {
                return PaymentVerificationResult::success(
                    referenceNumber: (string) $refId,
                    cardPan: $cardPan,
                    raw: $json
                );
            }

            $msg = $json['errors']['message'] ?? 'تراکنش در زرین‌پال تایید نشد (کد: ' . ($code ?? 'نامشخص') . ')';
            return PaymentVerificationResult::failure($msg, $json ?? []);
        } catch (\Throwable $e) {
            return PaymentVerificationResult::failure('خطا در اعتبارسنجی زرین‌پال: ' . $e->getMessage());
        }
    }
}
