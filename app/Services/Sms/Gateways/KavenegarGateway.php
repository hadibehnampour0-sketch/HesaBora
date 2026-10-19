<?php

namespace App\Services\Sms\Gateways;

use App\Services\Sms\Contracts\SmsGatewayInterface;
use App\Services\Sms\DTO\SmsSendResult;
use Illuminate\Support\Facades\Http;
use Throwable;

class KavenegarGateway implements SmsGatewayInterface
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
            return SmsSendResult::failure('Kavenegar API key is missing.');
        }

        try {
            // If pattern code is provided, use Kavenegar Verify Lookup endpoint (fast & passes blacklists)
            if (!empty($patternCode)) {
                $params = [
                    'receptor' => $recipient,
                    'template' => $patternCode,
                ];

                // Kavenegar expects token, token2, token3, token10, token20
                $tokenKeys = ['token', 'token2', 'token3', 'token10', 'token20'];
                $i = 0;
                foreach ($tokens as $val) {
                    if (isset($tokenKeys[$i])) {
                        $params[$tokenKeys[$i]] = str_replace(' ', '-', (string) $val);
                        $i++;
                    }
                }

                $response = Http::timeout(10)->asForm()->post(
                    "https://api.kavenegar.com/v1/{$this->apiKey}/verify/lookup.json",
                    $params
                );
            } else {
                // Regular SMS
                $response = Http::timeout(10)->asForm()->post(
                    "https://api.kavenegar.com/v1/{$this->apiKey}/sms/send.json",
                    [
                        'receptor' => $recipient,
                        'sender' => $this->senderNumber,
                        'message' => $message,
                    ]
                );
            }

            if ($response->successful()) {
                $data = $response->json();
                $messageId = (string) ($data['entries'][0]['messageid'] ?? 'KVN-' . time());
                return SmsSendResult::success($messageId, $data ?? []);
            }

            return SmsSendResult::failure(
                $response->body() ?: 'Kavenegar request failed with status ' . $response->status(),
                $response->json() ?? []
            );
        } catch (Throwable $e) {
            return SmsSendResult::failure($e->getMessage());
        }
    }
}
