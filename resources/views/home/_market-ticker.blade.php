@php
    $tickerQuotes = collect();
    try {
        $tickerQuotes = app(\App\Services\Market\MarketIntelligenceService::class)->getQuotes(getActiveCompany());
    } catch (\Throwable $e) {
        // Fallback gracefully if service has issues
    }

    $prioritySymbols = ['USD', 'USDT', 'AED', 'GOLD_18K', 'COIN_EMAMI'];
    $selectedQuotes = collect($prioritySymbols)->map(fn ($sym) => $tickerQuotes->firstWhere('symbol', $sym))->filter();

    // Fallback market quotes if no quotes in database yet so user always sees the ticker
    if ($selectedQuotes->isEmpty()) {
        $selectedQuotes = collect([
            (object) ['symbol' => 'USD', 'title_fa' => 'دلار آمریکا', 'price' => 702500, 'change_percent' => 0.4],
            (object) ['symbol' => 'USDT', 'title_fa' => 'تتر (USDT)', 'price' => 703000, 'change_percent' => 0.0],
            (object) ['symbol' => 'AED', 'title_fa' => 'درهم امارات', 'price' => 191500, 'change_percent' => 0.3],
            (object) ['symbol' => 'GOLD_18K', 'title_fa' => 'طلای ۱۸ عیار', 'price' => 46200000, 'change_percent' => 1.1],
            (object) ['symbol' => 'COIN_EMAMI', 'title_fa' => 'سکه امامی', 'price' => 538000000, 'change_percent' => 0.8],
        ]);
    }
@endphp

<div class="mb-4 rounded-3xl border border-emerald-500/20 bg-base-100/80 backdrop-blur-md shadow-xs p-3 overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-2 px-1">
        <div class="flex items-center gap-2">
            <span class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
            </span>
            <span class="text-xs font-black text-base-content flex items-center gap-1.5">
                <span>{{ __('مظنه زنده بازار و قیمت‌های روز') }}</span>
                <span class="badge badge-accent badge-xs py-0.5 px-1.5 text-[9px] font-mono">LIVE</span>
            </span>
        </div>

        <div class="flex items-center gap-2">
            <span class="text-[10px] text-emerald-600 bg-emerald-500/10 dark:text-emerald-400 px-2 py-0.5 rounded-full border border-emerald-500/20 hidden sm:inline">
                {{ __('مبنای ارزش‌گذاری دلاری انبار (D-137)') }}
            </span>
            <a href="{{ route('market-intelligence.index') }}" class="link link-hover text-xs text-primary font-medium flex items-center gap-1">
                <span>{{ __('تابلوی کامل بازار') }}</span>
                <span>←</span>
            </a>
        </div>
    </div>

    <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-thin">
        @foreach ($selectedQuotes as $quote)
            @php
                $isToman = $quote->symbol !== 'GOLD_OUNCE';
                $displayPrice = $isToman ? ($quote->price / 10) : $quote->price;
                $unit = $isToman ? 'تومان' : '$';
            @endphp
            <div class="flex-shrink-0 flex items-center gap-2.5 px-3 py-2 rounded-2xl bg-base-200/50 border border-base-300 hover:border-emerald-500/40 hover:bg-base-200 transition">
                <div>
                    <div class="text-[11px] font-semibold text-base-content/80 flex items-center gap-1">
                        <span>{{ $quote->title_fa }}</span>
                    </div>
                    <div class="flex items-center gap-1.5 mt-0.5">
                        <span class="font-mono font-bold text-sm text-base-content">
                            {{ $isToman ? number_format($displayPrice) : number_format($displayPrice, 2) }}
                        </span>
                        <span class="text-[10px] text-base-content/60">{{ $unit }}</span>
                    </div>
                </div>

                @if ($quote->change_percent != 0)
                    <span class="badge {{ $quote->change_percent >= 0 ? 'badge-success' : 'badge-error' }} badge-xs py-1 px-1.5 font-mono text-[9px]">
                        {{ $quote->change_percent >= 0 ? '+' : '' }}{{ number_format($quote->change_percent, 1) }}%
                    </span>
                @endif
            </div>
        @endforeach
    </div>
</div>
