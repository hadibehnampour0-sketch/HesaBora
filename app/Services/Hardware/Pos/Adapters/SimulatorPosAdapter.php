<?php

namespace App\Services\Hardware\Pos\Adapters;

use App\Models\PosTerminal;
use App\Services\Hardware\Pos\Contracts\PosTerminalAdapterInterface;
use App\Services\Hardware\Pos\DTO\PosTransactionResult;

class SimulatorPosAdapter implements PosTerminalAdapterInterface
{
    public function sendPaymentRequest(PosTerminal $terminal, float $amountRial, string $traceNumber, array $options = []): PosTransactionResult
    {
        // Support simulation mode triggers in options: 'success', 'cancel', 'timeout', 'declined'
        $scenario = $options['simulate_scenario'] ?? 'success';

        if ($scenario === 'cancel') {
            return PosTransactionResult::cancelled(
                message: 'مشتری از انجام تراکنش در پایانه شبیه‌ساز انصراف داد.',
                rawResponse: ['simulated' => true, 'scenario' => 'cancel']
            );
        }

        if ($scenario === 'timeout') {
            return PosTransactionResult::timeout(
                message: 'زمان مجاز کشیدن کارت در شبیه‌ساز به پایان رسید.',
                rawResponse: ['simulated' => true, 'scenario' => 'timeout']
            );
        }

        if ($scenario === 'declined') {
            return PosTransactionResult::failure(
                message: 'موجودی حساب مشتری کافی نیست (کد ۵۱).',
                responseCode: '51',
                status: 'failed',
                rawResponse: ['simulated' => true, 'scenario' => 'declined', 'code' => '51']
            );
        }

        if ($scenario === 'pending') {
            return PosTransactionResult::pending(
                message: 'شبیه‌ساز در انتظار ورود رمز مشتری است...',
                rawResponse: ['simulated' => true, 'scenario' => 'pending']
            );
        }

        // Default: generate realistic successful Iranian banking RRN and masked PAN
        $rrn = (string) random_int(100000000000, 999999999999);
        $terminalTrace = (string) random_int(100000, 999999);
        $samplePrefixes = ['603799', '610433', '589210', '621986', '502229'];
        $prefix = $samplePrefixes[array_rand($samplePrefixes)];
        $maskedPan = $prefix.'******'.random_int(1000, 9999);

        return PosTransactionResult::success(
            rrn: $rrn,
            terminalTraceNo: $terminalTrace,
            maskedCardNumber: $maskedPan,
            cardHolderName: 'مشتری فروشگاهی (تست شبیه‌ساز)',
            responseCode: '00',
            message: 'تراکنش با موفقیت در شبیه‌ساز کارتخوان به انجام رسید.',
            rawResponse: [
                'simulated' => true,
                'amount' => $amountRial,
                'rrn' => $rrn,
                'trace_no' => $terminalTrace,
                'pan' => $maskedPan,
                'date' => now()->toDateTimeString(),
            ]
        );
    }

    public function checkTransactionStatus(PosTerminal $terminal, string $traceNumber): PosTransactionResult
    {
        // In simulation, checking status returns success
        $rrn = (string) random_int(100000000000, 999999999999);

        return PosTransactionResult::success(
            rrn: $rrn,
            terminalTraceNo: '998877',
            maskedCardNumber: '603799******1122',
            cardHolderName: 'مشتری شبیه‌ساز',
            responseCode: '00',
            message: 'استعلام تراکنش شبیه‌ساز موفق بود.'
        );
    }

    public function ping(PosTerminal $terminal): bool
    {
        return true;
    }
}
