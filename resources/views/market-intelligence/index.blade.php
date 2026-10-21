<x-app-layout :title="__('رصد زنده بازار و قیمت روز اقلام')">
    <x-show-message-bags />

    <div class="space-y-6" x-data="marketIntelligence({ activeTab: '{{ $tab }}' })">
        {{-- Header & Top Stats --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gradient-to-l from-primary/10 via-base-100 to-base-200 p-6 rounded-2xl border border-base-300 shadow-sm">
            <div>
                <div class="flex items-center gap-2">
                    <span class="badge badge-accent badge-sm font-semibold tracking-wider">LIVE COCKPIT</span>
                    <span class="text-xs text-base-content/60">{{ now()->format('Y-m-d H:i') }}</span>
                </div>
                <h1 class="text-2xl font-bold mt-1 text-base-content">{{ __('رصد زنده بازار، ارز، طلا و نرخ روز اقلام') }}</h1>
                <p class="text-xs text-base-content/70 mt-1 max-w-2xl">
                    {{ __('استعلام آنلاین و ثبت دستی قیمت لحظه‌ای ارزها و طلا، مقایسه قیمت روز اقلام انبار با بهای دفتری، و اتصال به شبکه تبادل قیمت عمده‌فروشی هم‌صنفان.') }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <form method="POST" action="{{ route('market-intelligence.sync-online') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline btn-primary btn-sm gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        {{ __('استعلام آنلاین نرخ‌ها') }}
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
                <div class="card bg-base-100 shadow border border-base-200">
                    <div class="card-body p-4">
                        <div class="flex items-center justify-between text-xs text-base-content/60">
                            <span>{{ $usd->title_fa }}</span>
                            <span class="badge {{ $usd->change_percent >= 0 ? 'badge-success' : 'badge-error' }} badge-xs py-1">
                                {{ $usd->change_percent >= 0 ? '+' : '' }}{{ number_format($usd->change_percent, 1) }}%
                            </span>
                        </div>
                        <div class="text-xl font-black mt-1 text-primary">
                            {{ number_format($usd->price) }} <span class="text-xs font-normal text-base-content/70">ریال</span>
                        </div>
                        <div class="text-[10px] text-base-content/50 flex items-center justify-between mt-1">
                            <span>کف: {{ number_format($usd->low_price ?: $usd->price * 0.99) }}</span>
                            <span>سقف: {{ number_format($usd->high_price ?: $usd->price * 1.01) }}</span>
                        </div>
                    </div>
                </div>
            @endif

            @if ($gold)
                <div class="card bg-base-100 shadow border border-base-200">
                    <div class="card-body p-4">
                        <div class="flex items-center justify-between text-xs text-base-content/60">
                            <span>{{ $gold->title_fa }}</span>
                            <span class="badge {{ $gold->change_percent >= 0 ? 'badge-success' : 'badge-error' }} badge-xs py-1">
                                {{ $gold->change_percent >= 0 ? '+' : '' }}{{ number_format($gold->change_percent, 1) }}%
                            </span>
                        </div>
                        <div class="text-xl font-black mt-1 text-amber-500">
                            {{ number_format($gold->price) }} <span class="text-xs font-normal text-base-content/70">ریال</span>
                        </div>
                        <div class="text-[10px] text-base-content/50 flex items-center justify-between mt-1">
                            <span>مظنه: {{ number_format(($quotes->firstWhere('symbol', 'GOLD_MAZANEH')?->price) ?: 0) }}</span>
                            <span class="badge badge-ghost badge-xs">{{ $gold->source === 'manual' ? 'دستی' : 'آنلاین' }}</span>
                        </div>
                    </div>
                </div>
            @endif

            @if ($coin)
                <div class="card bg-base-100 shadow border border-base-200">
                    <div class="card-body p-4">
                        <div class="flex items-center justify-between text-xs text-base-content/60">
                            <span>{{ $coin->title_fa }}</span>
                            <span class="badge {{ $coin->change_percent >= 0 ? 'badge-success' : 'badge-error' }} badge-xs py-1">
                                {{ $coin->change_percent >= 0 ? '+' : '' }}{{ number_format($coin->change_percent, 1) }}%
                            </span>
                        </div>
                        <div class="text-xl font-black mt-1 text-warning">
                            {{ number_format($coin->price) }} <span class="text-xs font-normal text-base-content/70">ریال</span>
                        </div>
                        <div class="text-[10px] text-base-content/50 flex items-center justify-between mt-1">
                            <span>طرح جدید (امامی)</span>
                            <span class="badge badge-ghost badge-xs">{{ $coin->source === 'manual' ? 'دستی' : 'آنلاین' }}</span>
                        </div>
                    </div>
                </div>
            @endif

            @if ($usdt)
                <div class="card bg-base-100 shadow border border-base-200">
                    <div class="card-body p-4">
                        <div class="flex items-center justify-between text-xs text-base-content/60">
                            <span>{{ $usdt->title_fa }}</span>
                            <span class="badge {{ $usdt->change_percent >= 0 ? 'badge-success' : 'badge-error' }} badge-xs py-1">
                                {{ $usdt->change_percent >= 0 ? '+' : '' }}{{ number_format($usdt->change_percent, 1) }}%
                            </span>
                        </div>
                        <div class="text-xl font-black mt-1 text-emerald-600">
                            {{ number_format($usdt->price) }} <span class="text-xs font-normal text-base-content/70">ریال</span>
                        </div>
                        <div class="text-[10px] text-base-content/50 flex items-center justify-between mt-1">
                            <span>Tether USD (TRC20)</span>
                            <span class="badge badge-ghost badge-xs">{{ $usdt->source === 'manual' ? 'دستی' : 'آنلاین' }}</span>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Tabs Navigation --}}
        <div class="tabs tabs-boxed bg-base-200 p-1 rounded-xl">
            <button type="button" class="tab font-medium transition-all" :class="{ 'tab-active': activeTab === 'rates' }" @click="activeTab = 'rates'">
                💵 {{ __('تابلوی زنده قیمت طلا، ارز و سکه') }}
            </button>
            <button type="button" class="tab font-medium transition-all" :class="{ 'tab-active': activeTab === 'inventory' }" @click="activeTab = 'inventory'">
                📦 {{ __('قیمت روز اقلام انبار و ارزیابی سود') }}
            </button>
            <button type="button" class="tab font-medium transition-all" :class="{ 'tab-active': activeTab === 'guild' }" @click="activeTab = 'guild'">
                🤝 {{ __('شبکه عمده‌فروشی هم‌صنفان (B2B)') }}
                @if (count($guildBroadcasts) > 0)
                    <span class="badge badge-secondary badge-xs mr-2">{{ count($guildBroadcasts) }}</span>
                @endif
            </button>
            <button type="button" class="tab font-medium transition-all" :class="{ 'tab-active': activeTab === 'settings' }" @click="activeTab = 'settings'">
                ⚙️ {{ __('تنظیمات وب‌سرویس و کلید API') }}
            </button>
        </div>

        {{-- TAB 1: CURRENCY & GOLD RATES --}}
        <div x-show="activeTab === 'rates'" class="space-y-4">
            <div class="card bg-base-100 shadow border border-base-200">
                <div class="card-body p-4 sm:p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                        <div>
                            <h2 class="text-lg font-bold">{{ __('مظنه زنده بازار آزاد و مسکوکات') }}</h2>
                            <p class="text-xs text-base-content/60">{{ __('امکان مشاهده آخرین مظنه‌های آنلاین، ثبت دستی نرخ اختصاصی، و نگهداری سوابق.') }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="badge badge-outline text-xs">منبع فعال: ترکیبی (آنلاین + دستی)</span>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="table table-zebra table-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('شاخص / نماد') }}</th>
                                    <th>{{ __('دسته‌بندی') }}</th>
                                    <th class="text-end">{{ __('آخرین نرخ') }}</th>
                                    <th class="text-end">{{ __('نوسان') }}</th>
                                    <th class="text-end">{{ __('کف / سقف روز') }}</th>
                                    <th>{{ __('منبع / ثبت‌کننده') }}</th>
                                    <th>{{ __('آخرین به‌روزرسانی') }}</th>
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
                                                <span class="badge badge-info badge-sm">ارز</span>
                                            @elseif ($q->category === 'gold')
                                                <span class="badge badge-warning badge-sm">طلا</span>
                                            @elseif ($q->category === 'coin')
                                                <span class="badge badge-warning badge-outline badge-sm">سکه</span>
                                            @else
                                                <span class="badge badge-success badge-sm">کریپتو</span>
                                            @endif
                                        </td>
                                        <td class="text-end font-mono font-bold text-base">
                                            {{ number_format($q->price, $q->symbol === 'GOLD_OUNCE' ? 2 : 0) }}
                                            <span class="text-xs font-normal opacity-70">{{ $q->symbol === 'GOLD_OUNCE' ? '$' : 'ریال' }}</span>
                                        </td>
                                        <td class="text-end font-mono">
                                            <span class="badge {{ $q->change_percent >= 0 ? 'badge-success' : 'badge-error' }} badge-sm">
                                                {{ $q->change_percent >= 0 ? '+' : '' }}{{ number_format($q->change_percent, 2) }}%
                                            </span>
                                        </td>
                                        <td class="text-end text-xs opacity-75 font-mono">
                                            {{ number_format($q->low_price ?: $q->price * 0.99) }} / {{ number_format($q->high_price ?: $q->price * 1.01) }}
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
                                            <button type="button" class="btn btn-xs btn-outline"
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
                            <h2 class="text-lg font-bold">{{ __('ارزیابی موجودی انبار با قیمت روز بازار') }}</h2>
                            <p class="text-xs text-base-content/60">{{ __('مقایسه بهای تمام‌شده دفتری هر کالا با قیمت روز بازار، لینک‌کردن به سایت‌های مرجع و تنظیم انتشار در شبکه همکاران.') }}</p>
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
                                    <th>{{ __('موجودی') }}</th>
                                    <th class="text-end">{{ __('بهای تمام‌شده دفتری') }}</th>
                                    <th class="text-end">{{ __('قیمت فروش فعلی') }}</th>
                                    <th class="text-end">{{ __('قیمت روز بازار') }}</th>
                                    <th class="text-end">{{ __('حاشیه سود روز') }}</th>
                                    <th>{{ __('لینک استعلام وب') }}</th>
                                    <th>{{ __('انتشار عمده') }}</th>
                                    <th class="text-center">{{ __('عملیات') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($inventoryComparison as $item)
                                    <tr class="hover">
                                        <td>
                                            <div class="font-bold">{{ $item['name'] }}</div>
                                            <div class="text-xs opacity-50 font-mono">{{ $item['code'] }}</div>
                                        </td>
                                        <td class="font-mono">
                                            {{ number_format($item['quantity']) }}
                                        </td>
                                        <td class="text-end font-mono">
                                            {{ number_format($item['book_cost']) }} <span class="text-xs opacity-60">ریال</span>
                                        </td>
                                        <td class="text-end font-mono font-medium">
                                            {{ number_format($item['selling_price']) }} <span class="text-xs opacity-60">ریال</span>
                                        </td>
                                        <td class="text-end font-mono font-bold text-accent">
                                            @if ($item['market_price'])
                                                {{ number_format($item['market_price']) }} <span class="text-xs opacity-60">ریال</span>
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
                                                <a href="{{ $item['market_source_url'] }}" target="_blank" rel="noopener noreferrer" class="link link-primary text-xs flex items-center gap-1">
                                                    <span>{{ __('مشاهده در وب') }}</span>
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
                                                <span class="badge badge-success badge-xs">{{ __('منتشر شده') }}</span>
                                            @else
                                                <span class="badge badge-ghost badge-xs">{{ __('داخلی') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-xs btn-outline btn-primary"
                                                @click="openProductModal({{ json_encode($item) }})">
                                                {{ __('ثبت قیمت روز') }}
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center py-6 text-sm opacity-60">
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
                            <h2 class="text-lg font-bold">{{ __('شبکه تبادل نرخ عمده‌فروشی هم‌صنفان (B2B)') }}</h2>
                            <p class="text-xs text-base-content/60">{{ __('قیمت‌های عمده‌فروشی منتشر شده توسط بنکداران و عمده‌فروشان دخل برای آگاهی همکاران و ثبت سفارشات.') }}</p>
                        </div>

                        {{-- Guild Filter --}}
                        <form method="GET" action="{{ route('market-intelligence.index') }}" class="flex items-center gap-2">
                            <input type="hidden" name="tab" value="guild">
                            <select name="guild" class="select select-sm select-bordered" onchange="this.form.submit()">
                                <option value="">{{ __('همه اصناف') }}</option>
                                @foreach ($availableGuilds as $g)
                                    <option value="{{ $g }}" @selected($activeGuild === $g)>{{ $g }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('جستجوی نام کالا...') }}" class="input input-sm input-bordered w-48">
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('فیلتر') }}</button>
                        </form>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @forelse ($guildBroadcasts as $b)
                            <div class="card bg-base-200/50 border border-base-300 p-4 rounded-xl hover:shadow-md transition">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <h3 class="font-bold text-base text-base-content">{{ $b['product_name'] }}</h3>
                                        <div class="text-xs opacity-60 mt-0.5">{{ $b['wholesaler_name'] }}</div>
                                    </div>
                                    <span class="badge badge-accent badge-sm">{{ $b['guild_category'] ?: 'عمومی' }}</span>
                                </div>

                                <div class="mt-4 p-3 bg-base-100 rounded-lg flex items-center justify-between">
                                    <div>
                                        <div class="text-xs opacity-60">{{ __('نرخ عمده‌فروشی') }}</div>
                                        <div class="text-lg font-black text-primary font-mono mt-0.5">
                                            {{ number_format($b['wholesale_price']) }} <span class="text-xs font-normal">ریال</span>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="text-xs opacity-60">{{ __('حداقل تیراژ') }}</div>
                                        <div class="text-sm font-bold font-mono">
                                            {{ number_format($b['wholesale_min_qty']) }} <span class="text-xs font-normal">عدد</span>
                                        </div>
                                    </div>
                                </div>

                                @if ($b['wholesale_note'])
                                    <div class="text-xs opacity-75 mt-2 bg-base-200 p-2 rounded">
                                        {{ $b['wholesale_note'] }}
                                    </div>
                                @endif

                                <div class="flex items-center justify-between text-[11px] opacity-60 mt-3 pt-2 border-t border-base-300">
                                    <span>{{ $b['time_ago'] }}</span>
                                    @if ($b['market_source_url'])
                                        <a href="{{ $b['market_source_url'] }}" target="_blank" rel="noopener noreferrer" class="link link-primary">لینک وب</a>
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
                        <h2 class="text-lg font-bold">{{ __('پیکربندی منبع دریافت قیمت‌ها (Bring Your Own API)') }}</h2>
                        <p class="text-xs text-base-content/60">{{ __('شما می‌توانید از سرویس هوشمند داخلی دخل استفاده کنید یا کلید API اختصاصی خود از سامانه‌های نوسان یا بن‌بست را متصل نمایید.') }}</p>
                    </div>

                    <form method="POST" action="{{ route('market-intelligence.save-settings') }}" class="space-y-6">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach ($providerConfig['providers_list'] as $pKey => $pSpec)
                                <label class="card bg-base-200/60 border border-base-300 p-4 rounded-xl cursor-pointer hover:border-primary transition"
                                    :class="{ 'border-primary ring-2 ring-primary/20 bg-primary/5': selectedProvider === '{{ $pKey }}' }">
                                    <div class="flex items-start gap-3">
                                        <input type="radio" name="provider" value="{{ $pKey }}" class="radio radio-primary radio-sm mt-1"
                                            x-model="selectedProvider" @checked($providerConfig['provider'] === $pKey)>
                                        <div>
                                            <div class="font-bold text-sm">{{ $pSpec['title'] }}</div>
                                            <div class="text-xs text-base-content/70 mt-1 leading-relaxed">{{ $pSpec['description'] }}</div>
                                            @if (! empty($pSpec['signup_url']))
                                                <a href="{{ $pSpec['signup_url'] }}" target="_blank" rel="noopener noreferrer" class="link link-primary text-[11px] mt-2 inline-block">
                                                    دریافت کلید API از وب‌سایت رسمی ←
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>

                        <div x-show="selectedProvider === 'navasan' || selectedProvider === 'bonbast'" class="card bg-base-200 p-4 rounded-xl space-y-3">
                            <h3 class="font-bold text-sm">کلید دسترسی اختصاصی (API Key / Token)</h3>
                            <div class="form-control">
                                <input type="text" name="api_key" x-model="apiKey" placeholder="مثال: nvs_live_9a8b7c6d5e4f3a2b1..." class="input input-bordered w-full font-mono text-sm">
                                <span class="text-[11px] opacity-60 mt-1">کلید اختصاصی حساب کاربری شما در سرویس‌دهنده منتخب.</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <button type="button" class="btn btn-sm btn-outline" @click="testConnection()" :disabled="testing">
                                    <span x-show="!testing">تست اتصال به وب‌سرویس</span>
                                    <span x-show="testing" class="loading loading-spinner loading-xs"></span>
                                </button>
                                <span x-text="testMessage" class="text-xs font-medium" :class="testSuccess ? 'text-success' : 'text-error'"></span>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2">
                            <button type="submit" class="btn btn-primary">{{ __('ذخیره تنظیمات وب‌سرویس') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- MODAL 1: Manual Quote Update --}}
        <div class="modal" :class="{ 'modal-open': isQuoteModalOpen }">
            <div class="modal-box">
                <h3 class="font-bold text-lg mb-2">{{ __('ثبت دستی نرخ') }}: <span x-text="quoteModalData.title"></span></h3>
                <p class="text-xs text-base-content/60 mb-4">{{ __('نرخ وارد شده جایگزین نرخ آنلاین جاری برای این شرکت خواهد شد.') }}</p>

                <form :action="quoteModalData.action" method="POST">
                    @csrf
                    <div class="form-control mb-4">
                        <label class="label">
                            <span class="label-text text-xs">{{ __('نرخ جدید (ریال)') }}</span>
                        </label>
                        <input type="number" step="any" name="price" x-model="quoteModalData.price" required class="input input-bordered w-full font-mono text-lg font-bold">
                    </div>

                    <div class="modal-action">
                        <button type="button" class="btn btn-ghost" @click="isQuoteModalOpen = false">{{ __('انصراف') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('ثبت و ذخیره نرخ') }}</button>
                    </div>
                </form>
            </div>
            <div class="modal-backdrop" @click="isQuoteModalOpen = false"></div>
        </div>

        {{-- MODAL 2: Product Market Price & Broadcast --}}
        <div class="modal" :class="{ 'modal-open': isProductModalOpen }">
            <div class="modal-box max-w-lg">
                <h3 class="font-bold text-lg mb-1">{{ __('ثبت قیمت روز و تنظیمات بازار') }}</h3>
                <p class="text-xs text-base-content/60 mb-4" x-text="productModalData.name"></p>

                <form :action="productModalData.action" method="POST" class="space-y-4">
                    @csrf
                    <div class="form-control">
                        <label class="label"><span class="label-text text-xs font-semibold">{{ __('قیمت روز بازار (ریال)') }}</span></label>
                        <input type="number" step="any" name="market_price" x-model="productModalData.market_price" class="input input-bordered w-full font-mono">
                    </div>

                    <div class="form-control">
                        <label class="label"><span class="label-text text-xs">{{ __('لینک استعلام وب (ترب، ایمالز، سایت مرجع)') }}</span></label>
                        <input type="url" name="market_source_url" x-model="productModalData.market_source_url" placeholder="https://..." class="input input-bordered w-full text-xs">
                    </div>

                    <div class="divider text-xs opacity-60">{{ __('اشتراک در شبکه عمده‌فروشی هم‌صنفان (B2B)') }}</div>

                    <div class="form-control">
                        <label class="label cursor-pointer justify-start gap-3">
                            <input type="checkbox" name="is_wholesale_broadcast" value="1" x-model="productModalData.is_wholesale_broadcast" class="checkbox checkbox-primary checkbox-sm">
                            <span class="label-text text-xs font-medium">{{ __('انتشار قیمت عمده‌فروشی در شبکه همکاران') }}</span>
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
                                    <option value="{{ $g }}">{{ $g }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-control">
                            <label class="label"><span class="label-text text-xs">{{ __('شرایط و توضیحات فروش عمده') }}</span></label>
                            <input type="text" name="wholesale_note" x-model="productModalData.wholesale_note" placeholder="{{ __('مثال: تحویل پای انبار، تسویه نقدی') }}" class="input input-bordered input-sm text-xs">
                        </div>
                    </div>

                    <div class="modal-action">
                        <button type="button" class="btn btn-ghost" @click="isProductModalOpen = false">{{ __('انصراف') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('ذخیره تنظیمات کالا') }}</button>
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
                isQuoteModalOpen: false,
                quoteModalData: { symbol: '', title: '', price: 0, action: '' },
                isProductModalOpen: false,
                productModalData: {},
                selectedProvider: '{{ $providerConfig['provider'] }}',
                apiKey: '{{ $providerConfig['api_key'] }}',
                testing: false,
                testMessage: '',
                testSuccess: true,

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
