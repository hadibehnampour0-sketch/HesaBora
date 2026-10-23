<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\PriceQuote;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class PriceQuoteService
{
    /**
     * Record a fast price quotation given to a customer or partner.
     */
    public function recordQuote(
        int $companyId,
        ?int $userId,
        string $customerName,
        string $productName,
        float $quotedPrice,
        string $currency = 'toman',
        ?string $notes = null,
        ?int $customerId = null,
        ?int $productId = null,
        ?string $customerPhone = null
    ): PriceQuote {
        // Auto-resolve customer if not provided
        if (! $customerId && ! empty($customerName)) {
            $matchedCustomer = Customer::where('company_id', $companyId)
                ->where(function ($q) use ($customerName) {
                    $q->where('name', $customerName)
                        ->orWhere('name', 'LIKE', "%{$customerName}%");
                })
                ->first();

            if ($matchedCustomer) {
                $customerId = $matchedCustomer->id;
                $customerPhone ??= $matchedCustomer->mobile;
            }
        }

        // Auto-resolve product if not provided
        if (! $productId && ! empty($productName)) {
            $matchedProduct = Product::where('company_id', $companyId)
                ->where(function ($q) use ($productName) {
                    $q->where('name', $productName)
                        ->orWhere('name', 'LIKE', "%{$productName}%");
                })
                ->first();

            if ($matchedProduct) {
                $productId = $matchedProduct->id;
            }
        }

        return PriceQuote::create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'customer_id' => $customerId,
            'customer_name' => trim($customerName),
            'customer_phone' => $customerPhone,
            'product_id' => $productId,
            'product_name' => trim($productName),
            'quoted_price' => $quotedPrice,
            'currency' => in_array($currency, ['rial', 'toman']) ? $currency : 'toman',
            'notes' => $notes,
            'status' => 'quoted',
        ]);
    }

    /**
     * Get recent price quotations for the company.
     */
    public function getRecentQuotes(int $companyId, int $limit = 30): Collection
    {
        return PriceQuote::where('company_id', $companyId)
            ->latest()
            ->take($limit)
            ->get();
    }

    /**
     * Search quotes given to a specific customer or for a specific product.
     */
    public function searchQuotes(int $companyId, ?string $customerName = null, ?string $productName = null): Collection
    {
        $query = PriceQuote::where('company_id', $companyId);

        if (! empty($customerName)) {
            $query->where('customer_name', 'LIKE', "%{$customerName}%");
        }

        if (! empty($productName)) {
            $query->where('product_name', 'LIKE', "%{$productName}%");
        }

        return $query->latest()->get();
    }
}
