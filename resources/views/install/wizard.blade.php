<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>راه‌اندازی اولیه سامانه مالی و اداری نپتون</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.14.8/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Vazirmatn', sans-serif; }
        .cosmic-glow {
            box-shadow: 0 0 30px -5px rgba(0, 240, 255, 0.25);
        }
        .cosmic-border {
            border-color: rgba(0, 240, 255, 0.2);
        }
    </style>
</head>
<body class="bg-[#070D1E] text-slate-100 min-h-screen flex flex-col justify-between selection:bg-cyan-500 selection:text-slate-950" x-data="installWizard()">
    <!-- Cosmic background ambient glows -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden z-0">
        <div class="absolute -top-40 right-1/4 w-96 h-96 bg-cyan-500/10 rounded-full blur-3xl"></div>
        <div class="absolute top-1/2 -left-32 w-80 h-80 bg-blue-600/10 rounded-full blur-3xl"></div>
    </div>

    <header class="py-5 border-b border-slate-800/80 bg-[#0B132B]/90 backdrop-blur-md sticky top-0 z-50">
        <div class="max-w-4xl mx-auto px-4 flex items-center justify-between">
            <div class="flex items-center space-x-3.5 space-x-reverse">
                <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-cyan-500 via-blue-600 to-indigo-600 flex items-center justify-center p-2 shadow-lg shadow-cyan-500/25">
                    <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full h-full text-white">
                        <circle cx="32" cy="32" r="16" fill="url(#neptuneGradHeader)" />
                        <ellipse cx="32" cy="32" rx="28" ry="8" stroke="#00F0FF" stroke-width="2.5" stroke-linecap="round" transform="rotate(-25 32 32)" opacity="0.9" />
                        <defs>
                            <linearGradient id="neptuneGradHeader" x1="16" y1="16" x2="48" y2="48" gradientUnits="userSpaceOnUse">
                                <stop stop-color="#00F0FF" />
                                <stop offset="0.6" stop-color="#0284C7" />
                                <stop offset="1" stop-color="#0F172A" />
                            </linearGradient>
                        </defs>
                    </svg>
                </div>
                <div>
                    <h1 class="text-xl font-black tracking-tight text-white flex items-center gap-2">
                        <span>نپتون</span>
                        <span class="text-cyan-400 font-light text-sm tracking-wider">NEPTUNE</span>
                    </h1>
                    <p class="text-xs text-slate-400 font-medium">سیستم جامع مالی، حسابداری ابری و هوش مصنوعی</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-mono font-semibold bg-cyan-950/60 border border-cyan-500/30 text-cyan-300">
                    <span class="w-2 h-2 rounded-full bg-cyan-400 animate-pulse"></span>
                    نسخه ۲.۵.۰ مستقل
                </span>
            </div>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-8 flex-1 w-full relative z-10">
        <!-- Step Progress Tracker -->
        <div class="mb-8">
            <div class="grid grid-cols-4 gap-2 text-center">
                <div :class="step >= 1 ? 'border-cyan-400 text-cyan-300 font-bold' : 'border-slate-800 text-slate-500'" class="border-b-2 pb-2.5 transition-all duration-300">
                    <span class="text-[11px] block text-slate-400">گام ۱</span>
                    <span class="text-xs sm:text-sm">سلامت سیستم</span>
                </div>
                <div :class="step >= 2 ? 'border-cyan-400 text-cyan-300 font-bold' : 'border-slate-800 text-slate-500'" class="border-b-2 pb-2.5 transition-all duration-300">
                    <span class="text-[11px] block text-slate-400">گام ۲</span>
                    <span class="text-xs sm:text-sm">پایگاه داده</span>
                </div>
                <div :class="step >= 3 ? 'border-cyan-400 text-cyan-300 font-bold' : 'border-slate-800 text-slate-500'" class="border-b-2 pb-2.5 transition-all duration-300">
                    <span class="text-[11px] block text-slate-400">گام ۳</span>
                    <span class="text-xs sm:text-sm">کدینگ و نقش‌ها</span>
                </div>
                <div :class="step >= 4 ? 'border-cyan-400 text-cyan-300 font-bold' : 'border-slate-800 text-slate-500'" class="border-b-2 pb-2.5 transition-all duration-300">
                    <span class="text-[11px] block text-slate-400">گام ۴</span>
                    <span class="text-xs sm:text-sm">حساب مدیر ارشد</span>
                </div>
            </div>
        </div>

        <div class="bg-[#0B132B]/80 border cosmic-border rounded-2xl p-6 sm:p-8 backdrop-blur-xl shadow-2xl cosmic-glow">
            <!-- STEP 1: Requirements -->
            <div x-show="step === 1" x-transition>
                <div class="mb-6 flex items-start justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-white mb-1.5 flex items-center gap-2">
                            <span>بررسی سلامت سرور و پیش‌نیازها</span>
                        </h2>
                        <p class="text-xs sm:text-sm text-slate-300">پیش از آغاز پیکربندی نپتون، اطمینان حاصل می‌کنیم کلیه اکستنشن‌های PHP و مجوزهای فایل مهیا هستند.</p>
                    </div>
                    <span class="hidden sm:inline-flex px-2.5 py-1 text-xs rounded-lg bg-slate-800 border border-slate-700 text-slate-300 font-mono">PHP 8.4+ Ready</span>
                </div>

                <!-- PHP Version -->
                <div class="mb-4 p-4 rounded-xl border flex items-center justify-between {{ $requirements['php']['passed'] ? 'bg-cyan-950/20 border-cyan-500/30' : 'bg-rose-950/20 border-rose-500/30' }}">
                    <div class="flex items-center space-x-3 space-x-reverse">
                        <div class="w-8 h-8 rounded-lg bg-cyan-500/10 border border-cyan-500/30 flex items-center justify-center text-cyan-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        </div>
                        <div>
                            <span class="font-bold text-sm block text-white">نسخه موتور پردازشی PHP</span>
                            <span class="text-xs font-mono text-cyan-300/80">{{ $requirements['php']['version'] }}</span>
                        </div>
                    </div>
                    <div>
                        @if($requirements['php']['passed'])
                            <span class="text-cyan-300 text-xs font-bold px-3 py-1 rounded-full bg-cyan-500/15 border border-cyan-500/30">تأیید شد (>= 8.2)</span>
                        @else
                            <span class="text-rose-400 text-xs font-bold px-3 py-1 rounded-full bg-rose-500/15 border border-rose-500/30">نیازمند PHP 8.2 یا بالاتر</span>
                        @endif
                    </div>
                </div>

                <!-- Extensions -->
                <div class="mb-6">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">اکستنشن‌های الزامی PHP نپتون</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                        @foreach($requirements['extensions'] as $ext)
                            <div class="p-3 rounded-xl border flex items-center justify-between {{ $ext['passed'] ? 'bg-[#0E1A38] border-slate-800' : 'bg-rose-950/30 border-rose-500/40' }}">
                                <span class="font-mono text-xs text-slate-200">{{ $ext['name'] }}</span>
                                @if($ext['passed'])
                                    <span class="w-5 h-5 rounded-full bg-cyan-500/20 text-cyan-400 flex items-center justify-center">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                    </span>
                                @else
                                    <span class="w-5 h-5 rounded-full bg-rose-500/20 text-rose-400 flex items-center justify-center">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Directories -->
                <div class="mb-6">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">دسترسی ذخیره‌سازی داده‌ها</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        @foreach($requirements['directories'] as $key => $dir)
                            <div class="p-3 rounded-xl border flex items-center justify-between {{ $dir['passed'] ? 'bg-[#0E1A38] border-slate-800' : 'bg-rose-950/30 border-rose-500/40' }}">
                                <span class="font-mono text-xs text-slate-300">{{ $key }}</span>
                                @if($dir['passed'])
                                    <span class="text-cyan-400 text-xs font-medium bg-cyan-950/50 px-2 py-0.5 rounded border border-cyan-800/40">آماده نوشتن</span>
                                @else
                                    <span class="text-rose-400 text-xs font-medium bg-rose-950/50 px-2 py-0.5 rounded border border-rose-800/40">نیاز به مجوز</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Database status -->
                <div class="p-4 rounded-xl border mb-6 flex items-center justify-between {{ $dbConnected ? 'bg-cyan-950/20 border-cyan-500/30' : 'bg-amber-950/20 border-amber-500/30' }}">
                    <div class="flex items-center gap-3">
                        <div class="w-2.5 h-2.5 rounded-full {{ $dbConnected ? 'bg-cyan-400 animate-ping' : 'bg-amber-400' }}"></div>
                        <span class="text-sm font-bold text-white">موتور ذخیره‌سازی و پایگاه داده (SQLite / MariaDB)</span>
                    </div>
                    @if($dbConnected)
                        <span class="text-cyan-300 text-xs font-bold px-3 py-1 rounded-full bg-cyan-500/15 border border-cyan-500/30">اتصال برقرار است</span>
                    @else
                        <span class="text-amber-400 text-xs font-bold px-3 py-1 rounded-full bg-amber-500/15 border border-amber-500/30">تنظیمات .env را بررسی فرمایید</span>
                    @endif
                </div>

                <div class="flex justify-end pt-2">
                    @if($allPassed && $dbConnected)
                        <button type="button" @click="step = 2" class="bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-slate-950 font-bold py-2.5 px-7 rounded-xl transition shadow-lg shadow-cyan-500/25 text-sm flex items-center space-x-2 space-x-reverse">
                            <span>گام بعدی: ایجاد جداول</span>
                            <svg class="w-4 h-4 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </button>
                    @else
                        <div class="text-xs text-amber-400 bg-amber-950/30 border border-amber-500/30 px-4 py-2 rounded-xl">لطفاً پیش از ادامه، موارد قرمز را بررسی فرمایید.</div>
                    @endif
                </div>
            </div>

            <!-- STEP 2: Database Migration -->
            <div x-show="step === 2" x-transition>
                <div class="mb-6">
                    <h2 class="text-xl font-bold text-white mb-1.5">ساختار استاندارد حسابداری نپتون</h2>
                    <p class="text-xs sm:text-sm text-slate-300">جداول اسناد مالی دوبل، زنجیره اسناد، انبارداری با بهای تمام‌شده، ترازنامه و تریگرهای تغییرناپذیری مالی ایجاد می‌شوند.</p>
                </div>

                <div class="bg-[#070D1E] p-5 rounded-xl border border-slate-800 mb-6 font-mono text-xs text-slate-300 shadow-inner">
                    <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-800 text-slate-500 text-[11px]">
                        <span>Neptune Core Migration Pipeline</span>
                        <span class="text-cyan-400">ACID & Event-Sourced</span>
                    </div>
                    <p class="mb-2 text-cyan-400 font-bold">> php artisan migrate --force</p>
                    <p class="text-slate-400 leading-relaxed">تضمین ایزولاسیون کامل شرکت‌ها (Multi-Company Scoping)، حفاظت از ثبات بالانس ترازنامه (ADR-E) و سازگاری بی‌درنگ با آفلاین/آنلاین.</p>
                </div>

                <div x-show="migrationLoading" class="p-8 text-center bg-[#070D1E]/40 rounded-xl border border-slate-800/60 mb-6">
                    <div class="inline-block animate-spin rounded-full h-9 w-9 border-t-2 border-b-2 border-cyan-400 mb-3"></div>
                    <p class="text-sm font-semibold text-white">در حال استقرار ساختار دیتابیس نپتون...</p>
                    <p class="text-xs text-slate-400 mt-1">لطفاً چند لحظه شکیبا باشید.</p>
                </div>

                <div x-show="migrationDone" class="p-4 rounded-xl bg-cyan-950/25 border border-cyan-500/40 text-cyan-200 text-sm mb-6 flex items-center space-x-3 space-x-reverse">
                    <svg class="w-5 h-5 text-cyan-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                    <span x-text="migrationMessage"></span>
                </div>

                <div class="flex justify-between items-center pt-2">
                    <button type="button" @click="step = 1" class="text-slate-400 hover:text-white text-sm transition">بازگشت</button>
                    <button type="button" x-show="!migrationDone && !migrationLoading" @click="runMigrations()" class="bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-slate-950 font-bold py-2.5 px-7 rounded-xl transition shadow-lg shadow-cyan-500/25 text-sm">
                        شروع ساخت جداول
                    </button>
                    <button type="button" x-show="migrationDone" @click="step = 3" class="bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-slate-950 font-bold py-2.5 px-7 rounded-xl transition shadow-lg shadow-cyan-500/25 text-sm flex items-center space-x-2 space-x-reverse">
                        <span>گام بعدی: کدینگ پایه</span>
                        <svg class="w-4 h-4 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </button>
                </div>
            </div>

            <!-- STEP 3: Baseline Seeding -->
            <div x-show="step === 3" x-transition>
                <div class="mb-6">
                    <h2 class="text-xl font-bold text-white mb-1.5">بارگذاری اطلاعات پایه و استاندارد حسابداری ایران</h2>
                    <p class="text-xs sm:text-sm text-slate-300">تعریف سطوح دسترسی (مدیر ارشد، حسابدار، انباردار، مسئول فروش) و سرفصل‌های کدینگ استاندارد.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                    <div class="p-4 rounded-xl bg-[#0E1A38] border border-slate-800">
                        <div class="flex items-center gap-2 mb-1.5">
                            <span class="w-2 h-2 rounded-full bg-cyan-400"></span>
                            <div class="font-bold text-sm text-white">کدینگ استاندارد مالی</div>
                        </div>
                        <p class="text-xs text-slate-400 leading-relaxed">گروه، کل، معین و تفصیلی منطبق بر استانداردهای حسابرسی ملی، قانون مالیات‌های مستقیم و سامانه مؤدیان.</p>
                    </div>
                    <div class="p-4 rounded-xl bg-[#0E1A38] border border-slate-800">
                        <div class="flex items-center gap-2 mb-1.5">
                            <span class="w-2 h-2 rounded-full bg-blue-400"></span>
                            <div class="font-bold text-sm text-white">تفکیک وظایف و کنترل داخلی (SOD)</div>
                        </div>
                        <p class="text-xs text-slate-400 leading-relaxed">جلوگیری از تأیید اسناد توسط ایجادکننده و پیاده‌سازی گیت‌های امنیتی تغییرناپذیری اسناد نهایی.</p>
                    </div>
                </div>

                <div x-show="seedingLoading" class="p-8 text-center bg-[#070D1E]/40 rounded-xl border border-slate-800/60 mb-6">
                    <div class="inline-block animate-spin rounded-full h-9 w-9 border-t-2 border-b-2 border-cyan-400 mb-3"></div>
                    <p class="text-sm font-semibold text-white">در حال مقداردهی اولیه سرفصل‌ها و مجوزها...</p>
                </div>

                <div x-show="seedingDone" class="p-4 rounded-xl bg-cyan-950/25 border border-cyan-500/40 text-cyan-200 text-sm mb-6 flex items-center space-x-3 space-x-reverse">
                    <svg class="w-5 h-5 text-cyan-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                    <span x-text="seedingMessage"></span>
                </div>

                <div class="flex justify-between items-center pt-2">
                    <button type="button" @click="step = 2" class="text-slate-400 hover:text-white text-sm transition">بازگشت</button>
                    <button type="button" x-show="!seedingDone && !seedingLoading" @click="runSeeding()" class="bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-slate-950 font-bold py-2.5 px-7 rounded-xl transition shadow-lg shadow-cyan-500/25 text-sm">
                        بارگذاری اطلاعات پایه
                    </button>
                    <button type="button" x-show="seedingDone" @click="step = 4" class="bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-slate-950 font-bold py-2.5 px-7 rounded-xl transition shadow-lg shadow-cyan-500/25 text-sm flex items-center space-x-2 space-x-reverse">
                        <span>گام پایانی: ایجاد حساب کاربری</span>
                        <svg class="w-4 h-4 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </button>
                </div>
            </div>

            <!-- STEP 4: Admin & Company Setup -->
            <div x-show="step === 4" x-transition>
                <div class="mb-6">
                    <h2 class="text-xl font-bold text-white mb-1.5">اطلاعات کسب‌وکار و حساب مدیر ارشد</h2>
                    <p class="text-xs sm:text-sm text-slate-300">نام مجموعه بازرگانی/تولیدی و مشخصات ورود مدیر ارشد سیستم نپتون را وارد نمایید.</p>
                </div>

                <form @submit.prevent="submitAdminForm()">
                    <div class="space-y-4 mb-6">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1.5">نام شرکت یا واحد کسب‌وکار</label>
                            <input type="text" x-model="adminForm.company_name" required placeholder="مثال: شرکت بازرگانی نپتون آریا" class="w-full bg-[#070D1E] border border-slate-700/80 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 transition">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1.5">نام و نام خانوادگی مدیر ارشد</label>
                                <input type="text" x-model="adminForm.admin_name" required placeholder="مثال: هادی بهنام‌پور" class="w-full bg-[#070D1E] border border-slate-700/80 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 transition">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1.5">نشانی رایانامه (ایمیل ورود به سیستم)</label>
                                <input type="email" x-model="adminForm.email" required placeholder="admin@neptune.local" class="w-full bg-[#070D1E] border border-slate-700/80 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 transition" dir="ltr">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1.5">کلمه عبور امن</label>
                                <input type="password" x-model="adminForm.password" required minlength="8" placeholder="حداقل ۸ کاراکتر امن" class="w-full bg-[#070D1E] border border-slate-700/80 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 transition" dir="ltr">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1.5">تکرار کلمه عبور</label>
                                <input type="password" x-model="adminForm.password_confirmation" required minlength="8" placeholder="تکرار دقیق کلمه عبور" class="w-full bg-[#070D1E] border border-slate-700/80 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 transition" dir="ltr">
                            </div>
                        </div>
                    </div>

                    <div x-show="errorMessage" class="p-4 rounded-xl bg-rose-950/30 border border-rose-500/40 text-rose-300 text-xs mb-6" x-text="errorMessage"></div>

                    <div class="flex justify-between items-center pt-2">
                        <button type="button" @click="step = 3" class="text-slate-400 hover:text-white text-sm transition">بازگشت</button>
                        <button type="submit" :disabled="submitting" class="bg-gradient-to-r from-cyan-500 via-blue-600 to-indigo-600 hover:from-cyan-400 hover:to-blue-500 text-slate-950 font-black py-2.5 px-8 rounded-xl transition shadow-lg shadow-cyan-500/30 text-sm flex items-center space-x-2 space-x-reverse disabled:opacity-50">
                            <span x-text="submitting ? 'در حال ثبت نهایی...' : 'تکمیل نصب و ورود به سامانه نپتون'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <footer class="py-4 border-t border-slate-800/80 text-center text-xs text-slate-400 relative z-10">
        سیستم مالی و ERP نپتون &bull; نسخه ۲.۵ پایدار &bull; صاحب امتیاز و طراح: هادی بهنام‌پور &bull; تمامی حقوق محفوظ است
    </footer>

    <script>
        function installWizard() {
            return {
                step: 1,
                migrationLoading: false,
                migrationDone: false,
                migrationMessage: '',
                seedingLoading: false,
                seedingDone: false,
                seedingMessage: '',
                submitting: false,
                errorMessage: '',
                adminForm: {
                    company_name: 'شرکت بازرگانی نپتون',
                    admin_name: 'هادی بهنام‌پور',
                    email: 'admin@neptune.local',
                    password: '',
                    password_confirmation: ''
                },
                async runMigrations() {
                    this.migrationLoading = true;
                    try {
                        const res = await fetch("{{ route('install.migrate') }}", {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json'
                            }
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.migrationDone = true;
                            this.migrationMessage = data.message;
                        } else {
                            alert(data.message || 'خطا در اجرای مایگریشن دیتابیس');
                        }
                    } catch (e) {
                        alert('خطا در اتصال: ' + e.message);
                    } finally {
                        this.migrationLoading = false;
                    }
                },
                async runSeeding() {
                    this.seedingLoading = true;
                    try {
                        const res = await fetch("{{ route('install.seed') }}", {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json'
                            }
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.seedingDone = true;
                            this.seedingMessage = data.message;
                        } else {
                            alert(data.message || 'خطا در بارگذاری اطلاعات پایه');
                        }
                    } catch (e) {
                        alert('خطا در اتصال: ' + e.message);
                    } finally {
                        this.seedingLoading = false;
                    }
                },
                async submitAdminForm() {
                    if (this.adminForm.password !== this.adminForm.password_confirmation) {
                        this.errorMessage = 'کلمه عبور با تکرار آن همخوانی ندارد.';
                        return;
                    }
                    this.submitting = true;
                    this.errorMessage = '';
                    try {
                        const res = await fetch("{{ route('install.setup-admin') }}", {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(this.adminForm)
                        });
                        const data = await res.json();
                        if (data.success) {
                            window.location.href = data.redirect || "{{ route('login') }}";
                        } else {
                            this.errorMessage = data.message || 'خطا در ثبت اطلاعات';
                        }
                    } catch (e) {
                        this.errorMessage = 'خطای ارتباط با سرور: ' + e.message;
                    } finally {
                        this.submitting = false;
                    }
                }
            };
        }
    </script>
</body>
</html>