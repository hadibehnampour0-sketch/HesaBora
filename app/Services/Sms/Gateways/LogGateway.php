<?php

namespace App\Services\Sms\Gateways;

use App\Services\Sms\Contracts\SmsGatewayInterface;
use App\Services\Sms\DTO\SmsSendResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LogGateway implements SmsGatewayInterface
{
    public function send(
        string $recipient,
        string $message,
        ?string $patternCode = null,
        array $tokens = []
    ): SmsSendResult {
        $messageId = 'LOG-' . Str::uuid()->toString();

        Log::info("[Dakhl SMS Dispatch] Recipient: {$recipient} | Pattern: {$patternCode} | Message: {$message}", [
            'tokens' => $tokens,
            'message_id' => $messageId,
        ]);

        return SmsSendResult::success($messageId, [
            'driver' => 'log',
            'recipient' => $recipient,
            'message' => $message,
            'pattern' => $patternCode,
            'tokens' => $tokens,
        ]);
    }
}
