<x-app-layout :title="__('پایش و نظارت هوش مصنوعی - تراز افتتاحیه')">
    <x-show-message-bags />
    @php
        $aiInspection = $aiInspection ?? app(\App\Services\Migration\CompetitorMigrationService::class)->aiSupervisionReport($payload, $health);
    @endphp

    <div class="container mx-auto px-4 py-6 max-w-5xl space-y-6">
        {{-- Header --}}
        <div class="flex flex-wrap items-center justify-between gap-4 border-b pb-4">
            <div>
                <div class="inline-flex items-center gap-2 px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 text-xs font-bold mb-1">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    {{ __('پایش و نگاشت نظارتی فعال است') }}
                </div>
                <h1 class="text-2xl font-black text-slate-800 dark:text-white">{{ __('گام ۲: گزارش پایش هوش مصنوعی و تراز افتتاحیه') }}</h1>
                <p class="text-sm text-base-content/60 mt-0.5">{{ __('بررسی خودکار ناهمخوانی‌ها، برقراری معادله بنیادین دارایی = بدهی + سرمایه و نقشه جای‌گذاری داده‌ها') }}</p>
            </div>
            <a href="{{ route('migration.index') }}" class="btn btn-outline btn-sm gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                {{ __('بازگشت به گام ۱') }}
            </a>
        </div>

        {{-- 3-Step Wizard Progress --}}
        <ul class="steps steps-horizontal w-full py-2">
            <li class="step step-primary font-bold text-xs">{{ __('گام ۱: بارگذاری دیتابیس') }}</li>
            <li class="step step-primary font-bold text-xs">{{ __('گام ۲: پایش هوش مصنوعی و تراز افتتاحیه') }}</li>
            <li class="step text-xs">{{ __('گام ۳: انتقال قطعی و جای‌گذاری در نپتون') }}</li>
        </ul>

        {{-- AI Supervisor Cockpit Banner --}}
        <div class="card bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 text-white shadow-2xl rounded-2xl border border-indigo-500/30 overflow-hidden">
            <div class="card-body p-6 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-4 pb-3 border-b border-white/10">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-xl bg-indigo-500/20 border border-indigo-400/40 flex items-center justify-center text-indigo-300 shadow-inner">
                            <svg class="w-7 h-7 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <div>
                            <span class="text-xs text-indigo-300 font-bold uppercase tracking-wider block">{{ __('ناظر هوش مصنوعی نپتون (AI Supervisor)') }}</span>
                            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                                <span>{{ $aiInspection['detected_source'] }}</span>
                                <span class="badge badge-success badge-sm text-white font-mono">{{ $aiInspection['source_confidence'] }}% اطمینان</span>
                            </h2>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="text-right">
                            <span class="text-[11px] text-white/60 block">{{ __('شاخص سلامت داده‌ها') }}</span>
                            <span class="text-2xl font-black font-mono {{ $aiInspection['health_score'] >= 80 ? 'text-emerald-400' : 'text-amber-400' }}">
                                {{ $aiInspection['health_score'] }} / ۱۰۰
                            </span>
                        </div>
                    </div>
                </div>

                {{-- AI Commentary Speech Bubble --}}
                <div class="space-y-2">
                    <span class="text-xs text-indigo-200 font-semibold block">{{ __('تفسیر و تاییدیه ناظر هوش مصنوعی:') }}</span>
                    <div class="bg-white/5 rounded-xl p-4 border border-white/10 space-y-2 text-xs md:text-sm text-indigo-50 leading-relaxed">
                        @foreach ($aiInspection['commentary'] as $comment)
                            <div class="flex items-start gap-2">
                                <span class="text-emerald-400 shrink-0 font-bold">✓</span>
                                <span>{{ $comment }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Stats Cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="card bg-base-100 p-4 rounded-xl border border-base-200 shadow-sm">
                <div class="text-xs text-base-content/60 font-medium">{{ __('تعداد طرف‌حساب‌ها / مشتریان') }}</div>
                <div class="text-2xl font-black text-slate-800 dark:text-white mt-1">{{ localizeNumber(count($payload->customers)) }}</div>
                <div class="text-[11px] text-success mt-1">تطبیق داده شده در گروه تفصیلی</div>
            </div>
            <div class="card bg-base-100 p-4 rounded-xl border border-base-200 shadow-sm">
                <div class="text-xs text-base-content/60 font-medium">{{ __('تعداد کالاها و خدمات انبار') }}</div>
                <div class="text-2xl font-black text-slate-800 dark:text-white mt-1">{{ localizeNumber(count($payload->products)) }}</div>
                <div class="text-[11px] text-success mt-1">انتقال با کاردکس ریالی</div>
            </div>
            <div class="card bg-base-100 p-4 rounded-xl border border-base-200 shadow-sm">
                <div class="text-xs text-base-content/60 font-medium">{{ __('تعداد چک‌های در جریان') }}</div>
                <div class="text-2xl font-black text-slate-800 dark:text-white mt-1">{{ localizeNumber(count($payload->cheques)) }}</div>
                <div class="text-[11px] text-info mt-1">تفکیک دریافتنی و پرداختنی</div>
            </div>
            <div class="card bg-base-100 p-4 rounded-xl border border-base-200 shadow-sm">
                <div class="text-xs text-base-content/60 font-medium">{{ __('ارزش ریالی موجودی انبار') }}</div>
                <div class="text-lg font-black text-emerald-600 font-mono mt-1">{{ number_format($health->reconciliation['total_inventory_valuation'] ?? 0) }} ریال</div>
                <div class="text-[11px] text-base-content/50 mt-1">بر مبنای نرخ خرید قدیم</div>
            </div>
        </div>

        {{-- AI Smart Placement Table (نقشه جای‌گذاری هوشمند فیلدها سر جای خود در نپتون) --}}
        <div class="card bg-base-100 shadow-lg border border-base-200">
            <div class="card-body p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-slate-800 dark:text-white text-base flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                        <span>{{ __('نقشه جای‌گذاری هوشمند اطلاعات در ماژول‌های نپتون (AI Entity Placement Map)') }}</span>
                    </h3>
                    <span class="badge badge-primary badge-outline text-xs">تعیین خودکار سرفصل‌ها</span>
                </div>

                <div class="overflow-x-auto border border-base-200 rounded-xl">
                    <table class="table table-zebra w-full text-xs md:text-sm">
                        <thead class="bg-base-200/60">
                            <tr>
                                <th>{{ __('دسته داده‌ها') }}</th>
                                <th>{{ __('تعداد رکورد') }}</th>
                                <th>{{ __('جدول و مقصد در نپتون') }}</th>
                                <th>{{ __('کد سرفصل حسابداری') }}</th>
                                <th>{{ __('عملیات ناظر هوش مصنوعی') }}</th>
                                <th>{{ __('وضعیت') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($aiInspection['placements'] as $item)
                                <tr>
                                    <td class="font-bold">{{ $item['category'] }}</td>
                                    <td class="font-mono font-bold">{{ localizeNumber($item['count']) }}</td>
                                    <td class="font-mono text-primary font-semibold">{{ $item['target_table'] }}</td>
                                    <td class="font-mono text-xs">{{ $item['account_code'] }}</td>
                                    <td class="text-xs text-base-content/80">{{ $item['ai_action'] }}</td>
                                    <td>
                                        <span class="badge badge-success text-white badge-xs gap-1 py-1">
                                            ✓ {{ $item['status'] }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Warnings --}}
        @if(! empty($health->warnings))
            <div class="card bg-amber-50 dark:bg-amber-950/30 border border-amber-300/60 rounded-xl">
                <div class="card-body p-5">
                    <h3 class="font-bold text-amber-900 dark:text-amber-200 text-sm flex items-center gap-2">
                        <svg class="h-5 w-5 text-amber-600" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        <span>{{ __('هشدارهای رصد شده در دیتابیس قدیمی (:count مورد):', ['count' => count($health->warnings)]) }}</span>
                    </h3>
                    <ul class="list-disc list-inside text-xs text-amber-800 dark:text-amber-300 space-y-1 mt-2">
                        @foreach($health->warnings as $w)
                            <li>{{ $w['message'] }}</li>
                        @endforeach
                    </ul>
                    <div class="text-[11px] text-amber-700 dark:text-amber-400 mt-2 font-medium">
                        {{ __('💡 نگران نباشید: با فعال بودن گزینه «اصلاح خودکار هوش مصنوعی»، تمامی این موارد هنگام انتقال استانداردسازی خواهند شد.') }}
                    </div>
                </div>
            </div>
        @endif

        {{-- Accounting Reconciliation Table --}}
        <div class="card bg-base-100 shadow border border-base-200">
            <div class="card-body p-6 space-y-4">
                <h3 class="font-bold text-slate-800 dark:text-white text-base">
                    {{ __('پیش‌نمایش تراز سند افتتاحیه شماره ۱ (معادله بنیادین دارایی = بدهی + سرمایه)') }}
                </h3>

                <div class="overflow-x-auto border border-base-200 rounded-xl">
                    <table class="table table-zebra w-full text-sm">
                        <thead class="bg-base-200/50">
                            <tr>
                                <th>{{ __('سرفصل حسابداری در نپتون') }}</th>
                                <th>{{ __('ماهیت حساب') }}</th>
                                <th class="text-left">{{ __('مبلغ (ریال)') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="font-medium">{{ __('بدهکاران تجاری (مانده بدهی مشتریان)') }}</td>
                                <td>{{ __('بدهکار (دارایی)') }}</td>
                                <td class="text-left font-mono">{{ number_format($health->reconciliation['total_debtors'] ?? 0) }}</td>
                            </tr>
                            <tr>
                                <td class="font-medium">{{ __('موجودی کالا (ارزش ریالی انبار)') }}</td>
                                <td>{{ __('بدهکار (دارایی)') }}</td>
                                <td class="text-left font-mono">{{ number_format($health->reconciliation['total_inventory_valuation'] ?? 0) }}</td>
                            </tr>
                            <tr>
                                <td class="font-medium">{{ __('اسناد دریافتنی (چک‌های نزد صندوق)') }}</td>
                                <td>{{ __('بدهکار (دارایی)') }}</td>
                                <td class="text-left font-mono">{{ number_format($health->reconciliation['total_receivable_cheques'] ?? 0) }}</td>
                            </tr>
                            <tr class="bg-emerald-50 dark:bg-emerald-950/20 font-bold text-emerald-700 dark:text-emerald-400">
                                <td colspan="2">{{ __('مجموع دارایی‌های انتقالی:') }}</td>
                                <td class="text-left font-mono">{{ number_format($health->reconciliation['total_assets'] ?? 0) }}</td>
                            </tr>
                            <tr>
                                <td class="font-medium">{{ __('بستانکاران تجاری (طلب تامین‌کنندگان)') }}</td>
                                <td>{{ __('بستانکار (بدهی)') }}</td>
                                <td class="text-left font-mono">{{ number_format($health->reconciliation['total_creditors'] ?? 0) }}</td>
                            </tr>
                            <tr>
                                <td class="font-medium">{{ __('اسناد پرداختنی (چک‌های صادره سررسید نشده)') }}</td>
                                <td>{{ __('بستانکار (بدهی)') }}</td>
                                <td class="text-left font-mono">{{ number_format($health->reconciliation['total_payable_cheques'] ?? 0) }}</td>
                            </tr>
                            <tr class="bg-primary/10 font-bold text-primary">
                                <td colspan="2">{{ __('سرمایه و تراز افتتاحیه (متوازن‌ساز خودکار سند افتتاحیه):') }}</td>
                                <td class="text-left font-mono text-base">{{ number_format($health->reconciliation['opening_equity'] ?? 0) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Execute Form --}}
                <form action="{{ route('migration.execute') }}" method="POST" class="pt-6 border-t space-y-4">
                    @csrf
                    <input type="hidden" name="source_type" value="{{ $sourceType }}">
                    <input type="hidden" name="raw_json" value="{{ $rawJson }}">

                    <div class="form-control bg-base-200/50 p-3 rounded-xl border border-base-300">
                        <label class="label cursor-pointer justify-start gap-3">
                            <input type="checkbox" name="auto_repair" value="1" checked class="checkbox checkbox-primary" />
                            <span class="label-text font-bold text-xs md:text-sm">
                                {{ __('فعال‌سازی اصلاح خودکار هوش مصنوعی (Auto-Repair): موجودی‌های منفی صفر شده و سند افتتاحیه متوازن به صورت سیستمی صادر شود.') }}
                            </span>
                        </label>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-4 pt-2">
                        <div class="text-xs text-base-content/60 flex items-center gap-1.5">
                            <span class="badge badge-neutral badge-xs font-mono">D-085</span>
                            <span>{{ __('تمام اسناد با مهر سیستمی BASELINE (اصالت انتقال تاریخی) ثبت می‌شوند.') }}</span>
                        </div>
                        <button type="submit" class="btn btn-success text-white px-10 gap-2 shadow-lg" {{ ! $health->passed ? 'disabled' : '' }}>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>{{ __('تایید هوش مصنوعی و انتقال قطعی با ۱ کلیک') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
