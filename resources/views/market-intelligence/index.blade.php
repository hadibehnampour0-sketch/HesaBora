<x-app-layout :title="__('رصد زنده بازار و قیمت روز اقلام')">
    <x-show-message-bags />

    <div class="space-y-6" x-data="marketIntelligence({ activeTab: '{{ $tab }}' })">
        {{-- Header & Top Stats --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gradient-to-l from-primary/10 via-base-100 to-base-200 p-6 rounded-2xl border border-base-300 shadow-sm">
            <div>
                <div class="flex items-center gap-2">
                    <span class="badge badge-accent badge-sm font-semibold tracking-wider">LIVE MARKET COCKPIT</span>
                    <span class="text-xs text-base-content/60">{{ now()->format('Y-m-d H:i') }}</span>
                    <span class="badge badge-outline badge-xs text-[10px]" x-text="'واحد فعال: ' + (unit === 'toman' ? 'تومان' : 'ریال')"></span>
                </div>
                <h1 class="text-2xl font-bold mt-1 text-base-content">{{ __('رصد زنده بازار، ارز، طلا و نرخ روز اقلام') }}</h1>
                <p class="text-xs text-base-content/70 mt-1 max-w-2xl leading-relaxed">
                    {{ __('استعلام آنلاین و لحظه‌ای طلا و ارزهای آزاد تهران، انطباق ارزش جایگزینی انبار با قیمت روز بازار، و شبکه تبادل نرخ عمده‌فروشی همکاران.') }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                {{-- Unit Switcher (تومان / ریال) --}}
                <div class="bg-base-200/80 p-1 rounded-xl border border-base-300 flex items-center shadow-inner">
                    <button type="button" class="btn btn-xs rounded-lg transition-all"
                        :class="unit === 'toman' ? 'btn-primary shadow-sm font-bold' : 'btn-ghost text-base-content/70'"
                        @click="setUnit('toman')">
                        {{ __('تومان') }}
                    </button>
                    <button type="button" class="btn btn-xs rounded-lg transition-all"
                        :class="unit === 'rial' ? 'btn-primary shadow-sm font-bold' : 'btn-ghost text-base-content/70'"
                        @click="setUnit('rial')">
                        {{ __('ریال') }}
                    </button>
                </div>

                {{-- Online Sync Button --}}
                <form method="POST" action="{{ route('market-intelligence.sync-online') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline btn-primary btn-sm gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        {{ __('به‌روزرسانی آنلاین نرخ‌ها') }}
                    </button>
                </form>
            </div>
        </div>

        {{-- Top Market Ticker Cards --}}
        @php
            $usd = $quotes->firstWhere('symbol', 'USD');
            $gold = $quotes->firstWhere('symbol', 'GOLD_18K');
            $coin = $quotes->firstWhere('symbol', 'COIN_EMAMI');
            $usdt = $quotes->firstWhere('symbol', 'USDT');
        @endphp
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            @if ($usd)
                <div class="card bg-base-100 shadow border border-base-200 hover:border-primary/40 transition">
                    <div class="card-body p-4">
                        <div class="flex items-center justify-between text-xs text-base-content/60">
                            <span class="font-bold text-base-content/90">{{ $usd->title_fa }}</span>
                            <span class="badge {{ $usd->change_percent >= 0 ? 'badge-success' : 'badge-error' }} badge-xs py-1">
                                {{ $usd->change_percent >= 0 ? '+' : '' }}{{ number_format($usd->change_percent, 1) }}%
                            </span>
                        </div>
                        <div class="text-xl font-black mt-1 text-primary font-mono">
                            <span x-text="formatMoney({{ $usd->price }})"></span>
                            <span class="text-xs font-normal text-base-content/70" x-text="unitLabel()"></span>
                        </div>
                        <div class="text-[10px] text-base-content/50 flex items-center justify-between mt-1">
                            <span x-text="'معادل: ' + formatAltMoney({{ $usd->price }}) + ' ' + altUnitLabel()"></span>
                            <span class="badge badge-ghost badge-xs">{{ $usd->source === 'manual' ? 'دستی' : 'آنلاین' }}</span>
                        </div>
                    </div>
                </div>
            @endif

            @if ($gold)
                <div class="card bg-base-100 shadow border border-base-200 hover:border-amber-400/40 transition">
                    <div class="card-body p-4">
                        <div class="flex items-center justify-between text-xs text-base-content/60">
                            <span class="font-bold text-base-content/90">{{ $gold->title_fa }}</span>
                            <span class="badge {{ $gold->change_percent >= 0 ? 'badge-success' : 'badge-error' }} badge-xs py-1">
                                {{ $gold->change_percent >= 0 ? '+' : '' }}{{ number_format($gold->change_percent, 1) }}%
                            </span>
                        </div>
                        <div class="text-xl font-black mt-1 text-amber-500 font-mono">
                            <span x-text="formatMoney({{ $gold->price }})"></span>
                            <span class="text-xs font-normal text-base-content/70" x-text="unitLabel()"></span>
                        </div>
                        <div class="text-[10px] text-base-content/50 flex items-center justify-between mt-1">
                            <span x-text="'معادل: ' + formatAltMoney({{ $gold->price }}) + ' ' + altUnitLabel()"></span>
                            <span class="badge badge-ghost badge-xs">طلا ۱۸</span>
                        </div>
                    </div>
                </div>
            @endif

            @if ($coin)
                <div class="card bg-base-100 shadow border border-base-200 hover:border-warning/40 transition">
                    <div class="card-body p-4">
                        <div class="flex items-center justify-between text-xs text-base-content/60">
                            <span class="font-bold text-base-content/90">{{ $coin->title_fa }}</span>
                            <span class="badge {{ $coin->change_percent >= 0 ? 'badge-success' : 'badge-error' }} badge-xs py-1">
                                {{ $coin->change_percent >= 0 ? '+' : '' }}{{ number_format($coin->change_percent, 1) }}%
                            </span>
                        </div>
                        <div class="text-xl font-black mt-1 text-warning font-mono">
                            <span x-text="formatMoney({{ $coin->price }})"></span>
                            <span class="text-xs font-normal text-base-content/70" x-text="unitLabel()"></span>
                        </div>
                        <div class="text-[10px] text-base-content/50 flex items-center justify-between mt-1">
                            <span x-text="'معادل: ' + formatAltMoney({{ $coin->price }}) + ' ' + altUnitLabel()"></span>
                            <span class="badge badge-ghost badge-xs">طرح جدید</span>
                        </div>
                    </div>
                </div>
            @endif

            @if ($usdt)
                <div class="card bg-base-100 shadow border border-base-200 hover:border-emerald-500/40 transition">
                    <div class="card-body p-4">
                        <div class="flex items-center justify-between text-xs text-base-content/60">
                            <span class="font-bold text-base-content/90">{{ $usdt->title_fa }}</span>
                            <span class="badge {{ $usdt->change_percent >= 0 ? 'badge-success' : 'badge-error' }} badge-xs py-1">
                                {{ $usdt->change_percent >= 0 ? '+' : '' }}{{ number_format($usdt->change_percent, 1) }}%
                            </span>
                        </div>
                        <div class="text-xl font-black mt-1 text-emerald-600 font-mono">
                            <span x-text="formatMoney({{ $usdt->price }})"></span>
                            <span class="text-xs font-normal text-base-content/70" x-text="unitLabel()"></span>
                        </div>
                        <div class="text-[10px] text-base-content/50 flex items-center justify-between mt-1">
                            <span x-text="'معادل: ' + formatAltMoney({{ $usdt->price }}) + ' ' + altUnitLabel()"></span>
                            <span class="badge badge-ghost badge-xs">USDT</span>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Tabs Navigation --}}
        <div class="tabs tabs-boxed bg-base-200 p-1.5 rounded-2xl flex flex-wrap gap-1">
            <button type="button" class="tab font-bold transition-all gap-1.5" :class="{ 'tab-active': activeTab === 'rates' }" @click="activeTab = 'rates'">
                💵 <span>{{ __('تابلوی زنده قیمت طلا، ارز و سکه') }}</span>
            </button>
            <button type="button" class="tab font-bold transition-all gap-1.5" :class="{ 'tab-active': activeTab === 'inventory' }" @click="activeTab = 'inventory'">
                📦 <span>{{ __('قیمت روز اقلام انبار و ارزیابی مارجین') }}</span>
                @if (count($inventoryComparison) > 0)
                    <span class="badge badge-primary badge-xs">{{ count($inventoryComparison) }}</span>
                @endif
            </button>
            <button type="button" class="tab font-bold transition-all gap-1.5" :class="{ 'tab-active': activeTab === 'guild' }" @click="activeTab = 'guild'">
                🤝 <span>{{ __('قیمت عمده‌فروشان و هم‌صنفان (B2B)') }}</span>
                @if (count($guildBroadcasts) > 0)
                    <span class="badge badge-secondary badge-xs">{{ count($guildBroadcasts) }}</span>
                @endif
            </button>
            <button type="button" class="tab font-bold transition-all gap-1.5" :class="{ 'tab-active': activeTab === 'settings' }" @click="activeTab = 'settings'">
                ⚙️ <span>{{ __('تنظیمات وب‌سرویس و منابع استعلام') }}</span>
            </button>
        </div>

        {{-- TAB 1: CURRENCY & GOLD RATES --}}
        <div x-show="activeTab === 'rates'" class="space-y-4">
            <div class="card bg-base-100 shadow border border-base-200">
                <div class="card-body p-4 sm:p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                        <div>
                            <h2 class="text-lg font-bold">{{ __('مظنه زنده بازار آزاد و مسکوکات تهران') }}</h2>
                            <p class="text-xs text-base-content/60">{{ __('نرخ‌های معاملاتی لحظه‌ای؛ برای ویرایش سریع هر نرخ روی دکمه «ثبت دستی نرخ» کلیک کنید.') }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="badge badge-accent badge-sm font-semibold">استعلام خودکار فعال</span>
                            <span class="text-xs opacity-60">کل شاخص‌ها: {{ $quotes->count() }}</span>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="table table-zebra table-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('شاخص / نماد') }}</th>
                                    <th>{{ __('دسته‌بندی') }}</th>
                                    <th class="text-end">
                                        <span x-text="'آخرین نرخ (' + unitLabel() + ')'"></span>
                                    </th>
                                    <th class="text-end">{{ __('نوسان') }}</th>
                                    <th class="text-end">{{ __('کف / سقف روز') }}</th>
                                    <th>{{ __('وضعیت منبع') }}</th>
                                    <th>{{ __('زمان استعلام') }}</th>
                                    <th class="text-center">{{ __('عملیات') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($quotes as $q)
                                    <tr class="hover">
                                        <td class="font-bold">
                                            <div class="flex items-center gap-2">
                                                <span>{{ $q->title_fa }}</span>
                                                <span class="badge badge-ghost badge-xs">{{ $q->symbol }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            @if ($q->category === 'currency')
                                                <span class="badge badge-info badge-sm">ارز آزاد</span>
                                            @elseif ($q->category === 'gold')
                                                <span class="badge badge-warning badge-sm">طلا</span>
                                            @elseif ($q->category === 'coin')
                                                <span class="badge badge-warning badge-outline badge-sm">سکه</span>
                                            @else
                                                <span class="badge badge-success badge-sm">کریپتو</span>
                                            @endif
                                        </td>
                                        <td class="text-end font-mono font-bold text-base">
                                            @if ($q->symbol === 'GOLD_OUNCE')
                                                <span>${{ number_format($q->price, 2) }}</span>
                                            @else
                                                <div class="text-primary font-black" x-text="formatMoney({{ $q->price }})"></div>
                                                <div class="text-[10px] text-base-content/50 font-normal" x-text="formatAltMoney({{ $q->price }}) + ' ' + altUnitLabel()"></div>
                                            @endif
                                        </td>
                                        <td class="text-end font-mono">
                                            <span class="badge {{ $q->change_percent >= 0 ? 'badge-success' : 'badge-error' }} badge-sm">
                                                {{ $q->change_percent >= 0 ? '+' : '' }}{{ number_format($q->change_percent, 2) }}%
                                            </span>
                                        </td>
                                        <td class="text-end text-xs opacity-75 font-mono">
                                            @if ($q->symbol === 'GOLD_OUNCE')
                                                ${{ number_format($q->low_price ?: $q->price * 0.99, 1) }} / ${{ number_format($q->high_price ?: $q->price * 1.01, 1) }}
                                            @else
                                                <span x-text="formatMoney({{ $q->low_price ?: $q->price * 0.99 }}) + ' / ' + formatMoney({{ $q->high_price ?: $q->price * 1.01 }})"></span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($q->source === 'manual')
                                                <span class="badge badge-neutral badge-xs">دستی</span>
                                            @else
                                                <span class="badge badge-accent badge-outline badge-xs">آنلاین</span>
                                            @endif
                                        </td>
                                        <td class="text-xs opacity-60">
                                            {{ $q->updated_at->format('Y-m-d H:i') }}
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-xs btn-outline btn-primary"
                                                @click="openQuoteModal('{{ $q->symbol }}', '{{ $q->title_fa }}', {{ $q->price }})">
                                                {{ __('ثبت دستی نرخ') }}
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- TAB 2: INVENTORY MARKET VALUE & SPREAD --}}
        <div x-show="activeTab === 'inventory'" class="space-y-4">
            <div class="card bg-base-100 shadow border border-base-200">
                <div class="card-body p-4 sm:p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                        <div>
                            <h2 class="text-lg font-bold">{{ __('ارزیابی موجودی انبار با قیمت روز بازار و مارجین سود') }}</h2>
                            <p class="text-xs text-base-content/60">{{ __('مقایسه بهای تمام‌شده دفتری هر کالا با قیمت روز کف بازار در ترب و دیجی‌کالا، و تعیین انتشار در شبکه عمده‌فروشی.') }}</p>
                        </div>

                        {{-- Search Filter --}}
                        <form method="GET" action="{{ route('market-intelligence.index') }}" class="flex items-center gap-2">
                            <input type="hidden" name="tab" value="inventory">
                            <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('جستجوی کالا یا بارکد...') }}" class="input input-sm input-bordered w-56">
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('بیاب') }}</button>
                        </form>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="table table-zebra table-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('کد و نام کالا') }}</th>
                                    <th>{{ __('صنف / گروه') }}</th>
                                    <th>{{ __('موجودی') }}</th>
                                    <th class="text-end">
                                        <span x-text="'بهای دفتری (' + unitLabel() + ')'"></span>
                                    </th>
                                    <th class="text-end">
                                        <span x-text="'فروش شما (' + unitLabel() + ')'"></span>
                                    </th>
                                    <th class="text-end font-semibold text-cyan-600 dark:text-cyan-400" title="{{ __('محاسبه بر اساس نرخ دلار روز و بهای دلاری خرید (D-137)') }}">
                                        <span x-text="'بهای جایگزینی دلاری (' + unitLabel() + ')'"></span>
                                    </th>
                                    <th class="text-end font-semibold text-emerald-600 dark:text-emerald-400" title="{{ __('قیمت پیشنهادی فروش با لحاظ حاشیه سود هدف') }}">
                                        <span x-text="'فروش پیشنهادی (' + unitLabel() + ')'"></span>
                                    </th>
                                    <th class="text-center">{{ __('محافظ سرمایه (D-137)') }}</th>
                                    <th class="text-end">
                                        <span x-text="'نرخ روز بازار (' + unitLabel() + ')'"></span>
                                    </th>
                                    <th class="text-end">{{ __('مارجین سود روز') }}</th>
                                    <th>{{ __('استعلام آنلاین') }}</th>
                                    <th>{{ __('شبکه عمده') }}</th>
                                    <th class="text-center">{{ __('عملیات') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($inventoryComparison as $item)
                                    <tr class="hover">
                                        <td>
                                            <div class="font-bold text-base-content">{{ $item['name'] }}</div>
                                            <div class="text-xs opacity-50 font-mono">{{ $item['code'] }}</div>
                                        </td>
                                        <td>
                                            <span class="badge badge-ghost badge-sm">{{ $item['group'] ?: 'عمومی' }}</span>
                                        </td>
                                        <td class="font-mono font-semibold">
                                            {{ number_format($item['quantity']) }}
                                        </td>
                                        <td class="text-end font-mono">
                                            <span x-text="formatMoney({{ $item['book_cost'] }})"></span>
                                        </td>
                                        <td class="text-end font-mono font-medium">
                                            <span x-text="formatMoney({{ $item['selling_price'] }})"></span>
                                        </td>
                                        {{-- Decision D-137: Real-time Replacement Cost --}}
                                        <td class="text-end font-mono text-cyan-600 dark:text-cyan-400 font-semibold">
                                            <div x-text="formatMoney({{ $item['replacement_cost_irr'] }})"></div>
                                            <div class="text-[10px] opacity-70 font-mono" title="{{ __('بهای دلاری پایه') }}">${{ number_format($item['base_usd_cost'], 2) }}</div>
                                        </td>
                                        {{-- Decision D-137: Suggested Selling Price with Margin --}}
                                        <td class="text-end font-mono text-emerald-600 dark:text-emerald-400 font-bold">
                                            <span x-text="formatMoney({{ $item['suggested_selling_price_irr'] }})"></span>
                                        </td>
                                        {{-- Decision D-137: Capital Protection Alert Badge --}}
                                        <td class="text-center">
                                            @if ($item['is_below_replacement'])
                                                <span class="badge badge-error badge-sm gap-1" title="{{ __('فروش زیر بهای جایگزینی دلاری!') }}">
                                                    ⚠️ {{ __('زیر جایگزینی') }}
                                                </span>
                                            @elseif ($item['is_below_suggested'])
                                                <span class="badge badge-warning badge-sm gap-1" title="{{ __('حاشیه سود کمتر از هدف است') }}">
                                                    ⚠️ {{ __('سود پایین') }}
                                                </span>
                                            @else
                                                <span class="badge badge-success badge-sm gap-1">
                                                    🛡️ {{ __('حاشیه امن') }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-end font-mono font-bold text-accent">
                                            @if ($item['market_price'])
                                                <div x-text="formatMoney({{ $item['market_price'] }})"></div>
                                                <div class="text-[10px] opacity-60 font-normal" x-text="formatAltMoney({{ $item['market_price'] }}) + ' ' + altUnitLabel()"></div>
                                            @else
                                                <span class="text-xs opacity-40 font-normal">ثبت نشده</span>
                                            @endif
                                        </td>
                                        <td class="text-end font-mono">
                                            @if ($item['spread_percent'] !== null)
                                                <span class="badge {{ $item['spread_percent'] >= 0 ? 'badge-success' : 'badge-error' }} badge-sm">
                                                    {{ $item['spread_percent'] >= 0 ? '+' : '' }}{{ number_format($item['spread_percent'], 1) }}%
                                                </span>
                                            @else
                                                <span class="text-xs opacity-40">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($item['market_source_url'])
                                                <a href="{{ $item['market_source_url'] }}" target="_blank" rel="noopener noreferrer" class="link link-primary text-xs flex items-center gap-1 font-medium">
                                                    <span>{{ __('استعلام در وب') }}</span>
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                    </svg>
                                                </a>
                                            @else
                                                <span class="text-xs opacity-40">{{ __('بدون لینک') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($item['is_wholesale_broadcast'])
                                                <span class="badge badge-accent badge-xs">{{ __('منتشر شده') }}</span>
                                            @else
                                                <span class="badge badge-ghost badge-xs">{{ __('داخلی') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-xs btn-outline btn-primary"
                                                @click="openProductModal({{ json_encode($item) }})">
                                                {{ __('تنظیم بهای دلاری و عمده') }}
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="13" class="text-center py-6 text-sm opacity-60">
                                            {{ __('هیچ کالایی یافت نشد.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- TAB 3: B2B GUILD WHOLESALE NETWORK --}}
        <div x-show="activeTab === 'guild'" class="space-y-4">
            <div class="card bg-base-100 shadow border border-base-200">
                <div class="card-body p-4 sm:p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-lg font-bold">{{ __('شبکه تبادل نرخ عمده‌فروشی هم‌صنفان (B2B)') }}</h2>
                                <span class="badge badge-accent badge-sm font-semibold">تأییدشده</span>
                            </div>
                            <p class="text-xs text-base-content/60">{{ __('نرخ‌های پخش و عمده‌فروشی ثبت‌شده توسط بنکداران برای همکاران نپتون جهت تأمین کالا، سفارش تیراژ و هماهنگی قیمت کف بازار.') }}</p>
                        </div>

                        {{-- Guild Filter --}}
                        <form method="GET" action="{{ route('market-intelligence.index') }}" class="flex flex-wrap items-center gap-2">
                            <input type="hidden" name="tab" value="guild">
                            <select name="guild" class="select select-sm select-bordered" onchange="this.form.submit()">
                                <option value="">{{ __('همه اصناف') }}</option>
                                @foreach ($availableGuilds as $g)
                                    <option value="{{ $g }}" @selected($activeGuild === $g)>
                                        @if ($g === 'mobile') 📱 موبایل و جانبی
                                        @elseif ($g === 'home_appliances') 📺 لوازم خانگی
                                        @elseif ($g === 'auto_parts') 🚗 لوازم یدکی خودرو
                                        @elseif ($g === 'gold') 💍 طلا و مسکوکات
                                        @elseif ($g === 'apparel') 👕 پوشاک و مد
                                        @else {{ $g }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('جستجوی نام کالا یا همکار...') }}" class="input input-sm input-bordered w-48">
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('فیلتر') }}</button>
                        </form>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @forelse ($guildBroadcasts as $b)
                            <div class="card bg-base-200/50 border border-base-300 p-4 rounded-2xl hover:shadow-lg hover:border-primary/50 transition">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <h3 class="font-bold text-sm text-base-content truncate">{{ $b['product_name'] }}</h3>
                                        <div class="text-xs text-primary font-medium mt-0.5 flex items-center gap-1">
                                            <span>🏢</span>
                                            <span>{{ $b['wholesaler_name'] }}</span>
                                        </div>
                                    </div>
                                    <span class="badge badge-accent badge-sm font-semibold shrink-0">
                                        @if ($b['guild_category'] === 'mobile') 📱 موبایل
                                        @elseif ($b['guild_category'] === 'home_appliances') 📺 لوازم خانگی
                                        @elseif ($b['guild_category'] === 'auto_parts') 🚗 یدکی خودرو
                                        @elseif ($b['guild_category'] === 'gold') 💍 طلا و مسکوکات
                                        @elseif ($b['guild_category'] === 'apparel') 👕 پوشاک
                                        @else {{ $b['guild_category'] }}
                                        @endif
                                    </span>
                                </div>

                                <div class="mt-4 p-3 bg-base-100 rounded-xl border border-base-200 flex items-center justify-between">
                                    <div>
                                        <div class="text-xs opacity-60">{{ __('نرخ عمده‌فروشی') }}</div>
                                        <div class="text-lg font-black text-primary font-mono mt-0.5">
                                            <span x-text="formatMoney({{ $b['wholesale_price'] }})"></span>
                                            <span class="text-xs font-normal" x-text="unitLabel()"></span>
                                        </div>
                                        <div class="text-[10px] opacity-60 font-mono" x-text="formatAltMoney({{ $b['wholesale_price'] }}) + ' ' + altUnitLabel()"></div>
                                    </div>
                                    <div class="text-end">
                                        <div class="text-xs opacity-60">{{ __('حداقل تیراژ') }}</div>
                                        <div class="text-sm font-bold font-mono text-base-content mt-0.5">
                                            {{ number_format($b['wholesale_min_qty']) }} <span class="text-xs font-normal">عدد</span>
                                        </div>
                                        <span class="badge badge-ghost badge-xs mt-1">ویژه همکار</span>
                                    </div>
                                </div>

                                @if ($b['wholesale_note'])
                                    <div class="text-xs opacity-80 mt-3 bg-base-200/80 p-2.5 rounded-lg border border-base-300 flex items-start gap-1.5 leading-relaxed">
                                        <span class="text-base-content/50">📝</span>
                                        <span>{{ $b['wholesale_note'] }}</span>
                                    </div>
                                @endif

                                <div class="flex items-center justify-between text-[11px] opacity-60 mt-3 pt-2.5 border-t border-base-300">
                                    <span>به‌روزرسانی: {{ $b['time_ago'] }}</span>
                                    @if ($b['market_source_url'])
                                        <a href="{{ $b['market_source_url'] }}" target="_blank" rel="noopener noreferrer" class="link link-primary font-medium flex items-center gap-0.5">
                                            <span>بررسی در وب</span>
                                            <span>↗</span>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="col-span-full py-12 text-center text-sm opacity-60">
                                {{ __('در حال حاضر هیچ پیشنهاد عمده‌فروشی فعالی در این صنف ثبت نشده است.') }}
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- TAB 4: API & PROVIDER SETTINGS --}}
        <div x-show="activeTab === 'settings'" class="space-y-4">
            <div class="card bg-base-100 shadow border border-base-200">
                <div class="card-body p-4 sm:p-6">
                    <div class="mb-4">
                        <h2 class="text-lg font-bold">{{ __('پیکربندی منابع دریافت قیمت‌ها (Bring Your Own API)') }}</h2>
                        <p class="text-xs text-base-content/60">{{ __('سرویس هوشمند نپتون به طور پیش‌فرض آخرین نرخ‌های زنده و واقعی بازار را دریافت می‌کند. همچنین می‌توانید وب‌سرویس اختصاصی خود را متصل فرمایید.') }}</p>
                    </div>

                    <form method="POST" action="{{ route('market-intelligence.save-settings') }}" class="space-y-6">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach ($providerConfig['providers_list'] as $pKey => $pSpec)
                                <label class="card bg-base-200/60 border border-base-300 p-4 rounded-2xl cursor-pointer hover:border-primary transition"
                                    :class="{ 'border-primary ring-2 ring-primary/20 bg-primary/5': selectedProvider === '{{ $pKey }}' }">
                                    <div class="flex items-start gap-3">
                                        <input type="radio" name="provider" value="{{ $pKey }}" class="radio radio-primary radio-sm mt-1"
                                            x-model="selectedProvider" @checked($providerConfig['provider'] === $pKey)>
                                        <div class="flex-1">
                                            <div class="font-bold text-sm text-base-content flex items-center justify-between">
                                                <span>{{ $pSpec['title'] }}</span>
                                                @if (! $pSpec['needs_api_key'])
                                                    <span class="badge badge-success badge-xs">رایگان و بدون کلید</span>
                                                @else
                                                    <span class="badge badge-info badge-xs">نیازمند API Key</span>
                                                @endif
                                            </div>
                                            <div class="text-xs text-base-content/70 mt-1 leading-relaxed">{{ $pSpec['description'] }}</div>
                                            @if (! empty($pSpec['signup_url']))
                                                <a href="{{ $pSpec['signup_url'] }}" target="_blank" rel="noopener noreferrer" class="link link-primary text-[11px] mt-2 inline-block">
                                                    دریافت کلید اختصاصی از وب‌سایت مرجع ←
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>

                        {{-- API Key Input --}}
                        <div x-show="selectedProvider === 'navasan' || selectedProvider === 'bonbast'" class="form-control max-w-md">
                            <label class="label">
                                <span class="label-text text-xs font-semibold">{{ __('کلید اختصاصی وب‌سرویس (API Key)') }}</span>
                            </label>
                            <input type="text" name="api_key" x-model="apiKey" placeholder="مثال: nvs_live_abcdef123456" class="input input-bordered input-sm font-mono">
                        </div>

                        {{-- Test Connection Button --}}
                        <div class="pt-2 border-t border-base-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <button type="button" class="btn btn-sm btn-outline btn-primary" @click="testConnection()" :disabled="testing">
                                    <span x-show="!testing">تست اتصال به وب‌سرویس و اندازه‌گیری تأخیر</span>
                                    <span x-show="testing" class="loading loading-spinner loading-xs"></span>
                                </button>
                                <span x-text="testMessage" class="text-xs font-medium" :class="testSuccess ? 'text-success' : 'text-error'"></span>
                            </div>

                            <button type="submit" class="btn btn-primary btn-sm">{{ __('ذخیره تنظیمات وب‌سرویس') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- MODAL 1: Manual Quote Update --}}
        <div class="modal" :class="{ 'modal-open': isQuoteModalOpen }">
            <div class="modal-box">
                <h3 class="font-bold text-lg mb-1">{{ __('ثبت دستی نرخ') }}: <span class="text-primary" x-text="quoteModalData.title"></span></h3>
                <p class="text-xs text-base-content/60 mb-4">{{ __('مبلغ وارد شده مستقیماً در تابلوی نرخ‌های شرکت ثبت و محاسبه خواهد شد.') }}</p>

                <form :action="quoteModalData.action" method="POST">
                    @csrf
                    <div class="form-control mb-4">
                        <label class="label">
                            <span class="label-text text-xs font-semibold">{{ __('مبلغ بر حسب ریال') }}</span>
                            <span class="label-text-alt text-xs text-primary font-mono" x-text="'معادل: ' + (Math.round((quoteModalData.price || 0) / 10)).toLocaleString('fa-IR') + ' تومان'"></span>
                        </label>
                        <input type="number" step="any" name="price" x-model="quoteModalData.price" required class="input input-bordered w-full font-mono text-lg font-bold">
                    </div>

                    <div class="modal-action">
                        <button type="button" class="btn btn-ghost btn-sm" @click="isQuoteModalOpen = false">{{ __('انصراف') }}</button>
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('ثبت و ذخیره نرخ') }}</button>
                    </div>
                </form>
            </div>
            <div class="modal-backdrop" @click="isQuoteModalOpen = false"></div>
        </div>

        {{-- MODAL 2: Product Market Price & Broadcast --}}
        <div class="modal" :class="{ 'modal-open': isProductModalOpen }">
            <div class="modal-box max-w-lg">
                <h3 class="font-bold text-lg mb-1">{{ __('ثبت قیمت روز و تنظیمات عمده کالا') }}</h3>
                <p class="text-xs text-base-content/60 mb-4" x-text="productModalData.name"></p>

                <form :action="productModalData.action" method="POST" class="space-y-4">
                    @csrf
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text text-xs font-semibold">{{ __('قیمت روز بازار (ریال)') }}</span>
                            <span class="label-text-alt text-xs text-primary font-mono" x-text="'معادل: ' + (Math.round((productModalData.market_price || 0) / 10)).toLocaleString('fa-IR') + ' تومان'"></span>
                        </label>
                        <input type="number" step="any" name="market_price" x-model="productModalData.market_price" class="input input-bordered w-full font-mono">
                    </div>

                    <div class="form-control">
                        <label class="label"><span class="label-text text-xs">{{ __('لینک استعلام وب (ترب، دیجی‌کالا، ایمالز)') }}</span></label>
                        <input type="url" name="market_source_url" x-model="productModalData.market_source_url" placeholder="https://torob.com/p/..." class="input input-bordered w-full text-xs">
                    </div>

                    <div class="divider text-xs opacity-60 text-cyan-600 dark:text-cyan-400 font-bold">💎 {{ __('محافظ هوشمند سود و بهای جایگزینی دلاری (D-137)') }}</div>

                    <div class="form-control">
                        <label class="label cursor-pointer justify-start gap-3">
                            <input type="checkbox" name="is_pegged_to_usd" value="1" x-model="productModalData.is_pegged_to_usd" class="checkbox checkbox-accent checkbox-sm">
                            <span class="label-text text-xs font-bold">{{ __('فعال‌سازی ارزیابی دلاری و سپر ضد تورم برای این کالا') }}</span>
                        </label>
                    </div>

                    <div x-show="productModalData.is_pegged_to_usd" class="space-y-3 pt-1">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="form-control">
                                <label class="label"><span class="label-text text-xs">{{ __('بهای دلاری خرید ($)') }}</span></label>
                                <input type="number" step="0.0001" name="base_usd_cost" x-model="productModalData.base_usd_cost" placeholder="0.00" class="input input-bordered input-sm font-mono text-cyan-600">
                            </div>
                            <div class="form-control">
                                <label class="label"><span class="label-text text-xs">{{ __('حاشیه سود هدف (٪)') }}</span></label>
                                <input type="number" step="0.1" name="target_margin_percent" x-model="productModalData.target_margin_percent" placeholder="20" class="input input-bordered input-sm font-mono text-emerald-600">
                            </div>
                        </div>
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text text-xs">{{ __('نرخ دلار مبنا در زمان خرید (ریال)') }}</span>
                                <span class="label-text-alt text-xs opacity-60" x-text="'معادل: ' + (Math.round((productModalData.purchase_usd_rate || 0) / 10)).toLocaleString('fa-IR') + ' تومان'"></span>
                            </label>
                            <input type="number" step="any" name="purchase_usd_rate" x-model="productModalData.purchase_usd_rate" class="input input-bordered input-sm font-mono">
                        </div>
                    </div>

                    <div class="divider text-xs opacity-60">{{ __('اشتراک در شبکه عمده‌فروشی هم‌صنفان (B2B)') }}</div>

                    <div class="form-control">
                        <label class="label cursor-pointer justify-start gap-3">
                            <input type="checkbox" name="is_wholesale_broadcast" value="1" x-model="productModalData.is_wholesale_broadcast" class="checkbox checkbox-primary checkbox-sm">
                            <span class="label-text text-xs font-bold">{{ __('انتشار قیمت عمده‌فروشی در تابلوی همکاران نپتون') }}</span>
                        </label>
                    </div>

                    <div x-show="productModalData.is_wholesale_broadcast" class="space-y-3 pt-2">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="form-control">
                                <label class="label"><span class="label-text text-xs">{{ __('قیمت عمده (ریال)') }}</span></label>
                                <input type="number" step="any" name="wholesale_price" x-model="productModalData.wholesale_price" class="input input-bordered input-sm font-mono">
                            </div>
                            <div class="form-control">
                                <label class="label"><span class="label-text text-xs">{{ __('حداقل تیراژ سفارش') }}</span></label>
                                <input type="number" step="any" name="wholesale_min_qty" x-model="productModalData.wholesale_min_qty" class="input input-bordered input-sm font-mono">
                            </div>
                        </div>

                        <div class="form-control">
                            <label class="label"><span class="label-text text-xs">{{ __('صنف کالا') }}</span></label>
                            <select name="guild_category" x-model="productModalData.guild_category" class="select select-bordered select-sm w-full">
                                <option value="">{{ __('انتخاب صنف...') }}</option>
                                @foreach ($availableGuilds as $g)
                                    <option value="{{ $g }}">
                                        @if ($g === 'mobile') موبایل و جانبی
                                        @elseif ($g === 'home_appliances') لوازم خانگی
                                        @elseif ($g === 'auto_parts') لوازم یدکی خودرو
                                        @elseif ($g === 'gold') طلا و مسکوکات
                                        @elseif ($g === 'apparel') پوشاک و مد
                                        @else {{ $g }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-control">
                            <label class="label"><span class="label-text text-xs">{{ __('شرایط و توضیحات فروش عمده') }}</span></label>
                            <input type="text" name="wholesale_note" x-model="productModalData.wholesale_note" placeholder="{{ __('مثال: تحویل پای انبار تهران، تسویه نقدی یا چک صیادی') }}" class="input input-bordered input-sm text-xs">
                        </div>
                    </div>

                    <div class="modal-action">
                        <button type="button" class="btn btn-ghost btn-sm" @click="isProductModalOpen = false">{{ __('انصراف') }}</button>
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('ذخیره تنظیمات کالا') }}</button>
                    </div>
                </form>
            </div>
            <div class="modal-backdrop" @click="isProductModalOpen = false"></div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('marketIntelligence', (config) => ({
                activeTab: config.activeTab || 'rates',
                unit: localStorage.getItem('market_display_unit') || 'toman',
                isQuoteModalOpen: false,
                quoteModalData: { symbol: '', title: '', price: 0, action: '' },
                isProductModalOpen: false,
                productModalData: {},
                selectedProvider: '{{ $providerConfig['provider'] }}',
                apiKey: '{{ $providerConfig['api_key'] }}',
                testing: false,
                testMessage: '',
                testSuccess: true,

                setUnit(newUnit) {
                    this.unit = newUnit;
                    localStorage.setItem('market_display_unit', newUnit);
                },

                unitLabel() {
                    return this.unit === 'toman' ? 'تومان' : 'ریال';
                },

                altUnitLabel() {
                    return this.unit === 'toman' ? 'ریال' : 'تومان';
                },

                formatMoney(amountInRials) {
                    if (!amountInRials && amountInRials !== 0) return '-';
                    const num = Number(amountInRials);
                    const val = this.unit === 'toman' ? Math.round(num / 10) : Math.round(num);
                    return val.toLocaleString('fa-IR');
                },

                formatAltMoney(amountInRials) {
                    if (!amountInRials && amountInRials !== 0) return '-';
                    const num = Number(amountInRials);
                    const val = this.unit === 'toman' ? Math.round(num) : Math.round(num / 10);
                    return val.toLocaleString('fa-IR');
                },

                async testConnection() {
                    this.testing = true;
                    this.testMessage = '';
                    try {
                        const token = document.querySelector('input[name=_token]').value;
                        const res = await fetch('{{ route('market-intelligence.test-connection') }}', {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token,
                            },
                            body: JSON.stringify({
                                provider: this.selectedProvider,
                                api_key: this.apiKey,
                            }),
                        });
                        const data = await res.json();
                        this.testSuccess = data.success;
                        this.testMessage = data.message || (data.success ? 'اتصال با موفقیت تأیید شد.' : 'خطا در برقراری ارتباط');
                    } catch (e) {
                        this.testSuccess = false;
                        this.testMessage = 'خطای ارتباط با سرور: ' + e;
                    } finally {
                        this.testing = false;
                    }
                },

                openQuoteModal(symbol, title, price) {
                    this.quoteModalData = {
                        symbol: symbol,
                        title: title,
                        price: price,
                        action: '{{ route('market-intelligence.index') }}/quotes/' + symbol,
                    };
                    this.isQuoteModalOpen = true;
                },

                openProductModal(item) {
                    this.productModalData = {
                        name: item.name,
                        market_price: item.market_price,
                        purchase_usd_rate: item.purchase_usd_rate,
                        base_usd_cost: item.base_usd_cost,
                        target_margin_percent: item.usd_valuation?.target_margin_percent ?? 20,
                        is_pegged_to_usd: item.usd_valuation?.is_pegged_to_usd ?? true,
                        market_source_url: item.market_source_url || '',
                        is_wholesale_broadcast: item.is_wholesale_broadcast,
                        wholesale_price: item.wholesale_price,
                        wholesale_min_qty: item.wholesale_min_qty || 1,
                        wholesale_note: item.wholesale_note || '',
                        guild_category: item.guild_category || '',
                        action: '{{ route('market-intelligence.index') }}/products/' + item.product_id,
                    };
                    this.isProductModalOpen = true;
                },
            }));
        });
    </script>
</x-app-layout>
