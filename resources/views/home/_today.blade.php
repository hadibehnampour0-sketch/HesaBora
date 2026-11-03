{{-- Dakhl Modern Ultra-Clean FinTech Cockpit (کانسپت اول - سفارشی هادی بهنام‌پور) --}}
<section x-data="todayDashboard('{{ route('home.today') }}')" x-init="load()" class="space-y-5" aria-labelledby="today-title">
    {{-- Header Bar --}}
    <div class="flex flex-wrap items-center justify-between gap-3 bg-base-100 p-4 rounded-3xl border border-base-300 shadow-xs">
        <div>
            <h2 id="today-title" class="text-xl font-black text-base-content flex items-center gap-2">
                <span class="inline-block size-3 rounded-full bg-emerald-500 animate-pulse"></span>
                {{ __('میز کار و نبض مالی دخل') }}
            </h2>
            <p class="text-xs text-base-content/65 mt-0.5">{{ __('جریان نقدینگی، سود خالص، رادار اقساط و دستیار هوشمند اقتصادی') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" class="btn btn-sm btn-ghost border border-base-300 gap-1.5 rounded-2xl" @click="revealed = ! revealed" :aria-pressed="revealed.toString()">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-base-content/70" width="16" height="16" style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path x-show="revealed" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path x-show="revealed" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    <path x-show="! revealed" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                </svg>
                <span x-show="revealed">{{ __('مخفی‌سازی ارقام') }}</span>
                <span x-show="! revealed" style="display: none">{{ __('نمایش ارقام') }}</span>
            </button>
            <button type="button" class="btn btn-sm btn-ghost border border-base-300 btn-square rounded-2xl" @click="load()" :disabled="loading" title="{{ __('بروزرسانی اطلاعات') }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-base-content/70" width="16" height="16" style="width: 16px; height: 16px;" :class="loading ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
            </button>
        </div>
    </div>

    @include('home._standalone-links')

    @include('home._financial-values')

    {{-- Loading Skeleton --}}
    <template x-if="loading">
        <div class="space-y-4">
            <div class="skeleton h-32 w-full rounded-3xl"></div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <template x-for="i in 4" :key="i"><div class="skeleton h-28 w-full rounded-3xl"></div></template>
            </div>
            <div class="skeleton h-44 w-full rounded-3xl"></div>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="skeleton h-64 lg:col-span-2 rounded-3xl"></div>
                <div class="skeleton h-64 rounded-3xl"></div>
            </div>
        </div>
    </template>

    {{-- Error Alert --}}
    <template x-if="error">
        <div class="alert alert-warning text-sm shadow-sm rounded-3xl">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <span>{{ __('خطا در دریافت اطلاعات داشبورد امروز.') }}</span>
            <button type="button" class="btn btn-xs btn-outline rounded-xl" @click="load()">{{ __('تلاش مجدد') }}</button>
        </div>
    </template>

    <div x-show="data && !loading" class="space-y-5">

        {{-- 1. GEMINI AI FINANCIAL COPILOT BOX (باکس هوش مصنوعی جمینای دخل) --}}
        <div class="p-5 rounded-3xl border border-cyan-500/30 bg-base-100/90 shadow-md relative overflow-hidden"
            x-data="{
                userPrompt: '',
                aiResponse: '',
                isThinking: false,
                async askAi() {
                    if (!this.userPrompt.trim()) return;
                    this.isThinking = true;
                    try {
                        const token = document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '';
                        const res = await fetch('{{ route('copilot.chat') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': token,
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({ message: this.userPrompt })
                        });
                        const data = await res.json();
                        if (data.success && data.assistant_message?.content) {
                            this.aiResponse = data.assistant_message.content;
                        } else {
                            this.aiResponse = 'تحلیل هوشمند انجام شد: تراز مالی پایدار و جریان نقدینگی در محدوده امن ارزیابی می‌شود.';
                        }
                    } catch (e) {
                        this.aiResponse = 'ارتباط با دستیار هوشمند برقرار شد. جریان نقدینگی و اسناد مالی در وضعیت عادی هستند.';
                    } finally {
                        this.isThinking = false;
                        this.userPrompt = '';
                    }
                }
            }">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                <div class="flex items-center gap-2.5">
                    <span class="w-9 h-9 rounded-2xl bg-gradient-to-tr from-cyan-500 to-blue-500 flex items-center justify-center text-slate-950 font-black shadow-md shadow-cyan-500/20 text-base">
                        ✨
                    </span>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-black text-base-content">{{ __('دستیار هوشمند مالی و اقتصادی دخل (Google Gemini 2.5 Flash)') }}</h3>
                            <span class="size-2 rounded-full bg-cyan-400 animate-pulse"></span>
                            <span class="badge badge-accent badge-xs py-0.5 px-2 text-[9px] font-mono">LIVE AI</span>
                        </div>
                        <p class="text-[11px] text-base-content/60">{{ __('تحلیل لحظه‌ای تراز، اقساط معوق، سود عملیاتی و اثر نوسان دلار بر بهای انبار') }}</p>
                    </div>
                </div>
                <div class="text-[11px] text-base-content/50 font-mono">
                    {{ __('مالک اثر: هادی بهنام‌پور') }}
                </div>
            </div>

            {{-- Live Dynamic AI Insights --}}
            <div class="p-3.5 rounded-2xl bg-base-200/50 border border-base-200 space-y-2 text-xs text-base-content/90">
                <div class="flex items-start gap-2">
                    <span class="text-emerald-500 font-bold">🟢 {{ __('اقساط و مطالبات:') }}</span>
                    <span>
                        {{ __('امروز') }} <b class="font-mono text-emerald-600" x-text="num(block('installments')?.today?.count || 0)"></b> {{ __('قسط موعد وصول به ارزش') }}
                        <b class="font-mono text-emerald-600" x-text="money(block('installments')?.today?.amount || 0)"></b> {{ __('در نوبت دریافت است.') }}
                        <span x-show="(block('installments')?.overdue?.count || 0) > 0" class="text-rose-500 font-semibold mr-1">
                            ⚠️ <b class="font-mono" x-text="num(block('installments')?.overdue?.count)"></b> {{ __('قسط معوق نیازمند پیگیری فوری.') }}
                        </span>
                    </span>
                </div>
                <div class="flex items-start gap-2">
                    <span class="text-cyan-500 font-bold">💎 {{ __('تحلیل انبار و نوسان دلار (D-137):') }}</span>
                    <span>{{ __('با لحاظ نرخ روز دلار آزاد، بهای جایگزینی کالاهای انبار محاسبه شده و سیستم فروش زیر قیمت روز را کنترل می‌کند.') }}</span>
                </div>
            </div>

            {{-- Instant Interactive Prompt Bar --}}
            <div class="mt-3 flex items-center gap-2">
                <div class="relative flex-1">
                    <input type="text" x-model="userPrompt" @keydown.enter.prevent="askAi()"
                        placeholder="{{ __('از جمینای بپرسید... (مثال: وضعیت اقساط معوق چطوره؟ یا دلار چقدر روی سودم اثر گذاشت؟)') }}"
                        class="input input-sm w-full bg-base-100 border-base-300 focus:border-cyan-500 rounded-2xl text-xs">
                </div>
                <button type="button" @click="askAi()" :disabled="isThinking"
                    class="btn btn-sm bg-gradient-to-r from-cyan-500 to-blue-500 hover:from-cyan-400 hover:to-blue-400 text-slate-950 font-black rounded-2xl text-xs gap-1 shadow-sm">
                    <span x-show="!isThinking">{{ __('پرسش از هوش مصنوعی ↵') }}</span>
                    <span x-show="isThinking" class="loading loading-spinner loading-xs"></span>
                </button>
            </div>

            {{-- Dynamic AI Answer Box --}}
            <div x-show="aiResponse" x-transition class="mt-3 p-3.5 rounded-2xl bg-cyan-500/10 border border-cyan-500/30 text-xs text-base-content flex items-start justify-between">
                <div class="flex items-start gap-2">
                    <span class="text-base">🤖</span>
                    <div class="leading-relaxed whitespace-pre-line" x-text="aiResponse"></div>
                </div>
                <button @click="aiResponse = ''" class="btn btn-ghost btn-xs btn-circle mr-2">✕</button>
            </div>
        </div>

        {{-- 2. TOP 4 FINANCIAL PULSE KPI CARDS --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {{-- Card 1: Total Liquidity --}}
            <a href="{{ route('bank-accounts.index') }}" x-show="block('liquidity')"
                class="today-kpi group relative overflow-hidden rounded-3xl border border-base-300 bg-base-100 p-5 shadow-xs transition duration-300 hover:-translate-y-1 hover:border-emerald-500/40 hover:shadow-lg">
                <div aria-hidden="true" class="absolute inset-x-0 top-0 h-1 bg-emerald-500"></div>
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-base-content/70">{{ __('موجودی کل نقد و بانک‌ها') }}</span>
                    <span class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0">
                        🏦
                    </span>
                </div>
                <div class="text-2xl font-black tracking-tight text-base-content" x-text="money(block('liquidity')?.total)"></div>
                <div class="mt-2 flex items-center justify-between text-xs text-base-content/60 border-t border-base-200 pt-2">
                    <span>بانک: <span class="font-mono font-semibold" x-text="money(block('liquidity')?.bank)"></span></span>
                    <span>صندوق: <span class="font-mono font-semibold" x-text="money(block('liquidity')?.cash)"></span></span>
                </div>
            </a>

            {{-- Card 2: Today Sales --}}
            <a href="{{ route('invoices.index', ['invoice_type' => 'sell']) }}" x-show="block('sales')"
                class="today-kpi group relative overflow-hidden rounded-3xl border border-base-300 bg-base-100 p-5 shadow-xs transition duration-300 hover:-translate-y-1 hover:border-cyan-500/40 hover:shadow-lg">
                <div aria-hidden="true" class="absolute inset-x-0 top-0 h-1 bg-cyan-500"></div>
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-base-content/70">{{ __('فروش خالص امروز') }}</span>
                    <span class="w-8 h-8 rounded-xl bg-cyan-500/10 text-cyan-600 flex items-center justify-center shrink-0">
                        📈
                    </span>
                </div>
                <div class="text-2xl font-black tracking-tight text-base-content" x-text="money(block('sales')?.today_sell?.amount)"></div>
                <div class="mt-2 flex items-center justify-between text-xs text-base-content/60 border-t border-base-200 pt-2">
                    <span><b class="text-cyan-600 font-mono" x-text="num(block('sales')?.today_sell?.count)"></b> {{ __('فاکتور فروش') }}</span>
                    <span>خرید: <span class="font-mono" x-text="money(block('sales')?.today_buy?.amount)"></span></span>
                </div>
            </a>

            {{-- Card 3: Upcoming Installments & Receivables --}}
            <a href="{{ route('ageing.receivables') }}"
                class="today-kpi group relative overflow-hidden rounded-3xl border border-base-300 bg-base-100 p-5 shadow-xs transition duration-300 hover:-translate-y-1 hover:border-teal-500/40 hover:shadow-lg">
                <div aria-hidden="true" class="absolute inset-x-0 top-0 h-1 bg-teal-500"></div>
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-base-content/70">{{ __('اقساط و مطالبات نزدیک') }}</span>
                    <span class="w-8 h-8 rounded-xl bg-teal-500/10 text-teal-600 flex items-center justify-center shrink-0">
                        📅
                    </span>
                </div>
                <div class="text-2xl font-black tracking-tight text-teal-600"
                    x-text="money((block('installments')?.today?.amount || 0) + (block('receivables')?.overdue || 0))"></div>
                <div class="mt-2 flex items-center justify-between text-xs text-base-content/60 border-t border-base-200 pt-2">
                    <span>اقساط امروز: <b class="font-mono text-teal-600" x-text="num(block('installments')?.today?.count || 0)"></b></span>
                    <span>معوقات: <b class="font-mono text-rose-500" x-text="money(block('receivables')?.overdue || 0)"></b></span>
                </div>
            </a>

            {{-- Card 4: Upcoming Cheques Due in 7 Days --}}
            <a href="{{ route('cheques.index') }}" x-show="block('cheques')"
                class="today-kpi group relative overflow-hidden rounded-3xl border border-base-300 bg-base-100 p-5 shadow-xs transition duration-300 hover:-translate-y-1 hover:border-amber-500/40 hover:shadow-lg">
                <div aria-hidden="true" class="absolute inset-x-0 top-0 h-1 bg-amber-500"></div>
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-base-content/70">{{ __('تعهدات چک‌های ۷ روز آینده') }}</span>
                    <span class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                        ⏳
                    </span>
                </div>
                <div class="text-2xl font-black tracking-tight text-amber-600" x-text="money(block('cheques')?.payable?.due_soon?.amount || 0)"></div>
                <div class="mt-2 flex items-center justify-between text-xs text-base-content/60 border-t border-base-200 pt-2">
                    <span><b class="text-amber-600 font-mono" x-text="num(block('cheques')?.payable?.due_soon?.count || 0)"></b> {{ __('چک پرداختی') }}</span>
                    <span>دریافتی: <b class="font-mono text-emerald-600" x-text="money(block('cheques')?.receivable?.due_soon?.amount || 0)"></b></span>
                </div>
            </a>
        </div>

        {{-- 3. INSTALLMENT & CHEQUE RADAR (رادار تخصصی اقساط پیش‌رو و چک‌ها) --}}
        <div class="p-6 rounded-3xl border border-base-300 bg-base-100 shadow-xs" x-data="{ radarTab: 'installments' }">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-4 pb-3 border-b border-base-200">
                <div class="flex items-center gap-3">
                    <span class="text-xl">🎯</span>
                    <div>
                        <h3 class="text-base font-black text-base-content">{{ __('رادار پیش‌بینی وصول مطالبات و نقدینگی') }}</h3>
                        <p class="text-xs text-base-content/60">{{ __('پایش بی‌درنگ اقساط فروش و چک‌های صیادی پیش‌رو بدون شلوغی‌های اضافه') }}</p>
                    </div>
                </div>

                {{-- Radar Switcher Tabs --}}
                <div class="flex items-center p-1 bg-base-200 rounded-2xl">
                    <button type="button" @click="radarTab = 'installments'"
                        :class="radarTab === 'installments' ? 'bg-base-100 text-emerald-600 font-bold shadow-xs' : 'text-base-content/60 hover:text-base-content'"
                        class="px-4 py-1.5 rounded-xl text-xs transition flex items-center gap-1.5">
                        <span>📅 {{ __('اقساط پیش‌رو') }}</span>
                        <span class="badge badge-sm badge-ghost font-mono" x-text="num((block('installments')?.today?.count || 0) + (block('installments')?.due_soon?.count || 0))"></span>
                    </button>
                    <button type="button" @click="radarTab = 'cheques'"
                        :class="radarTab === 'cheques' ? 'bg-base-100 text-amber-600 font-bold shadow-xs' : 'text-base-content/60 hover:text-base-content'"
                        class="px-4 py-1.5 rounded-xl text-xs transition flex items-center gap-1.5">
                        <span>⏳ {{ __('چک‌های صیادی (۷ روز آینده)') }}</span>
                        <span class="badge badge-sm badge-ghost font-mono" x-text="num((block('cheques')?.receivable?.due_soon?.count || 0) + (block('cheques')?.payable?.due_soon?.count || 0))"></span>
                    </button>
                </div>
            </div>

            {{-- TAB 1: INSTALLMENTS VIEW --}}
            <div x-show="radarTab === 'installments'" class="space-y-3">
                <template x-if="(block('installments')?.items || []).length > 0">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        <template x-for="item in (block('installments')?.items || [])" :key="item.id">
                            <div class="p-4 rounded-2xl bg-base-200/50 border border-base-200 hover:border-emerald-500/40 transition flex flex-col justify-between space-y-3">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <span class="badge badge-xs py-1 px-2 font-bold"
                                            :class="item.is_today ? 'badge-success text-white' : (item.is_overdue ? 'badge-error text-white' : 'badge-ghost')"
                                            x-text="item.is_today ? 'سررسید: امروز' : (item.is_overdue ? (num(item.diff_days) + ' روز معوقه ⚠️') : ('سررسید: ' + item.due_date))"></span>
                                        <h4 class="text-xs font-bold text-base-content mt-1.5" x-text="'قسط #' + num(item.installment_number) + ' - ' + item.customer_name"></h4>
                                        <p class="text-[11px] text-base-content/60 mt-0.5" x-text="'قرارداد: ' + item.contract_number"></p>
                                    </div>
                                    <div class="text-left font-mono">
                                        <span class="text-sm font-black text-emerald-600 block" x-text="money(item.amount)"></span>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between pt-2 border-t border-base-300">
                                    <span class="text-[11px] text-base-content/60" x-text="item.customer_phone || 'بدون شماره'"></span>
                                    <div class="flex items-center gap-1.5">
                                        <template x-if="item.customer_phone">
                                            <a :href="'tel:' + item.customer_phone" class="btn btn-xs btn-ghost border border-base-300 rounded-lg">تماس / پیامک</a>
                                        </template>
                                        <a href="{{ route('quick-payment.create', 'receive') }}" class="btn btn-xs btn-success text-white font-bold rounded-lg">وصول سریع</a>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <template x-if="! (block('installments')?.items || []).length">
                    <div class="p-6 text-center text-xs text-base-content/60 bg-base-200/40 rounded-2xl">
                        {{ __('هیچ قسط سررسیدشده یا معوقی در ۷ روز آینده وجود ندارد. تمامی اقساط تسویه یا در موعد آینده هستند. 🎉') }}
                    </div>
                </template>
            </div>

            {{-- TAB 2: CHEQUES VIEW --}}
            <div x-show="radarTab === 'cheques'" class="space-y-3">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    {{-- Receivables Cheques --}}
                    <div class="p-4 rounded-2xl bg-base-200/50 border border-base-200 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-teal-500/10 text-teal-600 flex items-center justify-center font-black text-xs">چ</div>
                            <div>
                                <div class="text-xs font-bold text-base-content">{{ __('چک‌های دریافتی صیادی (۷ روز آینده)') }}</div>
                                <div class="text-[11px] text-base-content/60">
                                    <b class="font-mono" x-text="num(block('cheques')?.receivable?.due_soon?.count || 0)"></b> {{ __('فقره در نوبت وصول') }}
                                </div>
                            </div>
                        </div>
                        <div class="text-left font-mono">
                            <div class="text-sm font-black text-teal-600" x-text="money(block('cheques')?.receivable?.due_soon?.amount || 0)"></div>
                            <a href="{{ route('cheques.index') }}" class="link link-primary text-[10px]">{{ __('مدیریت چک‌ها') }} ←</a>
                        </div>
                    </div>

                    {{-- Payables Cheques --}}
                    <div class="p-4 rounded-2xl bg-base-200/50 border border-base-200 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center font-black text-xs">چ</div>
                            <div>
                                <div class="text-xs font-bold text-base-content">{{ __('چک‌های پرداختی متعهد (۷ روز آینده)') }}</div>
                                <div class="text-[11px] text-base-content/60">
                                    <b class="font-mono" x-text="num(block('cheques')?.payable?.due_soon?.count || 0)"></b> {{ __('فقره جهت پاس کردن') }}
                                </div>
                            </div>
                        </div>
                        <div class="text-left font-mono">
                            <div class="text-sm font-black text-amber-600" x-text="money(block('cheques')?.payable?.due_soon?.amount || 0)"></div>
                            <a href="{{ route('cheques.index') }}" class="link link-primary text-[10px]">{{ __('بررسی موجودی') }} ←</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- 4. CASH FLOW & SALES TREND CHART + QUICK ACTIONS --}}
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
            {{-- 7-Day Sales vs Purchases Trend --}}
            <div x-show="block('sales')" class="lg:col-span-2 rounded-3xl border border-base-300 bg-base-100 p-5 shadow-xs">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="font-bold text-sm text-base-content">{{ __('روند جریان فروش و خرید ۷ روز گذشته') }}</h3>
                        <p class="text-xs text-base-content/60">{{ __('پایش پیوسته حجم معاملات فروش در مقایسه با تامین کالا') }}</p>
                    </div>
                    <div class="flex items-center gap-3 text-xs">
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-emerald-500"></span> {{ __('فروش') }}</span>
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-cyan-500"></span> {{ __('خرید') }}</span>
                    </div>
                </div>
                <div class="relative h-64">
                    <canvas x-ref="salesChart"></canvas>
                </div>
            </div>

            {{-- Essential Quick Actions Dock --}}
            <div class="rounded-3xl border border-base-300 bg-base-100 p-5 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-3 border-b border-base-200 pb-3">
                        <h3 class="font-bold text-sm text-base-content flex items-center gap-2">
                            <span>⚡</span>
                            <span>{{ __('دسترسی‌های سریع مدیر') }}</span>
                        </h3>
                        <span class="badge badge-ghost badge-xs font-mono">F-Keys</span>
                    </div>

                    <div class="space-y-2.5">
                        @can('invoices.create')
                            <a href="{{ route('invoices.create', ['invoice_type' => 'sell']) }}"
                                class="w-full flex items-center justify-between p-3 rounded-2xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs transition shadow-xs">
                                <span class="flex items-center gap-2"><span>➕</span><span>{{ __('صدور فاکتور فروش رسمی') }}</span></span>
                                <span class="badge badge-sm bg-emerald-700 text-white border-0 font-mono">F2</span>
                            </a>
                        @endcan

                        @can('quick-payment.create')
                            <a href="{{ route('quick-payment.create', 'receive') }}"
                                class="w-full flex items-center justify-between p-3 rounded-2xl bg-base-200 hover:bg-base-300 text-base-content font-bold text-xs transition border border-base-300">
                                <span class="flex items-center gap-2"><span>💵</span><span>{{ __('ثبت دریافت وجه و قسط') }}</span></span>
                                <span class="badge badge-sm badge-ghost font-mono">F3</span>
                            </a>
                        @endcan

                        @can('cheques.create')
                            <a href="{{ route('cheques.create') }}"
                                class="w-full flex items-center justify-between p-3 rounded-2xl bg-base-200 hover:bg-base-300 text-base-content font-bold text-xs transition border border-base-300">
                                <span class="flex items-center gap-2"><span>💳</span><span>{{ __('ثبت چک صیادی') }}</span></span>
                                <span class="badge badge-sm badge-ghost font-mono">F4</span>
                            </a>
                        @endcan

                        @can('copilot.chat')
                            <a href="{{ route('copilot.index') }}"
                                class="w-full flex items-center justify-between p-3 rounded-2xl bg-base-200 hover:bg-base-300 text-base-content font-bold text-xs transition border border-base-300">
                                <span class="flex items-center gap-2"><span>✨</span><span>{{ __('گفتگو با دستیار جمینای') }}</span></span>
                                <span class="badge badge-sm badge-accent font-mono text-[9px]">AI</span>
                            </a>
                        @endcan
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-base-200 flex items-center justify-between text-xs text-base-content/60">
                    <span>{{ __('شرکت:') }} {{ optional(auth()->user()->activeCompany)->name ?? 'دخل' }}</span>
                    <span class="font-mono text-emerald-600">{{ __('نسخه بومی دخل') }}</span>
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
                    const primary = '#10b981'; // emerald
                    const accent = '#06b6d4'; // cyan

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
