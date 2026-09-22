<?php

namespace App\Services;

use App\Jobs\DeliverWebhookJob;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Support\Str;

/**
 * P6 (API v1 Platform). Pure additive notification layer -- dispatching an
 * event here never affects the outcome of whatever domain action triggered
 * it (invoice approval, cheque transition, etc.); a webhook endpoint being
 * down or slow never blocks or fails the underlying request, since
 * delivery always happens through a queued job.
 */
class WebhookService
{
    public function register(int $companyId, string $url, array $eventTypes, int $createdBy): Webhook
    {
        return Webhook::create([
            'company_id' => $companyId,
            'url' => $url,
            'secret' => Str::random(40),
            'event_types' => $eventTypes,
            'is_active' => true,
            'created_by' => $createdBy,
        ]);
    }

    public function deactivate(Webhook $webhook): void
    {
        $webhook->update(['is_active' => false]);
    }

    /**
     * Fan out one domain event to every active webhook in the given
     * company subscribed to it. Each match gets its own WebhookDelivery
     * row and its own queued delivery attempt.
     */
    public function dispatchEvent(string $eventType, array $payload, int $companyId): void
    {
        $webhooks = Webhook::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->get()
            ->filter(fn (Webhook $webhook) => in_array($eventType, $webhook->event_types, true));

        foreach ($webhooks as $webhook) {
            $delivery = WebhookDelivery::create([
                'webhook_id' => $webhook->id,
                'event_type' => $eventType,
                'payload' => $payload,
                'status' => 'pending',
                'attempt_count' => 0,
            ]);

            DeliverWebhookJob::dispatch($delivery->id);
        }
    }

    public static function signature(string $secret, string $body): string
    {
        return hash_hmac('sha256', $body, $secret);
    }
}
