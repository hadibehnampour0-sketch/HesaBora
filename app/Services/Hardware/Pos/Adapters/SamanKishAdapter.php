<?php

namespace App\Services\Hardware\Pos\Adapters;

use App\Models\PosTerminal;
use App\Services\Hardware\Pos\Contracts\PosTerminalAdapterInterface;
use App\Services\Hardware\Pos\DTO\PosTransactionResult;
use Illuminate\Support\Facades\Http;
use Throwable;

class SamanKishAdapter implements PosTerminalAdapterInterface
{
    public function sendPaymentRequest(PosTerminal $terminal, float $amountRial, string $traceNumber, array $options = []): PosTransactionResult
    {
        $ip = $terminal->ip_address ?? '127.0.0.1';
        $port = $terminal->port ?: 1394;
        $timeout = (int) ($terminal->metadata['timeout'] ?? 45);

        // SEP (Saman Electronic Payment) PC-POS protocol payload
        $payload = [
            'Amount' => (int) round($amountRial),
            'ResNum' => $traceNumber,
            'TerminalId' => $terminal->terminal_id ?? '',
            'PrintData' => $options['description'] ?? 'نرم‌افزار حسابداری دخل',
        ];

        try {
            $url = "http://{$ip}:{$port}/pos/pay";
            $response = Http::timeout($timeout)->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $status = (string) ($data['Status'] ?? $data['ResponseCode'] ?? '99');

                if ($status === '0' || $status === '00' || $status === 'Success') {
                    return PosTransactionResult::success(
                        rrn: (string) ($data['RRN'] ?? $data['RefNum'] ?? random_int(100000000000, 999999999999)),
                        terminalTraceNo: (string) ($data['TraceNo'] ?? null),
                        maskedCardNumber: (string) ($data['MaskedPan'] ?? $data['CardNo'] ?? null),
                        cardHolderName: $data['CardHolder'] ?? null,
                        responseCode: '00',
                        message: 'تراکنش با موفقیت در پایانه سامان‌کیش انجام شد.',
                        rawResponse: $data
                    );
                }

                if ($status === 'Cancel' || $status === 'EX') {
                    return PosTransactionResult::cancelled('تراکنش توسط مشتری در کارتخوان سامان‌کیش لغو شد.', $data);
                }

                return PosTransactionResult::failure(
                    message: $data['Message'] ?? "خطا در تراکنش سامان‌کیش (کد {$status})",
                    responseCode: $status,
                    rawResponse: $data
                );
            }
        } catch (Throwable $e) {
            return PosTransactionResult::failure(
                message: 'عدم پاسخگویی کارتخوان سامان‌کیش در شبکه: '.$e->getMessage(),
                responseCode: 'NETWORK_ERR'
            );
        }

        return PosTransactionResult::failure('خطای نامشخص در ارتباط با پایانه سامان‌کیش');
    }

    public function checkTransactionStatus(PosTerminal $terminal, string $traceNumber): PosTransactionResult
    {
        return PosTransactionResult::failure('استعلام تراکنش سامان‌کیش پشتیبانی نمی‌شود.');
    }

    public function ping(PosTerminal $terminal): bool
    {
        $ip = $terminal->ip_address ?? '127.0.0.1';
        $port = $terminal->port ?: 1394;

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
