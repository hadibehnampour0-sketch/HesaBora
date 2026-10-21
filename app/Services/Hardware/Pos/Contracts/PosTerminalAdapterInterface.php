<?php

namespace App\Services\Hardware\Pos\Contracts;

use App\Models\PosTerminal;
use App\Services\Hardware\Pos\DTO\PosTransactionResult;

interface PosTerminalAdapterInterface
{
    /**
     * Send payment request (amount in Rials) to physical or virtual terminal.
     */
    public function sendPaymentRequest(PosTerminal $terminal, float $amountRial, string $traceNumber, array $options = []): PosTransactionResult;

    /**
     * Query or poll status of a pending transaction by trace number.
     */
    public function checkTransactionStatus(PosTerminal $terminal, string $traceNumber): PosTransactionResult;

    /**
     * Ping terminal connectivity over network or serial interface.
     */
    public function ping(PosTerminal $terminal): bool;
}
