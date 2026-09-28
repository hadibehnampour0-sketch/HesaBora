<?php

namespace App\Services\Integration\Adapters;

use App\Models\IntegrationConnection;
use App\Services\Integration\CanonicalEvent;
use App\Services\Integration\IntegrationAdapter;
use App\Services\Integration\IntegrationEventType;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * WooCommerce (design doc: first platform, no third-party approval needed to build/test).
 * Verifies the `X-WC-Webhook-Signature` header (base64 HMAC-SHA256 of the raw body, keyed by
 * the connection's webhook secret) before trusting any payload -- WooCommerce's own documented
 * signing scheme, not invented here.
 */
class WooCommerceAdapter implements IntegrationAdapter
{
    public function __construct(private readonly IntegrationConnection $connection) {}

    public function source(): string
    {
        return 'woocommerce';
    }

    public function normalize(array $rawPayload, array $headers, string $rawBody = ''): array
    {
        $this->verifySignature($headers, $rawBody);

        if (! isset($rawPayload['id'])) {
            throw new InvalidArgumentException('Payload is not a recognizable WooCommerce order.');
        }

        // WooCommerce has no separate "order cancelled"/"refund" webhook topic -- it re-delivers
        // the same order resource on the `order.updated` topic with its `status` field changed.
        $topic = strtolower($headers['x-wc-webhook-topic'] ?? 'order.created');
        $status = strtolower((string) ($rawPayload['status'] ?? ''));

        if (str_starts_with($topic, 'order.updated')) {
            return match ($status) {
                'cancelled' => [new CanonicalEvent($this->source(), (string) $rawPayload['id'], IntegrationEventType::ORDER_CANCELLED, ['order_number' => (string) ($rawPayload['number'] ?? $rawPayload['id'])])],
                'refunded' => [new CanonicalEvent($this->source(), (string) $rawPayload['id'], IntegrationEventType::REFUND_ISSUED, ['order_number' => (string) ($rawPayload['number'] ?? $rawPayload['id'])])],
                // Every other status transition (processing, completed, on-hold, ...) has no
                // automated effect in this version -- nothing to ingest, not an error.
                default => [],
            };
        }

        if (! isset($rawPayload['line_items'])) {
            throw new InvalidArgumentException('Payload is not a recognizable WooCommerce order.');
        }

        $billing = $rawPayload['billing'] ?? [];
        $lines = array_values(array_map(fn (array $line) => [
            'external_product_id' => (string) ($line['product_id'] ?? ''),
            'sku' => $line['sku'] ?? null,
            'name' => $line['name'] ?? '',
            'quantity' => (float) ($line['quantity'] ?? 0),
            'unit_price' => $this->linePrice($line),
        ], $rawPayload['line_items']));

        $payload = [
            'order_number' => (string) ($rawPayload['number'] ?? $rawPayload['id']),
            'currency' => $rawPayload['currency'] ?? null,
            'customer' => [
                'external_id' => isset($rawPayload['customer_id']) && $rawPayload['customer_id'] > 0 ? (string) $rawPayload['customer_id'] : null,
                'name' => trim(($billing['first_name'] ?? '').' '.($billing['last_name'] ?? '')),
                'phone' => $billing['phone'] ?? null,
                'email' => $billing['email'] ?? null,
            ],
            'lines' => $lines,
        ];

        return [new CanonicalEvent($this->source(), (string) $rawPayload['id'], IntegrationEventType::ORDER_CREATED, $payload)];
    }

    public function pushInventory(string $externalProductId, float $quantity): void
    {
        $credentials = $this->connection->credentials;

        $response = Http::withBasicAuth($credentials['consumer_key'], $credentials['consumer_secret'])
            ->put(rtrim($credentials['site_url'], '/')."/wp-json/wc/v3/products/{$externalProductId}", [
                'manage_stock' => true,
                'stock_quantity' => max(0, (int) $quantity),
            ]);

        if ($response->failed()) {
            throw new \RuntimeException("WooCommerce stock push failed for product {$externalProductId}: HTTP {$response->status()}");
        }
    }

    private function linePrice(array $line): float
    {
        $quantity = (float) ($line['quantity'] ?? 0);
        if ($quantity <= 0) {
            return 0.0;
        }

        // WooCommerce's `price` field is not always present on every order payload shape;
        // `subtotal` (before line-level discount) divided by quantity is always present and
        // is the correct value to compare against Dakhl's own selling price.
        return isset($line['price']) ? (float) $line['price'] : ((float) ($line['subtotal'] ?? 0) / $quantity);
    }

    private function verifySignature(array $headers, string $rawBody): void
    {
        $secret = $this->connection->credentials['webhook_secret'] ?? null;
        if (! $secret) {
            throw new InvalidArgumentException('No webhook secret configured for this connection.');
        }

        $provided = $headers['x-wc-webhook-signature'] ?? null;
        if (! $provided) {
            throw new InvalidArgumentException('Missing WooCommerce webhook signature.');
        }

        $expected = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));

        if (! hash_equals($expected, $provided)) {
            throw new InvalidArgumentException('WooCommerce webhook signature does not match.');
        }
    }
}
