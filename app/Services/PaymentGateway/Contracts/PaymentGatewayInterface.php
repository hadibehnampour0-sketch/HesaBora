<?php

namespace App\Services\PaymentGateway\Contracts;

use App\Models\PaymentLink;
use App\Services\PaymentGateway\DTO\PaymentGatewayResponse;
use App\Services\PaymentGateway\DTO\PaymentVerificationResult;

interface PaymentGatewayInterface
{
    /**
     * Request a payment transaction from the gateway.
     */
    public function requestPayment(PaymentLink $link, string $callbackUrl): PaymentGatewayResponse;

    /**
     * Verify payment transaction after returning from gateway.
     */
    public function verifyPayment(PaymentLink $link, array $callbackData): PaymentVerificationResult;

    /**
     * Gateway machine name.
     */
    public function getName(): string;
}
