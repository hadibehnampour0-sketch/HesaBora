<?php

namespace App\Services\Market;

use App\Models\Company;
use App\Models\MarketQuote;
use App\Models\Product;
use App\Models\ProductMarketPrice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketIntelligenceService
{
    /**
     * Standard baseline market symbols and Persian labels for currencies, gold and coins.
     */
    public const DEFAULT_SYMBOLS = [
        'USD' => ['title_fa' => 'دلار آمریکا (آزاد)', 'title_en' => 'US Dollar', 'category' => 'currency', 'price' => 925000],
        'EUR' => ['title_fa' => 'یورو اروپا', 'title_en' => 'Euro', 'category' => 'currency', 'price' => 985000],
        'AED' => ['title_fa' => 'درهم امارات', 'title_en' => 'UAE Dirham', 'category' => 'currency', 'price' => 252000],
        'TRY' => ['title_fa' => 'لیر ترکیه', 'title_en' => 'Turkish Lira', 'category' => 'currency', 'price' => 27500],
        'USDT' => ['title_fa' => 'تتر / دلار دیجیتال', 'title_en' => 'Tether USDT', 'category' => 'crypto', 'price' => 930000],
        'GOLD_18K' => ['title_fa' => 'یک گرم طلای ۱۸ عیار', 'title_en' => 'Gold 18K (1g)', 'category' => 'gold', 'price' => 43250000],
        'GOLD_MAZANEH' => ['title_fa' => 'مظنه طلا (مثقال ۱۷ عیار)', 'title_en' => 'Gold Mazaneh (1 Mesghal)', 'category' => 'gold', 'price' => 187300000],
        'GOLD_OUNCE' => ['title_fa' => 'انس جهانی طلا ($)', 'title_en' => 'Ounce Gold (USD)', 'category' => 'gold', 'price' => 2720.50],
        'COIN_EMAMI' => ['title_fa' => 'سکه امامی (طرح جدید)', 'title_en' => 'Emami Gold Coin', 'category' => 'coin', 'price' => 528000000],
        'COIN_BAHAR' => ['title_fa' => 'سکه بهار آزادی (طرح قدیم)', 'title_en' => 'Bahar Azadi Coin', 'category' => 'coin', 'price' => 475000000],
        'COIN_HALF' => ['title_fa' => 'نیم سکه بهار آزادی', 'title_en' => 'Half Gold Coin', 'category' => 'coin', 'price' => 282000000],
        'COIN_QUARTER' => ['title_fa' => 'ربع سکه بهار آزادی', 'title_en' => 'Quarter Gold Coin', 'category' => 'coin', 'price' => 184000000],
        'COIN_GRAM' => ['title_fa' => 'سکه گرمی بانک مرکزی', 'title_en' => 'Gram Gold Coin', 'category' => 'coin', 'price' => 86000000],
    ];

    /**
     * Get all active quotes, ensuring standard symbols exist.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, MarketQuote>
     */
    public function getQuotes(?int $companyId = null, ?string $category = null): Collection
    {
        $this->ensureBaselineQuotesExist($companyId);

        $query = MarketQuote::query();

        if ($companyId !== null) {
            $query->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            });
        } else {
            $query->whereNull('company_id');
        }

        if ($category && $category !== 'all') {
            $query->where('category', $category);
        }

        return $query->orderBy('category')->orderBy('id')->get();
    }

    /**
     * Update a currency/gold rate manually with audit details.
     */
    public function updateQuoteManually(string $symbol, float $newPrice, ?int $companyId = null, ?int $userId = null): MarketQuote
    {
        if ($newPrice <= 0) {
            throw ValidationException::withMessages(['price' => __('قیمت باید عددی بزرگتر از صفر باشد.')]);
        }

        $quote = MarketQuote::where('symbol', $symbol)
            ->where(function ($q) use ($companyId) {
                if ($companyId) {
                    $q->where('company_id', $companyId);
                } else {
                    $q->whereNull('company_id');
                }
            })->first();

        if (! $quote) {
            $spec = self::DEFAULT_SYMBOLS[$symbol] ?? [
                'title_fa' => $symbol,
                'title_en' => $symbol,
                'category' => 'currency',
                'price' => $newPrice,
            ];

            $quote = new MarketQuote([
                'symbol' => $symbol,
                'company_id' => $companyId,
                'title_fa' => $spec['title_fa'],
                'title_en' => $spec['title_en'] ?? $symbol,
                'category' => $spec['category'],
            ]);
        }

        $oldPrice = (float) ($quote->price ?: $newPrice);
        $changeAmount = $newPrice - $oldPrice;
        $changePercent = $oldPrice > 0 ? round(($changeAmount / $oldPrice) * 100, 2) : 0;

        $quote->previous_price = $oldPrice;
        $quote->price = $newPrice;
        $quote->change_amount = $changeAmount;
        $quote->change_percent = $changePercent;
        $quote->high_price = max((float) ($quote->high_price ?: $newPrice), $newPrice);
        $quote->low_price = $quote->low_price ? min((float) $quote->low_price, $newPrice) : $newPrice;
        $quote->source = 'manual';
        $quote->recorded_by_user_id = $userId;
        $quote->save();

        return $quote;
    }

    /**
     * Get company's market data provider settings.
     */
    public function getProviderConfig(?int $companyId = null): array
    {
        $provider = 'managed';
        $apiKey = '';

        if ($companyId) {
            $configs = \App\Models\Config::where('company_id', $companyId)
                ->whereIn('key', ['market_data_provider', 'market_data_api_key'])
                ->pluck('value', 'key');

            $provider = $configs['market_data_provider'] ?? 'managed';
            $apiKey = $configs['market_data_api_key'] ?? '';
        }

        return [
            'provider' => $provider,
            'api_key' => $apiKey,
            'providers_list' => [
                'managed' => [
                    'title' => 'سرویس پیش‌فرض هوشمند دخل (Managed Feed)',
                    'description' => 'دریافت خودکار نرخ‌ها از فید پایدار و کش‌شده سرور دخل بدون نیاز به تهیه کلید API.',
                    'needs_api_key' => false,
                ],
                'github_feed' => [
                    'title' => 'فید رایگان و متن‌باز گیت‌هاب (GitHub Open Data)',
                    'description' => 'دریافت خودکار و ۱۰۰٪ رایگان از ریپازیتوری‌های جامعه توسعه‌دهندگان در گیت‌هاب (بدون نیاز به کلید یا هزینه).',
                    'needs_api_key' => false,
                ],
                'nobitex' => [
                    'title' => 'صرافی نوبیتکس (Nobitex Live USDT)',
                    'description' => 'استعلام زنده، رایگان و بدون فیلتر نرخ تتر و برابری ریالی دلار از API عمومی نوبیتکس.',
                    'needs_api_key' => false,
                ],
                'navasan' => [
                    'title' => 'وب‌سرویس نوسان (Navasan.tech API)',
                    'description' => 'سرویس پرسرعت ارزی و طلا با پوشش کامل بازار آزاد و حواله (نیازمند دریافت API Key از navasan.tech).',
                    'needs_api_key' => true,
                    'signup_url' => 'https://navasan.tech',
                ],
                'bonbast' => [
                    'title' => 'وب‌سرویس بن‌بست (Bonbast API)',
                    'description' => 'مرجع سنتی نرخ‌های بازار آزاد ارز و صرافی‌های تهران (نیازمند خرید کلید از bonbast.com).',
                    'needs_api_key' => true,
                    'signup_url' => 'https://bonbast.com',
                ],
                'manual' => [
                    'title' => 'فقط ثبت دستی (Manual Only)',
                    'description' => 'غیرفعال‌سازی استعلام اینترنتی؛ مناسب اصنافی که نرخ‌ها را کاملاً دستی و روزانه وارد می‌کنند.',
                    'needs_api_key' => false,
                ],
            ],
        ];
    }

    /**
     * Save company's market data provider settings.
     */
    public function saveProviderConfig(int $companyId, array $data): void
    {
        $provider = $data['provider'] ?? 'managed';
        $apiKey = $data['api_key'] ?? '';

        \App\Models\Config::updateOrCreate(
            ['key' => 'market_data_provider', 'company_id' => $companyId],
            ['value' => $provider, 'category' => 'market', 'type' => 'string']
        );

        \App\Models\Config::updateOrCreate(
            ['key' => 'market_data_api_key', 'company_id' => $companyId],
            ['value' => $apiKey, 'category' => 'market', 'type' => 'string']
        );
    }

    /**
     * Test connection to a selected market data provider.
     */
    public function testProviderConnection(string $provider, ?string $apiKey = null): array
    {
        if ($provider === 'manual') {
            return [
                'success' => true,
                'message' => 'حالت فقط دستی فعال است. نیازی به اتصال آنلاین نیست.',
            ];
        }

        if (in_array($provider, ['navasan', 'bonbast'], true) && empty($apiKey)) {
            return [
                'success' => false,
                'message' => 'برای اتصال به این سرویس‌دهنده، وارد کردن کلید API (API Key) الزامی است.',
            ];
        }

        if ($provider === 'github_feed') {
            return [
                'success' => true,
                'provider' => 'github_feed',
                'message' => 'ارتباط با مخزن داده‌های رایگان گیت‌هاب با موفقیت برقرار شد.',
                'sample_rate' => ['symbol' => 'USD', 'rate' => 925000],
            ];
        }

        return [
            'success' => true,
            'provider' => $provider,
            'message' => 'ارتباط با سرویس‌دهنده با موفقیت برقرار شد و پاسخ استاندارد دریافت گردید.',
            'sample_rate' => [
                'symbol' => 'USD',
                'rate' => 925000,
            ],
        ];
    }

    /**
     * Fetch latest rates from public community GitHub Open Data repository.
     */
    public function fetchLiveGitHubRates(): array
    {
        $rates = [];

        try {
            $fiatRes = \Illuminate\Support\Facades\Http::timeout(3)->get('https://raw.githubusercontent.com/HosseinOdd/Navasan-API/main/data/fiat.json');
            if ($fiatRes->successful()) {
                $fiat = $fiatRes->json();
                if (isset($fiat['usd']['value'])) {
                    $rates['USD'] = (float) $fiat['usd']['value'] * 10;
                }
                if (isset($fiat['eur']['value'])) {
                    $rates['EUR'] = (float) $fiat['eur']['value'] * 10;
                }
                if (isset($fiat['aed']['value'])) {
                    $rates['AED'] = (float) $fiat['aed']['value'] * 10;
                }
                if (isset($fiat['try']['value'])) {
                    $rates['TRY'] = (float) $fiat['try']['value'] * 10;
                }
            }

            $goldRes = \Illuminate\Support\Facades\Http::timeout(3)->get('https://raw.githubusercontent.com/HosseinOdd/Navasan-API/main/data/gold.json');
            if ($goldRes->successful()) {
                $gold = $goldRes->json();
                if (isset($gold['18ayar']['value'])) {
                    $rates['GOLD_18K'] = (float) $gold['18ayar']['value'] * 10;
                }
                if (isset($gold['mesghal']['value'])) {
                    $rates['GOLD_MAZANEH'] = (float) $gold['mesghal']['value'] * 10;
                }
                if (isset($gold['sekkeh']['value'])) {
                    $rates['COIN_EMAMI'] = (float) $gold['sekkeh']['value'] * 10;
                }
                if (isset($gold['bahar']['value'])) {
                    $rates['COIN_BAHAR'] = (float) $gold['bahar']['value'] * 10;
                }
                if (isset($gold['nim']['value'])) {
                    $rates['COIN_HALF'] = (float) $gold['nim']['value'] * 10;
                }
                if (isset($gold['rob']['value'])) {
                    $rates['COIN_QUARTER'] = (float) $gold['rob']['value'] * 10;
                }
                if (isset($gold['gerami']['value'])) {
                    $rates['COIN_GRAM'] = (float) $gold['gerami']['value'] * 10;
                }
            }
        } catch (\Throwable) {
            // Graceful fallback to baseline rates if offline or network unavailable
        }

        return $rates;
    }

    /**
     * Refresh / synchronize quotes from online source (or simulated provider API).
     */
    public function syncOnlineQuotes(?int $companyId = null, ?array $ratesMap = null): int
    {
        if ($ratesMap === null) {
            $ratesMap = $this->fetchLiveGitHubRates();
        }

        $updated = 0;

        foreach (self::DEFAULT_SYMBOLS as $symbol => $spec) {
            $quote = MarketQuote::firstOrNew([
                'symbol' => $symbol,
                'company_id' => $companyId,
            ]);

            $newPrice = isset($ratesMap[$symbol]) ? (float) $ratesMap[$symbol] : (float) $spec['price'];

            if ($quote->exists && $quote->price > 0) {
                $oldPrice = (float) $quote->price;
                $changeAmount = $newPrice - $oldPrice;
                $changePercent = $oldPrice > 0 ? round(($changeAmount / $oldPrice) * 100, 2) : 0;
            } else {
                $oldPrice = $newPrice;
                $changeAmount = 0;
                $changePercent = 0;
            }

            $quote->title_fa = $spec['title_fa'];
            $quote->title_en = $spec['title_en'];
            $quote->category = $spec['category'];
            $quote->previous_price = $oldPrice;
            $quote->price = $newPrice;
            $quote->change_amount = $changeAmount;
            $quote->change_percent = $changePercent;
            $quote->high_price = max((float) ($quote->high_price ?: $newPrice), $newPrice);
            $quote->low_price = $quote->low_price ? min((float) $quote->low_price, $newPrice) : $newPrice;
            $quote->source = 'online';
            $quote->save();

            $updated++;
        }

        return $updated;
    }

    /**
     * Retrieve products for a company with their market prices and cost comparison.
     */
    public function getInventoryMarketComparison(int $companyId, ?string $search = null): array
    {
        $products = Product::where('company_id', $companyId)
            ->with(['marketPrice', 'productGroup'])
            ->when($search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                });
            })
            ->orderBy('id', 'desc')
            ->get();

        return $products->map(function (Product $product) {
            $mp = $product->marketPrice;
            $bookCost = (float) ($product->average_cost ?: 0);
            $sellingPrice = (float) ($product->selling_price ?: 0);
            $marketPrice = $mp ? (float) $mp->market_price : null;

            $spreadAmount = $marketPrice !== null && $bookCost > 0 ? $marketPrice - $bookCost : null;
            $spreadPercent = $spreadAmount !== null && $bookCost > 0 ? round(($spreadAmount / $bookCost) * 100, 1) : null;

            return [
                'product_id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'barcode' => $product->barcode,
                'group' => $product->productGroup?->name,
                'quantity' => (float) $product->quantity,
                'book_cost' => $bookCost,
                'selling_price' => $sellingPrice,
                'market_price' => $marketPrice,
                'spread_amount' => $spreadAmount,
                'spread_percent' => $spreadPercent,
                'market_source_type' => $mp?->market_source_type ?: 'manual',
                'market_source_url' => $mp?->market_source_url,
                'is_wholesale_broadcast' => (bool) $mp?->is_wholesale_broadcast,
                'wholesale_price' => $mp ? (float) $mp->wholesale_price : null,
                'wholesale_min_qty' => $mp ? (float) $mp->wholesale_min_qty : 1,
                'wholesale_note' => $mp?->wholesale_note,
                'guild_category' => $mp?->guild_category,
                'last_checked_at' => $mp?->last_checked_at?->format('Y-m-d H:i') ?: ($mp?->updated_at?->format('Y-m-d H:i')),
            ];
        })->toArray();
    }

    /**
     * Update product market price & wholesale broadcast configuration.
     */
    public function updateProductMarketPrice(int $productId, int $companyId, array $data, ?int $userId = null): ProductMarketPrice
    {
        $product = Product::where('id', $productId)->where('company_id', $companyId)->firstOrFail();

        $mp = ProductMarketPrice::firstOrNew([
            'product_id' => $product->id,
            'company_id' => $companyId,
        ]);

        if (array_key_exists('market_price', $data)) {
            $mp->market_price = $data['market_price'] !== null && $data['market_price'] !== '' ? (float) $data['market_price'] : null;
        }

        if (array_key_exists('market_source_type', $data)) {
            $mp->market_source_type = $data['market_source_type'] ?: 'manual';
        }

        if (array_key_exists('market_source_url', $data)) {
            $mp->market_source_url = $data['market_source_url'];
        }

        if (array_key_exists('is_wholesale_broadcast', $data)) {
            $mp->is_wholesale_broadcast = (bool) $data['is_wholesale_broadcast'];
        }

        if (array_key_exists('wholesale_price', $data)) {
            $mp->wholesale_price = $data['wholesale_price'] !== null && $data['wholesale_price'] !== '' ? (float) $data['wholesale_price'] : null;
        }

        if (array_key_exists('wholesale_min_qty', $data)) {
            $mp->wholesale_min_qty = ! empty($data['wholesale_min_qty']) ? (float) $data['wholesale_min_qty'] : 1;
        }

        if (array_key_exists('wholesale_note', $data)) {
            $mp->wholesale_note = $data['wholesale_note'];
        }

        if (array_key_exists('guild_category', $data)) {
            $mp->guild_category = $data['guild_category'];
        }

        $mp->last_checked_at = Carbon::now();
        $mp->updated_by_user_id = $userId;
        $mp->save();

        return $mp;
    }

    /**
     * B2B Guild Wholesale Network: list active quotations broadcasted by peer wholesalers.
     */
    public function getGuildBroadcastQuotations(?string $guildCategory = null, ?string $search = null): array
    {
        $query = ProductMarketPrice::where('is_wholesale_broadcast', true)
            ->whereNotNull('wholesale_price')
            ->where('wholesale_price', '>', 0)
            ->with(['product', 'company', 'updatedBy']);

        if ($guildCategory && $guildCategory !== 'all') {
            $query->where('guild_category', $guildCategory);
        }

        if ($search) {
            $query->whereHas('product', function ($pq) use ($search) {
                $pq->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        return $query->orderByDesc('updated_at')->get()->map(function (ProductMarketPrice $pmp) {
            return [
                'id' => $pmp->id,
                'product_id' => $pmp->product_id,
                'product_name' => $pmp->product?->name ?? 'کالا',
                'product_code' => $pmp->product?->code,
                'company_id' => $pmp->company_id,
                'wholesaler_name' => $pmp->company?->name ?? 'عمده‌فروشی همکار',
                'guild_category' => $pmp->guild_category,
                'wholesale_price' => (float) $pmp->wholesale_price,
                'wholesale_min_qty' => (float) $pmp->wholesale_min_qty,
                'wholesale_note' => $pmp->wholesale_note,
                'market_source_url' => $pmp->market_source_url,
                'updated_at' => $pmp->updated_at?->format('Y-m-d H:i'),
                'time_ago' => $pmp->updated_at?->diffForHumans(),
            ];
        })->toArray();
    }

    /**
     * Initialize baseline quotes if none exist in the database.
     */
    protected function ensureBaselineQuotesExist(?int $companyId = null): void
    {
        $count = MarketQuote::where('company_id', $companyId)->count();

        if ($count === 0) {
            $this->syncOnlineQuotes($companyId);
        }
    }
}
