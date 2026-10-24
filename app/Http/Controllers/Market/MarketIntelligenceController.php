<?php

namespace App\Http\Controllers\Market;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Industry\IndustryProfileRegistry;
use App\Services\Market\MarketIntelligenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketIntelligenceController extends Controller
{
    public function __construct(
        private readonly MarketIntelligenceService $service,
        private readonly IndustryProfileRegistry $industryRegistry,
    ) {}

    public function index(Request $request): View
    {
        $companyId = (int) getActiveCompany();
        $tab = $request->query('tab', 'rates');
        $guild = $request->query('guild');
        $search = $request->query('search');

        $this->service->ensureRealisticMarketData($companyId, auth()->id());
        $quotes = $this->service->getQuotes($companyId);
        $inventoryComparison = $this->service->getInventoryMarketComparison($companyId, $search);
        $guildBroadcasts = $this->service->getGuildBroadcastQuotations($guild, $search);
        $providerConfig = $this->service->getProviderConfig($companyId);

        $availableGuilds = array_keys($this->industryRegistry->all());

        return view('market-intelligence.index', [
            'tab' => $tab,
            'quotes' => $quotes,
            'inventoryComparison' => $inventoryComparison,
            'guildBroadcasts' => $guildBroadcasts,
            'providerConfig' => $providerConfig,
            'availableGuilds' => $availableGuilds,
            'activeGuild' => $guild,
            'search' => $search,
        ]);
    }

    public function updateQuote(Request $request, string $symbol): RedirectResponse|JsonResponse
    {
        $request->validate([
            'price' => ['required', 'numeric', 'min:0.0001'],
        ]);

        $companyId = (int) getActiveCompany();
        $userId = auth()->id();

        $quote = $this->service->updateQuoteManually($symbol, (float) $request->input('price'), $companyId, $userId);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'quote' => $quote,
                'message' => __('نرخ با موفقیت به صورت دستی ثبت شد.'),
            ]);
        }

        return redirect()->route('market-intelligence.index', ['tab' => 'rates'])
            ->with('success', __('نرخ :title با موفقیت به‌روزرسانی شد.', ['title' => $quote->title_fa]));
    }

    public function syncOnline(Request $request): RedirectResponse|JsonResponse
    {
        $companyId = (int) getActiveCompany();
        $updated = $this->service->syncOnlineQuotes($companyId);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'updated_count' => $updated,
                'message' => __('نرخ‌های بازار با موفقیت از منبع آنلاین استعلام و ثبت شدند.'),
            ]);
        }

        return redirect()->route('market-intelligence.index', ['tab' => 'rates'])
            ->with('success', __('تمام نرخ‌های بازار با موفقیت به‌روزرسانی شدند.'));
    }

    public function updateProductMarketPrice(Request $request, int $productId): RedirectResponse|JsonResponse
    {
        $request->validate([
            'market_price' => ['nullable', 'numeric', 'min:0'],
            'market_source_type' => ['nullable', 'string', 'in:manual,web_link,guild_peer'],
            'market_source_url' => ['nullable', 'string', 'max:500'],
            'is_wholesale_broadcast' => ['nullable', 'boolean'],
            'wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'wholesale_min_qty' => ['nullable', 'numeric', 'min:1'],
            'wholesale_note' => ['nullable', 'string', 'max:255'],
            'guild_category' => ['nullable', 'string', 'max:50'],
        ]);

        $companyId = (int) getActiveCompany();
        $userId = auth()->id();

        $pmp = $this->service->updateProductMarketPrice(
            $productId,
            $companyId,
            $request->only([
                'market_price',
                'market_source_type',
                'market_source_url',
                'is_wholesale_broadcast',
                'wholesale_price',
                'wholesale_min_qty',
                'wholesale_note',
                'guild_category',
            ]),
            $userId
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $pmp,
                'message' => __('اطلاعات قیمت روز کالا با موفقیت ذخیره شد.'),
            ]);
        }

        return redirect()->route('market-intelligence.index', ['tab' => 'inventory'])
            ->with('success', __('قیمت روز و مشخصات بازار کالا ثبت شد.'));
    }

    public function saveProviderSettings(Request $request): RedirectResponse
    {
        $request->validate([
            'provider' => ['required', 'string', 'in:managed,nobitex,navasan,bonbast,github_feed,manual'],
            'api_key' => ['nullable', 'string', 'max:255'],
        ]);

        $companyId = (int) getActiveCompany();
        $this->service->saveProviderConfig($companyId, $request->only('provider', 'api_key'));

        return redirect()->route('market-intelligence.index', ['tab' => 'settings'])
            ->with('success', __('تنظیمات سرویس‌دهنده قیمت‌ها با موفقیت ذخیره شد.'));
    }

    public function testProviderConnection(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => ['required', 'string', 'in:managed,nobitex,navasan,bonbast,github_feed,manual'],
            'api_key' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->service->testProviderConnection(
            $request->input('provider'),
            $request->input('api_key')
        );

        return response()->json($result);
    }

    public function getQuotesApi(): JsonResponse
    {
        $companyId = (int) getActiveCompany();
        $quotes = $this->service->getQuotes($companyId);

        return response()->json([
            'success' => true,
            'timestamp' => now()->toIso8601String(),
            'quotes' => $quotes,
        ]);
    }
}
