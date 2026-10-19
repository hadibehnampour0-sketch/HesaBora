<?php

namespace App\Jobs;

use App\Models\SmsLog;
use App\Services\Sms\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        public readonly int $smsLogId,
        public readonly ?string $patternCode = null,
        public readonly array $tokens = []
    ) {}

    public function handle(SmsService $smsService): void
    {
        $smsLog = SmsLog::withoutGlobalScopes()->find($this->smsLogId);
        if (!$smsLog || $smsLog->status === 'sent') {
            return;
        }

        $smsService->executeSend($smsLog, $this->patternCode, $this->tokens);
    }

    public function failed(?Throwable $exception): void
    {
        $smsLog = SmsLog::withoutGlobalScopes()->find($this->smsLogId);
        if ($smsLog && $smsLog->status !== 'sent') {
            $smsLog->update([
                'status' => 'failed',
                'error_message' => $exception?->getMessage() ?? 'Job failed after max attempts',
            ]);
        }
    }
}
