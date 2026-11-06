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

        {{-- Ready-to-Test Sample Databases Section --}}
        <div class="card bg-base-100 shadow-xl border border-primary/20">
            <div class="card-body p-6 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-base-200 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="p-2 rounded-xl bg-primary/10 text-primary">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                        </span>
                        <div>
                            <h2 class="text-base font-bold text-base-content">{{ __('دیتابیس‌های نمونه آماده برای تست انتقال') }}</h2>
                            <p class="text-xs text-base-content/60">{{ __('می‌توانید فایل‌های واقعی زیر را دانلود کنید یا با ۱ کلیک مستقیماً در فرم زیر بارگذاری و تست نمایید:') }}</p>
                        </div>
                    </div>
                    <span class="badge badge-accent badge-outline text-xs font-mono">۵ دیتابیس آماده تست</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    {{-- Sample 1: Holoo --}}
                    <div class="border border-base-300 rounded-xl p-4 bg-base-200/40 hover:border-primary transition-all flex flex-col justify-between space-y-3">
                        <div class="space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="badge badge-primary badge-sm font-bold">هلو (Holoo)</span>
                                <span class="text-[11px] text-base-content/50 font-mono">JSON</span>
                            </div>
                            <h3 class="font-bold text-sm text-base-content">بازرگانی پارس دیجیتال</h3>
                            <p class="text-[11px] text-base-content/70">صنف لوازم صوتی، تصویری و خانگی با حساب‌های اشخاص، انبار کالاهای برند و چک‌های صیادی بنفش.</p>
                            <div class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium pt-1">
                                ✦ ۸ مشتری | ۸ قلم کالا | ۵ فقره چک صیادی
                            </div>
                        </div>
                        <div class="flex items-center gap-2 pt-2 border-t border-base-200">
                            <button type="button" onclick="applySample('holoo', '01_holoo_backup_digital_store.json', 'هلو (پارس دیجیتال)')" class="btn btn-primary btn-xs flex-1 gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                <span>تست ۱ کلیکه</span>
                            </button>
                            <a href="/sample-databases/01_holoo_backup_digital_store.json" download class="btn btn-ghost btn-xs text-base-content/70 hover:text-primary gap-1" title="دانلود فایل">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                <span>دانلود</span>
                            </a>
                        </div>
                    </div>

                    {{-- Sample 2: Sepidar --}}
                    <div class="border border-base-300 rounded-xl p-4 bg-base-200/40 hover:border-primary transition-all flex flex-col justify-between space-y-3">
                        <div class="space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="badge badge-secondary badge-sm font-bold">سپیدار همکاران سیستم</span>
                                <span class="text-[11px] text-base-content/50 font-mono">JSON/Excel</span>
                            </div>
                            <h3 class="font-bold text-sm text-base-content">پخش البرز سلامت دارو</h3>
                            <p class="text-[11px] text-base-content/70">شرکت توزیع دارویی و بهداشتی با کدینگ تفصیلی، شناسه ملی اشخاص حقوقی، بارکدهای TTAC و اسناد خزانه.</p>
                            <div class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium pt-1">
                                ✦ ۶ مشتری و شرکت | ۶ قلم دارو | ۴ سند چک
                            </div>
                        </div>
                        <div class="flex items-center gap-2 pt-2 border-t border-base-200">
                            <button type="button" onclick="applySample('sepidar', '02_sepidar_distribution_company.json', 'سپیدار (البرز سلامت)')" class="btn btn-secondary btn-xs flex-1 gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                <span>تست ۱ کلیکه</span>
                            </button>
                            <a href="/sample-databases/02_sepidar_distribution_company.json" download class="btn btn-ghost btn-xs text-base-content/70 hover:text-primary gap-1" title="دانلود فایل">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                <span>دانلود</span>
                            </a>
                        </div>
                    </div>

                    {{-- Sample 3: SQL Dump --}}
                    <div class="border border-base-300 rounded-xl p-4 bg-base-200/40 hover:border-primary transition-all flex flex-col justify-between space-y-3">
                        <div class="space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="badge badge-info badge-sm font-bold">SQL Database Dump</span>
                                <span class="text-[11px] text-base-content/50 font-mono">.SQL</span>
                            </div>
                            <h3 class="font-bold text-sm text-base-content">دیتابیس صنعت‌یدک خاورمیانه</h3>
                            <p class="text-[11px] text-base-content/70">فایل کامل SQL با جداول tbl_ashkhas، tbl_anbar_kala و tbl_asnad_chq (پارسیان / شایگان / دشت / رافع).</p>
                            <div class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium pt-1">
                                ✦ ۶ طرف‌حساب | ۷ کالا و قطعه | ۵ چک صیاد
                            </div>
                        </div>
                        <div class="flex items-center gap-2 pt-2 border-t border-base-200">
                            <button type="button" onclick="applySample('sql_dump', '03_legacy_autodb_sanat_yadak.sql', 'دیتابیس کامل SQL (صنعت‌یدک)')" class="btn btn-info btn-xs flex-1 gap-1 text-white">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                <span>تست ۱ کلیکه</span>
                            </button>
                            <a href="/sample-databases/03_legacy_autodb_sanat_yadak.sql" download class="btn btn-ghost btn-xs text-base-content/70 hover:text-primary gap-1" title="دانلود فایل">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                <span>دانلود</span>
                            </a>
                        </div>
                    </div>

                    {{-- Sample 4: CSV / Excel --}}
                    <div class="border border-base-300 rounded-xl p-4 bg-base-200/40 hover:border-primary transition-all flex flex-col justify-between space-y-3">
                        <div class="space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="badge badge-success badge-sm font-bold text-white">اکسل / CSV</span>
                                <span class="text-[11px] text-base-content/50 font-mono">.CSV</span>
                            </div>
                            <h3 class="font-bold text-sm text-base-content">بوتیک و پوشاک تندیس</h3>
                            <p class="text-[11px] text-base-content/70">خروجی صفحات گسترده اکسل با ستون‌های فارسی نام، تلفن، کدملی و مانده حساب بدهکار/بستانکار.</p>
                            <div class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium pt-1">
                                ✦ ۶ مشتری و تامین‌کننده صنف پوشاک
                            </div>
                        </div>
                        <div class="flex items-center gap-2 pt-2 border-t border-base-200">
                            <button type="button" onclick="applySample('csv', '04_traditional_store_excel.csv', 'اکسل و CSV (بوتیک تندیس)')" class="btn btn-success btn-xs flex-1 gap-1 text-white">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                <span>تست ۱ کلیکه</span>
                            </button>
                            <a href="/sample-databases/04_traditional_store_excel.csv" download class="btn btn-ghost btn-xs text-base-content/70 hover:text-primary gap-1" title="دانلود فایل">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                <span>دانلود</span>
                            </a>
                        </div>
                    </div>

                    {{-- Sample 5: Messy AI Stress Test --}}
                    <div class="border border-base-300 rounded-xl p-4 bg-base-200/40 hover:border-primary transition-all flex flex-col justify-between space-y-3 md:col-span-2 lg:col-span-2">
                        <div class="space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="badge badge-warning badge-sm font-bold text-black">تست خودترمیمی و پایش AI</span>
                                <span class="text-[11px] text-base-content/50 font-mono">Stress Test</span>
                            </div>
                            <h3 class="font-bold text-sm text-base-content">دیتابیس نامنظم با خطاهای عمدی حسابداری</h3>
                            <p class="text-[11px] text-base-content/70">حاوی کالای با موجودی منفی (-۴)، شماره صیاد ناقص (۱۴ رقم)، و تلاش برای تزریق فرمول سنتی اکسل. برای اثبات قدرت پالایش، عیب‌یابی و تراز خودکار سند افتتاحیه توسط هوش مصنوعی نپتون.</p>
                            <div class="text-[11px] text-warning font-medium pt-1">
                                ✦ آزمون پالایش خودکار خطاهای دیتابیس قدیمی
                            </div>
                        </div>
                        <div class="flex items-center gap-2 pt-2 border-t border-base-200">
                            <button type="button" onclick="applySample('universal_json', '05_messy_legacy_stress_test.json', 'تست خودترمیمی هوش مصنوعی')" class="btn btn-warning btn-xs flex-1 gap-1 text-black font-bold">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                <span>تست پالایش هوش مصنوعی</span>
                            </button>
                            <a href="/sample-databases/05_messy_legacy_stress_test.json" download class="btn btn-ghost btn-xs text-base-content/70 hover:text-primary gap-1" title="دانلود فایل">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                <span>دانلود</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Form Card --}}
        <div class="card bg-base-100 shadow-xl border border-base-200" id="migration-form-card">
            <div class="card-body p-6 md:p-8 space-y-6">
                <h2 class="text-lg font-bold text-primary flex items-center gap-2">
                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    {{ __('گام ۱: انتخاب نرم‌افزار حسابداری مبدا و بارگذاری داده‌ها') }}
                </h2>

                <form id="migration-form" action="{{ route('migration.preview') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
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
                        <input type="file" name="file" id="migration-file-input" class="file-input file-input-bordered file-input-primary w-full" accept=".json,.txt,.sql,.csv" />
                        <div class="text-xs text-base-content/60 flex items-center justify-between">
                            <span>{{ __('پشتیبانی از انواع فایل‌های متنی دیتابیس تا سقف ۵۰ مگابایت') }}</span>
                            <span class="text-success font-medium">✓ بدون محدودیت تعداد رکورد</span>
                        </div>
                    </div>

                    <div class="divider text-xs text-base-content/40">{{ __('یا کدهای دیتابیس و داده‌ها را مستقیم در کادر زیر جای‌گذاری کنید') }}</div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="label font-bold text-sm">
                                {{ __('متن داده‌ها، JSON یا دستورات SQL INSERT:') }}
                            </label>
                            <span id="loaded-sample-indicator" class="hidden text-xs text-primary font-bold animate-pulse"></span>
                        </div>
                        <textarea name="raw_json" id="migration-raw-json" rows="6" dir="ltr" class="textarea textarea-bordered w-full font-mono text-xs" placeholder='{"customers": [...], "products": [...], "cheques": [...]} یا کدهای SQL INSERT INTO ...'></textarea>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-4 pt-4 border-t">
                        <div class="text-xs text-base-content/70 flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            <span>{{ __('هوش مصنوعی نپتون پیش از ثبت نهایی، کلیه ترازها و خطاها را به شما نشان خواهد داد.') }}</span>
                        </div>
                        <button type="submit" id="submit-preview-btn" class="btn btn-primary btn-md px-8 gap-2 shadow-lg">
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

    {{-- Interactive Sample Loader Script --}}
    <script>
        function applySample(sourceType, fileName, sampleName) {
            const indicator = document.getElementById('loaded-sample-indicator');
            const textarea = document.getElementById('migration-raw-json');
            const fileInput = document.getElementById('migration-file-input');
            const radio = document.querySelector(`input[name="source_type"][value="${sourceType}"]`);

            if (radio) {
                radio.checked = true;
            }
            if (fileInput) {
                fileInput.value = '';
            }

            if (indicator) {
                indicator.classList.remove('hidden');
                indicator.innerText = `در حال دریافت دیتابیس «${sampleName}»...`;
            }

            fetch(`/sample-databases/${fileName}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('خطا در دریافت فایل نمونه');
                    }
                    return response.text();
                })
                .then(text => {
                    textarea.value = text;
                    if (indicator) {
                        indicator.innerText = `✓ دیتابیس «${sampleName}» در کادر قرار گرفت.`;
                    }
                    const submitBtn = document.getElementById('submit-preview-btn');
                    if (submitBtn) {
                        submitBtn.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        submitBtn.classList.add('animate-bounce');
                        setTimeout(() => submitBtn.classList.remove('animate-bounce'), 1500);
                    }
                })
                .catch(err => {
                    alert('خطا در بارگذاری دیتابیس نمونه: ' + err.message);
                    if (indicator) {
                        indicator.classList.add('hidden');
                    }
                });
        }
    </script>
</x-app-layout>
