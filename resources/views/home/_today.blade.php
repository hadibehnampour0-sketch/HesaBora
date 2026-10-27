{{-- Dakhl Modern Executive Cockpit --}}
<section x-data="todayDashboard('{{ route('home.today') }}')" x-init="load()" class="space-y-5" aria-labelledby="today-title">
    {{-- Header Bar --}}
    <div class="flex flex-wrap items-center justify-between gap-3 bg-base-100 p-4 rounded-2xl border border-base-300 shadow-xs">
        <div>
            <h2 id="today-title" class="text-xl font-black text-base-content flex items-center gap-2">
                <span class="inline-block size-3 rounded-full bg-emerald-500 animate-pulse"></span>
                {{ __('میز کار و نبض امروز کسب‌وکار') }}
            </h2>
            <p class="text-xs text-base-content/65 mt-0.5">{{ __('خلاصه فوری مهم‌ترین اطلاعات مالی، هشدارهای کاری و دسترسی‌های سریع') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" class="btn btn-sm btn-ghost border border-base-300 gap-1.5" @click="revealed = ! revealed" :aria-pressed="revealed.toString()">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-base-content/70" width="16" height="16" style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path x-show="revealed" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path x-show="revealed" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    <path x-show="! revealed" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                </svg>
                <span x-show="revealed">{{ __('مخفی‌سازی ارقام') }}</span>
                <span x-show="! revealed" style="display: none">{{ __('نمایش ارقام') }}</span>
            </button>
            <button type="button" class="btn btn-sm btn-ghost border border-base-300 btn-square" @click="load()" :disabled="loading" title="{{ __('بروزرسانی اطلاعات') }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-base-content/70" width="16" height="16" style="width: 16px; height: 16px;" :class="loading ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
            </button>
        </div>
    </div>

    {{-- Loading Skeleton --}}
    <template x-if="loading">
        <div class="space-y-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <template x-for="i in 4" :key="i"><div class="skeleton h-28 w-full rounded-2xl"></div></template>
            </div>
            <div class="skeleton h-20 w-full rounded-2xl"></div>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="skeleton h-64 lg:col-span-2 rounded-2xl"></div>
                <div class="skeleton h-64 rounded-2xl"></div>
            </div>
        </div>
    </template>

    {{-- Error Alert --}}
    <template x-if="error">
        <div class="alert alert-warning text-sm shadow-sm rounded-2xl">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 shrink-0" width="20" height="20" style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <span>{{ __('خطا در دریافت اطلاعات داشبورد امروز.') }}</span>
            <button type="button" class="btn btn-xs btn-outline" @click="load()">{{ __('تلاش مجدد') }}</button>
        </div>
    </template>

    <div x-show="data && !loading" class="space-y-5">
        {{-- SECTION 1: Critical Alerts Hub --}}
        <div>
            <template x-if="(block('alerts') || []).length > 0">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    <template x-for="(alert, idx) in block('alerts')" :key="idx">
                        <a :href="alert.url" class="flex items-center justify-between p-3.5 rounded-2xl border transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md"
                            :class="{
                                'bg-rose-50/70 border-rose-300 text-rose-900 dark:bg-rose-950/30 dark:border-rose-800 dark:text-rose-200': alert.severity === 'error',
                                'bg-amber-50/70 border-amber-300 text-amber-900 dark:bg-amber-950/30 dark:border-amber-800 dark:text-amber-200': alert.severity === 'warning',
                                'bg-blue-50/70 border-blue-300 text-blue-900 dark:bg-blue-950/30 dark:border-blue-800 dark:text-blue-200': alert.severity === 'info'
                            }">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="size-2.5 rounded-full shrink-0"
                                    :class="{
                                        'bg-rose-500 animate-ping': alert.severity === 'error',
                                        'bg-amber-500': alert.severity === 'warning',
                                        'bg-blue-500': alert.severity === 'info'
                                    }"></span>
                                <div class="min-w-0">
                                    <div class="text-xs font-bold truncate" x-text="alert.title"></div>
                                    <div class="text-[11px] opacity-80 mt-0.5" x-show="alert.amount">
                                        {{ __('مبلغ') }}: <span class="font-mono font-bold" x-text="money(alert.amount)"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="badge badge-sm font-bold font-mono shrink-0 mr-2"
                                :class="{
                                    'badge-error text-white': alert.severity === 'error',
                                    'badge-warning': alert.severity === 'warning',
                                    'badge-info': alert.severity === 'info'
                                }" x-text="num(alert.count)"></div>
                        </a>
                    </template>
                </div>
            </template>

            <template x-if="! (block('alerts') || []).length">
                <div class="flex items-center gap-2 p-3 bg-emerald-50/60 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800/40 rounded-2xl text-xs text-emerald-800 dark:text-emerald-300 shadow-2xs">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width: 20px; height: 20px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="font-medium">{{ __('وضعیت سامانه پایدار است؛ هیچ چک برگشتی، کسری بحرانی انبار یا فاکتور معوقی ثبت نشده است.') }}</span>
                </div>
            </template>
        </div>

        {{-- SECTION 2: 4 Core Financial Pulse Cards --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {{-- Card 1: Today Sales --}}
            <a href="{{ route('invoices.index', ['invoice_type' => 'sell']) }}" x-show="block('sales')"
                class="group relative overflow-hidden rounded-3xl border border-base-300 bg-base-100 p-5 shadow-xs transition duration-300 hover:-translate-y-1 hover:border-emerald-500/30 hover:shadow-lg">
                <div aria-hidden="true" class="absolute inset-x-0 top-0 h-1 bg-emerald-500"></div>
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-bold text-base-content/70">{{ __('فروش خالص امروز') }}</span>
                    <span class="w-9 h-9 rounded-2xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width: 20px; height: 20px;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                    </span>
                </div>
                <div class="text-2xl font-black tracking-tight text-base-content" x-text="money(block('sales')?.today_sell?.amount)"></div>
                <div class="mt-2 flex items-center justify-between text-xs text-base-content/60 border-t border-base-200 pt-2">
                    <span><b class="text-emerald-600 font-mono" x-text="num(block('sales')?.today_sell?.count)"></b> {{ __('فاکتور فروش') }}</span>
                    <span>خرید: <span class="font-mono" x-text="money(block('sales')?.today_buy?.amount)"></span></span>
                </div>
            </a>

            {{-- Card 2: Total Liquidity (Cash + Banks) --}}
            <a href="{{ route('bank-accounts.index') }}" x-show="block('liquidity')"
                class="group relative overflow-hidden rounded-3xl border border-base-300 bg-base-100 p-5 shadow-xs transition duration-300 hover:-translate-y-1 hover:border-sky-500/30 hover:shadow-lg">
                <div aria-hidden="true" class="absolute inset-x-0 top-0 h-1 bg-sky-500"></div>
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-bold text-base-content/70">{{ __('موجودی نقدینگی در دسترس') }}</span>
                    <span class="w-9 h-9 rounded-2xl bg-sky-500/10 text-sky-600 flex items-center justify-center shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width: 20px; height: 20px;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                    </span>
                </div>
                <div class="text-2xl font-black tracking-tight text-base-content text-sky-600 dark:text-sky-400" x-text="money(block('liquidity')?.total)"></div>
                <div class="mt-2 flex items-center justify-between text-xs text-base-content/60 border-t border-base-200 pt-2">
                    <span>بانک: <span class="font-mono font-semibold" x-text="money(block('liquidity')?.bank)"></span></span>
                    <span>صندوق: <span class="font-mono font-semibold" x-text="money(block('liquidity')?.cash)"></span></span>
                </div>
            </a>

            {{-- Card 3: Overdue Receivables --}}
            <a href="{{ route('ageing.receivables') }}" x-show="block('receivables')"
                class="group relative overflow-hidden rounded-3xl border border-base-300 bg-base-100 p-5 shadow-xs transition duration-300 hover:-translate-y-1 hover:border-rose-500/30 hover:shadow-lg">
                <div aria-hidden="true" class="absolute inset-x-0 top-0 h-1 bg-rose-500"></div>
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-bold text-base-content/70">{{ __('مطالبات سررسید گذشته') }}</span>
                    <span class="w-9 h-9 rounded-2xl bg-rose-500/10 text-rose-600 flex items-center justify-center shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width: 20px; height: 20px;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                </div>
                <div class="text-2xl font-black tracking-tight text-rose-600" x-text="money(block('receivables')?.overdue)"></div>
                <div class="mt-2 flex items-center justify-between text-xs text-base-content/60 border-t border-base-200 pt-2">
                    <span>کل مانده بدهکاران:</span>
                    <span class="font-mono font-bold" x-text="money(block('receivables')?.total)"></span>
                </div>
            </a>

            {{-- Card 4: Upcoming Cheques Due in 7 Days --}}
            <a href="{{ route('cheques.index') }}" x-show="block('cheques')"
                class="group relative overflow-hidden rounded-3xl border border-base-300 bg-base-100 p-5 shadow-xs transition duration-300 hover:-translate-y-1 hover:border-amber-500/30 hover:shadow-lg">
                <div aria-hidden="true" class="absolute inset-x-0 top-0 h-1 bg-amber-500"></div>
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-bold text-base-content/70">{{ __('چک‌های دریافتی نزدیک (۷ روز)') }}</span>
                    <span class="w-9 h-9 rounded-2xl bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width: 20px; height: 20px;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </span>
                </div>
                <div class="text-2xl font-black tracking-tight text-amber-600" x-text="money(block('cheques')?.receivable?.due_soon?.amount)"></div>
                <div class="mt-2 flex items-center justify-between text-xs text-base-content/60 border-t border-base-200 pt-2">
                    <span><b class="text-amber-600 font-mono" x-text="num(block('cheques')?.receivable?.due_soon?.count)"></b> {{ __('فقره در نوبت وصول') }}</span>
                    <span class="text-rose-500" x-show="block('cheques')?.receivable?.bounced?.count > 0">{{ __('برگشتی') }}: <b x-text="num(block('cheques')?.receivable?.bounced?.count)"></b></span>
                </div>
            </a>
        </div>

        {{-- SECTION 3: 4 Essential Quick Actions --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @can('invoices.create')
                <a href="{{ route('pos.terminal') }}"
                    class="flex items-center gap-3 p-3 rounded-2xl border border-base-300 bg-base-100 hover:border-primary/40 hover:bg-primary/5 transition duration-200 group shadow-xs">
                    <span class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                        <svg class="w-4 h-4" width="16" height="16" style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <div class="text-xs font-bold text-base-content truncate">{{ __('صندوق سریع فروش (POS)') }}</div>
                        <div class="text-[11px] text-base-content/60 truncate">{{ __('ثبت آنی فاکتور و فیش') }}</div>
                    </div>
                </a>
            @endcan

            @can('quick-payment.create')
                <a href="{{ route('quick-payment.create', 'receive') }}"
                    class="flex items-center gap-3 p-3 rounded-2xl border border-base-300 bg-base-100 hover:border-emerald-500/40 hover:bg-emerald-500/5 transition duration-200 group shadow-xs">
                    <span class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                        <svg class="w-4 h-4" width="16" height="16" style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <div class="text-xs font-bold text-base-content truncate">{{ __('دریافت وجه و تسویه') }}</div>
                        <div class="text-[11px] text-base-content/60 truncate">{{ __('ثبت واریز نقدی و حواله') }}</div>
                    </div>
                </a>
            @endcan

            @can('cheques.create')
                <a href="{{ route('cheques.create') }}"
                    class="flex items-center gap-3 p-3 rounded-2xl border border-base-300 bg-base-100 hover:border-indigo-500/40 hover:bg-indigo-500/5 transition duration-200 group shadow-xs">
                    <span class="w-8 h-8 rounded-xl bg-indigo-500/10 text-indigo-600 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                        <svg class="w-4 h-4" width="16" height="16" style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <div class="text-xs font-bold text-base-content truncate">{{ __('ثبت چک صیادی') }}</div>
                        <div class="text-[11px] text-base-content/60 truncate">{{ __('ثبت چک دریافتی یا پرداختی') }}</div>
                    </div>
                </a>
            @endcan

            @can('copilot.chat')
                <a href="{{ route('copilot.index') }}"
                    class="flex items-center gap-3 p-3 rounded-2xl border border-base-300 bg-base-100 hover:border-amber-500/40 hover:bg-amber-500/5 transition duration-200 group shadow-xs">
                    <span class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                        <svg class="w-4 h-4" width="16" height="16" style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <div class="text-xs font-bold text-base-content truncate">{{ __('دستیار مالی هوشمند (AI)') }}</div>
                        <div class="text-[11px] text-base-content/60 truncate">{{ __('پرسش و تحلیل صوتی/متنی') }}</div>
                    </div>
                </a>
            @endcan
        </div>

        {{-- SECTION 4: Charts & Overdue Debtors --}}
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
            {{-- 7-Day Sales vs Purchases Trend --}}
            <div x-show="block('sales')" class="lg:col-span-2 rounded-3xl border border-base-300 bg-base-100 p-5 shadow-xs">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="font-bold text-sm text-base-content">{{ __('روند فروش و خرید ۷ روز گذشته') }}</h3>
                        <p class="text-xs text-base-content/60">{{ __('مقایسه حجم معاملات روزانه فروش در برابر خرید') }}</p>
                    </div>
                    <div class="flex items-center gap-3 text-xs">
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-primary"></span> {{ __('فروش') }}</span>
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-accent"></span> {{ __('خرید') }}</span>
                    </div>
                </div>
                <div class="relative h-64">
                    <canvas x-ref="salesChart"></canvas>
                </div>
            </div>

            {{-- Top Overdue Debtors --}}
            <div x-show="block('receivables')" class="rounded-3xl border border-base-300 bg-base-100 p-5 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-3 border-b border-base-200 pb-3">
                        <div>
                            <h3 class="font-bold text-sm text-base-content">{{ __('بیشترین مطالبات معوق') }}</h3>
                            <p class="text-xs text-base-content/60">{{ __('۵ مشتری با طولانی‌ترین تاخیر پرداخت') }}</p>
                        </div>
                        <a href="{{ route('ageing.receivables') }}" class="link link-primary text-xs">{{ __('مشاهده همه') }}</a>
                    </div>
                    <template x-if="! (block('receivables')?.top_overdue || []).length">
                        <div class="py-12 text-center text-sm text-emerald-600">
                            {{ __('هیچ بدهی معوقی از طرف مشتریان وجود ندارد. 🎉') }}
                        </div>
                    </template>
                    <ul class="space-y-2.5 text-xs">
                        <template x-for="row in (block('receivables')?.top_overdue || [])" :key="row.customer_id">
                            <li class="flex items-center justify-between p-2 rounded-xl bg-base-200/60 hover:bg-base-200 transition">
                                <a :href="'{{ url('ageing/statement') }}/' + row.customer_id + '/receivables'" class="link link-hover font-semibold truncate max-w-[140px]" x-text="row.name"></a>
                                <div class="text-left font-mono">
                                    <span class="font-bold text-rose-600 block" x-text="money(row.remaining)"></span>
                                    <span class="text-[10px] text-base-content/60" x-text="num(row.oldest_days) + ' {{ __('روز تاخیر') }}'"></span>
                                </div>
                            </li>
                        </template>
                    </ul>
                </div>
                <div class="mt-4 pt-3 border-t border-base-200 text-center">
                    <a href="{{ route('ageing.receivables') }}" class="btn btn-xs btn-ghost text-primary w-full gap-1">
                        {{ __('گزارش کامل تحلیل سنی بدهکاران') }} ←
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

@push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('todayDashboard', (url) => ({
                data: null,
                loading: true,
                error: false,
                revealed: true,
                charts: {},
                lang: document.documentElement.lang || 'fa',
                unit: @js(config('amir.currency') ?? __('Rial')),

                block(name) { return this.data?.blocks?.[name] ?? null; },
                num(v) { return new Intl.NumberFormat(this.lang).format(v ?? 0); },
                money(v) {
                    if (! this.revealed) { return '••••••'; }
                    return new Intl.NumberFormat(this.lang, { maximumFractionDigits: 0 }).format(v ?? 0) + ' ' + this.unit;
                },
                color(name, fallback) {
                    const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
                    return v || fallback;
                },

                async load() {
                    this.loading = true; this.error = false;
                    try {
                        const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' });
                        if (! res.ok) { throw new Error('HTTP ' + res.status); }
                        this.data = await res.json();
                        this.loading = false;
                        this.$nextTick(() => this.draw());
                    } catch (e) {
                        this.error = true; this.loading = false;
                    }
                },

                draw() {
                    Object.values(this.charts).forEach((c) => c.destroy());
                    this.charts = {};
                    const primary = this.color('--color-primary', '#0b5a8e');
                    const accent = this.color('--color-accent', '#e59a2f');

                    const sales = this.block('sales');
                    if (sales && this.$refs.salesChart) {
                        const fa = this.lang.startsWith('fa');
                        this.charts.sales = new Chart(this.$refs.salesChart, {
                            type: 'bar',
                            data: {
                                labels: sales.series.map((d) => new Date(d.date).toLocaleDateString(fa ? 'fa-IR' : undefined, { month: 'short', day: 'numeric' })),
                                datasets: [
                                    { label: @js(__('فروش')), data: sales.series.map((d) => d.sell), backgroundColor: primary, borderRadius: 8 },
                                    { label: @js(__('خرید')), data: sales.series.map((d) => d.buy), backgroundColor: accent, borderRadius: 8 },
                                ],
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { display: false },
                                    tooltip: {
                                        callbacks: {
                                            label: (ctx) => `${ctx.dataset.label}: ${new Intl.NumberFormat(this.lang).format(ctx.raw)} ${this.unit}`
                                        }
                                    }
                                },
                                scales: {
                                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
                                    x: { grid: { display: false } }
                                }
                            },
                        });
                    }
                },

                init() {
                    window.addEventListener('theme:changed', () => this.$nextTick(() => this.draw()));
                },
            }));
        });
    </script>
@endpush


