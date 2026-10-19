<?php

namespace App\Services\Sms\Gateways;

use App\Services\Sms\Contracts\SmsGatewayInterface;
use App\Services\Sms\DTO\SmsSendResult;
use Illuminate\Support\Facades\Http;
use Throwable;

class MelipayamakGateway implements SmsGatewayInterface
{
    public function __construct(
        protected readonly string $apiKey,
        protected readonly ?string $senderNumber = null
    ) {}

    public function send(
        string $recipient,
        string $message,
        ?string $patternCode = null,
        array $tokens = []
    ): SmsSendResult {
        if (empty($this->apiKey)) {
            return SmsSendResult::failure('Melipayamak API key is missing.');
        }

        try {
            // Melipayamak REST pattern or regular
            if (!empty($patternCode)) {
                $response = Http::timeout(10)->post("https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber", [
                    'userName' => '',
                    'password' => $this->apiKey,
                    'text' => array_values($tokens),
                    'to' => $recipient,
                    'bodyId' => (int) $patternCode,
                ]);
            } else {
                $response = Http::timeout(10)->post("https://rest.payamak-panel.com/api/SendSMS/SendSMS", [
                    'userName' => '',
                    'password' => $this->apiKey,
                    'to' => $recipient,
                    'from' => $this->senderNumber,
                    'text' => $message,
                    'isFlash' => false,
                ]);
            }

            if ($response->successful()) {
                $data = $response->json();
                $recId = (string) ($data['Value'] ?? $data['RetStatus'] ?? 'MELI-' . time());
                return SmsSendResult::success($recId, $data ?? []);
            }

            return SmsSendResult::failure(
                $response->body() ?: 'Melipayamak request failed',
                $response->json() ?? []
            );
        } catch (Throwable $e) {
            return SmsSendResult::failure($e->getMessage());
        }
    }
}
