<?php

namespace App\Services\Integration\Handlers;

use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\IntegrationConnection;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\WarehouseProductStock;
use App\Services\CustomerService;
use App\Services\Integration\CanonicalEvent;
use App\Services\Integration\ExternalReferenceService;
use App\Services\Integration\HandlerResult;
use App\Services\Integration\IntegrationEventHandler;
use App\Services\Integration\IntegrationEventType;
use App\Services\InvoiceService;

/**
 * Dakhl_INTEGRATION_PLATFORMS_AND_POLICY_PLAN_2026-09-28.md §2: an imported order auto-creates
 * and auto-approves a Sell invoice through the UNCHANGED InvoiceService path -- but only when
 * every line/customer/price/stock check is unambiguous. Any doubt returns pendingDependency(),
 * which IntegrationHub keeps as a retryable, visible, un-dropped row (the "review queue") --
 * never a guess, never a silent drop, matching D-086-D-089.
 */
class OrderImportHandler implements IntegrationEventHandler
{
    public function __construct(
        private readonly ExternalReferenceService $externalReferenceService,
        private readonly CustomerService $customerService,
    ) {}

    public function handle(CanonicalEvent $event): HandlerResult
    {
        if ($event->type !== IntegrationEventType::ORDER_CREATED) {
            return HandlerResult::rejected(__('This handler only processes order-created events.'));
        }

        $connection = IntegrationConnection::where('source_system', $event->source)->where('is_active', true)->first();
        if (! $connection) {
            return HandlerResult::pendingDependency(__('No active connection configured for :source.', ['source' => $event->source]));
        }
        if (! $connection->default_warehouse_id) {
            return HandlerResult::pendingDependency(__('This connection has no default warehouse configured yet.'));
        }

        $payload = $event->payload;

        $customerId = $this->resolveCustomer($payload['customer'] ?? [], $event->source, $connection);
        if ($customerId === null) {
            return HandlerResult::pendingDependency(__('Could not resolve or create a customer for this order.'));
        }

        $itemsOrError = $this->resolveItems($payload['lines'] ?? [], $event->source, $connection);
        if (is_string($itemsOrError)) {
            return HandlerResult::pendingDependency($itemsOrError);
        }

        $number = (Invoice::withoutGlobalScopes()->max('number') ?? 0) + 1;

        $result = InvoiceService::createInvoice(
            $connection->creator ?? $connection->company->users()->first(),
            [
                'title' => __('Imported order').' #'.($payload['order_number'] ?? $event->externalId),
                'date' => now()->toDateString(),
                'invoice_type' => InvoiceType::SELL,
                'customer_id' => $customerId,
                'warehouse_id' => $connection->default_warehouse_id,
                'document_number' => $number,
                'number' => $number,
            ],
            $itemsOrError,
            true
        );

        $invoice = $result['invoice'];
        if ($invoice->invoice_type === InvoiceType::SELL) {
            (new InvoiceService)->changeInvoiceStatus($invoice, 'approved');
        }

        $this->externalReferenceService->link($event->source, $event->externalId, 'invoice', $invoice->id);

        return HandlerResult::processed(__('Imported as invoice #:number', ['number' => $invoice->number]));
    }

    private function resolveCustomer(array $customer, string $source, IntegrationConnection $connection): ?int
    {
        if (! empty($customer['external_id'])) {
            $existing = $this->externalReferenceService->resolve($source, $customer['external_id'], 'customer');
            if ($existing) {
                return $existing;
            }
        }

        $phone = $customer['phone'] ?? null;
        if ($phone) {
            $matched = Customer::where('mobile', $phone)->orWhere('phone', $phone)->first();
            if ($matched) {
                if (! empty($customer['external_id'])) {
                    $this->externalReferenceService->link($source, $customer['external_id'], 'customer', $matched->id);
                }

                return $matched->id;
            }
        }

        if (! $connection->default_customer_group_id) {
            return null; // Nothing to auto-create against; needs a human to configure the connection or map the customer.
        }

        $name = trim((string) ($customer['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $created = $this->customerService->create([
            'name' => $name,
            'mobile' => $phone,
            'email' => $customer['email'] ?? null,
            'group_id' => $connection->default_customer_group_id,
        ]);

        if (! empty($customer['external_id'])) {
            $this->externalReferenceService->link($source, $customer['external_id'], 'customer', $created->id);
        }

        return $created->id;
    }

    /** @return array<int, array<string, mixed>>|string list of invoice items, or an error message */
    private function resolveItems(array $lines, string $source, IntegrationConnection $connection): array|string
    {
        if (empty($lines)) {
            return __('The order has no recognizable line items.');
        }

        $items = [];

        foreach ($lines as $line) {
            $productId = $this->externalReferenceService->resolve($source, $line['external_product_id'] ?? '', 'product');

            if (! $productId && ! empty($line['sku'])) {
                $bySku = Product::where('code', $line['sku'])->first();
                if ($bySku) {
                    $this->externalReferenceService->link($source, $line['external_product_id'], 'product', $bySku->id);
                    $productId = $bySku->id;
                }
            }

            if (! $productId) {
                return __('Product ":name" (platform id :id) is not mapped to a Dakhl product yet.', ['name' => $line['name'] ?? '?', 'id' => $line['external_product_id'] ?? '?']);
            }

            $product = Product::find($productId);
            if (! $product) {
                return __('The Dakhl product mapped to ":name" no longer exists.', ['name' => $line['name'] ?? '?']);
            }

            $expected = (float) $product->selling_price;
            $platformPrice = (float) ($line['unit_price'] ?? 0);
            if ($expected > 0) {
                $diffPercent = abs($platformPrice - $expected) / $expected * 100;
                if ($diffPercent > (float) $connection->price_tolerance_percent) {
                    return __('Price mismatch for ":name": platform :platform vs Dakhl :dakhl.', ['name' => $product->name, 'platform' => $platformPrice, 'dakhl' => $expected]);
                }
            }

            $quantity = (float) ($line['quantity'] ?? 0);
            $available = (float) (WarehouseProductStock::where('warehouse_id', $connection->default_warehouse_id)->where('product_id', $productId)->value('quantity') ?? 0);
            if ($quantity <= 0 || $available < $quantity) {
                return __('Not enough stock for ":name" (need :need, have :have).', ['name' => $product->name, 'need' => $quantity, 'have' => $available]);
            }

            $items[] = [
                'itemable_type' => 'product',
                'itemable_id' => $productId,
                'quantity' => $quantity,
                'unit' => $platformPrice > 0 ? $platformPrice : $expected,
                'unit_discount' => 0,
                'vat' => 0,
            ];
        }

        return $items;
    }
}
