<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعرفه‌ها و پکیج‌های لایسنس ماژولار | نرم‌افزار حسابداری و مالی نپتون</title>
    <link rel="icon" type="image/svg+xml" href="/images/neptune-logo.svg">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: Vazirmatn, system-ui, -apple-system, "Segoe UI", Roboto, Tahoma, sans-serif !important; }
    </style>
</head>
<body class="bg-base-200/50 text-base-content min-h-screen">

    <!-- Top Navigation Header -->
    <header class="bg-base-100 border-b border-base-300 sticky top-0 z-50 shadow-sm backdrop-blur-md bg-base-100/90">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-[#090D16] p-1.5 shadow-lg shadow-cyan-500/25 border border-cyan-500/30 flex items-center justify-center shrink-0">
                    <img src="/images/neptune-logo.svg" alt="Neptune ERP" class="w-full h-full object-contain">
                </div>
                <div>
                    <span class="text-xl font-black tracking-tight text-base-content flex items-center gap-1.5">
                        <span>نپتون</span>
                        <span class="badge badge-accent badge-xs py-0.5 px-1.5 font-mono text-[10px]">ERP v2.5</span>
                    </span>
                    <span class="text-[11px] text-base-content/60 block font-medium">سامانه یکپارچه مالی، حسابداری و مدیریت اصناف</span>
                </div>
            </div>

            <nav class="hidden md:flex items-center gap-6 text-xs font-bold text-base-content/80">
                <a href="{{ route('landing') }}" class="hover:text-primary transition flex items-center gap-1">
                    <span>🏠</span> <span>{{ __('معرفی محصول') }}</span>
                </a>
                <a href="#plans" class="hover:text-primary transition">{{ __('پکیج‌های تجاری') }}</a>
                <a href="#matrix" class="hover:text-primary transition">{{ __('جدول مقایسه امکانات') }}</a>
                <a href="#industries" class="hover:text-primary transition">{{ __('۱۶ صنف تخصصی') }}</a>
                <a href="#calculator" class="hover:text-primary transition">{{ __('محاسبه‌گر لایسنس') }}</a>
                <a href="#modules" class="hover:text-primary transition">{{ __('ماژول‌های سیستم') }}</a>
                <a href="#faq" class="hover:text-primary transition">{{ __('پرسش‌های متداول') }}</a>
            </nav>

            <div class="flex items-center gap-2">
                <a href="{{ route('login') }}" class="btn btn-primary btn-sm rounded-xl px-4 font-bold shadow-md shadow-primary/25 text-xs flex items-center gap-1.5 hover:scale-105 active:scale-95 transition-all">
                    <svg class="w-4 h-4 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path></svg>
                    <span>{{ __('بازگشت به ورود') }}</span>
                </a>
                @auth
                    <a href="{{ route('home') }}" class="btn btn-outline btn-primary btn-sm rounded-xl px-4 font-bold text-xs">
                        <span>{{ __('پیشخوان') }}</span>
                        <span>←</span>
                    </a>
                @else
                    <a href="#plans" class="btn btn-accent btn-sm rounded-xl px-4 font-bold text-xs hidden sm:inline-flex">
                        {{ __('خرید اشتراک') }}
                    </a>
                @endauth
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-24">

        <!-- Hero Section -->
        <section class="text-center max-w-3xl mx-auto space-y-6 pt-4">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-sky-500/10 border border-sky-500/25 text-sky-700 text-xs font-bold">
                <span class="w-2 h-2 rounded-full bg-sky-500 animate-ping"></span>
                <span>نسل نوین حسابداری ایران؛ ماژولار، ۱۰۰٪ آفلاین و هوشمند</span>
            </div>

            <h1 class="text-3xl sm:text-5xl font-black text-base-content tracking-tight leading-tight">
                فقط هزینه امکاناتی را بپردازید که <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-600 to-teal-500">واقعاً نیاز دارید!</span>
            </h1>

            <p class="text-sm sm:text-base text-base-content/70 leading-relaxed max-w-2xl mx-auto">
                با معماری لایسنسینگ ماژولار «نپتون»، می‌توانید پکیج متناسب با صنف و ابعاد کسب‌وکارتان را انتخاب کرده و هر زمان که رشد کردید، تنها با وارد کردن یک کلید فعال‌سازی بدون نیاز به نصب مجدد، ظرفیت سیستم را ارتقا دهید.
            </p>

            <div class="flex flex-wrap justify-center gap-3 pt-2">
                <a href="#plans" class="btn btn-primary rounded-2xl px-6 font-bold shadow-lg shadow-primary/25 text-sm">
                    <span>مشاهده ۵ پکیج تجاری</span>
                    <span>⚡</span>
                </a>
                <a href="#calculator" class="btn btn-outline rounded-2xl px-6 text-sm font-bold">
                    <span>محاسبه‌گر آنلاین لایسنس</span>
                </a>
            </div>
        </section>

        <!-- 5 Commercial Plans Grid -->
        <section id="plans" class="space-y-8 scroll-mt-28">
            <div class="text-center space-y-2">
                <span class="badge badge-primary badge-sm font-bold">پلن‌های رسمی نپتون</span>
                <h2 class="text-2xl sm:text-4xl font-black text-base-content">پکیج‌های استاندارد متناسب با هر نوع کسب‌وکار</h2>
                <p class="text-xs sm:text-sm text-base-content/60">از فروشگاه‌ها و اصناف خرد تا شرکت‌های بزرگ بازرگانی، پخش مویرگی، کارخانجات و هلدینگ‌ها</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-5 items-stretch">
                <!-- 1. Retail / Shop Plan (Bronze) -->
                <div class="bg-base-100 border border-base-300 rounded-3xl p-6 shadow-sm hover:border-primary/40 transition flex flex-col justify-between relative">
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="badge badge-ghost font-bold text-[11px]">اصناف و فروشگاهی</span>
                            <span class="text-xl">🥉</span>
                        </div>
                        <div>
                            <h3 class="text-lg font-black text-base-content">نسخه برنزی</h3>
                            <div class="text-xs text-primary font-bold mt-0.5">فروشگاهی و اصناف خرد</div>
                            <p class="text-[11px] text-base-content/60 mt-1 leading-relaxed">ویژه مغازه‌ها، بوتیک‌ها، خرده‌فروشی و اصناف خرد</p>
                        </div>
                        <div class="flex items-baseline gap-1 py-3 border-y border-base-200">
                            <span class="text-2xl font-black text-base-content font-mono">۴,۸۰۰,۰۰۰</span>
                            <span class="text-[10px] text-base-content/60">تومان/سالانه</span>
                        </div>
                        <ul class="space-y-2 text-xs text-base-content/80">
                            <li class="flex items-center gap-1.5"><span class="text-success font-bold">✓</span> ۱ کاربر محلی LAN</li>
                            <li class="flex items-center gap-1.5"><span class="text-success font-bold">✓</span> ۱ شرکت / سال مالی</li>
                            <li class="flex items-center gap-1.5 font-bold text-primary"><span class="text-success font-bold">✓</span> ۱ صنف تخصصی دلخواه</li>
                            <li class="flex items-center gap-1.5"><span class="text-success font-bold">✓</span> سامانه مودیان (رایگان)</li>
                            <li class="flex items-center gap-1.5"><span class="text-success font-bold">✓</span> صندوق فروشگاهی POS</li>
                            <li class="flex items-center gap-1.5"><span class="text-success font-bold">✓</span> لاگ‌بوک اموال و تعمیرات (TCO)</li>
                            <li class="flex items-center gap-1.5"><span class="text-success font-bold">✓</span> مهاجرت هوشمند از هلو/سپیدار</li>
                        </ul>
                    </div>
                    <div class="pt-6">
                        <a href="tel:02188884422" class="btn btn-outline btn-block rounded-xl font-bold text-xs">سفارش نسخه فروشگاهی</a>
                    </div>
                </div>

                <!-- 2. Commercial Plan (Silver) -->
                <div class="bg-gradient-to-b from-base-100 to-primary/5 border-2 border-primary rounded-3xl p-6 shadow-xl shadow-primary/10 flex flex-col justify-between relative scale-105 z-10">
                    <span class="badge badge-primary badge-xs font-bold absolute -top-3 left-1/2 -translate-x-1/2 shadow-md">
                        پرفروش‌ترین پکیج
                    </span>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="badge badge-primary badge-outline font-bold text-[11px]">بازرگانی و شرکتی</span>
                            <span class="text-xl">🥈</span>
                        </div>
                        <div>
                            <h3 class="text-lg font-black text-base-content">نسخه نقره‌ای</h3>
                            <div class="text-xs text-primary font-bold mt-0.5">بازرگانی و خدمات</div>
                            <p class="text-[11px] text-base-content/60 mt-1 leading-relaxed">ویژه دفاتر بازرگانی، واردات/صادرات و عمده‌فروشان</p>
                        </div>
                        <div class="flex items-baseline gap-1 py-3 border-y border-base-200">
                            <span class="text-2xl font-black text-primary font-mono">۱۱,۵۰۰,۰۰۰</span>
                            <span class="text-[10px] text-base-content/60">تومان/سالانه</span>
                        </div>
                        <ul class="space-y-2 text-xs text-base-content/90 font-medium">
                            <li class="flex items-center gap-1.5"><span class="text-success font-bold">✓</span> ۳ کاربر محلی LAN</li>
                            <li class="flex items-center gap-1.5"><span class="text-success font-bold">✓</span> ۳ شرکت / سال مالی</li>
                            <li class="flex items-center gap-1.5 font-bold text-primary"><span class="text-success font-bold">✓</span> ۲ صنف تخصصی فعال</li>
                            <li class="flex items-center gap-1.5"><span class="text-success font-bold">✓</span> حسابداری چندارزی و تسعیر ارز</li>
                            <li class="flex items-center gap-1.5"><span class="text-success font-bold">✓</span> مراکز هزینه و پروژه</li>
                            <li class="flex items-center gap-1.5"><span class="text-success font-bold">✓</span> پیش‌فاکتور و رزرو کالا</li>
                            <li class="flex items-center gap-1.5"><span class="text-success font-bold">✓</span> مغایرت‌گیری بانکی هوشمند</li>
                            <li class="flex items-center gap-1.5"><span class="text-success font-bold">✓</span> حقوق و دستمزد تا ۵ پرسنل</li>
                        </ul>
                    </div>
                    <div class="pt-6">
                        <a href="tel:02188884422" class="btn btn-primary btn-block rounded-xl font-bold text-xs shadow-md shadow-primary/20">سفارش نسخه بازرگانی</a>
                    </div>
                </div>

                <!-- 3. Distribution Plan -->
                <div class="bg-base-100 border border-base-300 rounded-3xl p-6 shadow-sm hover:border-secondary/40 transition flex flex-col justify-between relative">
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="badge badge-secondary badge-outline font-bold text-[11px]">پخش و توزیع</span>
                            <span class="text-xl">🚚</span>
                        </div>
                        <div>
                            <h3 class="text-lg font-black text-base-content">نسخه مویرگی</h3>
                            <div class="text-xs text-secondary font-bold mt-0.5">پخش و ویزیتوری</div>
                            <p class="text-[11px] text-base-content/60 mt-1 leading-relaxed">ویژه شرکت‌های توزیع، فروش گرم و سرد و بنکداری</p>
                        </div>
                        <div class="flex items-baseline gap-1 py-3 border-y border-base-200">
                            <span class="text-2xl font-black text-base-content font-mono">۱۸,۹۰۰,۰۰۰</span>
                            <span class="text-[10px] text-base-content/60">تومان/سالانه</span>
                        </div>
                        <ul class="space-y-2 text-xs text-base-content/80">
                            <li class="flex items-center gap-1.5"><span class="text-success font-bold">✓</span> ۵ کاربر LAN + تبلت ویزیتورها</li>
                            <li class="flex items-center gap-1.5"><span class="text-success font-bold">✓</span> ۵ شرکت / سال مالی</li>
                            <li class="flex items-center gap-1.5 font-bold text-secondary"><span class="text-success font-bold">✓</span> ۱۶ صنف کامل و باز</li>
                            <li class="flex items-center gap-1.5 font-bold text-secondary"><span class="text-success font-bold">✓</span> هسته تخصصی پخش مویرگی</li>
                            <li class="flex items-center gap-1.5"><span class="text-success font-bold">✓</span> انبار ماشین (وان) و مسیربندی</li>
                            <li class="flex items-center gap-1.5"><span class="text-success font-bold">✓</span> موتور پورسانت پلکانی بازاریاب‌ها</li>
                            <li class="flex items-center gap-1.5"><span class="text-success font-bold">✓</span> همگام‌سازی نامحدود شعب و تبلت‌ها</li>
                            <li class="flex items-center gap-1.5"><span class="text-success font-bold">✓</span> حقوق و دستمزد تا ۲۰ پرسنل</li>
                        </ul>
                    </div>
                    <div class="pt-6">
                        <a href="tel:02188884422" class="btn btn-outline btn-block rounded-xl font-bold text-xs">سفارش نسخه مویرگی</a>
                    </div>
                </div>

                <!-- 4. Manufacturing Plan -->
                <div class="bg-base-100 border border-base-300 rounded-3xl p-6 shadow-sm hover:border-warning/40 transition flex flex-col justify-between relative">
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="badge badge-warning badge-outline font-bold text-[11px]">تولید و کارخانجات</span>
                            <span class="text-xl">⚙️</span>
                        </div>
                        <div>
                            <h3 class="text-lg font-black text-base-content">نسخه تولیدی</h3>
                            <div class="text-xs text-amber-600 font-bold mt-0.5">فرمول ساخت و بهای تمام‌شده</div>
                            <p class="text-[11px] text-base-content/60 mt-1 leading-relaxed">ویژه کارگاه‌ها، صنایع تبدیلی و کارخانه‌های تولیدی</p>
                        </div>
                        <div class="flex items-baseline gap-1 py-3 border-y border-base-200">
                            <span class="text-2xl font-black text-base-content font-mono">۲۱,۵۰۰,۰۰۰</span>
                            <span class="text-[10px] text-base-content/60">تومان/سالانه</span>
                        </div>
                        <ul class="space-y-2 text-xs text-base-content/80">
                            <li class="flex items-center gap-1.5"><span class="text-success font-bold">✓</span> ۵ کاربر محلی LAN</li>
                            <li class="flex items-center gap-1.5"><span class="text-success font-bold">✓</span> ۵ شرکت / سال مالی</li>
                            <li class="flex items-center gap-1.5 font-bold text-amber-600"><span class="text-success font-bold">✓</span> ۱۶ صنف کامل و باز</li>
                            <li class="flex items-center gap-1.5 font-bold text-amber-600"><span class="text-success font-bold">✓</span> فرمول ساخت (BOM) و سفارش تولید</li>
                            <li class="flex items-center gap-1.5"><span class="text-success font-bold">✓</span> بهای تمام‌شده محصول و سربار مستقیم</li>
                            <li class="flex items-center gap-1.5"><span class="text-success font-bold">✓</span> انبار مواد اولیه و کالای در جریان</li>
                            <li class="flex items-center gap-1.5"><span class="text-success font-bold">✓</span> مراکز هزینه خطوط تولید</li>
                            <li class="flex items-center gap-1.5"><span class="text-success font-bold">✓</span> حقوق و دستمزد پرسنل شیفت و کارگاهی</li>
                        </ul>
                    </div>
                    <div class="pt-6">
                        <a href="tel:02188884422" class="btn btn-outline btn-block rounded-xl font-bold text-xs">سفارش نسخه تولیدی</a>
                    </div>
                </div>

                <!-- 5. Enterprise Plan (Gold) -->
                <div class="bg-base-100 border border-base-300 rounded-3xl p-6 shadow-sm hover:border-accent/40 transition flex flex-col justify-between relative">
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="badge badge-accent badge-outline font-bold text-[11px]">هلدینگ‌ها و سازمان‌ها</span>
                            <span class="text-xl">🥇</span>
                        </div>
                        <div>
                            <h3 class="text-lg font-black text-base-content">نسخه طلایی</h3>
                            <div class="text-xs text-accent font-bold mt-0.5">جامع سازمانی و هلدینگ</div>
                            <p class="text-[11px] text-base-content/60 mt-1 leading-relaxed">دسترسی نامحدود به تمامی ماژول‌ها، شعبات و هوش مصنوعی</p>
                        </div>
                        <div class="flex items-baseline gap-1 py-3 border-y border-base-200">
                            <span class="text-2xl font-black text-base-content font-mono">۳۴,۰۰۰,۰۰۰</span>
                            <span class="text-[10px] text-base-content/60">تومان/سالانه</span>
                        </div>
                        <ul class="space-y-2 text-xs text-base-content/80">
                            <li class="flex items-center gap-1.5 font-bold text-accent"><span class="text-success font-bold">✓</span> کاربران محلی LAN نامحدود</li>
                            <li class="flex items-center gap-1.5 font-bold text-accent"><span class="text-success font-bold">✓</span> شرکت‌ها و سال‌های مالی نامحدود</li>
                            <li class="flex items-center gap-1.5 font-bold text-accent"><span class="text-success font-bold">✓</span> ۱۶ صنف کامل و باز</li>
                            <li class="flex items-center gap-1.5"><span class="text-success font-bold">✓</span> تمامی ماژول‌های سیستم (*) باز</li>
                            <li class="flex items-center gap-1.5"><span class="text-success font-bold">✓</span> پخش مویرگی + فرمول ساخت تولید</li>
                            <li class="flex items-center gap-1.5"><span class="text-success font-bold">✓</span> دستیار هوش مصنوعی و ورود فاکتور با OCR</li>
                            <li class="flex items-center gap-1.5"><span class="text-success font-bold">✓</span> تفکیک وظایف و کنترل داخلی (SoD)</li>
                            <li class="flex items-center gap-1.5"><span class="text-success font-bold">✓</span> پشتیبانی VIP و خدمات سفارشی‌سازی</li>
                        </ul>
                    </div>
                    <div class="pt-6">
                        <a href="tel:02188884422" class="btn btn-outline btn-block rounded-xl font-bold text-xs">سفارش نسخه سازمانی</a>
                    </div>
                </div>
            </div>
        </section>

        <!-- 16 Specialized Industry Profiles Showcase -->
        <section id="industries" class="space-y-8 scroll-mt-28">
            <div class="text-center space-y-2 max-w-2xl mx-auto">
                <span class="badge badge-accent badge-sm font-bold">مزیت رقابتی بی‌نظیر</span>
                <h2 class="text-2xl sm:text-3xl font-black text-base-content">۱۶ صنف تخصصی بازار ایران با زبان و عرف همان صنف</h2>
                <p class="text-xs text-base-content/60">نیازی به خرید نرم‌افزارهای جداگانه نیست! نپتون با یک کلیک به زبان صنف شما صحبت می‌کند.</p>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3">
                @php
                    $trades = [
                        ['emoji' => '💎', 'name' => 'طلا و جواهر', 'badge' => 'عیار، مظنه و سود'],
                        ['emoji' => '🚗', 'name' => 'لوازم یدکی', 'badge' => 'پارت‌نامبر و سود اتحادیه'],
                        ['emoji' => '🔧', 'name' => 'تعمیرگاه خودرو', 'badge' => 'اجرت و پذیرش ماشین'],
                        ['emoji' => '📱', 'name' => 'موبایل و تبلت', 'badge' => 'ردیابی IMEI و ۴٪ سود'],
                        ['emoji' => '🏗️', 'name' => 'آهن‌آلات و فولاد', 'badge' => 'جدول اشتال و باسکول'],
                        ['emoji' => '👗', 'name' => 'پوشاک و کفش', 'badge' => 'ماتریس سایز و رنگ'],
                        ['emoji' => '🧁', 'name' => 'قنادی و خشکبار', 'badge' => 'خالص‌فروشی و کسر جعبه'],
                        ['emoji' => '🛒', 'name' => 'سوپرمارکت', 'badge' => 'بارکد سریع و انقضا FEFO'],
                        ['emoji' => '☕', 'name' => 'رستوران و کافه', 'badge' => 'فیش آشپزخانه و Food Cost'],
                        ['emoji' => '📺', 'name' => 'لوازم خانگی', 'badge' => 'شماره سریال و گارانتی'],
                        ['emoji' => '💊', 'name' => 'دارو و سلامت', 'badge' => 'کد IRC و سامانه تی‌تک'],
                        ['emoji' => '🧶', 'name' => 'فرش و نساجی', 'badge' => 'متراژ، شانه و تراکم'],
                        ['emoji' => '🧱', 'name' => 'مصالح ساختمانی', 'badge' => 'پاکت، تن و کرایه حمل'],
                        ['emoji' => '📚', 'name' => 'کتاب و تحریر', 'badge' => 'شابک ISBN و تخفیف ناشر'],
                        ['emoji' => '📊', 'name' => 'هوش تجاری', 'badge' => 'دوره گردش و سلامت مالی'],
                        ['emoji' => '🏬', 'name' => 'بازرگانی عمومی', 'badge' => 'فروشگاه‌های زنجیره‌ای'],
                    ];
                @endphp

                @foreach ($trades as $trade)
                    <div class="bg-base-100 border border-base-200 hover:border-primary rounded-2xl p-3 text-center transition shadow-xs flex flex-col justify-between items-center gap-1.5 group">
                        <div class="text-2xl group-hover:scale-125 transition-transform">{{ $trade['emoji'] }}</div>
                        <div class="text-xs font-bold text-base-content mt-1">{{ $trade['name'] }}</div>
                        <div class="text-[9px] text-base-content/50 leading-tight">{{ $trade['badge'] }}</div>
                    </div>
                @endforeach
            </div>
        </section>

        <!-- Interactive Custom Configurator & Add-on Calculator -->
        <section id="calculator" class="bg-base-100 border border-base-300 rounded-3xl p-6 sm:p-10 shadow-sm scroll-mt-28" x-data="{
            basePlan: 'basic',
            basePrices: { basic: 4800000, professional: 11500000, distribution: 18900000, manufacturing: 21500000, enterprise: 34000000 },
            addonPrices: {
                pos_retail: 1500000,
                distribution: 4500000,
                production_bom: 5500000,
                hr_payroll: 2500000,
                sms_automation: 1200000,
                customer_loyalty: 1400000,
                telephony_voip: 1100000,
                official_inquiries: 800000,
                installment_financing: 1500000,
                ai_copilot: 3200000,
                migration_tools: 800000
            },
            selectedAddons: [],
            extraUsers: 0,
            userPrice: 750000,

            isAddonDisabled(key) {
                if (this.basePlan === 'enterprise') return true;
                if (this.basePlan === 'distribution' && key === 'distribution') return true;
                if (this.basePlan === 'manufacturing' && key === 'production_bom') return true;
                if (['professional', 'distribution', 'manufacturing'].includes(this.basePlan)) {
                    return ['pos_retail', 'customer_loyalty', 'sms_automation', 'hr_payroll', 'telephony_voip', 'official_inquiries', 'installment_financing', 'migration_tools'].includes(key);
                }
                return false;
            },

            calculateTotal() {
                if (this.basePlan === 'enterprise') return this.basePrices.enterprise + (this.extraUsers * this.userPrice);
                let sum = this.basePrices[this.basePlan] || 4800000;
                for (const key of this.selectedAddons) {
                    if (!this.isAddonDisabled(key) && this.addonPrices[key]) {
                        sum += this.addonPrices[key];
                    }
                }
                sum += (this.extraUsers * this.userPrice);
                return sum;
            }
        }">
            <div class="max-w-3xl mb-8">
                <span class="badge badge-accent badge-sm font-bold mb-2">محاسبه‌گر هوشمند</span>
                <h2 class="text-2xl sm:text-3xl font-black text-base-content">محاسبه آنلاین قیمت لایسنس و افزودنی‌ها</h2>
                <p class="text-xs text-base-content/60 mt-1">پکیج پایه را انتخاب کنید و ماژول‌ها یا کاربران دلخواهتان را اضافه کنید تا هزینه سالانه دقیق محاسبه شود.</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Selectors Left Column -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Base Plan Radio -->
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-base-content/80">۱. نگارش مد نظر خود را انتخاب کنید:</label>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                            <label class="flex items-center justify-between p-3 rounded-2xl border cursor-pointer transition text-xs"
                                   :class="basePlan === 'basic' ? 'border-primary bg-primary/5 font-bold' : 'border-base-200 bg-base-200/40'">
                                <div class="flex items-center gap-1.5">
                                    <input type="radio" x-model="basePlan" value="basic" class="radio radio-primary radio-xs" />
                                    <span>فروشگاهی (برنزی)</span>
                                </div>
                                <span class="font-mono text-[10px] text-primary">۴.۸ م</span>
                            </label>

                            <label class="flex items-center justify-between p-3 rounded-2xl border cursor-pointer transition text-xs"
                                   :class="basePlan === 'professional' ? 'border-primary bg-primary/5 font-bold' : 'border-base-200 bg-base-200/40'">
                                <div class="flex items-center gap-1.5">
                                    <input type="radio" x-model="basePlan" value="professional" class="radio radio-primary radio-xs" />
                                    <span>بازرگانی (نقره‌ای)</span>
                                </div>
                                <span class="font-mono text-[10px] text-primary">۱۱.۵ م</span>
                            </label>

                            <label class="flex items-center justify-between p-3 rounded-2xl border cursor-pointer transition text-xs"
                                   :class="basePlan === 'distribution' ? 'border-primary bg-primary/5 font-bold' : 'border-base-200 bg-base-200/40'">
                                <div class="flex items-center gap-1.5">
                                    <input type="radio" x-model="basePlan" value="distribution" class="radio radio-primary radio-xs" />
                                    <span>پخش مویرگی</span>
                                </div>
                                <span class="font-mono text-[10px] text-secondary">۱۸.۹ م</span>
                            </label>

                            <label class="flex items-center justify-between p-3 rounded-2xl border cursor-pointer transition text-xs"
                                   :class="basePlan === 'manufacturing' ? 'border-primary bg-primary/5 font-bold' : 'border-base-200 bg-base-200/40'">
                                <div class="flex items-center gap-1.5">
                                    <input type="radio" x-model="basePlan" value="manufacturing" class="radio radio-primary radio-xs" />
                                    <span>تولیدی و صنعتی</span>
                                </div>
                                <span class="font-mono text-[10px] text-amber-600">۲۱.۵ م</span>
                            </label>

                            <label class="flex items-center justify-between p-3 rounded-2xl border cursor-pointer transition text-xs sm:col-span-2"
                                   :class="basePlan === 'enterprise' ? 'border-primary bg-primary/5 font-bold' : 'border-base-200 bg-base-200/40'">
                                <div class="flex items-center gap-1.5">
                                    <input type="radio" x-model="basePlan" value="enterprise" class="radio radio-primary radio-xs" />
                                    <span>جامع سازمانی و هلدینگ (طلایی)</span>
                                </div>
                                <span class="font-mono text-[10px] text-accent">۳۴.۰ م (همه امکانات باز)</span>
                            </label>
                        </div>
                    </div>

                    <!-- Addon Modules Checkboxes -->
                    <div class="space-y-2" x-show="basePlan !== 'enterprise'">
                        <label class="text-xs font-bold text-base-content/80">۲. ماژول‌های افزونه مورد نیاز را تیک بزنید:</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <template x-for="(price, key) in addonPrices" :key="key">
                                <label class="flex items-center justify-between p-2.5 rounded-xl border border-base-200 bg-base-200/30 hover:bg-base-200/70 transition cursor-pointer text-xs"
                                       :class="isAddonDisabled(key) ? 'opacity-50 cursor-not-allowed bg-base-200/10' : ''">
                                    <div class="flex items-center gap-2">
                                        <input type="checkbox" :value="key" x-model="selectedAddons" :disabled="isAddonDisabled(key)" class="checkbox checkbox-primary checkbox-xs rounded" />
                                        <span x-text="key.replaceAll('_', ' ').toUpperCase()"></span>
                                        <span x-show="isAddonDisabled(key)" class="badge badge-xs text-[9px]">شامل در نگارش</span>
                                    </div>
                                    <span class="font-mono text-[11px] text-base-content/70" x-text="(price / 1000000) + ' م تومان'"></span>
                                </label>
                            </template>
                        </div>
                    </div>

                    <!-- Extra Users Slider -->
                    <div class="p-4 bg-base-200/40 rounded-2xl border border-base-200 flex items-center justify-between gap-4">
                        <div>
                            <div class="text-xs font-bold text-base-content">کاربران مازاد همزمان شبکه (LAN):</div>
                            <div class="text-[11px] text-base-content/60">هر کاربر اضافه: ۷۵۰,۰۰۰ تومان / سالانه</div>
                        </div>
                        <div class="flex items-center gap-3">
                            <button type="button" @click="extraUsers = Math.max(0, extraUsers - 1)" class="btn btn-sm btn-circle btn-ghost border border-base-300">-</button>
                            <span class="font-mono font-bold text-base tabular-nums" x-text="extraUsers"></span>
                            <button type="button" @click="extraUsers++" class="btn btn-sm btn-circle btn-ghost border border-base-300">+</button>
                        </div>
                    </div>
                </div>

                <!-- Live Price Calculation Card -->
                <div class="bg-gradient-to-br from-base-100 to-primary/10 border-2 border-primary/30 rounded-3xl p-6 flex flex-col justify-between shadow-lg">
                    <div class="space-y-4">
                        <div class="text-xs font-bold uppercase tracking-wider text-primary">پیش‌فاکتور آنلاین لایسنس</div>
                        <div>
                            <div class="text-xs text-base-content/60">مجموع سرمایه‌گذاری سالانه:</div>
                            <div class="text-3xl font-black text-primary font-mono mt-1" x-text="new Intl.NumberFormat('fa-IR').format(calculateTotal()) + ' تومان'"></div>
                        </div>

                        <div class="divider my-2"></div>

                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between">
                                <span class="text-base-content/60">پکیج پایه انتخابی:</span>
                                <span class="font-bold" x-text="basePlan === 'basic' ? 'فروشگاهی (برنزی)' : (basePlan === 'professional' ? 'بازرگانی (نقره‌ای)' : (basePlan === 'distribution' ? 'پخش مویرگی' : (basePlan === 'manufacturing' ? 'تولیدی' : 'سازمانی (طلایی)')))"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-base-content/60">ماژول‌های اضافه:</span>
                                <span class="font-mono font-bold" x-text="selectedAddons.length + ' ماژول'"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-base-content/60">کاربران مازاد:</span>
                                <span class="font-mono font-bold" x-text="extraUsers + ' کاربر'"></span>
                            </div>
                            <div class="flex justify-between text-success">
                                <span>پشتیبانی و آپدیت:</span>
                                <span class="font-bold">رایگان (۱ سال)</span>
                            </div>
                        </div>
                    </div>

                    <div class="pt-6 space-y-2">
                        <a href="tel:02188884422" class="btn btn-primary btn-block rounded-xl font-bold shadow-md shadow-primary/25 text-xs">
                            ثبت سفارش تلفنی و دریافت فوری کد
                        </a>
                        <div class="text-[10px] text-center text-base-content/50">فعال‌سازی آنی پس از پرداخت با امضای رمزنگاری‌شده</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 16 Modules Showcase -->
        <section id="modules" class="space-y-8 scroll-mt-28">
            <div class="text-center space-y-2 max-w-2xl mx-auto">
                <h2 class="text-2xl sm:text-3xl font-black text-base-content">۱۶ ماژول قدرتمند نپتون</h2>
                <p class="text-xs text-base-content/60">هر آنچه یک بنگاه اقتصادی مدرن از یک سیستم یکپارچه انتظار دارد</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach ($modules as $mod)
                    <div class="bg-base-100 border border-base-300 rounded-2xl p-5 hover:border-primary/50 transition space-y-3 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div class="w-10 h-10 rounded-xl bg-base-200/70 flex items-center justify-center text-xl">
                                {{ $mod['icon'] }}
                            </div>
                            <span class="badge badge-ghost badge-xs">{{ $mod['category'] }}</span>
                        </div>
                        <div>
                            <h4 class="font-bold text-sm text-base-content">{{ $mod['title'] }}</h4>
                            <p class="text-xs text-base-content/60 mt-1 leading-relaxed">{{ $mod['description'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <!-- FAQ Section -->
        <section id="faq" class="max-w-3xl mx-auto space-y-6 scroll-mt-28">
            <div class="text-center space-y-2 mb-8">
                <h2 class="text-2xl font-black text-base-content">پرسش‌های متداول مشتریان</h2>
                <p class="text-xs text-base-content/60">پاسخ به سوالات پرتکرار پیرامون لایسنس و فعال‌سازی</p>
            </div>

            <div class="space-y-3">
                <div class="collapse collapse-plus bg-base-100 border border-base-300 rounded-2xl">
                    <input type="radio" name="faq-accordion" checked="checked" />
                    <div class="collapse-title text-sm font-bold text-base-content">
                        آیا نرم‌افزار نپتون بدون اینترنت هم کار می‌کند؟
                    </div>
                    <div class="collapse-content text-xs text-base-content/70 leading-relaxed">
                        بله، ۱۰۰٪! هسته نرم‌افزار، حسابداری، انبارداری، صدور فاکتور و کارتخوان‌ها به صورت کاملاً آفلاین و محلی (Offline-First) اجرا می‌شوند و لایسنس شما نیز به صورت ریاضی و بدون نیاز به اینترنت اعتبارسنجی می‌گردد.
                    </div>
                </div>

                <div class="collapse collapse-plus bg-base-100 border border-base-300 rounded-2xl">
                    <input type="radio" name="faq-accordion" />
                    <div class="collapse-title text-sm font-bold text-base-content">
                        اگر لایسنس سالانه منقضی شود، آیا اطلاعات من پاک خواهد شد؟
                    </div>
                    <div class="collapse-content text-xs text-base-content/70 leading-relaxed">
                        خیر، به هیچ وجه! دیتابیس مالی شما همیشه در اختیارتان باقی می‌ماند. پس از پایان اعتبار، سیستم یک مهلت ۱۴ روزه کاری داده و سپس به حالت فقط-خواندنی (Read-Only) می‌رود تا بتوانید گزارشات و اسناد گذشته را مشاهده و چاپ کنید.
                    </div>
                </div>

                <div class="collapse collapse-plus bg-base-100 border border-base-300 rounded-2xl">
                    <input type="radio" name="faq-accordion" />
                    <div class="collapse-title text-sm font-bold text-base-content">
                        چگونه می‌توانم بعداً ماژول‌های جدید مثل سامانه مودیان یا اصناف دیگر را اضافه کنم؟
                    </div>
                    <div class="collapse-content text-xs text-base-content/70 leading-relaxed">
                        خیلی ساده؛ نیازی به نصب مجدد یا پاک کردن برنامه ندارید. کافیست کد فعال‌سازی افزونه را از واحد فروش دریافت کنید و در منوی مدیریت لایسنس وارد نمایید تا ماژول در همان لحظه برایتان باز شود.
                    </div>
                </div>
            </div>
        </section>

    </main>

    <!-- Footer -->
    <footer class="bg-base-100 border-t border-base-300 mt-20 py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row items-center justify-between gap-6 text-xs text-base-content/60">
            <div class="flex items-center gap-3">
                <span class="font-black text-sm text-base-content">نپتون (Neptune ERP)</span>
                <span>•</span>
                <span>توسعه‌یافته برای اصناف، شرکت‌های بازرگانی، پخش و کارخانجات ایران</span>
            </div>
            <div>
                <span>واحد فروش و پشتیبانی: <strong>۰۲۱-۸۸۸۸۴۴۲۲</strong></span>
            </div>
        </div>
    </footer>

</body>
</html>
