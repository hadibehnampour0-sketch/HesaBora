<?php

namespace App\Services\Integration;

use App\Models\IntegrationEvent;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Idempotent inbound event ledger (design doc B5). Key: (company, source, external_id, event_type).
 *  - same key + same payload  => no-op for finished events; re-run for PENDING_DEPENDENCY / FAILED_RETRY / UNHANDLED
 *  - same key + different payload => CONFLICT (surfaced, original untouched; never de-duplicated by similarity)
 *  - handler exception => FAILED_RETRY (technical), a redelivery retries it
 * Company scoping comes from the model's FiscalYearScope / active company.
 */
class IntegrationHub
{
    public const PROCESSED = 'processed';

    public const REJECTED = 'rejected';

    public const PENDING_DEPENDENCY = 'pending_dependency';

    public const FAILED_RETRY = 'failed_retry';

    public const UNHANDLED = 'unhandled';

    public const CONFLICT = 'conflict';

    public const DUPLICATE = 'duplicate';

    /** Statuses a redelivery may re-run. */
    private const RETRYABLE = [self::PENDING_DEPENDENCY, self::FAILED_RETRY, self::UNHANDLED];

    /** @var array<string, IntegrationEventHandler> */
    private array $handlers = [];

    public function registerHandler(IntegrationEventType $type, IntegrationEventHandler $handler): void
    {
        $this->handlers[$type->value] = $handler;
    }

    /** @return array{outcome: string, event: IntegrationEvent} outcome is a status constant or CONFLICT / DUPLICATE */
    public function ingest(CanonicalEvent $event): array
    {
        return DB::transaction(function () use ($event) {
            $record = IntegrationEvent::query()
                ->where('source_system', $event->source)
                ->where('external_id', $event->externalId)
                ->where('event_type', $event->type->value)
                ->lockForUpdate()
                ->first();

            if ($record === null) {
                $record = IntegrationEvent::create([
                    'source_system' => $event->source,
                    'external_id' => $event->externalId,
                    'event_type' => $event->type->value,
                    'payload_hash' => $event->payloadHash(),
                    'payload' => $event->payload,
                    'status' => self::UNHANDLED,
                    'attempts' => 0,
                ]);

                return $this->run($record, $event);
            }

            if ($record->payload_hash !== $event->payloadHash()) {
                return ['outcome' => self::CONFLICT, 'event' => $record];
            }

            if (in_array($record->status, self::RETRYABLE, true)) {
                return $this->run($record, $event);
            }

            return ['outcome' => self::DUPLICATE, 'event' => $record];
        });
    }

    /** Re-run one specific waiting/failed/unhandled event (e.g. after a human fixed its cause from the review queue). */
    public function retryEvent(IntegrationEvent $record): array
    {
        return DB::transaction(function () use ($record) {
            $locked = IntegrationEvent::query()->lockForUpdate()->findOrFail($record->id);

            return $this->run($locked, new CanonicalEvent($locked->source_system, $locked->external_id, IntegrationEventType::from($locked->event_type), $locked->payload));
        });
    }

    /** Re-run every waiting event (e.g. after a dependency such as the order's customer arrived). */
    public function retryWaiting(): int
    {
        $count = 0;

        foreach (IntegrationEvent::whereIn('status', [self::PENDING_DEPENDENCY, self::FAILED_RETRY])->get() as $record) {
            $this->ingest(new CanonicalEvent($record->source_system, $record->external_id, IntegrationEventType::from($record->event_type), $record->payload));
            $count++;
        }

        return $count;
    }

    /** @return array{outcome: string, event: IntegrationEvent} */
    private function run(IntegrationEvent $record, CanonicalEvent $event): array
    {
        $handler = $this->handlers[$event->type->value] ?? null;
        $record->attempts++;

        if ($handler === null) {
            $record->fill(['status' => self::UNHANDLED, 'message' => __('No handler is registered for this event type.')])->save();

            return ['outcome' => self::UNHANDLED, 'event' => $record];
        }

        try {
            $result = $handler->handle($event);
        } catch (Throwable $e) {
            $record->fill(['status' => self::FAILED_RETRY, 'message' => mb_substr($e->getMessage(), 0, 500)])->save();

            return ['outcome' => self::FAILED_RETRY, 'event' => $record];
        }

        $record->fill([
            'status' => $result->status,
            'message' => $result->message,
            'processed_at' => $result->status === self::PROCESSED ? now() : null,
        ])->save();

        return ['outcome' => $result->status, 'event' => $record];
    }
}
