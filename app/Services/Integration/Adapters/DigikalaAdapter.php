<?php

namespace App\Services\Integration\Adapters;

use App\Models\IntegrationConnection;
use App\Services\Integration\CanonicalEvent;
use App\Services\Integration\IntegrationAdapter;
use App\Services\Integration\IntegrationEventType;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * Digikala Marketplace Adapter (seller.digikala.com Open API).
 * Normalizes Digikala order payloads into canonical events and updates variant stock.
 */
class DigikalaAdapter implements IntegrationAdapter
{
    public function __construct(private readonly IntegrationConnection $connection) {}

    public function source(): string
    {
        return 'digikala';
    }

    public function normalize(array $rawPayload, array $headers, string $rawBody = ''): array
    {
        $this->verifySignature($headers, $rawBody);

        $orderId = $rawPayload['order_id'] ?? $rawPayload['id'] ?? null;
        if (! $orderId) {
            throw new InvalidArgumentException('Payload is not a recognizable Digikala order.');
        }

        $orderNumber = (string) ($rawPayload['order_number'] ?? $orderId);
        $status = strtolower((string) ($rawPayload['status'] ?? ''));

        if (in_array($status, ['cancelled', 'canceled', 'returned'])) {
            return [new CanonicalEvent(
                $this->source(),
                (string) $orderId,
                IntegrationEventType::ORDER_CANCELLED,
                ['order_number' => $orderNumber]
            )];
        }

        $items = $rawPayload['order_items'] ?? $rawPayload['items'] ?? null;
        if (! is_array($items)) {
            throw new InvalidArgumentException('Payload is not a recognizable Digikala order.');
        }

        $customerData = $rawPayload['customer'] ?? [];
        $lines = array_values(array_map(function (array $item) {
            $variantId = (string) ($item['variant_id'] ?? $item['product_id'] ?? $item['dkp'] ?? '');
            $quantity = (float) ($item['quantity'] ?? 1);
            $unitPrice = (float) ($item['selling_price'] ?? $item['price'] ?? 0);

            return [
                'external_product_id' => $variantId,
                'sku' => $item['seller_code'] ?? $item['sku'] ?? null,
                'name' => $item['product_title'] ?? $item['title'] ?? 'کالای دیجی‌کالا',
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
            ];
        }, $items));

        $payload = [
            'order_number' => $orderNumber,
            'currency' => 'IRR',
            'customer' => [
                'external_id' => isset($customerData['id']) ? (string) $customerData['id'] : null,
                'name' => trim(($customerData['first_name'] ?? '').' '.($customerData['last_name'] ?? '')) ?: ($customerData['name'] ?? 'مشتری دیجی‌کالا'),
                'phone' => $customerData['phone'] ?? $customerData['mobile'] ?? null,
                'email' => $customerData['email'] ?? null,
                'city' => $customerData['city'] ?? null,
            ],
            'lines' => $lines,
        ];

        return [new CanonicalEvent(
            $this->source(),
            (string) $orderId,
            IntegrationEventType::ORDER_CREATED,
            $payload
        )];
    }

    public function pushInventory(string $externalProductId, float $quantity): void
    {
        $credentials = $this->connection->credentials;
        $baseUrl = rtrim($credentials['site_url'] ?? 'https://seller.digikala.com', '/');
        $apiKey = $credentials['api_key'] ?? '';

        $response = Http::withHeaders([
            'api-key' => $apiKey,
            'Accept' => 'application/json',
        ])->put("{$baseUrl}/open-api/v1/variants/{$externalProductId}/", [
            'seller_stock' => max(0, (int) $quantity),
        ]);

        if ($response->failed()) {
            throw new \RuntimeException("Digikala stock push failed for variant {$externalProductId}: HTTP {$response->status()}");
        }
    }

    private function verifySignature(array $headers, string $rawBody): void
    {
        $secret = $this->connection->credentials['webhook_secret'] ?? null;
        if (! $secret || $rawBody === '') {
            return;
        }

        $signature = $headers['x-dk-signature'] ?? $headers['x-digikala-signature'] ?? null;
        if (! $signature) {
            throw new InvalidArgumentException('Missing Digikala webhook signature.');
        }

        $expected = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));
        if (! hash_equals($expected, $signature)) {
            throw new InvalidArgumentException('Invalid Digikala webhook signature.');
        }
    }
}
