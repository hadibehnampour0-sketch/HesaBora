<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Services\WebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

/**
 * P6 (API v1 Platform). One attempt per job run. On failure, re-dispatches
 * itself with a delay (rather than relying on Laravel's own queue-level
 * `$tries`/`backoff()`, which only apply when the underlying queue
 * connection actually replays failed jobs -- this app's default
 * `QUEUE_CONNECTION=sync` does not) so retry behavior is identical
 * regardless of which queue driver is configured.
 */
class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly int $webhookDeliveryId) {}

    public function handle(): void
    {
        $delivery = WebhookDelivery::with('webhook')->find($this->webhookDeliveryId);

        if (! $delivery || ! $delivery->webhook) {
            return;
        }

        $body = json_encode([
            'event' => $delivery->event_type,
            'payload' => $delivery->payload,
        ]);

        $delivery->attempt_count++;

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Webhook-Signature' => WebhookService::signature($delivery->webhook->secret, $body),
            ])->withBody($body, 'application/json')->timeout(10)->post($delivery->webhook->url);

            $succeeded = $response->successful();

            $delivery->update([
                'status' => $succeeded ? 'delivered' : ($delivery->attempt_count >= self::MAX_ATTEMPTS ? 'failed' : 'pending'),
                'last_response_status' => $response->status(),
                'last_response_body' => substr($response->body(), 0, 2000),
                'next_retry_at' => $succeeded || $delivery->attempt_count >= self::MAX_ATTEMPTS
                    ? null
                    : now()->addMinutes(2 ** $delivery->attempt_count),
            ]);
        } catch (\Throwable $e) {
            $delivery->update([
                'status' => $delivery->attempt_count >= self::MAX_ATTEMPTS ? 'failed' : 'pending',
                'last_response_status' => null,
                'last_response_body' => substr($e->getMessage(), 0, 2000),
                'next_retry_at' => $delivery->attempt_count >= self::MAX_ATTEMPTS
                    ? null
                    : now()->addMinutes(2 ** $delivery->attempt_count),
            ]);
        }

        if ($delivery->status === 'pending' && $delivery->attempt_count < self::MAX_ATTEMPTS) {
            self::dispatch($delivery->id)->delay($delivery->next_retry_at);
        }
    }
}
