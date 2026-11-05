<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مجموعه طراحی‌های مدرن صفحات نپتون (Google Flow & FinTech UI Showcase)</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.23/dist/full.min.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@500;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Vazirmatn', sans-serif; }
        .font-en { font-family: 'Plus Jakarta Sans', sans-serif; }
        .flow-glow { box-shadow: 0 0 50px -10px rgba(6, 182, 212, 0.25); }
        .glass-panel { background: rgba(15, 23, 42, 0.75); backdrop-filter: blur(16px); }
    </style>
</head>
<body class="bg-[#090D16] text-slate-100 min-h-screen py-10 px-4 md:px-10 space-y-12 selection:bg-cyan-500 selection:text-white"
      x-data="{ currentTheme: 'flow', currentPage: 'dashboard' }">

    <!-- Top Navigation & Concept Switcher -->
    <header class="max-w-7xl mx-auto space-y-6">
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 border-b border-slate-800/80 pb-6">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-slate-900 border border-slate-800 p-2 shadow-lg flex items-center justify-center">
                    <img src="/images/logo.svg" alt="Neptune Logo" class="w-full h-full object-contain">
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-2xl font-black text-white">طراحی نسل جدید صفحات «نپتون»</h1>
                        <span class="badge badge-sm badge-info font-bold text-xs">Google Flow UI</span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">
                        نمونه‌های تعاملی و ماژولار صفحات سیستم با الهام از Google Flow، Linear و Stripe
                    </p>
                </div>
            </div>

            <!-- Theme Picker Buttons -->
            <div class="flex items-center gap-2 bg-slate-900/90 p-1.5 rounded-2xl border border-slate-800">
                <button @click="currentTheme = 'flow'" 
                        :class="currentTheme === 'flow' ? 'bg-gradient-to-r from-blue-600 to-cyan-500 text-white shadow-lg shadow-cyan-500/20' : 'text-slate-400 hover:text-white'"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5">
                    <span>✨</span> سبک ۱: Google Flow (سیال و هوشمند)
                </button>
                <button @click="currentTheme = 'linear'" 
                        :class="currentTheme === 'linear' ? 'bg-slate-800 text-white border border-slate-700 shadow-md' : 'text-slate-400 hover:text-white'"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5">
                    <span>📐</span> سبک ۲: Linear Obsidian (مینیمال دقیق)
                </button>
                <button @click="currentTheme = 'stripe'" 
                        :class="currentTheme === 'stripe' ? 'bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-lg shadow-violet-500/20' : 'text-slate-400 hover:text-white'"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5">
                    <span>💎</span> سبک ۳: Stripe Glass (شیشه‌ای مدرن)
                </button>
            </div>
        </div>

        <!-- Page View Tab Selector -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <button @click="currentPage = 'dashboard'" 
                        :class="currentPage === 'dashboard' ? 'bg-cyan-500/10 text-cyan-400 border-cyan-500/30' : 'text-slate-400 hover:text-white border-transparent'"
                        class="px-4 py-2 rounded-xl border text-xs font-bold transition flex items-center gap-2">
                    <span>📊</span> ۱. داشبورد اصلی و هوش مصنوعی
                </button>
                <button @click="currentPage = 'invoice'" 
                        :class="currentPage === 'invoice' ? 'bg-cyan-500/10 text-cyan-400 border-cyan-500/30' : 'text-slate-400 hover:text-white border-transparent'"
                        class="px-4 py-2 rounded-xl border text-xs font-bold transition flex items-center gap-2">
                    <span>🧾</span> ۲. صفحه فاکتور فروش و صندوق
                </button>
                <button @click="currentPage = 'ledger'" 
                        :class="currentPage === 'ledger' ? 'bg-cyan-500/10 text-cyan-400 border-cyan-500/30' : 'text-slate-400 hover:text-white border-transparent'"
                        class="px-4 py-2 rounded-xl border text-xs font-bold transition flex items-center gap-2">
                    <span>⚖️</span> ۳. دفاتر مالی، اسناد و ترازنامه
                </button>
            </div>
            <div class="text-xs text-slate-500 hidden md:block">
                <span>وضعیت سایدبار: </span>
                <span class="text-emerald-400 font-bold">۱۰ سرفصل استاندارد حسابداری تفکیک شد</span>
            </div>
        </div>
    </header>

    <!-- Main Container Simulation (Showing Re-organized Sidebar + Dynamic Modern Page Body) -->
    <main class="max-w-7xl mx-auto rounded-3xl border border-slate-800 bg-[#060911] overflow-hidden shadow-2xl flex flex-col md:flex-row min-h-[780px]">

        <!-- Sidebar Navigation (Re-organized and Sorted) -->
        <aside class="w-full md:w-64 border-b md:border-b-0 md:border-l border-slate-800/80 bg-[#090D16] p-4 flex flex-col justify-between shrink-0">
            <div class="space-y-4">
                <!-- Brand Lockup -->
                <div class="flex items-center gap-3 px-2 py-2 border-b border-slate-800/80">
                    <div class="w-10 h-10 rounded-xl bg-slate-950 border border-cyan-500/30 p-1.5 shadow-md flex items-center justify-center">
                        <img src="/images/logo.svg" alt="Neptune" class="w-full h-full object-contain">
                    </div>
                    <div>
                        <div class="font-black text-sm text-white tracking-wide">دَخــل <span class="text-[9px] text-cyan-400 font-mono">FLOW</span></div>
                        <div class="text-[10px] text-slate-400">حسابداری و مالیات ابری</div>
                    </div>
                </div>

                <!-- 10 Group Navigation List -->
                <nav class="space-y-1 text-xs">
                    <!-- 1. Home / AI -->
                    <div class="px-3 py-2 rounded-xl bg-gradient-to-r from-cyan-500/20 to-blue-500/10 text-cyan-300 font-bold flex items-center justify-between border border-cyan-500/20">
                        <div class="flex items-center gap-2">
                            <span>🏠</span>
                            <span>میز کار و داشبورد</span>
                        </div>
                        <span class="badge badge-xs badge-info text-[9px]">AI</span>
                    </div>

                    <!-- 2. Sales & Retail -->
                    <div class="px-3 py-2 rounded-xl text-slate-300 hover:bg-slate-800/50 transition cursor-pointer flex items-center justify-between group">
                        <div class="flex items-center gap-2">
                            <span>🛒</span>
                            <span class="group-hover:text-white">فروش و صندوق</span>
                        </div>
                        <span class="text-[10px] text-slate-500">POS, فاکتور</span>
                    </div>

                    <!-- 3. Purchases & Costs -->
                    <div class="px-3 py-2 rounded-xl text-slate-300 hover:bg-slate-800/50 transition cursor-pointer flex items-center justify-between group">
                        <div class="flex items-center gap-2">
                            <span>📥</span>
                            <span class="group-hover:text-white">خرید و تدارکات</span>
                        </div>
                        <span class="badge badge-xs badge-neutral text-[9px]">OCR</span>
                    </div>

                    <!-- 4. Treasury & Cash -->
                    <div class="px-3 py-2 rounded-xl text-slate-300 hover:bg-slate-800/50 transition cursor-pointer flex items-center justify-between group">
                        <div class="flex items-center gap-2">
                            <span>💵</span>
                            <span class="group-hover:text-white">خزانه‌داری و چک</span>
                        </div>
                        <span class="text-[10px] text-emerald-400 font-mono">صیاد</span>
                    </div>

                    <!-- 5. Financial Accounting -->
                    <div class="px-3 py-2 rounded-xl text-slate-300 hover:bg-slate-800/50 transition cursor-pointer flex items-center justify-between group">
                        <div class="flex items-center gap-2">
                            <span>⚖️</span>
                            <span class="group-hover:text-white">حسابداری مالی</span>
                        </div>
                        <span class="text-[10px] text-slate-500">اسناد، دفاتر</span>
                    </div>

                    <!-- 6. Warehouse & Inventory -->
                    <div class="px-3 py-2 rounded-xl text-slate-300 hover:bg-slate-800/50 transition cursor-pointer flex items-center justify-between group">
                        <div class="flex items-center gap-2">
                            <span>📦</span>
                            <span class="group-hover:text-white">انبار و کالاها</span>
                        </div>
                        <span class="text-[10px] text-slate-500">موجودی</span>
                    </div>

                    <!-- 7. Tax & Moadian -->
                    <div class="px-3 py-2 rounded-xl text-slate-300 hover:bg-slate-800/50 transition cursor-pointer flex items-center justify-between group">
                        <div class="flex items-center gap-2">
                            <span>🏛️</span>
                            <span class="group-hover:text-white">سامانه مودیان</span>
                        </div>
                        <span class="badge badge-xs badge-warning text-[9px]">کارپوشه</span>
                    </div>

                    <!-- 8. CRM & Customers -->
                    <div class="px-3 py-2 rounded-xl text-slate-300 hover:bg-slate-800/50 transition cursor-pointer flex items-center justify-between group">
                        <div class="flex items-center gap-2">
                            <span>👥</span>
                            <span class="group-hover:text-white">مشتریان و CRM</span>
                        </div>
                        <span class="text-[10px] text-slate-500">باشگاه</span>
                    </div>

                    <!-- 9. HR & Payroll -->
                    <div class="px-3 py-2 rounded-xl text-slate-300 hover:bg-slate-800/50 transition cursor-pointer flex items-center justify-between group">
                        <div class="flex items-center gap-2">
                            <span>⏱️</span>
                            <span class="group-hover:text-white">حقوق و دستمزد</span>
                        </div>
                        <span class="text-[10px] text-slate-500">پرسنل</span>
                    </div>

                    <!-- 10. System & Settings -->
                    <div class="px-3 py-2 rounded-xl text-slate-300 hover:bg-slate-800/50 transition cursor-pointer flex items-center justify-between group">
                        <div class="flex items-center gap-2">
                            <span>⚙️</span>
                            <span class="group-hover:text-white">تنظیمات و سیستم</span>
                        </div>
                        <span class="text-[10px] text-slate-500">مهاجرت</span>
                    </div>
                </nav>
            </div>

            <!-- Active Company Footer -->
            <div class="pt-4 border-t border-slate-800/80 px-2 flex items-center justify-between text-[11px] text-slate-400">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>بازرگانی پارس (۱۴۰۵)</span>
                </div>
                <span class="font-mono text-cyan-400">v2.8.0</span>
            </div>
        </aside>

        <!-- Dynamic Page Canvas Area -->
        <div class="flex-1 p-6 md:p-8 overflow-y-auto space-y-6"
             :class="{
                'bg-[#060911] text-slate-100': currentTheme === 'flow',
                'bg-[#0D1117] text-slate-200': currentTheme === 'linear',
                'bg-gradient-to-br from-[#0B0F1E] via-[#080B16] to-[#0D1124] text-slate-100': currentTheme === 'stripe'
             }">

            <!-- VIEW 1: EXECUTIVE & RETAIL DASHBOARD -->
            <div x-show="currentPage === 'dashboard'" class="space-y-6">

                <!-- 1. Google Flow Conversational Accounting AI Bar -->
                <div class="p-4 rounded-3xl border transition duration-300 relative overflow-hidden"
                     :class="{
                        'bg-gradient-to-r from-blue-950/40 via-cyan-950/30 to-slate-900 border-cyan-500/40 shadow-xl shadow-cyan-500/5': currentTheme === 'flow',
                        'bg-slate-900/90 border-slate-800': currentTheme === 'linear',
                        'glass-panel border-white/10 shadow-2xl': currentTheme === 'stripe'
                     }">
                    <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                        <div class="flex items-center gap-3 w-full md:w-auto">
                            <div class="w-10 h-10 rounded-2xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center text-lg shrink-0">
                                ✨
                            </div>
                            <div>
                                <div class="font-bold text-sm text-white flex items-center gap-2">
                                    <span>دستیار هوشمند مالی و صوتی نپتون</span>
                                    <span class="badge badge-xs badge-success text-[10px]">آنلاین</span>
                                </div>
                                <div class="text-xs text-slate-400 mt-0.5">ثبت آنی اسناد با صحبت محاوره‌ای: «۲۰۰ تومن دادم علی»، «وضعیت چک‌های هفته بعد»</div>
                            </div>
                        </div>

                        <div class="w-full md:w-96 flex items-center gap-2 bg-slate-950/80 border border-slate-800 rounded-2xl px-3 py-2">
                            <input type="text" placeholder="دستور مالی خود را تایپ یا بیان کنید..." class="bg-transparent text-xs text-white focus:outline-none flex-1" />
                            <button class="btn btn-xs btn-primary rounded-xl font-bold">ثبت هوشمند</button>
                        </div>
                    </div>
                </div>

                <!-- 2. Four Key Financial Metric Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

                    <!-- Metric 1: Today's Sales -->
                    <div class="p-5 rounded-3xl border transition duration-300"
                         :class="{
                            'bg-slate-900/90 border-slate-800/90 hover:border-cyan-500/50 hover:shadow-lg hover:shadow-cyan-500/10': currentTheme === 'flow',
                            'bg-[#161B22] border-slate-800': currentTheme === 'linear',
                            'glass-panel border-white/10': currentTheme === 'stripe'
                         }">
                        <div class="flex items-center justify-between text-xs text-slate-400">
                            <span>فروش خالص امروز</span>
                            <span class="badge badge-xs badge-success text-white font-bold">+۱۸.۴٪</span>
                        </div>
                        <div class="text-2xl font-black text-white mt-3 font-mono">
                            ۴۸,۶۵۰,۰۰۰ <span class="text-xs font-normal text-slate-400 font-sans">تومان</span>
                        </div>
                        <div class="text-[11px] text-slate-500 mt-2 flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                            <span>۳۴ فاکتور صادر و تاییدشده</span>
                        </div>
                    </div>

                    <!-- Metric 2: Available Cash & Bank Balance -->
                    <div class="p-5 rounded-3xl border transition duration-300"
                         :class="{
                            'bg-slate-900/90 border-slate-800/90 hover:border-emerald-500/50 hover:shadow-lg hover:shadow-emerald-500/10': currentTheme === 'flow',
                            'bg-[#161B22] border-slate-800': currentTheme === 'linear',
                            'glass-panel border-white/10': currentTheme === 'stripe'
                         }">
                        <div class="flex items-center justify-between text-xs text-slate-400">
                            <span>موجودی نقد و بانک‌ها</span>
                            <span class="badge badge-xs badge-info text-white font-bold">تراز مثبت</span>
                        </div>
                        <div class="text-2xl font-black text-white mt-3 font-mono">
                            ۱۴۲,۹۰۰,۰۰۰ <span class="text-xs font-normal text-slate-400 font-sans">تومان</span>
                        </div>
                        <div class="text-[11px] text-slate-500 mt-2 flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-cyan-400"></span>
                            <span>متصل به ۴ حساب بانکی فعال</span>
                        </div>
                    </div>

                    <!-- Metric 3: Upcoming Cheques Risk -->
                    <div class="p-5 rounded-3xl border transition duration-300"
                         :class="{
                            'bg-slate-900/90 border-slate-800/90 hover:border-amber-500/50 hover:shadow-lg hover:shadow-amber-500/10': currentTheme === 'flow',
                            'bg-[#161B22] border-slate-800': currentTheme === 'linear',
                            'glass-panel border-white/10': currentTheme === 'stripe'
                         }">
                        <div class="flex items-center justify-between text-xs text-slate-400">
                            <span>چک‌های پرداختی این هفته</span>
                            <span class="badge badge-xs badge-warning font-bold">۲ روز مانده</span>
                        </div>
                        <div class="text-2xl font-black text-white mt-3 font-mono">
                            ۲۵,۰۰۰,۰۰۰ <span class="text-xs font-normal text-slate-400 font-sans">تومان</span>
                        </div>
                        <div class="text-[11px] text-emerald-400 mt-2 flex items-center gap-1.5">
                            <span>✓</span>
                            <span>پوشش کامل توسط مانده جاری</span>
                        </div>
                    </div>

                    <!-- Metric 4: Moadian Tax Queue Status -->
                    <div class="p-5 rounded-3xl border transition duration-300"
                         :class="{
                            'bg-slate-900/90 border-slate-800/90 hover:border-violet-500/50 hover:shadow-lg hover:shadow-violet-500/10': currentTheme === 'flow',
                            'bg-[#161B22] border-slate-800': currentTheme === 'linear',
                            'glass-panel border-white/10': currentTheme === 'stripe'
                         }">
                        <div class="flex items-center justify-between text-xs text-slate-400">
                            <span>ارسال به کارپوشه مودیان</span>
                            <span class="badge badge-xs badge-primary text-white font-bold">۱۰۰٪ معتبر</span>
                        </div>
                        <div class="text-2xl font-black text-white mt-3 font-mono">
                            ۰ <span class="text-xs font-normal text-slate-400 font-sans">خطای معلق</span>
                        </div>
                        <div class="text-[11px] text-slate-500 mt-2 flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-violet-400"></span>
                            <span>توکن مالیاتی فعال و همگام</span>
                        </div>
                    </div>

                </div>

                <!-- 3. Mid Section: Cash Flow Chart Simulation + Peer Barter Exchange Card -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    <!-- Chart Simulation Area -->
                    <div class="lg:col-span-2 p-6 rounded-3xl border space-y-4"
                         :class="{
                            'bg-slate-900/80 border-slate-800/80': currentTheme === 'flow',
                            'bg-[#161B22] border-slate-800': currentTheme === 'linear',
                            'glass-panel border-white/10': currentTheme === 'stripe'
                         }">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-black text-white">روند جریان نقدینگی و سود ناخالص (هفته جاری)</h3>
                                <p class="text-xs text-slate-400">مقایسه فروش نقد، کارتخوان POS و چک‌های وصول‌شده</p>
                            </div>
                            <div class="flex items-center gap-2 text-xs">
                                <span class="flex items-center gap-1 text-cyan-400">● ورودی نقد</span>
                                <span class="flex items-center gap-1 text-indigo-400">● چک وصولی</span>
                            </div>
                        </div>

                        <!-- Graphical Mockup Vector Bars -->
                        <div class="h-44 flex items-end justify-between gap-3 pt-6 border-b border-slate-800/80 pb-2">
                            <div class="flex-1 flex flex-col items-center gap-1">
                                <div class="w-full bg-cyan-500/30 rounded-t-xl h-24 hover:bg-cyan-500/50 transition"></div>
                                <span class="text-[10px] text-slate-500">شنبه</span>
                            </div>
                            <div class="flex-1 flex flex-col items-center gap-1">
                                <div class="w-full bg-cyan-500/40 rounded-t-xl h-32 hover:bg-cyan-500/60 transition"></div>
                                <span class="text-[10px] text-slate-500">یکشنبه</span>
                            </div>
                            <div class="flex-1 flex flex-col items-center gap-1">
                                <div class="w-full bg-cyan-500/35 rounded-t-xl h-28 hover:bg-cyan-500/55 transition"></div>
                                <span class="text-[10px] text-slate-500">دوشنبه</span>
                            </div>
                            <div class="flex-1 flex flex-col items-center gap-1">
                                <div class="w-full bg-cyan-500/50 rounded-t-xl h-36 hover:bg-cyan-500/70 transition"></div>
                                <span class="text-[10px] text-slate-500">سه‌شنبه</span>
                            </div>
                            <div class="flex-1 flex flex-col items-center gap-1">
                                <div class="w-full bg-emerald-500/60 rounded-t-xl h-44 hover:bg-emerald-500/80 transition relative">
                                    <span class="absolute -top-6 left-1/2 -translate-x-1/2 text-[9px] font-bold text-emerald-400">قله فروش</span>
                                </div>
                                <span class="text-[10px] text-emerald-400 font-bold">امروز</span>
                            </div>
                        </div>
                    </div>

                    <!-- Peer Barter & Quick Trade Widget -->
                    <div class="p-6 rounded-3xl border space-y-4 flex flex-col justify-between"
                         :class="{
                            'bg-slate-900/80 border-slate-800/80': currentTheme === 'flow',
                            'bg-[#161B22] border-slate-800': currentTheme === 'linear',
                            'glass-panel border-white/10': currentTheme === 'stripe'
                         }">
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-black text-white flex items-center gap-1.5">
                                    <span>🤝</span> تهاتر و امانت با مغازه‌های همکار
                                </h3>
                                <span class="badge badge-xs badge-info">فعال</span>
                            </div>
                            <p class="text-xs text-slate-400 leading-relaxed">
                                آخرین تبادل اقلام: ۵ عدد گلس به موبایل علی تحویل داده شد و ۲ کاور دریافت شد.
                            </p>
                            <div class="bg-slate-950/70 p-3 rounded-2xl border border-slate-800/80 space-y-2 text-xs">
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-400">طلب کالایی شما:</span>
                                    <span class="text-emerald-400 font-bold font-mono">۳ عدد گلس (معادل ۱۲۰,۰۰۰ ت)</span>
                                </div>
                                <div class="flex items-center justify-between text-[11px] text-slate-500">
                                    <span>همکار طرف حساب:</span>
                                    <span class="text-slate-300">علی حسینی (فروشگاه مجاور)</span>
                                </div>
                            </div>
                        </div>

                        <button class="btn btn-sm btn-outline btn-info w-full rounded-xl text-xs font-bold mt-2">
                            تسویه ریالی یا بازگرداندن کالا
                        </button>
                    </div>

                </div>

            </div>

            <!-- VIEW 2: SMART INVOICE & POS VIEW -->
            <div x-show="currentPage === 'invoice'" class="space-y-6">
                <div class="flex items-center justify-between border-b border-slate-800 pb-4">
                    <div>
                        <h2 class="text-lg font-black text-white">صدور فاکتور فروش سریع و متصل به کارتخوان (POS)</h2>
                        <p class="text-xs text-slate-400 mt-0.5">ثبت بارکدی کالا، اعمال خودکار تخفیف و ارسال به کارپوشه سامانه مودیان</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="badge badge-sm badge-success text-white">شماره فاکتور: ۱۴۰۵-۰۸۲</span>
                        <button class="btn btn-xs btn-primary rounded-xl">پیش‌فاکتور جدید</button>
                    </div>
                </div>

                <!-- Invoice Header Bar -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 p-4 rounded-3xl border bg-slate-900/60 border-slate-800">
                    <div>
                        <label class="text-[11px] text-slate-400 block mb-1">مشتری / طرف حساب:</label>
                        <div class="flex items-center gap-2 bg-slate-950 p-2.5 rounded-2xl border border-slate-800 text-xs">
                            <span>👤</span>
                            <span class="font-bold text-white flex-1">رضا محمدی (مشتری وفادار)</span>
                            <span class="badge badge-xs badge-accent">کیف‌پول: ۵۰,۰۰۰ ت</span>
                        </div>
                    </div>
                    <div>
                        <label class="text-[11px] text-slate-400 block mb-1">انبار تحویل کالا:</label>
                        <div class="bg-slate-950 p-2.5 rounded-2xl border border-slate-800 text-xs text-white">
                            انبار مرکزی تهران (خیابان جمهوری)
                        </div>
                    </div>
                    <div>
                        <label class="text-[11px] text-slate-400 block mb-1">روش تسویه پیشنهادی:</label>
                        <div class="bg-slate-950 p-2.5 rounded-2xl border border-slate-800 text-xs text-cyan-400 font-bold flex items-center justify-between">
                            <span>کارتخوان مستقیم PC-POS</span>
                            <span class="badge badge-xs badge-success">آنلاین</span>
                        </div>
                    </div>
                </div>

                <!-- Invoice Line Items Table Simulation -->
                <div class="rounded-3xl border border-slate-800 overflow-hidden bg-slate-900/40">
                    <table class="table table-xs w-full text-slate-200">
                        <thead class="bg-slate-950/80 text-slate-400 text-xs border-b border-slate-800">
                            <tr>
                                <th>#</th>
                                <th>نام کالا / بارکد</th>
                                <th>تعداد</th>
                                <th>قیمت واحد (تومان)</th>
                                <th>تخفیف</th>
                                <th>مالیات بر ارزش افزوده (۱۰٪)</th>
                                <th>مبلغ کل</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60 text-xs">
                            <tr>
                                <td>۱</td>
                                <td class="font-bold text-white">گلس سرامیکی آیفون ۱۵ پرو</td>
                                <td>۳ عدد</td>
                                <td class="font-mono">۱۵۰,۰۰۰</td>
                                <td class="font-mono text-amber-400">۱۰,۰۰۰</td>
                                <td class="font-mono">۴۴,۰۰۰</td>
                                <td class="font-mono font-bold text-cyan-400">۴۸۴,۰۰۰</td>
                            </tr>
                            <tr>
                                <td>۲</td>
                                <td class="font-bold text-white">قاب ژله‌ای ضدضربه سامسونگ S24</td>
                                <td>۲ عدد</td>
                                <td class="font-mono">۲۲۰,۰۰۰</td>
                                <td class="font-mono text-amber-400">۰</td>
                                <td class="font-mono">۴۴,۰۰۰</td>
                                <td class="font-mono font-bold text-cyan-400">۴۸۴,۰۰۰</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Checkout Footer & PC-POS Trigger -->
                <div class="flex flex-col md:flex-row items-center justify-between gap-4 p-5 rounded-3xl border bg-slate-900/80 border-slate-800">
                    <div class="flex items-center gap-6">
                        <div>
                            <div class="text-xs text-slate-400">مبلغ نهایی قابل پرداخت:</div>
                            <div class="text-2xl font-black text-white font-mono mt-0.5">۹۶۸,۰۰۰ <span class="text-xs font-normal text-slate-400 font-sans">تومان</span></div>
                        </div>
                        <div class="text-xs text-slate-500">سود شما از این فاکتور: <span class="text-emerald-400 font-bold font-mono">۲۸۰,۰۰۰ تومان</span></div>
                    </div>

                    <div class="flex items-center gap-3 w-full md:w-auto">
                        <button class="btn btn-outline btn-sm rounded-xl text-xs">ثبت به عنوان پیش‌فاکتور</button>
                        <button class="btn btn-primary btn-sm rounded-xl text-xs font-bold shadow-lg shadow-primary/30 flex items-center gap-2">
                            <span>💳</span> ارسال مبلغ به دستگاه پوز و چاپ فاکتور
                        </button>
                    </div>
                </div>
            </div>

            <!-- VIEW 3: FINANCIAL LEDGER & TRIAL BALANCE -->
            <div x-show="currentPage === 'ledger'" class="space-y-6">
                <div class="flex items-center justify-between border-b border-slate-800 pb-4">
                    <div>
                        <h2 class="text-lg font-black text-white">دفتر روزنامه، تراز آزمایشی و اسناد حسابداری دوبل</h2>
                        <p class="text-xs text-slate-400 mt-0.5">بررسی توازن دائمی بستانکار/بدهکار با انطباق کامل استانداردهای حسابداری ایران</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="badge badge-sm badge-outline text-emerald-400">سند شماره: ۴۰۲ (متوازن)</span>
                        <button class="btn btn-xs btn-primary rounded-xl">+ ثبت سند دستی</button>
                    </div>
                </div>

                <!-- Ledger Entry Card -->
                <div class="p-5 rounded-3xl border bg-slate-900/60 border-slate-800 space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                        <div class="text-xs text-slate-400">
                            سند اتوماتیک تسویه فاکتور فروش #۱۴۰۵-۰۸۲ • تاریخ: <span class="text-white font-mono">۱۴۰۵/۰۲/۰۴</span>
                        </div>
                        <span class="badge badge-xs badge-success text-white">تایید قطعی</span>
                    </div>

                    <table class="table table-xs w-full text-xs">
                        <thead class="text-slate-400">
                            <tr>
                                <th>کد معین</th>
                                <th>شرح ردیف سند</th>
                                <th class="text-left">بدهکار (تومان)</th>
                                <th class="text-left">بستانکار (تومان)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60 font-mono">
                            <tr>
                                <td class="text-cyan-400">۱۰۱۰۲</td>
                                <td class="text-white font-sans">موجودی نزد بانک سامان (دریافت از طریق کارتخوان)</td>
                                <td class="text-left font-bold text-emerald-400">۹۶۸,۰۰۰</td>
                                <td class="text-left text-slate-600">-</td>
                            </tr>
                            <tr>
                                <td class="text-cyan-400">۷۰۱۰۱</td>
                                <td class="text-white font-sans">درآمد حاصل از فروش کالا و خدمات</td>
                                <td class="text-left text-slate-600">-</td>
                                <td class="text-left font-bold text-cyan-400">۸۸۰,۰۰۰</td>
                            </tr>
                            <tr>
                                <td class="text-cyan-400">۲۲۱۰۵</td>
                                <td class="text-white font-sans">مالیات بر ارزش افزوده پرداختنی (مودیان)</td>
                                <td class="text-left text-slate-600">-</td>
                                <td class="text-left font-bold text-cyan-400">۸۸,۰۰۰</td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t border-slate-700 font-mono font-black text-xs text-white">
                            <tr>
                                <td colspan="2" class="font-sans">جمع اقلام تراز سند:</td>
                                <td class="text-left text-emerald-400">۹۶۸,۰۰۰</td>
                                <td class="text-left text-cyan-400">۹۶۸,۰۰۰</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Trial Balance Summary -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="p-4 rounded-2xl bg-slate-900/60 border border-slate-800 flex items-center justify-between">
                        <span class="text-xs text-slate-400">مجموع گردش بدهکار دوره:</span>
                        <span class="font-mono font-bold text-emerald-400 text-sm">۲,۴۸۵,۰۰۰,۰۰۰ ت</span>
                    </div>
                    <div class="p-4 rounded-2xl bg-slate-900/60 border border-slate-800 flex items-center justify-between">
                        <span class="text-xs text-slate-400">مجموع گردش بستانکار دوره:</span>
                        <span class="font-mono font-bold text-cyan-400 text-sm">۲,۴۸۵,۰۰۰,۰۰۰ ت</span>
                    </div>
                    <div class="p-4 rounded-2xl bg-slate-900/60 border border-slate-800 flex items-center justify-between">
                        <span class="text-xs text-slate-400">اختلاف ترازنامه:</span>
                        <span class="font-mono font-black text-emerald-400 text-sm">۰ (کاملاً متوازن)</span>
                    </div>
                </div>
            </div>

        </div>
    </main>

</body>
</html>