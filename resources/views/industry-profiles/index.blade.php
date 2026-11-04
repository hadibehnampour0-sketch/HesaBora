<x-app-layout :title="__('تنظیمات و پروفایل‌های اصناف و مشاغل')">
    <x-show-message-bags />

    @php
        $tradeMeta = [
            'gold' => ['emoji' => '💎', 'category' => 'metals', 'category_label' => 'طلا و فلزات گرانبها', 'color' => 'from-amber-500 to-yellow-600', 'bg_light' => 'bg-amber-500/10 text-amber-700 border-amber-200 dark:text-amber-400 dark:border-amber-900/40'],
            'iron' => ['emoji' => '🏗️', 'category' => 'metals', 'category_label' => 'فلزات و صنایع سنگین', 'color' => 'from-slate-600 to-zinc-700', 'bg_light' => 'bg-slate-500/10 text-slate-700 border-slate-200 dark:text-slate-400 dark:border-slate-800'],
            'auto_parts' => ['emoji' => '🚗', 'category' => 'automotive', 'category_label' => 'خودرو و خدمات فنی', 'color' => 'from-blue-600 to-indigo-600', 'bg_light' => 'bg-blue-500/10 text-blue-700 border-blue-200 dark:text-blue-400 dark:border-blue-900/40'],
            'auto_repair' => ['emoji' => '🔧', 'category' => 'automotive', 'category_label' => 'خودرو و تعمیرگاه', 'color' => 'from-cyan-600 to-blue-600', 'bg_light' => 'bg-cyan-500/10 text-cyan-700 border-cyan-200 dark:text-cyan-400 dark:border-cyan-900/40'],
            'mobile' => ['emoji' => '📱', 'category' => 'digital', 'category_label' => 'دیجیتال و فناوری', 'color' => 'from-indigo-600 to-purple-600', 'bg_light' => 'bg-indigo-500/10 text-indigo-700 border-indigo-200 dark:text-indigo-400 dark:border-indigo-900/40'],
            'home_appliances' => ['emoji' => '📺', 'category' => 'digital', 'category_label' => 'لوازم خانگی و صوتی‌تصویری', 'color' => 'from-violet-600 to-fuchsia-600', 'bg_light' => 'bg-violet-500/10 text-violet-700 border-violet-200 dark:text-violet-400 dark:border-violet-900/40'],
            'apparel' => ['emoji' => '👗', 'category' => 'retail', 'category_label' => 'پوشاک، کیف و کفش', 'color' => 'from-pink-500 to-rose-600', 'bg_light' => 'bg-pink-500/10 text-pink-700 border-pink-200 dark:text-pink-400 dark:border-pink-900/40'],
            'food' => ['emoji' => '🛒', 'category' => 'retail', 'category_label' => 'مواد غذایی و هایپرمارکت', 'color' => 'from-emerald-500 to-green-600', 'bg_light' => 'bg-emerald-500/10 text-emerald-700 border-emerald-200 dark:text-emerald-400 dark:border-emerald-900/40'],
            'confectionery_nuts' => ['emoji' => '🧁', 'category' => 'food', 'category_label' => 'قنادی، شیرینی و خشکبار', 'color' => 'from-orange-500 to-amber-600', 'bg_light' => 'bg-orange-500/10 text-orange-700 border-orange-200 dark:text-orange-400 dark:border-orange-900/40'],
            'restaurant_cafe' => ['emoji' => '☕', 'category' => 'food', 'category_label' => 'رستوران، کافی‌شاپ و غذا', 'color' => 'from-rose-600 to-red-600', 'bg_light' => 'bg-rose-500/10 text-rose-700 border-rose-200 dark:text-rose-400 dark:border-rose-900/40'],
            'pharmacy_medical' => ['emoji' => '💊', 'category' => 'health', 'category_label' => 'داروخانه و تجهیزات پزشکی', 'color' => 'from-teal-600 to-emerald-600', 'bg_light' => 'bg-teal-500/10 text-teal-700 border-teal-200 dark:text-teal-400 dark:border-teal-900/40'],
            'carpet_textile' => ['emoji' => '🧶', 'category' => 'retail', 'category_label' => 'فرش، موکت و نساجی', 'color' => 'from-yellow-600 to-amber-700', 'bg_light' => 'bg-yellow-500/10 text-yellow-700 border-yellow-200 dark:text-yellow-400 dark:border-yellow-900/40'],
            'building_materials' => ['emoji' => '🧱', 'category' => 'industry', 'category_label' => 'مصالح ساختمانی و پیمانکاری', 'color' => 'from-stone-600 to-amber-800', 'bg_light' => 'bg-stone-500/10 text-stone-700 border-stone-200 dark:text-stone-400 dark:border-stone-800'],
            'stationery_books' => ['emoji' => '📚', 'category' => 'retail', 'category_label' => 'کتاب و لوازم‌التحریر', 'color' => 'from-purple-600 to-indigo-600', 'bg_light' => 'bg-purple-500/10 text-purple-700 border-purple-200 dark:text-purple-400 dark:border-purple-900/40'],
            'business_insights' => ['emoji' => '📊', 'category' => 'general', 'category_label' => 'شاخص‌ها و هوش تجاری', 'color' => 'from-sky-500 to-blue-600', 'bg_light' => 'bg-sky-500/10 text-sky-700 border-sky-200 dark:text-sky-400 dark:border-sky-900/40'],
            'general' => ['emoji' => '🏬', 'category' => 'general', 'category_label' => 'بازرگانی و فروشگاه عمومی', 'color' => 'from-gray-600 to-slate-700', 'bg_light' => 'bg-gray-500/10 text-gray-700 border-gray-200 dark:text-gray-400 dark:border-gray-800'],
        ];

        $totalProfiles = count($cards);
        $activeProfiles = count(array_filter($cards, fn($c) => $c['active']));
        $totalCalculators = array_sum(array_column($cards, 'calculators'));
        $totalFields = array_sum(array_column($cards, 'fields'));
    @endphp

    <div class="space-y-6" x-data="{ search: '', activeTab: 'all' }">
        {{-- Hero Header & KPI Cards --}}
        <div class="rounded-3xl p-6 md:p-8 bg-gradient-to-br from-base-100 via-base-100 to-primary/5 border border-base-200 shadow-xl relative overflow-hidden">
            <div class="absolute -left-12 -top-12 w-48 h-48 bg-primary/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -right-12 -bottom-12 w-48 h-48 bg-accent/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="relative z-10 flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6">
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary/10 text-primary text-xs font-semibold mb-3">
                        <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                        <span>{{ __('معماری صنف‌محور هوشمند (Industry-Tailored Engine)') }}</span>
                    </div>
                    <h1 class="text-2xl md:text-3xl font-extrabold text-base-content tracking-tight">
                        {{ __('پروفایل‌ها و میز کار تخصصی اصناف و مشاغل') }}
                    </h1>
                    <p class="text-sm md:text-base text-base-content/70 mt-2 max-w-2xl leading-relaxed">
                        {{ __('هر صنف در بازار ایران دارای اصطلاحات، واحدهای اندازه‌گیری، قوانین ارزش‌افزوده، سقف سود مصوب اتحادیه و فرمول‌های محاسباتی خاص خود است. با فعال‌سازی صنف خود، نرم‌افزار دخل بدون نیاز به تغییر کد یا کدنویسی دقیقاً به زبان صنف شما صحبت می‌کند.') }}
                    </p>
                </div>

                {{-- Quick Stats --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 w-full lg:w-auto shrink-0">
                    <div class="bg-base-200/60 backdrop-blur rounded-2xl p-3 border border-base-300 text-center">
                        <div class="text-xl md:text-2xl font-black text-primary">{{ $totalProfiles }}</div>
                        <div class="text-xs text-base-content/70 font-medium mt-0.5">{{ __('صنف آماده') }}</div>
                    </div>
                    <div class="bg-base-200/60 backdrop-blur rounded-2xl p-3 border border-base-300 text-center">
                        <div class="text-xl md:text-2xl font-black text-emerald-500">{{ $activeProfiles }}</div>
                        <div class="text-xs text-base-content/70 font-medium mt-0.5">{{ __('فعال در این شرکت') }}</div>
                    </div>
                    <div class="bg-base-200/60 backdrop-blur rounded-2xl p-3 border border-base-300 text-center">
                        <div class="text-xl md:text-2xl font-black text-amber-500">{{ $totalCalculators }}</div>
                        <div class="text-xs text-base-content/70 font-medium mt-0.5">{{ __('ماشین‌حساب هوشمند') }}</div>
                    </div>
                    <div class="bg-base-200/60 backdrop-blur rounded-2xl p-3 border border-base-300 text-center">
                        <div class="text-xl md:text-2xl font-black text-info">{{ $totalFields }}+</div>
                        <div class="text-xs text-base-content/70 font-medium mt-0.5">{{ __('ضابطه و تنظیم عرف') }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Filter & Search Bar --}}
        <div class="card bg-base-100 shadow-lg border border-base-200">
            <div class="card-body p-4 sm:p-5">
                <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
                    {{-- Category Tabs --}}
                    <div class="flex items-center gap-1.5 overflow-x-auto pb-2 md:pb-0 scrollbar-none text-xs font-semibold">
                        <button type="button" 
                                @click="activeTab = 'all'" 
                                :class="activeTab === 'all' ? 'btn-primary text-primary-content shadow-sm' : 'btn-ghost text-base-content/70 hover:bg-base-200'" 
                                class="btn btn-sm rounded-xl">
                            {{ __('همه اصناف') }} ({{ $totalProfiles }})
                        </button>
                        <button type="button" 
                                @click="activeTab = 'active'" 
                                :class="activeTab === 'active' ? 'btn-primary text-primary-content shadow-sm' : 'btn-ghost text-base-content/70 hover:bg-base-200'" 
                                class="btn btn-sm rounded-xl">
                            {{ __('اصناف فعال') }} ({{ $activeProfiles }})
                        </button>
                        <button type="button" 
                                @click="activeTab = 'metals'" 
                                :class="activeTab === 'metals' ? 'btn-primary text-primary-content shadow-sm' : 'btn-ghost text-base-content/70 hover:bg-base-200'" 
                                class="btn btn-sm rounded-xl">
                            {{ __('طلا و فلزات') }}
                        </button>
                        <button type="button" 
                                @click="activeTab = 'automotive'" 
                                :class="activeTab === 'automotive' ? 'btn-primary text-primary-content shadow-sm' : 'btn-ghost text-base-content/70 hover:bg-base-200'" 
                                class="btn btn-sm rounded-xl">
                            {{ __('خودرو و قطعات') }}
                        </button>
                        <button type="button" 
                                @click="activeTab = 'digital'" 
                                :class="activeTab === 'digital' ? 'btn-primary text-primary-content shadow-sm' : 'btn-ghost text-base-content/70 hover:bg-base-200'" 
                                class="btn btn-sm rounded-xl">
                            {{ __('موبایل و دیجیتال') }}
                        </button>
                        <button type="button" 
                                @click="activeTab = 'retail'" 
                                :class="activeTab === 'retail' ? 'btn-primary text-primary-content shadow-sm' : 'btn-ghost text-base-content/70 hover:bg-base-200'" 
                                class="btn btn-sm rounded-xl">
                            {{ __('فروشگاهی و پوشاک') }}
                        </button>
                        <button type="button" 
                                @click="activeTab = 'food'" 
                                :class="activeTab === 'food' ? 'btn-primary text-primary-content shadow-sm' : 'btn-ghost text-base-content/70 hover:bg-base-200'" 
                                class="btn btn-sm rounded-xl">
                            {{ __('رستوران و قنادی') }}
                        </button>
                        <button type="button" 
                                @click="activeTab = 'health'" 
                                :class="activeTab === 'health' ? 'btn-primary text-primary-content shadow-sm' : 'btn-ghost text-base-content/70 hover:bg-base-200'" 
                                class="btn btn-sm rounded-xl">
                            {{ __('دارو و سلامت') }}
                        </button>
                    </div>

                    {{-- Search Input --}}
                    <div class="relative w-full md:w-72 shrink-0">
                        <input type="text" 
                               x-model="search" 
                               placeholder="{{ __('جستجوی صنف یا رسته شغلی...') }}" 
                               class="input input-sm input-bordered w-full pr-9 pl-3 rounded-xl bg-base-200/50 focus:bg-base-100 text-sm" />
                        <svg class="w-4 h-4 text-base-content/40 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        {{-- Cards Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
            @foreach ($cards as $card)
                @php
                    $meta = $tradeMeta[$card['key']] ?? [
                        'emoji' => '💼', 
                        'category' => 'general', 
                        'category_label' => 'عمومی', 
                        'color' => 'from-primary to-accent',
                        'bg_light' => 'bg-primary/10 text-primary border-primary/20'
                    ];
                @endphp
                <div class="card bg-base-100 shadow-md hover:shadow-xl transition-all duration-300 border border-base-200 hover:border-primary/40 rounded-3xl flex flex-col justify-between group overflow-hidden"
                     x-show="(activeTab === 'all' || 
                              (activeTab === 'active' && {{ $card['active'] ? 'true' : 'false' }}) || 
                              activeTab === '{{ $meta['category'] }}') && 
                             ('{{ addslashes($card['title']) }} {{ addslashes($card['description']) }}'.toLowerCase().includes(search.toLowerCase()))">
                    
                    {{-- Top Accent Bar --}}
                    <div class="h-1.5 w-full bg-gradient-to-r {{ $meta['color'] }} opacity-80 group-hover:opacity-100 transition-opacity"></div>

                    <div class="card-body p-5 md:p-6 flex flex-col justify-between flex-1 gap-4">
                        {{-- Header Row: Icon + Title + Status Badge --}}
                        <div>
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl bg-base-200/80 border border-base-300 shadow-sm group-hover:scale-110 transition-transform">
                                        {{ $meta['emoji'] }}
                                    </div>
                                    <div>
                                        <a href="{{ route('industry-profiles.show', $card['key']) }}" class="font-bold text-lg text-base-content hover:text-primary transition-colors block">
                                            {{ $card['title'] }}
                                        </a>
                                        <span class="inline-block text-[11px] font-medium px-2 py-0.5 rounded-md border mt-0.5 {{ $meta['bg_light'] }}">
                                            {{ $meta['category_label'] }}
                                        </span>
                                    </div>
                                </div>

                                {{-- Active Indicator Badge --}}
                                @if ($card['active'])
                                    <span class="inline-flex items-center gap-1.5 badge badge-success badge-sm font-semibold py-2.5 px-3 shadow-xs">
                                        <span class="w-1.5 h-1.5 rounded-full bg-white animate-ping"></span>
                                        {{ __('فعال در سیستم') }}
                                    </span>
                                @else
                                    <span class="badge badge-ghost badge-sm text-base-content/50 py-2.5 px-3">
                                        {{ __('غیرفعال') }}
                                    </span>
                                @endif
                            </div>

                            {{-- Description --}}
                            <p class="text-xs md:text-sm text-base-content/70 mt-3 line-clamp-3 leading-relaxed">
                                {{ $card['description'] }}
                            </p>
                        </div>

                        {{-- Metric Badges --}}
                        <div class="flex flex-wrap items-center gap-1.5 pt-2 border-t border-base-200/80 text-xs">
                            <span class="badge badge-outline border-base-300 text-base-content/80 font-medium py-2">
                                ⚙️ {{ __(':count تنظیم و شاخص', ['count' => $card['fields']]) }}
                            </span>
                            <span class="badge badge-outline border-base-300 text-base-content/80 font-medium py-2">
                                🧮 {{ __(':count ماشین‌حساب تخصصی', ['count' => $card['calculators']]) }}
                            </span>
                            @if ($card['pending']['needs_confirmation'] > 0)
                                <span class="badge badge-warning badge-sm py-2" title="{{ __('نیاز به بررسی و تایید برای عرف شرکت شما') }}">
                                    ⚠️ {{ __(':count تایید نشده', ['count' => $card['pending']['needs_confirmation']]) }}
                                </span>
                            @endif
                        </div>

                        {{-- Action Buttons --}}
                        <div class="flex items-center justify-between gap-2 pt-3 border-t border-base-200/80">
                            <a href="{{ route('industry-profiles.show', $card['key']) }}" 
                               class="btn btn-primary btn-sm rounded-xl font-bold px-4 flex items-center gap-1.5 shadow-sm hover:shadow">
                                <span>{{ __('ورود به میز کار صنف') }}</span>
                                <svg class="w-3.5 h-3.5 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                                </svg>
                            </a>

                            @can('industry-profiles.toggle')
                                <form method="POST" action="{{ route('industry-profiles.toggle', $card['key']) }}">
                                    @csrf
                                    <button type="submit" 
                                            class="btn btn-sm btn-ghost rounded-xl text-xs font-semibold {{ $card['active'] ? 'text-error hover:bg-error/10' : 'text-success hover:bg-success/10' }}">
                                        {{ $card['active'] ? __('غیرفعال‌سازی') : __('فعال‌سازی سریع') }}
                                    </button>
                                </form>
                            @endcan
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
