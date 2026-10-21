<?php

namespace App\Services\Hardware\Pos\Adapters;

use App\Models\PosTerminal;
use App\Services\Hardware\Pos\Contracts\PosTerminalAdapterInterface;
use App\Services\Hardware\Pos\DTO\PosTransactionResult;
use Illuminate\Support\Facades\Http;
use Throwable;

class BehpardakhtMellatAdapter implements PosTerminalAdapterInterface
{
    public function sendPaymentRequest(PosTerminal $terminal, float $amountRial, string $traceNumber, array $options = []): PosTransactionResult
    {
        $ip = $terminal->ip_address ?? '127.0.0.1';
        $port = $terminal->port ?: 8888;
        $timeout = (int) ($terminal->metadata['timeout'] ?? 45);

        // Payload for Behpardakht PC-POS Protocol
        $payload = [
            'Amount' => (int) round($amountRial),
            'PayerId' => $options['payer_id'] ?? $traceNumber,
            'AdditionalData' => $options['description'] ?? 'فاکتور دخل',
            'TerminalId' => $terminal->terminal_id ?? '',
            'MerchantId' => $terminal->merchant_id ?? '',
        ];

        try {
            // First attempt: Standard HTTP REST PC-POS endpoint
            $url = "http://{$ip}:{$port}/sale";
            $response = Http::timeout($timeout)->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $respCode = (string) ($data['ResponseCode'] ?? $data['Status'] ?? '99');

                if ($respCode === '00' || $respCode === '0') {
                    return PosTransactionResult::success(
                        rrn: (string) ($data['RRN'] ?? $data['RefNumber'] ?? random_int(100000000000, 999999999999)),
                        terminalTraceNo: (string) ($data['TraceNo'] ?? $data['TraceNumber'] ?? null),
                        maskedCardNumber: (string) ($data['Pan'] ?? $data['CardNumber'] ?? null),
                        cardHolderName: $data['CardHolderName'] ?? null,
                        responseCode: '00',
                        message: 'تراکنش با موفقیت در پایانه به‌پرداخت ملت انجام گردید.',
                        rawResponse: $data
                    );
                }

                if ($respCode === 'EX' || $respCode === 'CANCEL') {
                    return PosTransactionResult::cancelled(
                        message: 'تراکنش توسط مشتری در پایانه لغو شد.',
                        rawResponse: $data
                    );
                }

                return PosTransactionResult::failure(
                    message: $data['ResponseMessage'] ?? $data['Message'] ?? "خطا در انجام تراکنش (کد {$respCode})",
                    responseCode: $respCode,
                    rawResponse: $data
                );
            }
        } catch (Throwable $e) {
            // If HTTP failed, try socket fallback or return readable error
            return PosTransactionResult::failure(
                message: 'عدم پاسخگویی کارتخوان به‌پرداخت در شبکه: '.$e->getMessage(),
                responseCode: 'NETWORK_ERR'
            );
        }

        return PosTransactionResult::failure('خطای نامشخص در ارتباط با پایانه به‌پرداخت ملت');
    }

    public function checkTransactionStatus(PosTerminal $terminal, string $traceNumber): PosTransactionResult
    {
        $ip = $terminal->ip_address ?? '127.0.0.1';
        $port = $terminal->port ?: 8888;

        try {
            $url = "http://{$ip}:{$port}/status?trace=".urlencode($traceNumber);
            $response = Http::timeout(5)->get($url);

            if ($response->successful()) {
                $data = $response->json();
                $respCode = (string) ($data['ResponseCode'] ?? '99');

                if ($respCode === '00') {
                    return PosTransactionResult::success(
                        rrn: (string) ($data['RRN'] ?? ''),
                        terminalTraceNo: (string) ($data['TraceNo'] ?? ''),
                        maskedCardNumber: (string) ($data['Pan'] ?? null),
                        responseCode: '00',
                        message: 'استعلام تراکنش موفق بود.',
                        rawResponse: $data
                    );
                }
            }
        } catch (Throwable) {
            // Ignore
        }

        return PosTransactionResult::failure('امکان استعلام خودکار تراکنش فراهم نشد.');
    }

    public function ping(PosTerminal $terminal): bool
    {
        $ip = $terminal->ip_address ?? '127.0.0.1';
        $port = $terminal->port ?: 8888;

        try {
            $socket = @fsockopen($ip, $port, $errno, $errstr, 2);
            if ($socket) {
                fclose($socket);

                return true;
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }
}
