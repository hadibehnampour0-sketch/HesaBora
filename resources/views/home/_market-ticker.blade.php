@php
    $tickerQuotes = app(\App\Services\Market\MarketIntelligenceService::class)->getQuotes(getActiveCompany());
    $prioritySymbols = ['USD', 'GOLD_18K', 'COIN_EMAMI', 'AED', 'USDT', 'EUR', 'CNY', 'GOLD_MAZANEH', 'BTC', 'TRY', 'GBP'];
    $selectedQuotes = collect($prioritySymbols)->map(fn ($sym) => $tickerQuotes->firstWhere('symbol', $sym))->filter();
@endphp

@if ($selectedQuotes->isNotEmpty())
    <div class="mb-5 rounded-2xl border border-base-300 bg-base-100 shadow-sm p-3 overflow-hidden">
        <div class="flex items-center justify-between gap-3 mb-2 px-1">
            <div class="flex items-center gap-2">
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
                <span class="text-xs font-bold text-base-content flex items-center gap-1">
                    <span>{{ __('مظنه زنده بازار و قیمت‌های روز') }}</span>
                    <span class="badge badge-accent badge-xs py-0.5 px-1 text-[9px]">LIVE</span>
                </span>
            </div>

            <a href="{{ route('market-intelligence.index') }}" class="link link-hover text-xs text-primary font-medium flex items-center gap-1">
                <span>{{ __('مشاهده تابلوی کامل بازار و طلا') }}</span>
                <span>←</span>
            </a>
        </div>

        <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-thin">
            @foreach ($selectedQuotes as $quote)
                @php
                    $isToman = $quote->symbol !== 'GOLD_OUNCE';
                    $displayPrice = $isToman ? ($quote->price / 10) : $quote->price;
                    $unit = $isToman ? 'تومان' : '$';
                @endphp
                <div class="flex-shrink-0 flex items-center gap-2 px-3 py-2 rounded-xl bg-base-200/60 border border-base-200 hover:border-primary/40 hover:bg-base-200 transition">
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
@endif
