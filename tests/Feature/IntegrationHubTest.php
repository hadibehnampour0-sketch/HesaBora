<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\IntegrationEvent;
use App\Services\Integration\CanonicalEvent;
use App\Services\Integration\ExternalReferenceService;
use App\Services\Integration\HandlerResult;
use App\Services\Integration\IntegrationAdapter;
use App\Services\Integration\IntegrationEventHandler;
use App\Services\Integration\IntegrationEventType;
use App\Services\Integration\IntegrationHub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Integration Hub skeleton: idempotent event ledger + external references, proven with a
 * fake adapter and fake handlers (no real platform, no accounting writes).
 */
class IntegrationHubTest extends TestCase
{
    use RefreshDatabase;

    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyId = Company::factory()->create()->id;
        config(['active-company-id' => $this->companyId]);
    }

    private function event(string $id = 'ord-1', array $payload = ['total' => 100], IntegrationEventType $type = IntegrationEventType::ORDER_CREATED): CanonicalEvent
    {
        return new CanonicalEvent('fakeshop', $id, $type, $payload);
    }

    private function countingHandler(?callable $behaviour = null): object
    {
        return new class($behaviour) implements IntegrationEventHandler
        {
            public int $calls = 0;

            public function __construct(private $behaviour) {}

            public function handle(CanonicalEvent $event): HandlerResult
            {
                $this->calls++;

                return $this->behaviour ? ($this->behaviour)($this->calls, $event) : HandlerResult::processed();
            }
        };
    }

    public function test_an_event_is_processed_once_and_a_redelivery_is_a_no_op(): void
    {
        $hub = new IntegrationHub;
        $handler = $this->countingHandler();
        $hub->registerHandler(IntegrationEventType::ORDER_CREATED, $handler);

        $first = $hub->ingest($this->event());
        $second = $hub->ingest($this->event());

        $this->assertSame(IntegrationHub::PROCESSED, $first['outcome']);
        $this->assertSame(IntegrationHub::DUPLICATE, $second['outcome']);
        $this->assertSame(1, $handler->calls);
        $this->assertSame(1, IntegrationEvent::count());
    }

    public function test_the_same_key_with_a_different_payload_is_a_conflict_and_leaves_the_original_untouched(): void
    {
        $hub = new IntegrationHub;
        $hub->registerHandler(IntegrationEventType::ORDER_CREATED, $this->countingHandler());
        $hub->ingest($this->event('ord-1', ['total' => 100]));

        $result = $hub->ingest($this->event('ord-1', ['total' => 999]));

        $this->assertSame(IntegrationHub::CONFLICT, $result['outcome']);
        $this->assertSame(['total' => 100], IntegrationEvent::first()->payload);
    }

    public function test_key_order_inside_the_payload_does_not_create_a_false_conflict(): void
    {
        $hub = new IntegrationHub;
        $hub->registerHandler(IntegrationEventType::ORDER_CREATED, $this->countingHandler());
        $hub->ingest($this->event('ord-1', ['a' => 1, 'b' => ['x' => 1, 'y' => 2]]));

        $again = $hub->ingest($this->event('ord-1', ['b' => ['y' => 2, 'x' => 1], 'a' => 1]));

        $this->assertSame(IntegrationHub::DUPLICATE, $again['outcome']);
    }

    public function test_different_event_types_or_ids_are_independent(): void
    {
        $hub = new IntegrationHub;
        $handler = $this->countingHandler();
        $hub->registerHandler(IntegrationEventType::ORDER_CREATED, $handler);
        $hub->registerHandler(IntegrationEventType::REFUND_ISSUED, $handler);

        $hub->ingest($this->event('ord-1'));
        $hub->ingest($this->event('ord-2'));
        $hub->ingest($this->event('ord-1', ['total' => 100], IntegrationEventType::REFUND_ISSUED));

        $this->assertSame(3, $handler->calls);
    }

    public function test_an_event_with_no_handler_is_kept_as_unhandled_not_dropped(): void
    {
        $hub = new IntegrationHub;

        $result = $hub->ingest($this->event());

        $this->assertSame(IntegrationHub::UNHANDLED, $result['outcome']);
        $this->assertSame(1, IntegrationEvent::where('status', IntegrationHub::UNHANDLED)->count());

        $hub->registerHandler(IntegrationEventType::ORDER_CREATED, $this->countingHandler());
        $this->assertSame(IntegrationHub::PROCESSED, $hub->ingest($this->event())['outcome']);
    }

    public function test_a_business_rejection_is_final_and_visible(): void
    {
        $hub = new IntegrationHub;
        $handler = $this->countingHandler(fn () => HandlerResult::rejected('Product not mapped'));
        $hub->registerHandler(IntegrationEventType::ORDER_CREATED, $handler);

        $hub->ingest($this->event());
        $again = $hub->ingest($this->event());

        $this->assertSame(IntegrationHub::DUPLICATE, $again['outcome']);
        $this->assertSame(1, $handler->calls);
        $this->assertSame('Product not mapped', IntegrationEvent::first()->message);
    }

    public function test_an_out_of_order_event_waits_and_succeeds_once_its_dependency_exists(): void
    {
        $hub = new IntegrationHub;
        $customerKnown = false;
        $hub->registerHandler(IntegrationEventType::ORDER_CREATED, $this->countingHandler(
            function () use (&$customerKnown) {
                return $customerKnown ? HandlerResult::processed() : HandlerResult::pendingDependency('Customer not synced yet');
            }
        ));

        $first = $hub->ingest($this->event());
        $this->assertSame(IntegrationHub::PENDING_DEPENDENCY, $first['outcome']);

        $customerKnown = true;
        $this->assertSame(1, $hub->retryWaiting());

        $this->assertSame(IntegrationHub::PROCESSED, IntegrationEvent::first()->status);
        $this->assertNotNull(IntegrationEvent::first()->processed_at);
        $this->assertSame(2, IntegrationEvent::first()->attempts);
    }

    public function test_a_handler_exception_is_a_technical_retry_not_a_lost_event(): void
    {
        $hub = new IntegrationHub;
        $hub->registerHandler(IntegrationEventType::ORDER_CREATED, $this->countingHandler(
            fn (int $call) => $call === 1 ? throw new \RuntimeException('gateway timeout') : HandlerResult::processed()
        ));

        $first = $hub->ingest($this->event());
        $this->assertSame(IntegrationHub::FAILED_RETRY, $first['outcome']);
        $this->assertStringContainsString('gateway timeout', IntegrationEvent::first()->message);

        $this->assertSame(IntegrationHub::PROCESSED, $hub->ingest($this->event())['outcome']);
        $this->assertSame(1, IntegrationEvent::count());
    }

    public function test_events_are_isolated_between_companies(): void
    {
        $hub = new IntegrationHub;
        $hub->registerHandler(IntegrationEventType::ORDER_CREATED, $this->countingHandler());
        $hub->ingest($this->event());

        $other = Company::factory()->create();
        config(['active-company-id' => $other->id]);

        $this->assertSame(0, IntegrationEvent::count());
        // The same platform id in another company is a brand-new, independent event.
        $this->assertSame(IntegrationHub::PROCESSED, $hub->ingest($this->event())['outcome']);
        $this->assertSame(2, IntegrationEvent::withoutGlobalScopes()->count());
    }

    public function test_a_fake_adapter_feeds_the_hub_through_canonical_events_only(): void
    {
        $adapter = new class implements IntegrationAdapter
        {
            public function source(): string
            {
                return 'fakeshop';
            }

            public function normalize(array $rawPayload, array $headers): array
            {
                if (($headers['x-signature'] ?? null) !== 'valid') {
                    throw new InvalidArgumentException('bad signature');
                }

                return [new CanonicalEvent($this->source(), (string) $rawPayload['id'], IntegrationEventType::ORDER_CREATED, ['total' => $rawPayload['total']])];
            }
        };

        $hub = new IntegrationHub;
        $handler = $this->countingHandler();
        $hub->registerHandler(IntegrationEventType::ORDER_CREATED, $handler);

        foreach ($adapter->normalize(['id' => 77, 'total' => 500], ['x-signature' => 'valid']) as $event) {
            $hub->ingest($event);
        }

        $this->assertSame(1, $handler->calls);
        $this->assertSame('77', IntegrationEvent::first()->external_id);

        $this->expectException(InvalidArgumentException::class);
        $adapter->normalize(['id' => 78, 'total' => 1], ['x-signature' => 'forged']);
    }

    public function test_external_references_link_idempotently_and_reject_a_remap(): void
    {
        $refs = new ExternalReferenceService;

        $refs->link('fakeshop', 'cust-9', 'customer', 41);
        $refs->link('fakeshop', 'cust-9', 'customer', 41); // idempotent

        $this->assertSame(41, $refs->resolve('fakeshop', 'cust-9', 'customer'));
        $this->assertNull($refs->resolve('fakeshop', 'cust-9', 'product'));
        $this->assertNull($refs->resolve('otherplatform', 'cust-9', 'customer'));

        $this->expectException(ValidationException::class);
        $refs->link('fakeshop', 'cust-9', 'customer', 99);
    }

    public function test_external_references_are_company_scoped(): void
    {
        $refs = new ExternalReferenceService;
        $refs->link('fakeshop', 'cust-9', 'customer', 41);

        $other = Company::factory()->create();
        config(['active-company-id' => $other->id]);

        $this->assertNull($refs->resolve('fakeshop', 'cust-9', 'customer'));
        $refs->link('fakeshop', 'cust-9', 'customer', 500); // same external id, other tenant: allowed
        $this->assertSame(500, $refs->resolve('fakeshop', 'cust-9', 'customer'));
    }
}
