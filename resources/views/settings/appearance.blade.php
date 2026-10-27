<x-app-layout :title="__('تنظیمات ظاهری و پوسته سامانه دخل')">
    <div class="space-y-8 max-w-6xl mx-auto py-4 px-2" x-data="{
        selectedTheme: localStorage.getItem('ui_theme') || @json($currentTheme ?? session('dakhl_ui_theme', 'flow')),
        selectedAccent: localStorage.getItem('dakhl_accent_color') || @json($currentAccent ?? session('dakhl_accent_color', 'cyan')),
        selectedDensity: localStorage.getItem('dakhl_ui_density') || @json($currentDensity ?? session('dakhl_ui_density', 'comfortable')),
        selectedMode: localStorage.getItem('theme') || @json($currentMode ?? session('dakhl_theme_mode', 'light')),

        applyLiveTheme(theme) {
            this.selectedTheme = theme;
            document.documentElement.setAttribute('data-ui-theme', theme);
            localStorage.setItem('ui_theme', theme);

            // Set appropriate default accent if not explicitly changed
            if (theme === 'linear' && this.selectedAccent === 'cyan') {
                this.applyLiveAccent('indigo');
            } else if (theme === 'stripe' && this.selectedAccent === 'cyan') {
                this.applyLiveAccent('violet');
            } else if (theme === 'flow' && (this.selectedAccent === 'indigo' || this.selectedAccent === 'violet')) {
                this.applyLiveAccent('cyan');
            }
        },
        applyLiveAccent(accent) {
            this.selectedAccent = accent;
            document.documentElement.setAttribute('data-accent', accent);
            localStorage.setItem('dakhl_accent_color', accent);
        },
        applyLiveDensity(density) {
            this.selectedDensity = density;
            document.documentElement.setAttribute('data-ui-density', density);
            localStorage.setItem('dakhl_ui_density', density);
        },
        applyLiveMode(mode) {
            this.selectedMode = mode;
            document.documentElement.setAttribute('data-theme', mode);
            localStorage.setItem('theme', mode);
            window.dispatchEvent(new CustomEvent('theme:changed', { detail: { theme: mode } }));
        },
        syncBeforeSubmit() {
            localStorage.setItem('ui_theme', this.selectedTheme);
            localStorage.setItem('dakhl_accent_color', this.selectedAccent);
            localStorage.setItem('dakhl_ui_density', this.selectedDensity);
            localStorage.setItem('theme', this.selectedMode);
        }
    }" x-init="
        document.documentElement.setAttribute('data-ui-theme', selectedTheme);
        document.documentElement.setAttribute('data-accent', selectedAccent);
        document.documentElement.setAttribute('data-ui-density', selectedDensity);
        document.documentElement.setAttribute('data-theme', selectedMode);
    ">

        @if(session('success'))
            <div class="alert alert-success rounded-2xl shadow-sm text-sm font-medium flex items-center justify-between border border-success/30">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-white shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    <span>{{ session('success') }}</span>
                </div>
                <span class="badge badge-success badge-sm text-white font-mono">OK ✓</span>
            </div>
        @endif

        <!-- Header Banner -->
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 p-6 rounded-3xl border border-base-300 bg-base-100 shadow-sm transition-all">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-slate-900 border border-slate-800 p-2.5 shadow-lg flex items-center justify-center shrink-0">
                    <img src="/images/logo.svg" alt="Dakhl" class="w-full h-full object-contain">
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-2xl font-black text-base-content">{{ __('هویت بصری و شخصی‌سازی پوسته (Theme Settings)') }}</h1>
                        <span class="badge badge-primary badge-sm font-bold">نسل جدید</span>
                    </div>
                    <p class="text-xs text-base-content/60 mt-1">
                        {{ __('شخصی‌سازی سبک طراحی، تم روز و شب، رنگ شاخص سازمانی و تراکم اطلاعات دفاتر حسابداری دخل') }}
                    </p>
                </div>
            </div>

            <!-- Active Configuration Summary Pills -->
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <div class="badge badge-outline gap-1 font-mono text-[11px] py-3 px-3 rounded-xl border-base-300">
                    <span class="text-base-content/60">پوسته:</span>
                    <strong class="text-primary" x-text="selectedTheme === 'flow' ? 'Google Flow' : (selectedTheme === 'linear' ? 'Linear Obsidian' : 'Stripe Glass')"></strong>
                </div>
                <div class="badge badge-outline gap-1 font-mono text-[11px] py-3 px-3 rounded-xl border-base-300">
                    <span class="text-base-content/60">رنگ:</span>
                    <strong class="text-primary" x-text="selectedAccent === 'cyan' ? 'فیروزه‌ای' : (selectedAccent === 'emerald' ? 'زمردی' : (selectedAccent === 'indigo' ? 'کاربنی' : (selectedAccent === 'violet' ? 'بنفش' : 'تیتانیوم')))"></strong>
                </div>
                <div class="badge badge-outline gap-1 font-mono text-[11px] py-3 px-3 rounded-xl border-base-300">
                    <span class="text-base-content/60">حالت:</span>
                    <strong class="text-primary" x-text="selectedMode === 'dark' ? 'تیره 🌙' : 'روشن ☀️'"></strong>
                </div>
                <a href="{{ route('pages.showcase') }}" target="_blank" class="btn btn-ghost btn-sm rounded-xl text-xs gap-1 border border-base-300">
                    <span>✨</span> {{ __('آزمایشگاه') }}
                </a>
            </div>
        </div>

        <!-- Live Interactive Financial Sandbox / Preview Box -->
        <div class="p-6 rounded-3xl border border-primary/30 bg-base-100 shadow-md space-y-4 relative overflow-hidden transition-all duration-300">
            <div class="flex items-center justify-between border-b border-base-300/60 pb-3">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-primary animate-pulse"></span>
                    <h2 class="text-sm font-black text-base-content">{{ __('پیش‌نمایش زنده در لحظه (Interactive Live Sandbox)') }}</h2>
                    <span class="text-[11px] text-base-content/60 font-normal">{{ __('با کلیک بر روی هر گزینه، عناصر زیر و کل نرم‌افزار فوراً تغییر می‌کنند:') }}</span>
                </div>
                <div class="badge badge-primary badge-sm font-bold text-white">زنده (Live)</div>
            </div>

            <!-- Sandbox Mock Workspace -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-1">
                <!-- Sample Metric KPI -->
                <div class="p-4 rounded-2xl border border-base-300 bg-base-200/50 space-y-2">
                    <div class="flex items-center justify-between text-xs text-base-content/60">
                        <span>فروش خالص امروز</span>
                        <span class="badge badge-xs badge-success text-[9px]">متوازن</span>
                    </div>
                    <div class="text-xl font-black font-mono text-base-content">
                        ۴۸,۶۵۰,۰۰۰ <span class="text-xs font-normal text-base-content/60">تومان</span>
                    </div>
                    <div class="text-[11px] text-success font-medium flex items-center gap-1">
                        <span>↑ ۱۲.۴٪</span> <span>نسبت به روز گذشته</span>
                    </div>
                </div>

                <!-- Sample Ledger Balance & Action -->
                <div class="p-4 rounded-2xl border border-base-300 bg-base-200/50 space-y-3 flex flex-col justify-between">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-base-content">سند حسابداری #۱۰۹۴</span>
                        <span class="badge badge-primary badge-sm font-bold">تایید شده</span>
                    </div>
                    <div class="text-xs text-base-content/70 font-mono space-y-1">
                        <div class="flex justify-between"><span>بانک سامان:</span><span class="text-primary font-bold">۹۶۸,۰۰۰ بدهکار</span></div>
                        <div class="flex justify-between"><span>درآمد فروش:</span><span class="text-secondary font-bold">۹۶۸,۰۰۰ بستانکار</span></div>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm rounded-xl text-xs font-bold w-full shadow-sm">
                        <span>✓</span> ثبت در دفاتر مالی
                    </button>
                </div>

                <!-- Sample Micro Table with Density Effect -->
                <div class="p-4 rounded-2xl border border-base-300 bg-base-200/50 space-y-2 flex flex-col justify-between">
                    <div class="flex items-center justify-between text-xs text-base-content/60">
                        <span>نمونه جدول و تراکم سطور</span>
                        <span class="text-[10px] font-mono text-primary font-bold" x-text="selectedDensity === 'compact' ? 'حالت فشرده' : 'حالت جادار'"></span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table w-full text-xs">
                            <thead>
                                <tr class="text-base-content/60">
                                    <th>کد</th>
                                    <th>شرح</th>
                                    <th>وضعیت</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="font-mono font-bold">۱۰۱۰۱</td>
                                    <td>صندوق مرکزی</td>
                                    <td><span class="badge badge-xs badge-success">فعال</span></td>
                                </tr>
                                <tr>
                                    <td class="font-mono font-bold">۱۰۱۰۲</td>
                                    <td>بانک ملت</td>
                                    <td><span class="badge badge-xs badge-info">فعال</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('settings.appearance.update') }}" @submit="syncBeforeSubmit()" class="space-y-8">
            @csrf
            <input type="hidden" name="ui_theme" :value="selectedTheme">
            <input type="hidden" name="accent_color" :value="selectedAccent">
            <input type="hidden" name="ui_density" :value="selectedDensity">
            <input type="hidden" name="theme_mode" :value="selectedMode">

            <!-- Section 1: Choose Design Theme (3 Main Themes) -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-black text-base-content">{{ __('۱. انتخاب سبک پوسته اصلی (Core Design Theme)') }}</h2>
                        <p class="text-xs text-base-content/60">{{ __('روی هر پوسته کلیک کنید تا ویژگی‌های ساختاری و حاشیه‌های آن بلافاصله فعال شوند:') }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                    <!-- Theme 1: Google Flow -->
                    <div @click="applyLiveTheme('flow')"
                         :class="selectedTheme === 'flow' ? 'ring-2 ring-cyan-500 border-cyan-500 bg-slate-900 text-white shadow-xl shadow-cyan-500/10' : 'border-base-300 bg-base-100 text-base-content hover:border-cyan-500/50'"
                         class="cursor-pointer rounded-3xl border p-6 transition-all duration-300 relative overflow-hidden flex flex-col justify-between group">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="badge badge-sm badge-info font-bold text-white">سبک ۱ (Google Flow)</span>
                                <input type="radio" name="_theme_radio" value="flow" :checked="selectedTheme === 'flow'" @change="applyLiveTheme('flow')" class="radio radio-primary radio-sm">
                            </div>

                            <!-- Visual Mock Preview -->
                            <div class="h-36 bg-[#060911] rounded-2xl p-3 border border-slate-800 flex flex-col justify-between relative overflow-hidden">
                                <div class="absolute -right-10 -top-10 w-24 h-24 bg-cyan-500/20 rounded-full blur-xl pointer-events-none"></div>
                                <div class="flex items-center justify-between text-[10px] text-cyan-400">
                                    <span class="flex items-center gap-1 font-bold">✨ دستیار مالی صوتی</span>
                                    <span class="badge badge-xs badge-success text-[8px]">آنلاین</span>
                                </div>
                                <div class="p-2 rounded-xl bg-slate-900/80 border border-cyan-500/30 text-[10px] text-slate-300">
                                    «۲۰۰ تومن دادم علی» → سند خودکار
                                </div>
                                <div class="flex items-center justify-between text-[10px] font-mono">
                                    <span class="text-slate-400">فروش خالص:</span>
                                    <span class="text-emerald-400 font-bold">۴۸,۶۵۰,۰۰۰ ت</span>
                                </div>
                            </div>

                            <div>
                                <h3 class="font-black text-base">گوگل فلو (Google Flow Quantum)</h3>
                                <p class="text-xs text-base-content/70 mt-1 leading-relaxed">
                                    طراحی سیال، گرادیان‌های نئونی، کارت‌های نورانی و هماهنگی کامل با لوگوی جدید کوانتومی دخل.
                                </p>
                            </div>
                        </div>

                        <div class="pt-4 mt-4 border-t border-base-300/60 flex items-center justify-between text-xs font-bold text-cyan-500">
                            <span>پیش‌فرض پیشنهادی</span>
                            <span x-show="selectedTheme === 'flow'">انتخاب شده ✓</span>
                        </div>
                    </div>

                    <!-- Theme 2: Linear Obsidian -->
                    <div @click="applyLiveTheme('linear')"
                         :class="selectedTheme === 'linear' ? 'ring-2 ring-indigo-500 border-indigo-500 bg-[#0D1117] text-white shadow-xl shadow-indigo-500/10' : 'border-base-300 bg-base-100 text-base-content hover:border-indigo-500/50'"
                         class="cursor-pointer rounded-3xl border p-6 transition-all duration-300 relative overflow-hidden flex flex-col justify-between group">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="badge badge-sm badge-neutral font-bold text-white">سبک ۲ (Linear Obsidian)</span>
                                <input type="radio" name="_theme_radio" value="linear" :checked="selectedTheme === 'linear'" @change="applyLiveTheme('linear')" class="radio radio-primary radio-sm">
                            </div>

                            <!-- Visual Mock Preview -->
                            <div class="h-36 bg-[#0D1117] rounded-2xl p-3 border border-slate-800 flex flex-col justify-between font-mono">
                                <div class="flex items-center justify-between text-[10px] text-slate-400">
                                    <span>LEDGER // TRIAL_BALANCE</span>
                                    <span class="text-emerald-400 font-bold">BALANCED</span>
                                </div>
                                <div class="space-y-1 text-[9px] text-slate-300 border-y border-slate-800/80 py-1.5">
                                    <div class="flex justify-between"><span>10102 BANK_SAMAN</span><span class="text-emerald-400">968,000 DR</span></div>
                                    <div class="flex justify-between"><span>70101 SALES_REVENUE</span><span class="text-cyan-400">968,000 CR</span></div>
                                </div>
                                <div class="flex items-center justify-between text-[10px] text-slate-400">
                                    <span>DIFF: 0.00</span>
                                    <span class="badge badge-xs badge-outline text-[8px]">PRO</span>
                                </div>
                            </div>

                            <div>
                                <h3 class="font-black text-base">لینیر ابسیدین (Linear Minimal)</h3>
                                <p class="text-xs text-base-content/70 mt-1 leading-relaxed">
                                    سبک تیره مهندسی سوئیسی، خطوط مویی دقیق، تمرکز بسیار بالا روی سرعت ورود داده و ارقام حسابداری.
                                </p>
                            </div>
                        </div>

                        <div class="pt-4 mt-4 border-t border-base-300/60 flex items-center justify-between text-xs font-bold text-indigo-400">
                            <span>مناسب ثبت انبوه اسناد</span>
                            <span x-show="selectedTheme === 'linear'">انتخاب شده ✓</span>
                        </div>
                    </div>

                    <!-- Theme 3: Stripe Glassmorphism -->
                    <div @click="applyLiveTheme('stripe')"
                         :class="selectedTheme === 'stripe' ? 'ring-2 ring-violet-500 border-violet-500 bg-[#0B0F1E] text-white shadow-xl shadow-violet-500/10' : 'border-base-300 bg-base-100 text-base-content hover:border-violet-500/50'"
                         class="cursor-pointer rounded-3xl border p-6 transition-all duration-300 relative overflow-hidden flex flex-col justify-between group">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="badge badge-sm badge-secondary font-bold text-white">سبک ۳ (Stripe Glass)</span>
                                <input type="radio" name="_theme_radio" value="stripe" :checked="selectedTheme === 'stripe'" @change="applyLiveTheme('stripe')" class="radio radio-primary radio-sm">
                            </div>

                            <!-- Visual Mock Preview -->
                            <div class="h-36 bg-gradient-to-tr from-indigo-950/60 via-purple-950/40 to-slate-950 rounded-2xl p-3 border border-white/10 flex flex-col justify-between relative backdrop-blur-md">
                                <div class="flex items-center justify-between text-[10px]">
                                    <span class="text-violet-300 font-bold">✨ فاکتور رسمی و پوز</span>
                                    <span class="badge badge-xs badge-accent text-[8px]">PC-POS</span>
                                </div>
                                <div class="p-2 rounded-xl bg-white/5 border border-white/10 text-[10px] text-white flex justify-between items-center">
                                    <span>مبلغ تسویه آنلاین:</span>
                                    <span class="font-bold text-violet-300 font-mono">۹۶۸,۰۰۰ تومان</span>
                                </div>
                                <div class="flex items-center justify-between text-[9px] text-slate-400">
                                    <span>وضعیت کارپوشه:</span>
                                    <span class="text-emerald-400">ارسال موفق به مودیان</span>
                                </div>
                            </div>

                            <div>
                                <h3 class="font-black text-base">استرایپ گلس (Stripe Glassmorphism)</h3>
                                <p class="text-xs text-base-content/70 mt-1 leading-relaxed">
                                    پنل‌های شیشه‌ای ترنسپرنت (Frosted Glass)، گرادیان‌های لوکس بنفش و فیروزه‌ای و پرستیژ بصری فوق‌العاده.
                                </p>
                            </div>
                        </div>

                        <div class="pt-4 mt-4 border-t border-base-300/60 flex items-center justify-between text-xs font-bold text-violet-400">
                            <span>جذابیت بصری بالا</span>
                            <span x-show="selectedTheme === 'stripe'">انتخاب شده ✓</span>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Section 2: Light / Dark Mode Toggle -->
            <div class="p-6 rounded-3xl border border-base-300 bg-base-100 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-black text-base-content flex items-center gap-2">
                            <span>🌓</span> {{ __('۲. حالت روشنایی سامانه (Light / Dark Mode)') }}
                        </h2>
                        <p class="text-xs text-base-content/60 mt-0.5">
                            {{ __('انتخاب بین حالت روز (خوانایی بالا در محیط‌های اداری) و حالت شب (کاهش خستگی چشم و کنتراست عمیق):') }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                    <div @click="applyLiveMode('light')"
                         :class="selectedMode === 'light' ? 'border-primary ring-2 ring-primary/40 bg-base-200' : 'border-base-300 hover:border-base-content/30'"
                         class="cursor-pointer p-4 rounded-2xl border flex items-center justify-between transition-all">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center text-lg font-bold">
                                ☀️
                            </div>
                            <div>
                                <div class="font-bold text-sm text-base-content">حالت روز (Light Mode)</div>
                                <div class="text-[11px] text-base-content/60">پس‌زمینه سفید و طوسی روشن، مناسب کار در طول روز</div>
                            </div>
                        </div>
                        <input type="radio" name="_mode_radio" value="light" :checked="selectedMode === 'light'" @change="applyLiveMode('light')" class="radio radio-primary radio-sm">
                    </div>

                    <div @click="applyLiveMode('dark')"
                         :class="selectedMode === 'dark' ? 'border-primary ring-2 ring-primary/40 bg-slate-900 text-white' : 'border-base-300 hover:border-base-content/30'"
                         class="cursor-pointer p-4 rounded-2xl border flex items-center justify-between transition-all">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-slate-800 text-indigo-400 flex items-center justify-center text-lg font-bold">
                                🌙
                            </div>
                            <div>
                                <div class="font-bold text-sm">حالت شب (Dark Mode)</div>
                                <div class="text-[11px] opacity-70">پس‌زمینه تیره مخملی، مناسب کار شبانه و جلوگیری از خستگی چشم</div>
                            </div>
                        </div>
                        <input type="radio" name="_mode_radio" value="dark" :checked="selectedMode === 'dark'" @change="applyLiveMode('dark')" class="radio radio-primary radio-sm">
                    </div>
                </div>
            </div>

            <!-- Section 3: Color Accent & Typography -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <!-- Accent Color Picker -->
                <div class="p-6 rounded-3xl border border-base-300 bg-base-100 space-y-4">
                    <h3 class="font-black text-base text-base-content flex items-center gap-2">
                        <span>🎨</span> {{ __('۳. رنگ شاخص و هایلایت (Accent Color)') }}
                    </h3>
                    <p class="text-xs text-base-content/60">
                        {{ __('رنگ کلیدهای اصلی، نشانگرها، حاشیه‌های فعال و نمودارها:') }}
                    </p>

                    <div class="grid grid-cols-5 gap-3 pt-2">
                        <button type="button" @click="applyLiveAccent('cyan')" :class="selectedAccent === 'cyan' ? 'ring-2 ring-cyan-500 scale-105 shadow-md shadow-cyan-500/20' : 'opacity-70 hover:opacity-100'" class="p-3 rounded-2xl bg-cyan-500 text-white font-bold text-xs flex flex-col items-center gap-1.5 transition">
                            <span class="w-4 h-4 rounded-full bg-white/40 shadow-inner"></span>
                            <span>فیروزه‌ای</span>
                        </button>
                        <button type="button" @click="applyLiveAccent('emerald')" :class="selectedAccent === 'emerald' ? 'ring-2 ring-emerald-500 scale-105 shadow-md shadow-emerald-500/20' : 'opacity-70 hover:opacity-100'" class="p-3 rounded-2xl bg-emerald-500 text-white font-bold text-xs flex flex-col items-center gap-1.5 transition">
                            <span class="w-4 h-4 rounded-full bg-white/40 shadow-inner"></span>
                            <span>زمردی</span>
                        </button>
                        <button type="button" @click="applyLiveAccent('indigo')" :class="selectedAccent === 'indigo' ? 'ring-2 ring-indigo-500 scale-105 shadow-md shadow-indigo-500/20' : 'opacity-70 hover:opacity-100'" class="p-3 rounded-2xl bg-indigo-600 text-white font-bold text-xs flex flex-col items-center gap-1.5 transition">
                            <span class="w-4 h-4 rounded-full bg-white/40 shadow-inner"></span>
                            <span>کاربنی</span>
                        </button>
                        <button type="button" @click="applyLiveAccent('violet')" :class="selectedAccent === 'violet' ? 'ring-2 ring-violet-500 scale-105 shadow-md shadow-violet-500/20' : 'opacity-70 hover:opacity-100'" class="p-3 rounded-2xl bg-violet-600 text-white font-bold text-xs flex flex-col items-center gap-1.5 transition">
                            <span class="w-4 h-4 rounded-full bg-white/40 shadow-inner"></span>
                            <span>بنفش</span>
                        </button>
                        <button type="button" @click="applyLiveAccent('slate')" :class="selectedAccent === 'slate' ? 'ring-2 ring-slate-500 scale-105 shadow-md shadow-slate-500/20' : 'opacity-70 hover:opacity-100'" class="p-3 rounded-2xl bg-slate-700 text-white font-bold text-xs flex flex-col items-center gap-1.5 transition">
                            <span class="w-4 h-4 rounded-full bg-white/40 shadow-inner"></span>
                            <span>تیتانیوم</span>
                        </button>
                    </div>
                </div>

                <!-- UI Density & Layout Compactness -->
                <div class="p-6 rounded-3xl border border-base-300 bg-base-100 space-y-4">
                    <h3 class="font-black text-base text-base-content flex items-center gap-2">
                        <span>📏</span> {{ __('۴. تراکم چیدمان و ارتفاع سطرها (UI Density)') }}
                    </h3>
                    <p class="text-xs text-base-content/60">
                        {{ __('تنظیم فاصله سطرها در دفاتر حسابداری، جداول فاکتورها و فرم‌ها:') }}
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                        <div @click="applyLiveDensity('comfortable')" :class="selectedDensity === 'comfortable' ? 'border-primary bg-primary/5 text-primary ring-2 ring-primary/30' : 'border-base-300 text-base-content'" class="cursor-pointer p-4 rounded-2xl border flex items-center justify-between transition-all">
                            <div>
                                <div class="font-bold text-xs">استاندارد و جادار (Comfortable)</div>
                                <div class="text-[10px] text-base-content/60 mt-0.5">فاصله‌گذاری روان و خوانایی مطلوب اسناد</div>
                            </div>
                            <input type="radio" value="comfortable" :checked="selectedDensity === 'comfortable'" @change="applyLiveDensity('comfortable')" class="radio radio-primary radio-xs">
                        </div>

                        <div @click="applyLiveDensity('compact')" :class="selectedDensity === 'compact' ? 'border-primary bg-primary/5 text-primary ring-2 ring-primary/30' : 'border-base-300 text-base-content'" class="cursor-pointer p-4 rounded-2xl border flex items-center justify-between transition-all">
                            <div>
                                <div class="font-bold text-xs">فشرده حسابداری (Compact)</div>
                                <div class="text-[10px] text-base-content/60 mt-0.5">نمایش حداکثر سطرها در یک صفحه بدون اسکرول</div>
                            </div>
                            <input type="radio" value="compact" :checked="selectedDensity === 'compact'" @change="applyLiveDensity('compact')" class="radio radio-primary radio-xs">
                        </div>
                    </div>
                </div>

            </div>

            <!-- Submit Button Bar -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 p-5 rounded-3xl border border-base-300 bg-base-100 shadow-sm sticky bottom-4 backdrop-blur-md bg-base-100/90 z-20">
                <div class="text-xs text-base-content/70 flex items-center gap-2">
                    <span class="text-base">💡</span>
                    <span>{{ __('با کلیک بر روی ذخیره، پوسته انتخابی بلافاصله در تمام سیستم، مرورگرها و جلسات کاری شما ذخیره و ماندگار خواهد شد.') }}</span>
                </div>

                <div class="flex items-center gap-3 w-full sm:w-auto">
                    <button type="submit" class="btn btn-primary rounded-2xl px-8 font-bold shadow-lg shadow-primary/25 text-xs w-full sm:w-auto">
                        <span>💾</span> {{ __('ذخیره و اعمال بر کل سیستم') }}
                    </button>
                </div>
            </div>

        </form>

    </div>
</x-app-layout>
