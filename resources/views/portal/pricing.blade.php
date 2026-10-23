<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="emerald">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعرفه‌ها و پکیج‌های لایسنس ماژولار | نرم‌افزار حسابداری و مالی دخل</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.23/dist/full.min.css" rel="stylesheet" type="text/css" />
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
    <style>
        body { font-family: system-ui, -apple-system, "Vazirmatn", "Segoe UI", Roboto, Tahoma, sans-serif; }
    </style>
</head>
<body class="bg-base-200/50 text-base-content min-h-screen">

    <!-- Top Navigation Header -->
    <header class="bg-base-100 border-b border-base-300 sticky top-0 z-50 shadow-sm backdrop-blur-md bg-base-100/90">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-emerald-600 to-teal-400 text-white flex items-center justify-center text-2xl font-black shadow-lg shadow-emerald-600/20">
                    د
                </div>
                <div>
                    <span class="text-xl font-black tracking-tight text-base-content flex items-center gap-1.5">
                        <span>دخل</span>
                        <span class="badge badge-accent badge-xs py-0.5 px-1.5 font-mono text-[10px]">ERP v2.5</span>
                    </span>
                    <span class="text-[11px] text-base-content/60 block font-medium">سامانه یکپارچه مالی، حسابداری و مدیریت اصناف</span>
                </div>
            </div>

            <nav class="hidden md:flex items-center gap-8 text-xs font-bold text-base-content/80">
                <a href="#plans" class="hover:text-primary transition">{{ __('پکیج‌های تجاری') }}</a>
                <a href="#calculator" class="hover:text-primary transition">{{ __('محاسبه‌گر لایسنس') }}</a>
                <a href="#modules" class="hover:text-primary transition">{{ __('۱۶ ماژول سیستم') }}</a>
                <a href="#faq" class="hover:text-primary transition">{{ __('پرسش‌های متداول') }}</a>
            </nav>

            <div class="flex items-center gap-2">
                @auth
                    <a href="{{ route('home') }}" class="btn btn-primary btn-sm rounded-xl px-5 font-bold shadow-md shadow-primary/20 text-xs">
                        <span>{{ __('ورود به پیشخوان دخل') }}</span>
                        <span>←</span>
                    </a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-outline btn-sm rounded-xl px-4 text-xs font-bold">
                        {{ __('ورود کاربران') }}
                    </a>
                    <a href="#plans" class="btn btn-primary btn-sm rounded-xl px-5 font-bold shadow-md shadow-primary/20 text-xs">
                        {{ __('خرید اشتراک') }}
                    </a>
                @endauth
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-20">

        <!-- Hero Section -->
        <section class="text-center max-w-3xl mx-auto space-y-5 pt-4">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 text-xs font-bold">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                <span>نسل نوین حسابداری ایران؛ ماژولار، ۱۰۰٪ آفلاین و هوشمند</span>
            </div>

            <h1 class="text-3xl sm:text-5xl font-black text-base-content tracking-tight leading-tight">
                فقط هزینه امکاناتی را بپردازید که <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-600 to-teal-500">واقعاً استفاده می‌کنید!</span>
            </h1>

            <p class="text-sm sm:text-base text-base-content/70 leading-relaxed max-w-2xl mx-auto">
                با معماری لایسنسینگ ماژولار «دخل»، می‌توانید پکیج پایه را تهیه کرده و هر زمان که کسب‌وکارتان رشد کرد، با وارد کردن یک کد فعال‌سازی، ماژول‌های جدید مثل سامانه مودیان، کارتخوان یا هوش مصنوعی را درجا اضافه کنید.
            </p>

            <div class="flex flex-wrap justify-center gap-3 pt-2">
                <a href="#calculator" class="btn btn-primary rounded-2xl px-6 font-bold shadow-lg shadow-primary/25 text-sm">
                    <span>محاسبه قیمت لایسنس سفارشی</span>
                    <span>⚡</span>
                </a>
                <a href="#modules" class="btn btn-ghost rounded-2xl px-6 text-sm">
                    <span>مشاهده تمام ۱۶ ماژول</span>
                    <span>↓</span>
                </a>
            </div>
        </section>

        <!-- Pricing Cards Section -->
        <section id="plans" class="space-y-8 scroll-mt-28">
            <div class="text-center space-y-2">
                <h2 class="text-2xl sm:text-3xl font-black text-base-content">پکیج‌های استاندارد دخل</h2>
                <p class="text-xs text-base-content/60">انتخاب پکیج متناسب با اندازه و نیازهای اصناف و شرکت‌ها</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Basic Bronze Plan -->
                <div class="bg-base-100 border border-base-300 rounded-3xl p-7 shadow-sm hover:border-primary/40 transition flex flex-col justify-between relative">
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="badge badge-ghost font-bold text-xs">اصناف خرد و فروشگاهی</span>
                            <span class="text-2xl">🥉</span>
                        </div>
                        <div>
                            <h3 class="text-xl font-black text-base-content">نسخه برنزی (پایه)</h3>
                            <p class="text-xs text-base-content/60 mt-1">جایگزین دیجیتال و مدرن دفاتر کاغذی و فاکتور سنتی</p>
                        </div>
                        <div class="flex items-baseline gap-1 py-3 border-y border-base-200">
                            <span class="text-3xl font-black text-base-content font-mono">۳,۹۰۰,۰۰۰</span>
                            <span class="text-xs text-base-content/60">تومان / سالانه</span>
                        </div>
                        <ul class="space-y-2.5 text-xs text-base-content/80">
                            <li class="flex items-center gap-2"><span class="text-success font-bold">✓</span> حسابداری پایه و دفاتر دوبل مالی</li>
                            <li class="flex items-center gap-2"><span class="text-success font-bold">✓</span> صدور فاکتور رسمی و غیررسمی خرید/فروش</li>
                            <li class="flex items-center gap-2"><span class="text-success font-bold">✓</span> انبارداری و کاردکس کالا</li>
                            <li class="flex items-center gap-2"><span class="text-success font-bold">✓</span> خزانه‌داری، دریافت/پرداخت و چک صیادی</li>
                            <li class="flex items-center gap-2 text-base-content/40"><span class="text-base-content/30">✕</span> سامانه مودیان و پایانه‌های فروشگاهی</li>
                            <li class="flex items-center gap-2 text-base-content/40"><span class="text-base-content/30">✕</span> صندوق فروشگاهی POS و کارتخوان</li>
                        </ul>
                    </div>
                    <div class="pt-6">
                        <a href="tel:02188884422" class="btn btn-outline btn-block rounded-xl font-bold text-xs">
                            سفارش نسخه برنزی
                        </a>
                    </div>
                </div>

                <!-- Professional Silver Plan (Popular) -->
                <div class="bg-gradient-to-b from-base-100 to-primary/5 border-2 border-primary rounded-3xl p-7 shadow-xl shadow-primary/10 flex flex-col justify-between relative scale-105 z-10">
                    <span class="badge badge-primary badge-sm font-bold absolute -top-3 left-1/2 -translate-x-1/2 shadow-md">
                        پرفروش‌ترین و محبوب‌ترین
                    </span>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="badge badge-primary badge-outline font-bold text-xs">رشد کسب‌وکار و بازرگانی</span>
                            <span class="text-2xl">🥈</span>
                        </div>
                        <div>
                            <h3 class="text-xl font-black text-base-content">نسخه نقره‌ای (حرفه‌ای)</h3>
                            <p class="text-xs text-base-content/60 mt-1">مجهز به سامانه مودیان، صندوق فروشگاهی، پیامک و کارتخوان</p>
                        </div>
                        <div class="flex items-baseline gap-1 py-3 border-y border-base-200">
                            <span class="text-3xl font-black text-primary font-mono">۷,۹۰۰,۰۰۰</span>
                            <span class="text-xs text-base-content/60">تومان / سالانه</span>
                        </div>
                        <ul class="space-y-2.5 text-xs text-base-content/90 font-medium">
                            <li class="flex items-center gap-2"><span class="text-success font-bold">✓</span> تمامی امکانات نسخه پایه</li>
                            <li class="flex items-center gap-2 text-primary font-bold"><span class="text-success font-bold">✓</span> اتصال مستقیم به سامانه مودیان مالیاتی</li>
                            <li class="flex items-center gap-2"><span class="text-success font-bold">✓</span> صندوق فروشگاهی سریع POS + کارتخوان PC-POS</li>
                            <li class="flex items-center gap-2"><span class="text-success font-bold">✓</span> باشگاه مشتریان، وفاداری و کیف پول اعتباری</li>
                            <li class="flex items-center gap-2"><span class="text-success font-bold">✓</span> اتوماسیون پیامک هوشمند صدور و سررسید چک</li>
                            <li class="flex items-center gap-2"><span class="text-success font-bold">✓</span> تا ۳ شرکت همزمان و ۵ کاربر</li>
                        </ul>
                    </div>
                    <div class="pt-6">
                        <a href="tel:02188884422" class="btn btn-primary btn-block rounded-xl font-bold text-xs shadow-md shadow-primary/20">
                            سفارش نسخه نقره‌ای
                        </a>
                    </div>
                </div>

                <!-- Enterprise Gold Plan -->
                <div class="bg-base-100 border border-base-300 rounded-3xl p-7 shadow-sm hover:border-primary/40 transition flex flex-col justify-between relative">
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="badge badge-accent badge-outline font-bold text-xs">سازمان‌های بزرگ و تولیدی</span>
                            <span class="text-2xl">🥇</span>
                        </div>
                        <div>
                            <h3 class="text-xl font-black text-base-content">نسخه طلایی (سازمانی)</h3>
                            <p class="text-xs text-base-content/60 mt-1">دسترسی نامحدود به تمامی ۱۶ ماژول تخصصی دخل</p>
                        </div>
                        <div class="flex items-baseline gap-1 py-3 border-y border-base-200">
                            <span class="text-3xl font-black text-base-content font-mono">۱۴,۸۰۰,۰۰۰</span>
                            <span class="text-xs text-base-content/60">تومان / سالانه</span>
                        </div>
                        <ul class="space-y-2.5 text-xs text-base-content/80">
                            <li class="flex items-center gap-2"><span class="text-success font-bold">✓</span> فعال‌سازی ۱۰۰٪ تمامی ۱۶ ماژول سیستم</li>
                            <li class="flex items-center gap-2"><span class="text-success font-bold">✓</span> فرمول ساخت و بهای تمام‌شده تولید (BOM)</li>
                            <li class="flex items-center gap-2"><span class="text-success font-bold">✓</span> پخش مویرگی، ویزیتوری میدانی و GPS</li>
                            <li class="flex items-center gap-2"><span class="text-success font-bold">✓</span> حقوق، دستمزد قانون کار و پرسنلی</li>
                            <li class="flex items-center gap-2"><span class="text-success font-bold">✓</span> دستیار هوش مصنوعی و ورود فاکتور با OCR</li>
                            <li class="flex items-center gap-2"><span class="text-success font-bold">✓</span> کاربران، شرکت‌ها و پایانه‌های نامحدود</li>
                        </ul>
                    </div>
                    <div class="pt-6">
                        <a href="tel:02188884422" class="btn btn-outline btn-block rounded-xl font-bold text-xs">
                            سفارش نسخه سازمانی
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Interactive Custom Configurator & Add-on Calculator -->
        <section id="calculator" class="bg-base-100 border border-base-300 rounded-3xl p-6 sm:p-10 shadow-sm scroll-mt-28" x-data="{
            basePlan: 'basic',
            basePrices: { basic: 3900000, professional: 7900000, enterprise: 14800000 },
            addonPrices: {
                tax_moadian: 1800000,
                pos_retail: 1500000,
                distribution: 2500000,
                production_bom: 2800000,
                hr_payroll: 1900000,
                sms_automation: 800000,
                customer_loyalty: 950000,
                telephony_voip: 900000,
                official_inquiries: 600000,
                installment_financing: 1100000,
                ai_copilot: 2200000,
                migration_tools: 500000
            },
            selectedAddons: [],
            extraUsers: 0,
            userPrice: 600000,

            isAddonDisabled(key) {
                if (this.basePlan === 'enterprise') return true;
                if (this.basePlan === 'professional') {
                    return ['tax_moadian', 'pos_retail', 'customer_loyalty', 'sms_automation'].includes(key);
                }
                return false;
            },

            calculateTotal() {
                if (this.basePlan === 'enterprise') return this.basePrices.enterprise + (this.extraUsers * this.userPrice);
                let sum = this.basePrices[this.basePlan];
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
                <span class="badge badge-accent badge-sm font-bold mb-2">ماشین‌حساب هوشمند</span>
                <h2 class="text-2xl sm:text-3xl font-black text-base-content">محاسبه آنلاین قیمت لایسنس و افزودنی‌ها</h2>
                <p class="text-xs text-base-content/60 mt-1">پکیج پایه را انتخاب کنید و ماژول‌های دلخواهتان را تیک بزنید تا هزینه دقیق محاسبه شود.</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Selectors Left Column -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Base Plan Radio -->
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-base-content/80">۱. پکیج پایه خود را انتخاب کنید:</label>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <label class="flex items-center justify-between p-3.5 rounded-2xl border cursor-pointer transition"
                                   :class="basePlan === 'basic' ? 'border-primary bg-primary/5 font-bold' : 'border-base-200 bg-base-200/40'">
                                <div class="flex items-center gap-2 text-xs">
                                    <input type="radio" x-model="basePlan" value="basic" class="radio radio-primary radio-xs" />
                                    <span>نسخه برنزی</span>
                                </div>
                                <span class="font-mono text-[11px] text-primary">۳.۹ م تومان</span>
                            </label>

                            <label class="flex items-center justify-between p-3.5 rounded-2xl border cursor-pointer transition"
                                   :class="basePlan === 'professional' ? 'border-primary bg-primary/5 font-bold' : 'border-base-200 bg-base-200/40'">
                                <div class="flex items-center gap-2 text-xs">
                                    <input type="radio" x-model="basePlan" value="professional" class="radio radio-primary radio-xs" />
                                    <span>نسخه نقره‌ای</span>
                                </div>
                                <span class="font-mono text-[11px] text-primary">۷.۹ م تومان</span>
                            </label>

                            <label class="flex items-center justify-between p-3.5 rounded-2xl border cursor-pointer transition"
                                   :class="basePlan === 'enterprise' ? 'border-primary bg-primary/5 font-bold' : 'border-base-200 bg-base-200/40'">
                                <div class="flex items-center gap-2 text-xs">
                                    <input type="radio" x-model="basePlan" value="enterprise" class="radio radio-primary radio-xs" />
                                    <span>نسخه طلایی</span>
                                </div>
                                <span class="font-mono text-[11px] text-primary">۱۴.۸ م تومان</span>
                            </label>
                        </div>
                    </div>

                    <!-- Addon Modules Checkboxes -->
                    <div class="space-y-2" x-show="basePlan !== 'enterprise'">
                        <label class="text-xs font-bold text-base-content/80">۲. ماژول‌های افزونه مورد نیاز را تیک بزنید:</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            <template x-for="(price, key) in addonPrices" :key="key">
                                <label class="flex items-center justify-between p-2.5 rounded-xl border border-base-200 bg-base-200/30 hover:bg-base-200/70 transition cursor-pointer text-xs"
                                       :class="isAddonDisabled(key) ? 'opacity-50 cursor-not-allowed bg-base-200/10' : ''">
                                    <div class="flex items-center gap-2">
                                        <input type="checkbox" :value="key" x-model="selectedAddons" :disabled="isAddonDisabled(key)" class="checkbox checkbox-primary checkbox-xs rounded" />
                                        <span x-text="key.replaceAll('_', ' ').toUpperCase()"></span>
                                        <span x-show="isAddonDisabled(key)" class="badge badge-xs text-[9px]">شامل در پکیج</span>
                                    </div>
                                    <span class="font-mono text-[11px] text-base-content/70" x-text="(price / 1000000) + ' م تومان'"></span>
                                </label>
                            </template>
                        </div>
                    </div>

                    <!-- Extra Users Slider -->
                    <div class="p-4 bg-base-200/40 rounded-2xl border border-base-200 flex items-center justify-between gap-4">
                        <div>
                            <div class="text-xs font-bold text-base-content">کاربران مازاد همزمان:</div>
                            <div class="text-[11px] text-base-content/60">هر کاربر اضافه: ۶۰۰,۰۰۰ تومان / سالانه</div>
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
                                <span class="font-bold" x-text="basePlan === 'basic' ? 'برنزی' : (basePlan === 'professional' ? 'نقره‌ای' : 'طلایی')"></span>
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
                        <div class="text-[10px] text-center text-base-content/50">فعال‌سازی آنی پس از پرداخت بدون نیاز به اینترنت</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 16 Modules Showcase -->
        <section id="modules" class="space-y-8 scroll-mt-28">
            <div class="text-center space-y-2 max-w-2xl mx-auto">
                <h2 class="text-2xl sm:text-3xl font-black text-base-content">۱۶ ماژول قدرتمند دخل</h2>
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
                        آیا نرم‌افزار دخل بدون اینترنت هم کار می‌کند؟
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
                        خیر، به هیچ وجه! دیتابیس مالی شما همیشه در اختیارتان باقی می‌ماند. پس از پایان اعتبار، سیستم یک مهلت ۷ روزه کاری داده و سپس به حالت فقط-خواندنی (Read-Only) می‌رود تا بتوانید گزارشات و اسناد گذشته را مشاهده و چاپ کنید.
                    </div>
                </div>

                <div class="collapse collapse-plus bg-base-100 border border-base-300 rounded-2xl">
                    <input type="radio" name="faq-accordion" />
                    <div class="collapse-title text-sm font-bold text-base-content">
                        چگونه می‌توانم بعداً ماژول‌های جدید مثل سامانه مودیان را اضافه کنم؟
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
                <span class="font-black text-sm text-base-content">دخل (Dakhl ERP)</span>
                <span>•</span>
                <span>توسعه‌یافته برای اصناف، شرکت‌های بازرگانی و تولیدی ایران</span>
            </div>
            <div>
                <span>واحد فروش و پشتیبانی: <strong>۰۲۱-۸۸۸۸۴۴۲۲</strong></span>
            </div>
        </div>
    </footer>

</body>
</html>
