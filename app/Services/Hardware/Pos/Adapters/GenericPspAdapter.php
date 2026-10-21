<?php

namespace App\Services\Hardware\Pos\Adapters;

use App\Models\PosTerminal;
use App\Services\Hardware\Pos\Contracts\PosTerminalAdapterInterface;
use App\Services\Hardware\Pos\DTO\PosTransactionResult;
use Illuminate\Support\Facades\Http;
use Throwable;

class GenericPspAdapter implements PosTerminalAdapterInterface
{
    public function sendPaymentRequest(PosTerminal $terminal, float $amountRial, string $traceNumber, array $options = []): PosTransactionResult
    {
        $ip = $terminal->ip_address ?? '127.0.0.1';
        $port = $terminal->port ?: 8080;
        $timeout = (int) ($terminal->metadata['timeout'] ?? 45);

        $payload = [
            'amount' => (int) round($amountRial),
            'trace_number' => $traceNumber,
            'terminal_id' => $terminal->terminal_id ?? '',
            'merchant_id' => $terminal->merchant_id ?? '',
            'description' => $options['description'] ?? 'حسابداری دخل',
        ];

        try {
            $url = "http://{$ip}:{$port}/pay";
            $response = Http::timeout($timeout)->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $respCode = (string) ($data['response_code'] ?? $data['ResponseCode'] ?? $data['status'] ?? '99');

                if ($respCode === '00' || $respCode === '0') {
                    return PosTransactionResult::success(
                        rrn: (string) ($data['rrn'] ?? $data['RRN'] ?? random_int(100000000000, 999999999999)),
                        terminalTraceNo: (string) ($data['trace_no'] ?? $data['TraceNo'] ?? null),
                        maskedCardNumber: (string) ($data['card_number'] ?? $data['Pan'] ?? null),
                        cardHolderName: $data['card_holder'] ?? null,
                        responseCode: '00',
                        message: 'تراکنش با موفقیت در پایانه کارتخوان انجام گردید.',
                        rawResponse: $data
                    );
                }

                if ($respCode === 'EX' || $respCode === 'CANCEL') {
                    return PosTransactionResult::cancelled('تراکنش توسط کاربر در پایانه لغو شد.', $data);
                }

                return PosTransactionResult::failure(
                    message: $data['message'] ?? "خطا در انجام تراکنش (کد {$respCode})",
                    responseCode: $respCode,
                    rawResponse: $data
                );
            }
        } catch (Throwable $e) {
            return PosTransactionResult::failure(
                message: 'عدم پاسخگویی پایانه کارتخوان در شبکه: '.$e->getMessage(),
                responseCode: 'NETWORK_ERR'
            );
        }

        return PosTransactionResult::failure('خطای نامشخص در ارتباط با پایانه');
    }

    public function checkTransactionStatus(PosTerminal $terminal, string $traceNumber): PosTransactionResult
    {
        return PosTransactionResult::failure('استعلام خودکار تراکنش فراهم نیست.');
    }

    public function ping(PosTerminal $terminal): bool
    {
        $ip = $terminal->ip_address ?? '127.0.0.1';
        $port = $terminal->port ?: 8080;

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
