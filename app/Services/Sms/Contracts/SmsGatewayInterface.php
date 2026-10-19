<?php

namespace App\Services\Sms\Contracts;

use App\Services\Sms\DTO\SmsSendResult;

interface SmsGatewayInterface
{
    /**
     * Send an SMS message, either standard or pattern/lookup based.
     */
    public function send(
        string $recipient,
        string $message,
        ?string $patternCode = null,
        array $tokens = []
    ): SmsSendResult;
}
