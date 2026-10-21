<?php

namespace App\Services\Hardware\Pos\Adapters;

use App\Models\PosTerminal;
use App\Services\Hardware\Pos\Contracts\PosTerminalAdapterInterface;
use App\Services\Hardware\Pos\DTO\PosTransactionResult;
use Illuminate\Support\Facades\Http;
use Throwable;

class SadadAdapter implements PosTerminalAdapterInterface
{
    public function sendPaymentRequest(PosTerminal $terminal, float $amountRial, string $traceNumber, array $options = []): PosTransactionResult
    {
        $ip = $terminal->ip_address ?? '127.0.0.1';
        $port = $terminal->port ?: 9000;
        $timeout = (int) ($terminal->metadata['timeout'] ?? 45);

        $payload = [
            'Amount' => (int) round($amountRial),
            'InvoiceNumber' => $traceNumber,
            'TerminalId' => $terminal->terminal_id ?? '',
        ];

        try {
            $url = "http://{$ip}:{$port}/sadad/payment";
            $response = Http::timeout($timeout)->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $respCode = (string) ($data['ResultCode'] ?? $data['Status'] ?? '99');

                if ($respCode === '0' || $respCode === '00') {
                    return PosTransactionResult::success(
                        rrn: (string) ($data['RRN'] ?? random_int(100000000000, 999999999999)),
                        terminalTraceNo: (string) ($data['TraceNo'] ?? null),
                        maskedCardNumber: (string) ($data['CardNumber'] ?? null),
                        cardHolderName: $data['CardHolderName'] ?? null,
                        responseCode: '00',
                        message: 'تراکنش با موفقیت در پایانه سداد انجام شد.',
                        rawResponse: $data
                    );
                }

                if ($respCode === 'CANCEL' || $respCode === 'EX') {
                    return PosTransactionResult::cancelled('تراکنش توسط کاربر لغو گردید.', $data);
                }

                return PosTransactionResult::failure(
                    message: $data['Message'] ?? "خطا در تراکنش سداد (کد {$respCode})",
                    responseCode: $respCode,
                    rawResponse: $data
                );
            }
        } catch (Throwable $e) {
            return PosTransactionResult::failure(
                message: 'عدم پاسخگویی کارتخوان سداد در شبکه: '.$e->getMessage(),
                responseCode: 'NETWORK_ERR'
            );
        }

        return PosTransactionResult::failure('خطای نامشخص در ارتباط با پایانه سداد');
    }

    public function checkTransactionStatus(PosTerminal $terminal, string $traceNumber): PosTransactionResult
    {
        return PosTransactionResult::failure('استعلام تراکنش سداد پشتیبانی نمی‌شود.');
    }

    public function ping(PosTerminal $terminal): bool
    {
        $ip = $terminal->ip_address ?? '127.0.0.1';
        $port = $terminal->port ?: 9000;

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
