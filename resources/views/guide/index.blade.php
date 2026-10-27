<x-app-layout :title="__('راهنمای جامع و مصور سامانه دخل')">

    <div class="mx-auto max-w-6xl space-y-6 pb-12 pt-2" x-data="{
        activeTab: @js($activeTab),
        searchQuery: '',
        matchesSearch(text) {
            if (!this.searchQuery) return true;
            return text.toLowerCase().includes(this.searchQuery.toLowerCase());
        }
    }">

        {{-- Top Hero Header --}}
        <section class="relative isolate overflow-hidden rounded-3xl border border-primary/20 bg-gradient-to-l from-primary/15 via-base-100 to-base-100 p-6 shadow-xs">
            <div aria-hidden="true" class="absolute -end-16 -top-20 size-64 rounded-full bg-primary/10 blur-3xl"></div>
            <div class="relative flex flex-col md:flex-row items-center justify-between gap-5">
                <div class="text-center md:text-right">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary/15 text-primary text-xs font-bold mb-2">
                        <span class="size-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>{{ __('دانشنامه و مرکز آموزش دخل') }}</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-black text-base-content tracking-tight">
                        {{ __('راهنمای کامل و مصور سامانه دخل') }}
                    </h1>
                    <p class="text-xs sm:text-sm text-base-content/65 mt-1.5 max-w-2xl leading-relaxed">
                        {{ __('آموزش فرآیندهای مالی، فروش، انبارداری، خزانه‌داری، استاندارد دوبل و سامانه مودیان با فلوچارت‌های تصویری و مثال‌های عملیاتی.') }}
                    </p>
                </div>

                {{-- Interactive Search Filter --}}
                <div class="w-full md:w-80 shrink-0">
                    <div class="relative">
                        <input type="text"
                               x-model="searchQuery"
                               placeholder="{{ __('جستجوی سریع موضوعات راهنما...') }}"
                               class="input input-bordered w-full rounded-2xl text-xs pr-10 focus:outline-primary bg-base-100 shadow-xs" />
                        <span class="absolute end-3 top-1/2 -translate-y-1/2 text-base-content/40">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                    </div>
                </div>
            </div>
        </section>

        {{-- Topic Selection Tabs Grid --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2.5">
            @foreach($topics as $key => $topic)
                <button type="button"
                        @click="activeTab = '{{ $key }}'"
                        :class="activeTab === '{{ $key }}' ? 'border-primary bg-primary text-primary-content shadow-sm' : 'border-base-300 bg-base-100 text-base-content/80 hover:border-primary/40 hover:bg-base-200/50'"
                        class="flex flex-col items-center justify-center p-3 rounded-2xl border text-center transition-all duration-200 cursor-pointer">
                    <span class="size-8 rounded-xl flex items-center justify-center text-sm mb-1.5"
                          :class="activeTab === '{{ $key }}' ? 'bg-white/20 text-white' : 'bg-base-200 text-primary'">
                        <x-menu-icon :name="$topic['icon']" class="size-4" style="width: 16px; height: 16px;" />
                    </span>
                    <span class="text-xs font-bold truncate max-w-full leading-tight">{{ $topic['title'] }}</span>
                </button>
            @endforeach
        </div>

        {{-- CHAPTER 1: OVERVIEW --}}
        <div x-show="activeTab === 'overview'" class="space-y-6">
            <div class="card border border-base-300 bg-base-100 shadow-xs rounded-3xl p-6">
                <div class="flex items-center justify-between border-b border-base-200 pb-4 mb-5">
                    <div>
                        <h2 class="text-lg font-black text-base-content flex items-center gap-2">
                            <span>🚀</span>
                            <span>{{ __('مفاهیم پایه و شروع به کار با دخل') }}</span>
                        </h2>
                        <p class="text-xs text-base-content/65 mt-1">{{ __('معماری سیستم، چگونگی گردش اطلاعات و راه‌اندازی اولیه در ۳ گام') }}</p>
                    </div>
                    <span class="badge badge-primary font-bold text-xs">{{ __('گام‌های نخست') }}</span>
                </div>

                {{-- Illustrated Architecture Flowchart --}}
                <div class="rounded-2xl border border-base-300 bg-base-200/40 p-5 mb-6">
                    <div class="text-xs font-black text-base-content mb-3 flex items-center gap-1.5">
                        <span class="size-2 rounded-full bg-primary"></span>
                        <span>{{ __('نمودار جریان یکپارچه اطلاعات در دخل (از معامله تا تراز مالی)') }}</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 text-center text-xs">
                        <div class="p-4 rounded-2xl bg-base-100 border border-base-300 shadow-2xs space-y-2">
                            <div class="size-10 rounded-xl bg-emerald-500/15 text-emerald-600 font-black text-base mx-auto flex items-center justify-center">۱</div>
                            <div class="font-bold text-base-content">{{ __('ثبت رویداد تجاری') }}</div>
                            <p class="text-[11px] text-base-content/60">{{ __('فاکتور فروش، خرید، فیش POS یا تهاتر همکاران') }}</p>
                        </div>
                        <div class="p-4 rounded-2xl bg-base-100 border border-base-300 shadow-2xs space-y-2">
                            <div class="size-10 rounded-xl bg-sky-500/15 text-sky-600 font-black text-base mx-auto flex items-center justify-center">۲</div>
                            <div class="font-bold text-base-content">{{ __('تأثیر همزمان در انبار') }}</div>
                            <p class="text-[11px] text-base-content/60">{{ __('رزرو کالا، کسر موجودی، ردیابی سریال و بچ') }}</p>
                        </div>
                        <div class="p-4 rounded-2xl bg-base-100 border border-base-300 shadow-2xs space-y-2">
                            <div class="size-10 rounded-xl bg-indigo-500/15 text-indigo-600 font-black text-base mx-auto flex items-center justify-center">۳</div>
                            <div class="font-bold text-base-content">{{ __('صدور خودکار سند دوبل') }}</div>
                            <p class="text-[11px] text-base-content/60">{{ __('تولید سند موازنه با بدهکار/بستانکار شدن سرفصل‌ها') }}</p>
                        </div>
                        <div class="p-4 rounded-2xl bg-base-100 border border-base-300 shadow-2xs space-y-2">
                            <div class="size-10 rounded-xl bg-amber-500/15 text-amber-600 font-black text-base mx-auto flex items-center justify-center">۴</div>
                            <div class="font-bold text-base-content">{{ __('گزارش و انطباق مودیان') }}</div>
                            <p class="text-[11px] text-base-content/60">{{ __('تراز لحظه‌ای دفاتر، سودوزیان و ارسال به کارپوشه') }}</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="p-4 rounded-2xl border border-base-200 bg-base-100 space-y-2">
                        <div class="font-bold text-sm text-base-content flex items-center gap-2">
                            <span class="badge badge-success badge-sm">ساده</span>
                            <span>{{ __('حالت کاربری ساده (Simple Mode)') }}</span>
                        </div>
                        <p class="text-xs text-base-content/70 leading-relaxed">
                            {{ __('مخصوص فروشندگان، صندوق‌داران و کاربرانی که نیاز به منوهای تخصصی ندارند. منوها به «فروش»، «خرید»، «دریافت و پرداخت» و «مشتریان» خلاصه می‌شود و کاربری بسیار روان و سریعی دارد.') }}
                        </p>
                    </div>

                    <div class="p-4 rounded-2xl border border-base-200 bg-base-100 space-y-2">
                        <div class="font-bold text-sm text-base-content flex items-center gap-2">
                            <span class="badge badge-primary badge-sm">پیشرفته</span>
                            <span>{{ __('حالت کاربری پیشرفته (Advanced Mode)') }}</span>
                        </div>
                        <p class="text-xs text-base-content/70 leading-relaxed">
                            {{ __('تمام امکانات و زیرسیستم‌ها از جمله کدینگ سرفصل‌های کل و معین، اسناد دوبل حسابداری، تحلیل سود مراکز هزینه، حواله‌های بین انباری، مدیریت چک و سامانه مودیان را در دسترس قرار می‌دهد.') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- CHAPTER 2: SALES & POS --}}
        <div x-show="activeTab === 'sales'" class="space-y-6">
            <div class="card border border-base-300 bg-base-100 shadow-xs rounded-3xl p-6">
                <div class="flex items-center justify-between border-b border-base-200 pb-4 mb-5">
                    <div>
                        <h2 class="text-lg font-black text-base-content flex items-center gap-2">
                            <span>🛒</span>
                            <span>{{ __('چرخه فروش، فاکتور، صندوق POS و تهاتر') }}</span>
                        </h2>
                        <p class="text-xs text-base-content/65 mt-1">{{ __('نحوه ثبت فاکتور رسمی، فیش سریع فروش، ردیابی سریال در فروش و تهاتر کالا') }}</p>
                    </div>
                    <span class="badge badge-accent font-bold text-xs">{{ __('معاملات') }}</span>
                </div>

                {{-- Illustrated Step-by-Step Flow --}}
                <div class="space-y-4">
                    <div class="flex items-start gap-4 p-4 rounded-2xl bg-base-200/50 border border-base-200">
                        <span class="size-8 rounded-xl bg-primary text-primary-content flex items-center justify-center font-bold text-xs shrink-0">۱</span>
                        <div class="text-xs space-y-1">
                            <div class="font-bold text-base-content">{{ __('پیش‌فاکتور (Draft) و فاکتور نهایی') }}</div>
                            <p class="text-base-content/70 leading-relaxed">
                                {{ __('هنگام ثبت فاکتور جدید، فاکتور در حالت پیش‌نویس است و هیچ اثر حسابداری دائم ایجاد نمی‌کند. پس از زدن دکمه «تایید فاکتور»، سند دوبل حسابداری صادر شده و موجودی انبار به طور قطعی کسر می‌شود.') }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start gap-4 p-4 rounded-2xl bg-base-200/50 border border-base-200">
                        <span class="size-8 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-xs shrink-0">۲</span>
                        <div class="text-xs space-y-1">
                            <div class="font-bold text-base-content">{{ __('صندوق فروشگاهی سریع (POS Terminal)') }}</div>
                            <p class="text-base-content/70 leading-relaxed">
                                {{ __('مناسب برای فروشگاه‌ها و خرده‌فروشی با قابلیت اتصال به بارکدخوان، تسویه آنی نقدی یا کارتخوان، چاپ فیش حرارتی و جستجوی لحظه‌ای اقلام بدون نیاز به ترک صفحه.') }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start gap-4 p-4 rounded-2xl bg-base-200/50 border border-base-200">
                        <span class="size-8 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-bold text-xs shrink-0">۳</span>
                        <div class="text-xs space-y-1">
                            <div class="font-bold text-base-content">{{ __('تهاتر و مبادله کالا با کالا (Trade Barter)') }}</div>
                            <p class="text-base-content/70 leading-relaxed">
                                {{ __('در معاملات همکاران (مانند طلافروشی، قطعات یدکی یا مصالح)، می‌توانید اقلام مبادله‌شده را ثبت کنید. در زمان تسویه، دخل مابه‌التفاوت ریالی را محاسبه کرده و به طور کاملاً خودکار یک سند موازنه دوبل حسابداری بین بدهکار/بستانکار ثبت می‌کند.') }}
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Pro Tip Alert --}}
                <div class="mt-5 p-3.5 rounded-2xl border border-amber-500/30 bg-amber-500/10 text-xs text-amber-950 dark:text-amber-200 flex items-center gap-3">
                    <span class="text-base">💡</span>
                    <span><b>{{ __('نکته امنیتی فاکتورهای دارای تراکنش:') }}</b> {{ __('به محض اینکه برای فاکتوری واریز، پوز، چک یا تخصیص ثبت شود، فاکتور جهت حفظ سلامت ترازهای مالی قفل شده و ویرایش یا حذف مستقیم آن مسدود می‌گردد.') }}</span>
                </div>
            </div>
        </div>

        {{-- CHAPTER 3: TREASURY & CHEQUES --}}
        <div x-show="activeTab === 'treasury'" class="space-y-6">
            <div class="card border border-base-300 bg-base-100 shadow-xs rounded-3xl p-6">
                <div class="flex items-center justify-between border-b border-base-200 pb-4 mb-5">
                    <div>
                        <h2 class="text-lg font-black text-base-content flex items-center gap-2">
                            <span>💳</span>
                            <span>{{ __('خزانه‌داری، نقدینگی و چرخه ۷ گانه چک صیادی') }}</span>
                        </h2>
                        <p class="text-xs text-base-content/65 mt-1">{{ __('مدیریت صندوق، حساب‌های بانکی، ثبت رسید و گردش کامل چک صیادی در بازار ایران') }}</p>
                    </div>
                    <span class="badge badge-info font-bold text-xs">{{ __('نقد و اسناد') }}</span>
                </div>

                {{-- Visual Cheque 7-Step Lifecycle --}}
                <div class="rounded-2xl border border-base-300 bg-base-200/40 p-5 mb-5">
                    <div class="text-xs font-black text-base-content mb-3 flex items-center gap-1.5">
                        <span class="size-2 rounded-full bg-indigo-500"></span>
                        <span>{{ __('مراحل هفت‌گانه چرخه حیات چک در دخل:') }}</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-2.5 text-xs text-right">
                        <div class="p-3 rounded-xl bg-base-100 border border-base-300">
                            <span class="badge badge-primary badge-xs mb-1">۱. ثبت اولیه</span>
                            <div class="font-bold">{{ __('اسناد دریافتنی در جریان') }}</div>
                            <div class="text-[10px] opacity-70 mt-0.5">{{ __('ثبت مشخصات و شناسه ۱۶ رقمی صیاد') }}</div>
                        </div>

                        <div class="p-3 rounded-xl bg-base-100 border border-base-300">
                            <span class="badge badge-info badge-xs mb-1">۲. واگذاری به بانک</span>
                            <div class="font-bold">{{ __('اسناد در جریان وصول') }}</div>
                            <div class="text-[10px] opacity-70 mt-0.5">{{ __('خواباندن چک به حساب جهت وصول') }}</div>
                        </div>

                        <div class="p-3 rounded-xl bg-base-100 border border-base-300">
                            <span class="badge badge-success badge-xs mb-1">۳. وصول قطعی</span>
                            <div class="font-bold">{{ __('واریز به حساب بانک') }}</div>
                            <div class="text-[10px] opacity-70 mt-0.5">{{ __('افزایش موجودی بانک و تسویه تعهد') }}</div>
                        </div>

                        <div class="p-3 rounded-xl bg-base-100 border border-base-300">
                            <span class="badge badge-error badge-xs mb-1">۴. برگشت چک</span>
                            <div class="font-bold">{{ __('چک‌های لاوصول / حقوقی') }}</div>
                            <div class="text-[10px] opacity-70 mt-0.5">{{ __('انتقال مانده به مطالبات مشکوک‌الوصول') }}</div>
                        </div>

                        <div class="p-3 rounded-xl bg-base-100 border border-base-300">
                            <span class="badge badge-warning badge-xs mb-1">۵. خرج چک</span>
                            <div class="font-bold">{{ __('انتقال به طرف‌حساب') }}</div>
                            <div class="text-[10px] opacity-70 mt-0.5">{{ __('پشت‌نویسی و تسویه حساب تامین‌کننده') }}</div>
                        </div>

                        <div class="p-3 rounded-xl bg-base-100 border border-base-300">
                            <span class="badge badge-neutral badge-xs mb-1">۶. استرداد به مشتری</span>
                            <div class="font-bold">{{ __('برگشت فیزیکی لاشه') }}</div>
                            <div class="text-[10px] opacity-70 mt-0.5">{{ __('عودت چک به صاحب سند') }}</div>
                        </div>

                        <div class="p-3 rounded-xl bg-base-100 border border-base-300 col-span-1 sm:col-span-2">
                            <span class="badge badge-accent badge-xs mb-1">۷. چک ضمانت</span>
                            <div class="font-bold">{{ __('اسناد انتظامی غیرمالی') }}</div>
                            <div class="text-[10px] opacity-70 mt-0.5">{{ __('بدون اثر در تراز سود و زیان تا زمان اجرا') }}</div>
                        </div>
                    </div>
                </div>

                <div class="p-4 rounded-2xl bg-base-200/50 border border-base-200 text-xs space-y-1.5 leading-relaxed">
                    <div class="font-bold text-sm text-base-content">{{ __('تخصیص وجه به فاکتورها (Payment Allocation):') }}</div>
                    <p class="text-base-content/75">
                        {{ __('در دخل، دریافت وجه از تخصیص آن به فاکتور مستقل است؛ شما می‌توانید یک واریزی ۵۰ میلیون تومانی را همزمان به ۳ فاکتور قدیمی یا معوق یک مشتری اختصاص دهید تا مانده معوقه هر فاکتور به تفکیک به صفر برسد.') }}
                    </p>
                </div>
            </div>
        </div>

        {{-- CHAPTER 4: WAREHOUSE & LOT/SERIAL --}}
        <div x-show="activeTab === 'warehouse'" class="space-y-6">
            <div class="card border border-base-300 bg-base-100 shadow-xs rounded-3xl p-6">
                <div class="flex items-center justify-between border-b border-base-200 pb-4 mb-5">
                    <div>
                        <h2 class="text-lg font-black text-base-content flex items-center gap-2">
                            <span>📦</span>
                            <span>{{ __('انبارداری، ردیابی سریال و لات (بچ ساخت)') }}</span>
                        </h2>
                        <p class="text-xs text-base-content/65 mt-1">{{ __('ردیابی گارانتی، تاریخ انقضا، شماره بچ‌های بهداشتی/صنعتی و کاردکس کالا') }}</p>
                    </div>
                    <span class="badge badge-secondary font-bold text-xs">{{ __('لجستیک') }}</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                    <div class="p-4 rounded-2xl border border-base-200 bg-base-100 space-y-2">
                        <div class="font-bold text-sm text-base-content flex items-center gap-2">
                            <span class="badge badge-primary badge-xs">ردیابی شماره سریال</span>
                            <span>{{ __('شماره سریال‌های یکتای هر قطعه') }}</span>
                        </div>
                        <p class="text-base-content/70 leading-relaxed">
                            {{ __('برای اقلامی مانند موبایل (IMEI)، لپ‌تاپ، لوازم خانگی و قطعات یدکی خودرو که هر کالا کد یکتا دارد. در صفحه کالا می‌توانید شماره سریال‌ها را با بارکدخوان وارد کنید و هنگام صدور فاکتور فروش مشخص کنید دقیقاً کدام سریال تحویل مشتری شده است.') }}
                        </p>
                    </div>

                    <div class="p-4 rounded-2xl border border-base-200 bg-base-100 space-y-2">
                        <div class="font-bold text-sm text-base-content flex items-center gap-2">
                            <span class="badge badge-accent badge-xs">ردیابی لات / سری ساخت</span>
                            <span>{{ __('مدیریت انقضا و بچ‌های تولیدی') }}</span>
                        </div>
                        <p class="text-base-content/70 leading-relaxed">
                            {{ __('مخصوص اصناف دارویی، غذایی، شیمیایی یا رنگ‌ها که در محموله‌های دارای شماره سری و تاریخ انقضا تامین می‌شوند. هنگام فروش، نرم‌افزار به صورت خودکار اقلامی با تاریخ انقضای نزدیک‌تر را پیشنهاد می‌دهد (روش FEFO / FIFO).') }}
                        </p>
                    </div>
                </div>

                <div class="p-4 rounded-2xl bg-base-200/50 border border-base-200 text-xs space-y-1 mt-4">
                    <div class="font-bold text-base-content">{{ __('رسید ورود کالا (Goods Receipt) و حواله خروج (Delivery Note):') }}</div>
                    <p class="text-base-content/70 leading-relaxed">
                        {{ __('در دخل برای تفکیک عملیات فاکتور از تحویل فیزیکی کالا، می‌توانید حواله خروج رسمی و رسید تحویل انبار را با امضای انباردار و تحویل‌گیرنده ثبت و چاپ فرمایید.') }}
                    </p>
                </div>
            </div>
        </div>

        {{-- CHAPTER 5: ACCOUNTING --}}
        <div x-show="activeTab === 'accounting'" class="space-y-6">
            <div class="card border border-base-300 bg-base-100 shadow-xs rounded-3xl p-6">
                <div class="flex items-center justify-between border-b border-base-200 pb-4 mb-5">
                    <div>
                        <h2 class="text-lg font-black text-base-content flex items-center gap-2">
                            <span>🏛️</span>
                            <span>{{ __('حسابداری دوبل، ساختار کدینگ و اسناد مالی') }}</span>
                        </h2>
                        <p class="text-xs text-base-content/65 mt-1">{{ __('کدینگ ۳ سطحی، کنترل موازنه بدهکار/بستانکار، گردش دفاتر و بستن سال مالی') }}</p>
                    </div>
                    <span class="badge badge-primary font-bold text-xs">{{ __('دوبل رسمی') }}</span>
                </div>

                <div class="rounded-2xl border border-base-300 bg-base-200/40 p-4 mb-4 text-xs">
                    <div class="font-black text-sm text-base-content mb-2">{{ __('قانون بنیادین تراز در دخل:') }}</div>
                    <div class="font-mono text-center p-3 rounded-xl bg-base-100 border border-base-300 font-bold text-sm text-primary">
                        مجموع مبالغ بدهکار (Debit) == مجموع مبالغ بستانکار (Credit)
                    </div>
                    <p class="text-[11px] text-base-content/70 mt-2 text-center">
                        {{ __('هیچ سند حسابی در دخل بدون تراز صفر نهایی ثبت و تایید نمی‌شود تا دفاتر مالیاتی شما از هرگونه خدشه در امان بماند.') }}
                    </p>
                </div>

                <div class="space-y-2 text-xs">
                    <div class="p-3 rounded-xl bg-base-100 border border-base-200 flex items-center justify-between">
                        <div>
                            <span class="font-bold text-base-content">{{ __('حساب کل (General Ledger):') }}</span>
                            <span class="text-base-content/70 mr-1">{{ __('طبقه کلی حساب‌ها مانند موجودی نقد و بانک، حساب‌های دریافتنی، بهای تمام‌شده') }}</span>
                        </div>
                        <span class="badge badge-sm font-mono">۲ رقمی</span>
                    </div>

                    <div class="p-3 rounded-xl bg-base-100 border border-base-200 flex items-center justify-between">
                        <div>
                            <span class="font-bold text-base-content">{{ __('حساب معین (Subsidiary Ledger):') }}</span>
                            <span class="text-base-content/70 mr-1">{{ __('خرد شدن حساب کل، مانند بانک‌های ریالی، اسناد در جریان وصول، صندوق شعب') }}</span>
                        </div>
                        <span class="badge badge-sm font-mono">۴ رقمی</span>
                    </div>

                    <div class="p-3 rounded-xl bg-base-100 border border-base-200 flex items-center justify-between">
                        <div>
                            <span class="font-bold text-base-content">{{ __('تفصیلی شناور (Detailed Account):') }}</span>
                            <span class="text-base-content/70 mr-1">{{ __('طرف‌حساب‌ها، اشخاص، مشتریان و پروژه‌ها که به معین‌های مختلف متصل می‌شوند') }}</span>
                        </div>
                        <span class="badge badge-sm font-mono">۶ رقمی شناور</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- CHAPTER 6: TAX & MOADIAN --}}
        <div x-show="activeTab === 'tax'" class="space-y-6">
            <div class="card border border-base-300 bg-base-100 shadow-xs rounded-3xl p-6">
                <div class="flex items-center justify-between border-b border-base-200 pb-4 mb-5">
                    <div>
                        <h2 class="text-lg font-black text-base-content flex items-center gap-2">
                            <span>⚖️</span>
                            <span>{{ __('سامانه مودیان و پایانه‌های فروشگاهی') }}</span>
                        </h2>
                        <p class="text-xs text-base-content/65 mt-1">{{ __('قوانین ارسال صورتحساب الکترونیکی، تولید شناسه یکتای مالیاتی ۲۲ رقمی و ارسال به کارپوشه') }}</p>
                    </div>
                    <span class="badge badge-warning font-bold text-xs">{{ __('قوانین مالیاتی') }}</span>
                </div>

                <div class="space-y-3.5 text-xs">
                    <div class="p-4 rounded-2xl bg-base-200/50 border border-base-200 space-y-1">
                        <div class="font-bold text-sm text-base-content">{{ __('صورتحساب الکترونیکی نوع اول:') }}</div>
                        <p class="text-base-content/70 leading-relaxed">
                            {{ __('شامل اطلاعات کامل خریدار و فروشنده (کد اقتصادی، شناسه ملی، کد پستی). مناسب برای معاملات B2B و شرکت‌ها تا خریدار بتواند از اعتبار ارزش افزوده آن استفاده کند.') }}
                        </p>
                    </div>

                    <div class="p-4 rounded-2xl bg-base-200/50 border border-base-200 space-y-1">
                        <div class="font-bold text-sm text-base-content">{{ __('صورتحساب الکترونیکی نوع دوم:') }}</div>
                        <p class="text-base-content/70 leading-relaxed">
                            {{ __('مخصوص فروش به مصرف‌کننده نهایی در صندوق‌های فروشگاهی که ثبت اطلاعات خریدار الزامی نیست و مستقیماً به عنوان فروش نقدی ثبت می‌شود.') }}
                        </p>
                    </div>

                    <div class="p-4 rounded-2xl border border-sky-500/25 bg-sky-50/50 dark:bg-sky-950/20 space-y-1 text-sky-900 dark:text-sky-200">
                        <div class="font-bold text-sm">{{ __('تنظیمات اتصال به کارپوشه:') }}</div>
                        <p class="text-xs leading-relaxed">
                            {{ __('از منوی «مالیات و سامانه مودیان ⬅ تنظیمات اتصال» می‌توانید شناسه یکتای حافظه مالیاتی و کلید خصوصی CSR خود را بارگذاری کنید. دخل صورتحساب‌ها را با الگوریتم استاندارد امضا کرده و به سامانه مودیان ارسال می‌کند.') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- CHAPTER 7: AI COPILOT --}}
        <div x-show="activeTab === 'copilot'" class="space-y-6">
            <div class="card border border-base-300 bg-base-100 shadow-xs rounded-3xl p-6">
                <div class="flex items-center justify-between border-b border-base-200 pb-4 mb-5">
                    <div>
                        <h2 class="text-lg font-black text-base-content flex items-center gap-2">
                            <span>✨</span>
                            <span>{{ __('دستیار مالی هوش مصنوعی دخل (AI Copilot) و تنظیمات کلید') }}</span>
                        </h2>
                        <p class="text-xs text-base-content/65 mt-1">{{ __('پرسش صوتی و متنی از اسناد مالی، کارکرد کاملاً آفلاین و راهنمای اتصال به موتور ابری Gemini') }}</p>
                    </div>
                    <span class="badge badge-primary font-bold text-xs">{{ __('هوش مصنوعی') }}</span>
                </div>

                {{-- Offline vs Online Architecture Badge Banner --}}
                <div class="rounded-2xl border border-primary/20 bg-primary/5 p-4 mb-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-primary text-primary-content flex items-center justify-center font-bold text-lg shrink-0">
                            ⚡
                        </div>
                        <div>
                            <div class="font-bold text-sm text-base-content">{{ __('معماری هیبریدی: کارکرد ۱۰۰٪ آفلاین + موتور زایشی ابری اختیاری') }}</div>
                            <p class="text-xs text-base-content/70 mt-0.5">
                                {{ __('هوش مصنوعی دخل برای کارکرد به هیچ اینترنت یا سرور خارجی وابسته نیست و تمامی اسناد و محاسبات در دیتابیس محلی شما با امنیت مطلق انجام می‌شود.') }}
                            </p>
                        </div>
                    </div>
                    <a href="{{ route('copilot.index') }}" class="btn btn-primary btn-sm rounded-xl text-xs shrink-0 font-bold">
                        <span>💬</span> {{ __('ورود به چت هوش مصنوعی') }}
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                    <div class="p-4 rounded-2xl border border-base-200 bg-base-100 space-y-2">
                        <div class="font-bold text-sm text-base-content">{{ __('فرمان‌های نمونه برای گفتگو با دستیار:') }}</div>
                        <ul class="space-y-2 text-base-content/75 font-mono text-[11px]">
                            <li class="p-2 rounded-xl bg-base-200/60">💬 «بدهکارترین مشتریان من در این ماه کیا هستند؟»</li>
                            <li class="p-2 rounded-xl bg-base-200/60">💬 «چک‌هایی که تا ۳ روز آینده سررسید دارند رو نشون بده.»</li>
                            <li class="p-2 rounded-xl bg-base-200/60">💬 «به علی قیمت گلس رو ۴۰ تومن دادم ثبت کن» (ثبت مظنه)</li>
                            <li class="p-2 rounded-xl bg-base-200/60">💬 «۲۰۰ تومن دادم علی» (صدور خودکار کارت پرداخت)</li>
                        </ul>
                    </div>

                    <div class="p-4 rounded-2xl border border-base-200 bg-base-100 space-y-2">
                        <div class="font-bold text-sm text-base-content">{{ __('تابلوی زنده قیمت‌های آنلاین و طلا:') }}</div>
                        <p class="text-base-content/70 leading-relaxed">
                            {{ __('دخل به طور خودکار قیمت‌های روز دلار، درهم، طلای ۱۸ عیار و سکه امامی را استعلام می‌کند. می‌توانید کاردکس انبار و ارزش موجودی را به قیمت لحظه‌ای بازار مقایسه فرمایید تا سود و زیان ناشی از نوسانات تورمی محاسبه شود.') }}
                        </p>
                    </div>
                </div>

                {{-- Full Step-by-Step Tutorial on Gemini API Key --}}
                <div class="mt-6 pt-5 border-t border-base-200 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-black text-sm text-base-content flex items-center gap-2">
                            <span>🔑</span>
                            <span>{{ __('آموزش گام‌به‌گام دریافت رایگان کلید هوش مصنوعی (Google Gemini API Key)') }}</span>
                        </h3>
                        <span class="badge badge-success badge-sm font-bold text-white">رایگان (Free Tier)</span>
                    </div>

                    <p class="text-xs text-base-content/75 leading-relaxed">
                        {{ __('گوگل به هر کاربر با حساب جیمیل، سهمیه روزانه رایگان معادل ۱۵ درخواست در دقیقه برای مدل قدرتمند Gemini 2.5 Flash اختصاص می‌دهد. با فعال‌سازی این کلید، تحلیل‌های متنی و استراتژیک عمیق‌تری به زبان فارسی در دخل دریافت خواهید کرد.') }}
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 text-xs">
                        <!-- Step 1 -->
                        <div class="p-3.5 rounded-2xl border border-base-300 bg-base-200/40 space-y-2 flex flex-col justify-between">
                            <div>
                                <span class="badge badge-primary badge-xs font-mono font-bold">گام ۱</span>
                                <div class="font-bold text-xs mt-1 text-base-content">{{ __('ورود به گوگل استودیو') }}</div>
                                <p class="text-[11px] text-base-content/70 mt-1 leading-relaxed">
                                    {{ __('با مرورگر وارد سایت Google AI Studio شوید:') }}
                                </p>
                            </div>
                            <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener noreferrer" class="link link-primary font-mono text-[11px] font-bold dir-ltr block text-left">
                                aistudio.google.com/apikey ↗
                            </a>
                        </div>

                        <!-- Step 2 -->
                        <div class="p-3.5 rounded-2xl border border-base-300 bg-base-200/40 space-y-2">
                            <span class="badge badge-info badge-xs font-mono font-bold text-white">گام ۲</span>
                            <div class="font-bold text-xs mt-1 text-base-content">{{ __('ساخت کلید (Create API Key)') }}</div>
                            <p class="text-[11px] text-base-content/70 mt-1 leading-relaxed">
                                {{ __('روی دکمه آبی‌رنگ «Create API Key» کلیک کنید و یک پروژه دلخواه (مثلاً Dakhl ERP) انتخاب یا ایجاد نمایید.') }}
                            </p>
                        </div>

                        <!-- Step 3 -->
                        <div class="p-3.5 rounded-2xl border border-base-300 bg-base-200/40 space-y-2">
                            <span class="badge badge-accent badge-xs font-mono font-bold">گام ۳</span>
                            <div class="font-bold text-xs mt-1 text-base-content">{{ __('کپی کردن کلید') }}</div>
                            <p class="text-[11px] text-base-content/70 mt-1 leading-relaxed">
                                {{ __('کلید ساخته‌شده یک رشته متنی است که با عبارت AIzaSy شروع می‌شود. دکمه Copy را بزنید تا کلید در حافظه ذخیره شود.') }}
                            </p>
                        </div>

                        <!-- Step 4 -->
                        <div class="p-3.5 rounded-2xl border border-base-300 bg-base-200/40 space-y-2">
                            <span class="badge badge-success badge-xs font-mono font-bold text-white">گام ۴</span>
                            <div class="font-bold text-xs mt-1 text-base-content">{{ __('ثبت در نرم‌افزار دخل') }}</div>
                            <p class="text-[11px] text-base-content/70 mt-1 leading-relaxed">
                                {{ __('در صفحه هوش مصنوعی دخل، دکمه «تنظیم کلید هوش مصنوعی» را بزنید و کلید را پیست فرمایید.') }}
                            </p>
                        </div>
                    </div>

                    {{-- Server vs In-App Setup Info Box --}}
                    <div class="p-4 rounded-2xl bg-base-200/60 border border-base-300 text-xs space-y-2">
                        <div class="font-bold text-base-content flex items-center gap-1.5">
                            <span>⚙️</span>
                            <span>{{ __('دو روش برای ذخیره کلید در نرم‌افزار دخل:') }}</span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-[11px]">
                            <div class="p-2.5 rounded-xl bg-base-100 border border-base-200 space-y-1">
                                <strong class="text-primary">{{ __('روش اول (پیشنهادی): از داخل نرم‌افزار') }}</strong>
                                <p class="text-base-content/70 leading-relaxed">
                                    {{ __('وارد صفحه «دستیار مالی هوش مصنوعی» شوید و روی کلید چرخ‌دنده «تنظیم کلید هوش مصنوعی» کلیک کرده و کلید را ذخیره نمایید. این کلید برای شرکت شما ذخیره می‌شود.') }}
                                </p>
                            </div>
                            <div class="p-2.5 rounded-xl bg-base-100 border border-base-200 space-y-1">
                                <strong class="text-secondary">{{ __('روش دوم: از طریق فایل .env سرور') }}</strong>
                                <p class="text-base-content/70 leading-relaxed font-mono">
                                    GEMINI_API_KEY=AIzaSyBxxxxxxxxxxxxxxx
                                </p>
                                <p class="text-base-content/60 text-[10px]">
                                    {{ __('مناسب مدیران فنی و استقرارهای داکر / سرورهای اختصاصی.') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Sanctions & DNS Tip Box --}}
                    <div class="p-3.5 rounded-2xl border border-warning/30 bg-warning/10 text-xs text-base-content space-y-1">
                        <div class="font-bold text-warning-content flex items-center gap-1.5">
                            <span>🛡️</span>
                            <span>{{ __('نکته ویژه کاربران داخل ایران (رفع محدودیت دسترسی به سرورهای گوگل):') }}</span>
                        </div>
                        <p class="text-[11px] leading-relaxed opacity-90">
                            {{ __('به دلیل تحریم‌های شرکت گوگل علیه آی‌پی‌های ایران، جهت برقراری ارتباط پایدار سرور یا رایانه با API گوگل، استفاده از سرویس‌های رفع تحریم ایرانی مانند DNS شکن (178.22.122.100 و 185.51.200.2) یا سامانه ۴۰۳ روی رایانه الزامی است. در صورتی که ارتباط قطع شود، دخل بلافاصله و خودکار به حالت آفلاین برمی‌گردد.') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- CHAPTER 8: SHORTCUTS --}}
        <div x-show="activeTab === 'shortcuts'" class="space-y-6">
            <div class="card border border-base-300 bg-base-100 shadow-xs rounded-3xl p-6">
                <div class="flex items-center justify-between border-b border-base-200 pb-4 mb-5">
                    <div>
                        <h2 class="text-lg font-black text-base-content flex items-center gap-2">
                            <span>⌨️</span>
                            <span>{{ __('کلیدهای میانبر و ابزارهای سریع صفحه‌کلید') }}</span>
                        </h2>
                        <p class="text-xs text-base-content/65 mt-1">{{ __('سرعت بخشیدن به ورود اطلاعات و کاربری روان بدون ماوس') }}</p>
                    </div>
                    <span class="badge badge-neutral font-bold text-xs">{{ __('میانبرها') }}</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                    <div class="flex items-center justify-between p-3 rounded-2xl bg-base-200/60 border border-base-200">
                        <span class="font-medium text-base-content">{{ __('پالت جستجوی سریع در کل نرم‌افزار') }}</span>
                        <kbd class="px-2 py-1 rounded-lg bg-base-100 border border-base-300 font-mono text-xs font-bold text-primary">Ctrl + K</kbd>
                    </div>

                    <div class="flex items-center justify-between p-3 rounded-2xl bg-base-200/60 border border-base-200">
                        <span class="font-medium text-base-content">{{ __('بستن پنجره‌ها و مودال‌های باز') }}</span>
                        <kbd class="px-2 py-1 rounded-lg bg-base-100 border border-base-300 font-mono text-xs font-bold">Esc</kbd>
                    </div>

                    <div class="flex items-center justify-between p-3 rounded-2xl bg-base-200/60 border border-base-200">
                        <span class="font-medium text-base-content">{{ __('حرکت در لیست آیتم‌های جستجو') }}</span>
                        <div class="flex items-center gap-1">
                            <kbd class="px-1.5 py-1 rounded-lg bg-base-100 border border-base-300 font-mono text-xs font-bold">↑</kbd>
                            <kbd class="px-1.5 py-1 rounded-lg bg-base-100 border border-base-300 font-mono text-xs font-bold">↓</kbd>
                        </div>
                    </div>

                    <div class="flex items-center justify-between p-3 rounded-2xl bg-base-200/60 border border-base-200">
                        <span class="font-medium text-base-content">{{ __('انتخاب و تایید آیتم فعال') }}</span>
                        <kbd class="px-2 py-1 rounded-lg bg-base-100 border border-base-300 font-mono text-xs font-bold text-emerald-600">Enter ↵</kbd>
                    </div>
                </div>
            </div>
        </div>

    </div>

</x-app-layout>
