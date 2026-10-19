<?php

namespace App\Services\Sms\Gateways;

use App\Services\Sms\Contracts\SmsGatewayInterface;
use App\Services\Sms\DTO\SmsSendResult;
use Illuminate\Support\Facades\Http;
use Throwable;

class FarazSmsGateway implements SmsGatewayInterface
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
            return SmsSendResult::failure('FarazSMS API key is missing.');
        }

        try {
            $headers = [
                'Authorization' => "AccessKey {$this->apiKey}",
                'Content-Type' => 'application/json',
            ];

            if (!empty($patternCode)) {
                $response = Http::withHeaders($headers)->timeout(10)->post('https://edge.ippanel.com/api/v1/messages/patterns/send', [
                    'pattern_code' => $patternCode,
                    'originator' => $this->senderNumber ?: '+983000505',
                    'recipient' => $recipient,
                    'values' => (object) $tokens,
                ]);
            } else {
                $response = Http::withHeaders($headers)->timeout(10)->post('https://edge.ippanel.com/api/v1/messages/send', [
                    'originator' => $this->senderNumber ?: '+983000505',
                    'recipients' => [$recipient],
                    'message' => $message,
                ]);
            }

            if ($response->successful()) {
                $data = $response->json();
                $bulkId = (string) ($data['data']['message_id'] ?? $data['data']['bulk_id'] ?? 'FARAZ-' . time());
                return SmsSendResult::success($bulkId, $data ?? []);
            }

            return SmsSendResult::failure(
                $response->body() ?: 'FarazSMS request failed',
                $response->json() ?? []
            );
        } catch (Throwable $e) {
            return SmsSendResult::failure($e->getMessage());
        }
    }
}
