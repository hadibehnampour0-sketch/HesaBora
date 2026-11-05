<x-app-layout :title="__('انتقال دیتابیس قدیمی با هوش مصنوعی')">
    <x-show-message-bags />
    <div class="container mx-auto px-4 py-6 max-w-5xl space-y-6">
        {{-- Hero Header --}}
        <div class="card bg-gradient-to-r from-emerald-600 via-teal-600 to-cyan-700 text-white shadow-xl rounded-2xl overflow-hidden">
            <div class="card-body p-6 md:p-8 flex flex-col md:flex-row items-center justify-between gap-6">
                <div class="space-y-2">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/20 backdrop-blur-md text-xs font-semibold">
                        <span class="w-2 h-2 rounded-full bg-emerald-300 animate-pulse"></span>
                        {{ __('پایش و نگاشت هوشمند با هوش مصنوعی نپتون') }}
                    </div>
                    <h1 class="text-2xl md:text-3xl font-black">{{ __('مهاجرت و انتقال دیتابیس نرم‌افزار قدیمی') }}</h1>
                    <p class="text-sm md:text-base text-white/80 max-w-2xl leading-relaxed">
                        {{ __('در تمامی نسخه‌های نپتون، هر زمان که بخواهید می‌توانید با چند کلیک ساده اطلاعات نرم‌افزار قبلی خود (هلو، سپیدار، پارسیان، محک، رافع، شایگان یا دیتابیس‌های SQL و اکسل) را به نپتون منتقل کنید. هوش مصنوعی نپتون داده‌ها را پایش، عیب‌یابی و دقیقاً سر جای مناسب در سیستم قرار می‌دهد.') }}
                    </p>
                </div>
                <div class="shrink-0 flex items-center justify-center w-24 h-24 rounded-2xl bg-white/10 backdrop-blur-md border border-white/20 shadow-inner">
                    <svg class="w-14 h-14 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                </div>
            </div>
        </div>

        {{-- 3-Step Flow Wizard --}}
        <ul class="steps steps-horizontal w-full py-2">
            <li class="step step-primary font-bold text-xs">{{ __('گام ۱: بارگذاری دیتابیس / فایل') }}</li>
            <li class="step text-xs">{{ __('گام ۲: پایش هوش مصنوعی و تراز افتتاحیه') }}</li>
            <li class="step text-xs">{{ __('گام ۳: انتقال قطعی و جای‌گذاری در نپتون') }}</li>
        </ul>

        {{-- Form Card --}}
        <div class="card bg-base-100 shadow-xl border border-base-200">
            <div class="card-body p-6 md:p-8 space-y-6">
                <h2 class="text-lg font-bold text-primary flex items-center gap-2">
                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    {{ __('گام ۱: انتخاب نرم‌افزار حسابداری مبدا و بارگذاری داده‌ها') }}
                </h2>

                <form action="{{ route('migration.preview') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                    @csrf

                    <!-- Source System Selection -->
                    <div class="space-y-3">
                        <label class="label font-bold text-sm text-base-content/80">
                            {{ __('نرم‌افزار قبلی شما کدام است؟') }}
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                            <label class="border border-base-300 rounded-xl p-4 flex items-center gap-3 cursor-pointer hover:border-primary transition-all has-[:checked]:border-primary has-[:checked]:bg-primary/5 shadow-xs">
                                <input type="radio" name="source_type" value="holoo" checked class="radio radio-primary" />
                                <div>
                                    <div class="font-bold text-sm">{{ __('نرم‌افزار هلو (Holoo)') }}</div>
                                    <div class="text-[11px] text-base-content/60">{{ __('بک‌آپ یا اکسپورت کالا، اشخاص و چک') }}</div>
                                </div>
                            </label>

                            <label class="border border-base-300 rounded-xl p-4 flex items-center gap-3 cursor-pointer hover:border-primary transition-all has-[:checked]:border-primary has-[:checked]:bg-primary/5 shadow-xs">
                                <input type="radio" name="source_type" value="sepidar" class="radio radio-primary" />
                                <div>
                                    <div class="font-bold text-sm">{{ __('سپیدار همکاران سیستم') }}</div>
                                    <div class="text-[11px] text-base-content/60">{{ __('خروجی اکسل یا پشتیبان سپیدار') }}</div>
                                </div>
                            </label>

                            <label class="border border-base-300 rounded-xl p-4 flex items-center gap-3 cursor-pointer hover:border-primary transition-all has-[:checked]:border-primary has-[:checked]:bg-primary/5 shadow-xs">
                                <input type="radio" name="source_type" value="auto_unknown" class="radio radio-primary" />
                                <div>
                                    <div class="font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1 text-sm">
                                        <span>{{ __('پارسیان، محک، رافع و...') }}</span>
                                        <span class="badge badge-success badge-xs text-white">AI</span>
                                    </div>
                                    <div class="text-[11px] text-base-content/60">{{ __('تشخیص اتوماتیک الگو با هوش مصنوعی') }}</div>
                                </div>
                            </label>

                            <label class="border border-base-300 rounded-xl p-4 flex items-center gap-3 cursor-pointer hover:border-primary transition-all has-[:checked]:border-primary has-[:checked]:bg-primary/5 shadow-xs">
                                <input type="radio" name="source_type" value="sql_dump" class="radio radio-primary" />
                                <div>
                                    <div class="font-bold text-sm">{{ __('فایل دیتابیس (SQL Dump)') }}</div>
                                    <div class="text-[11px] text-base-content/60">{{ __('فایل‌های .sql استخراج‌شده از دیتابیس') }}</div>
                                </div>
                            </label>

                            <label class="border border-base-300 rounded-xl p-4 flex items-center gap-3 cursor-pointer hover:border-primary transition-all has-[:checked]:border-primary has-[:checked]:bg-primary/5 shadow-xs">
                                <input type="radio" name="source_type" value="csv" class="radio radio-primary" />
                                <div>
                                    <div class="font-bold text-sm">{{ __('فایل اکسل و متنی (CSV / Excel)') }}</div>
                                    <div class="text-[11px] text-base-content/60">{{ __('خروجی‌های متنی و صفحات گسترده') }}</div>
                                </div>
                            </label>

                            <label class="border border-base-300 rounded-xl p-4 flex items-center gap-3 cursor-pointer hover:border-primary transition-all has-[:checked]:border-primary has-[:checked]:bg-primary/5 shadow-xs">
                                <input type="radio" name="source_type" value="universal_json" class="radio radio-primary" />
                                <div>
                                    <div class="font-bold text-sm">{{ __('قالب جامع نپتون (JSON)') }}</div>
                                    <div class="text-[11px] text-base-content/60">{{ __('فرمت استاندارد و ساختاریافته') }}</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- File Upload or Raw Data -->
                    <div class="space-y-2">
                        <label class="label font-bold text-sm">
                            {{ __('بارگذاری فایل پشتیبان / دیتابیس (.sql, .json, .csv, .txt):') }}
                        </label>
                        <input type="file" name="file" class="file-input file-input-bordered file-input-primary w-full" accept=".json,.txt,.sql,.csv" />
                        <div class="text-xs text-base-content/60 flex items-center justify-between">
                            <span>{{ __('پشتیبانی از انواع فایل‌های متنی دیتابیس تا سقف ۵۰ مگابایت') }}</span>
                            <span class="text-success font-medium">✓ بدون محدودیت تعداد رکورد</span>
                        </div>
                    </div>

                    <div class="divider text-xs text-base-content/40">{{ __('یا کدهای دیتابیس و داده‌ها را مستقیم در کادر زیر جای‌گذاری کنید') }}</div>

                    <div class="space-y-2">
                        <label class="label font-bold text-sm">
                            {{ __('متن داده‌ها، JSON یا دستورات SQL INSERT:') }}
                        </label>
                        <textarea name="raw_json" rows="5" dir="ltr" class="textarea textarea-bordered w-full font-mono text-xs" placeholder='{"customers": [...], "products": [...], "cheques": [...]} یا کدهای SQL INSERT INTO ...'></textarea>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-4 pt-4 border-t">
                        <div class="text-xs text-base-content/70 flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            <span>{{ __('هوش مصنوعی نپتون پیش از ثبت نهایی، کلیه ترازها و خطاها را به شما نشان خواهد داد.') }}</span>
                        </div>
                        <button type="submit" class="btn btn-primary btn-md px-8 gap-2 shadow-lg">
                            <span>{{ __('پایش و عیب‌یابی با هوش مصنوعی (مرحله ۲)') }}</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
