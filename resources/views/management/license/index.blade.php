<x-app-layout>
    <x-slot name="title">{{ __('مدیریت لایسنس و حقوق اشتراک ماژولار') }}</x-slot>

    <div class="space-y-6" x-data="licenseManager()">

        <!-- Breadcrumb & Top Bar -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-base-100 p-5 rounded-2xl border border-base-300 shadow-sm">
            <div>
                <div class="flex items-center gap-2 text-xs text-base-content/60 mb-1">
                    <a href="/" class="hover:text-primary">{{ __('پیشخوان') }}</a>
                    <span>/</span>
                    <a href="{{ route('configs.index') }}" class="hover:text-primary">{{ __('مدیریت و پیکربندی') }}</a>
                    <span>/</span>
                    <span class="text-base-content font-medium">{{ __('لایسنس و ماژول‌ها') }}</span>
                </div>
                <h1 class="text-xl md:text-2xl font-black text-base-content flex items-center gap-2">
                    <span class="text-2xl">🔐</span>
                    {{ __('سیستم صدور، ارزیابی و فعال‌سازی لایسنس ماژولار دخل') }}
                </h1>
            </div>

            <!-- Actions & Hardware Fingerprint Pill -->
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('pricing') }}" target="_blank" class="btn btn-outline btn-sm rounded-xl text-xs gap-1">
                    <span>🌐</span>
                    <span>{{ __('پورتال تعرفه‌ها') }}</span>
                </a>

                @if (auth()->user()->isSuperAdmin() || app()->environment('local', 'testing'))
                    <button type="button" onclick="generator_modal.showModal()" class="btn btn-primary btn-sm rounded-xl text-xs gap-1 font-bold shadow-md shadow-primary/20">
                        <span>🛠️</span>
                        <span>{{ __('کارگاه صدور لایسنس و افزونه') }}</span>
                    </button>
                @endif

                <div class="flex items-center gap-2 bg-base-200/70 border border-base-300 px-3.5 py-1.5 rounded-xl text-xs">
                    <span class="text-base-content/60 font-medium">{{ __('شناسه سخت‌افزاری:') }}</span>
                    <span class="font-mono font-bold text-primary tabular-nums" id="hwFingerprint">{{ $hardwareFingerprint }}</span>
                    <button type="button"
                            @click="copyFingerprint('{{ $hardwareFingerprint }}')"
                            class="btn btn-ghost btn-xs px-2 text-primary hover:bg-primary/10">
                        <span x-text="copied ? 'کپی شد! ✓' : 'کپی'"></span>
                    </button>
                </div>
            </div>
        </div>

        @if (session('generated_key'))
            <div class="bg-primary/10 border-2 border-primary/30 rounded-3xl p-5 shadow-sm space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-primary flex items-center gap-1.5">
                        <span>🔑</span> {{ __('کلید لایسنس جدید صادر شد:') }}
                    </span>
                    <button type="button" @click="copyFingerprint('{{ session('generated_key') }}')" class="btn btn-primary btn-xs rounded-lg font-bold">
                        <span x-text="copied ? 'کپی شد! ✓' : 'کپی کلید لایسنس'"></span>
                    </button>
                </div>
                <div class="bg-base-100 p-3 rounded-xl border border-base-300 font-mono text-xs break-all text-base-content select-all dir-ltr text-left">
                    {{ session('generated_key') }}
                </div>
            </div>
        @endif

        @if (session('warning'))
            <div class="alert alert-warning rounded-2xl shadow-sm text-sm">
                <svg class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>{{ session('warning') }}</span>
            </div>
        @endif

        @if (session('success'))
            <div class="alert alert-success rounded-2xl shadow-sm text-sm text-white font-medium">
                <svg class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-error rounded-2xl shadow-sm text-sm text-white font-medium">
                <svg class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <!-- Active License Card & Key Activation Form -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Active License Status Card -->
            <div class="lg:col-span-1 bg-gradient-to-br from-base-100 via-base-100 to-primary/5 border border-base-300 rounded-3xl p-6 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-xs font-bold uppercase tracking-wider text-base-content/60">{{ __('وضعیت لایسنس فعال') }}</span>
                        <span class="badge {{ $license->getStatusBadgeClass() }} py-1 px-3 text-xs">
                            {{ $license->getStatusLabel() }}
                        </span>
                    </div>

                    <div class="space-y-1 mb-6">
                        <h2 class="text-2xl font-black text-base-content">{{ $license->getEditionLabel() }}</h2>
                        <div class="text-xs text-base-content/70">
                            {{ __('مالک امتیاز:') }} <span class="font-bold text-base-content">{{ $license->customer_name }}</span>
                        </div>
                    </div>

                    <!-- Quota & Counters Grid -->
                    <div class="grid grid-cols-2 gap-3 mb-6">
                        <div class="bg-base-200/50 p-3 rounded-2xl border border-base-300/50">
                            <div class="text-[11px] text-base-content/60">{{ __('اعتبار باقی‌مانده') }}</div>
                            <div class="text-lg font-black text-primary tabular-nums mt-0.5">
                                @if ($license->isLifetime())
                                    {{ __('دائمی (نامحدود)') }}
                                @else
                                    {{ $daysRemaining }} {{ __('روز') }}
                                @endif
                            </div>
                        </div>

                        <div class="bg-base-200/50 p-3 rounded-2xl border border-base-300/50">
                            <div class="text-[11px] text-base-content/60">{{ __('سقف تعداد شرکت‌ها') }}</div>
                            <div class="text-lg font-black text-base-content tabular-nums mt-0.5">
                                {{ $license->max_companies === -1 ? __('نامحدود') : $license->max_companies . ' شرکت' }}
                            </div>
                        </div>

                        <div class="bg-base-200/50 p-3 rounded-2xl border border-base-300/50">
                            <div class="text-[11px] text-base-content/60">{{ __('سقف کاربران همزمان') }}</div>
                            <div class="text-lg font-black text-base-content tabular-nums mt-0.5">
                                {{ $license->max_users === -1 ? __('نامحدود') : $license->max_users . ' کاربر' }}
                            </div>
                        </div>

                        <div class="bg-base-200/50 p-3 rounded-2xl border border-base-300/50">
                            <div class="text-[11px] text-base-content/60">{{ __('صندوق‌ها و دستگاه‌ها') }}</div>
                            <div class="text-lg font-black text-base-content tabular-nums mt-0.5">
                                {{ $license->max_devices === -1 ? __('نامحدود') : $license->max_devices . ' پایانه' }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-base-200/70 text-xs text-base-content/50 flex items-center justify-between">
                    <span>{{ __('تاریخ صدور:') }} {{ formatDate($license->issued_at) }}</span>
                    <span>{{ __('انقضا:') }} {{ $license->expires_at ? formatDate($license->expires_at) : 'دائمی' }}</span>
                </div>
            </div>

            <!-- License Key Activation Box -->
            <div class="lg:col-span-2 bg-base-100 border border-base-300 rounded-3xl p-6 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-lg">🔑</span>
                        <h3 class="text-lg font-bold text-base-content">{{ __('فعال‌سازی و ارتقای لایسنس جدید') }}</h3>
                    </div>
                    <p class="text-xs text-base-content/60 mb-4">
                        {{ __('کد لایسنس دریافتی از تیم فروش دخل یا کارگزار رسمی خود را در کادر زیر جای‌گذاری نموده و دکمه فعال‌سازی را فشار دهید. فعال‌سازی به صورت ۱۰۰٪ آفلاین و با امضای دیجیتال نامتقارن اعتبارسنجی می‌شود.') }}
                    </p>

                    <form action="{{ route('license.activate') }}" method="POST" class="space-y-4">
                        @csrf
                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-base-content/70">{{ __('کلید رمزنگاری لایسنس (DAKHL-LIC-v1...):') }}</label>
                            <textarea name="license_key"
                                      rows="3"
                                      placeholder="DAKHL-LIC-v1.eyJlZGl0aW9uIjoicHJvZmVzc2lvbmFs...c4b2a8f..."
                                      required
                                      class="textarea textarea-bordered w-full rounded-2xl font-mono text-xs focus:outline-primary bg-base-200/30 leading-relaxed dir-ltr"></textarea>
                        </div>

                        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2">
                            <div class="text-xs text-base-content/60 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-success"></span>
                                <span>{{ __('بدون نیاز به اتصال دائم اینترنت (Offline-First)') }}</span>
                            </div>

                            <button type="submit" class="btn btn-primary rounded-xl px-6 font-bold shadow-md shadow-primary/20 w-full sm:w-auto">
                                <span>{{ __('اعتبارسنجی و فعال‌سازی لایسنس') }}</span>
                                <span>↵</span>
                            </button>
                        </div>
                    </form>
                </div>

                @if (auth()->user()->isSuperAdmin() || app()->environment('local', 'testing'))
                    <!-- Development / Admin Demo Quick Issuer -->
                    <div class="mt-6 pt-4 border-t border-dashed border-base-300">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold text-base-content/70 flex items-center gap-1">
                                <span>⚡</span> {{ __('صدور سریع لایسنس آزمایشی (ویژه راهبر سیستم / دمو)') }}
                            </span>
                        </div>
                        <form action="{{ route('license.generate-demo') }}" method="POST" class="flex flex-wrap items-center gap-2">
                            @csrf
                            <select name="edition" class="select select-bordered select-xs rounded-lg text-xs">
                                <option value="enterprise">{{ __('نسخه طلایی سازمانی (تمام ۱۶ ماژول)') }}</option>
                                <option value="professional">{{ __('نسخه نقره‌ای حرفه‌ای') }}</option>
                                <option value="basic">{{ __('نسخه برنزی پایه') }}</option>
                            </select>

                            <select name="valid_days" class="select select-bordered select-xs rounded-lg text-xs">
                                <option value="365">{{ __('اعتبار ۱ ساله (۳۶۵ روز)') }}</option>
                                <option value="30">{{ __('اعتبار ۱ ماهه (۳۰ روز)') }}</option>
                                <option value="14">{{ __('اعتبار آزمایشی (۱۴ روز)') }}</option>
                            </select>

                            <button type="submit" class="btn btn-outline btn-xs rounded-lg text-xs font-medium">
                                {{ __('صدور و فعال‌سازی فوری دمو') }}
                            </button>
                        </form>
                    </div>
                @endif
            </div>

        </div>

        <!-- Matrix of 16 Modular Capabilities -->
        <div class="bg-base-100 border border-base-300 rounded-3xl p-6 shadow-sm">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-6">
                <div>
                    <h3 class="text-lg font-black text-base-content flex items-center gap-2">
                        <span>🧩</span>
                        {{ __('ماتریس ماژول‌ها و حقوق دسترسی فعال') }}
                    </h3>
                    <p class="text-xs text-base-content/60 mt-0.5">
                        {{ __('وضعیت دسترسی نرم‌افزار به قابلیت‌های ۱۶ گانه بر اساس لایسنس فعال فعلی') }}
                    </p>
                </div>

                <div class="flex items-center gap-4 text-xs font-medium">
                    <span class="flex items-center gap-1.5 text-success">
                        <span class="w-2.5 h-2.5 rounded-full bg-success"></span>
                        {{ __('فعال و دارای مجوز') }}
                    </span>
                    <span class="flex items-center gap-1.5 text-base-content/40">
                        <span class="w-2.5 h-2.5 rounded-full bg-base-300"></span>
                        {{ __('قفل شده / نیازمند ارتقا') }}
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach ($modules as $modKey => $mod)
                    @php
                        $isLicensed = $license->hasModule($modKey);
                        $isHighlighted = session('highlight_module') === $modKey;
                    @endphp
                    <div class="relative p-4 rounded-2xl border transition-all {{ $isHighlighted ? 'ring-2 ring-warning' : '' }} {{ $isLicensed ? 'border-primary/20 bg-primary/[0.02] shadow-sm' : 'border-base-200 bg-base-200/20 opacity-70' }}">
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <span class="text-2xl">{{ $mod['icon'] }}</span>
                            @if ($isLicensed)
                                <span class="badge badge-success badge-sm text-white font-bold py-1 px-2 text-[10px]">
                                    ✓ {{ __('فعال') }}
                                </span>
                            @else
                                <span class="badge badge-ghost badge-sm text-base-content/60 font-medium py-1 px-2 text-[10px]">
                                    🔒 {{ __('نیازمند ارتقا') }}
                                </span>
                            @endif
                        </div>

                        <h4 class="font-bold text-sm text-base-content mb-1">{{ $mod['title'] }}</h4>
                        <p class="text-[11px] text-base-content/60 leading-relaxed mb-3">{{ $mod['description'] }}</p>

                        <div class="flex items-center justify-between text-[10px] text-base-content/50 pt-2 border-t border-base-200">
                            <span>{{ $mod['category'] }}</span>
                            @if ($mod['is_core'])
                                <span class="text-primary font-bold">{{ __('ماژول پایه') }}</span>
                            @else
                                <span>{{ __('ماژول ماژولار') }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Commercial Pricing & Subscription Catalog -->
        <div class="bg-gradient-to-tr from-base-100 via-base-100 to-primary/5 border border-base-300 rounded-3xl p-6 shadow-sm">
            <div class="text-center max-w-xl mx-auto mb-8">
                <span class="text-xs font-bold text-primary uppercase tracking-wider">{{ __('پلن‌های تجاری و تجاری‌سازی') }}</span>
                <h3 class="text-2xl font-black text-base-content mt-1">{{ __('بسته‌های اشتراک تجاری و فروش ماژولار دخل') }}</h3>
                <p class="text-xs text-base-content/60 mt-1">
                    {{ __('برای کسب‌وکارهای در حال رشد، پلن متناسب با نیازهای خود را انتخاب کنید و تنها به ازای قابلیت‌هایی که استفاده می‌کنید هزینه بپردازید.') }}
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-6xl mx-auto">
                @foreach ($plans as $pKey => $plan)
                    <div class="bg-base-100 rounded-3xl p-6 border {{ $plan['popular'] ? 'border-primary ring-2 ring-primary/20 shadow-xl' : 'border-base-300 shadow-sm' }} flex flex-col justify-between relative overflow-hidden">
                        @if ($plan['popular'])
                            <div class="absolute top-4 left-4">
                                <span class="badge badge-primary font-bold text-xs py-1 px-2.5 shadow-sm">{{ $plan['badge'] }}</span>
                            </div>
                        @endif

                        <div>
                            <h4 class="text-lg font-bold text-base-content mb-1">{{ $plan['name'] }}</h4>
                            <p class="text-xs text-base-content/60 mb-4 min-h-[32px]">{{ $plan['description'] }}</p>

                            <div class="py-4 border-y border-base-200 mb-4">
                                <div class="flex items-baseline gap-1">
                                    <span class="text-2xl font-black text-base-content tabular-nums">{{ $plan['price'] }}</span>
                                    <span class="text-xs text-base-content/60">{{ __('تومان') }}</span>
                                    <span class="text-xs text-base-content/40">/ {{ $plan['period'] }}</span>
                                </div>
                            </div>

                            <div class="space-y-2 mb-6">
                                <div class="text-xs font-bold text-base-content/70 mb-2">{{ __('امکانات موجود در این پکیج:') }}</div>
                                <div class="text-xs text-base-content/80 flex items-center gap-2">
                                    <span class="text-success font-bold">✓</span>
                                    <span>{{ $plan['max_companies'] === -1 ? __('نامحدود شرکت') : $plan['max_companies'] . ' شرکت مجزا' }}</span>
                                </div>
                                <div class="text-xs text-base-content/80 flex items-center gap-2">
                                    <span class="text-success font-bold">✓</span>
                                    <span>{{ $plan['max_users'] === -1 ? __('نامحدود کاربر همزمان') : $plan['max_users'] . ' کاربر همزمان' }}</span>
                                </div>

                                @if ($plan['modules'] === ['*'])
                                    <div class="text-xs text-primary font-bold flex items-center gap-2">
                                        <span class="text-success font-bold">✓</span>
                                        <span>{{ __('دسترسی کامل به تمامی ۱۶ ماژول سیستم') }}</span>
                                    </div>
                                @else
                                    @foreach ($plan['modules'] as $mKey)
                                        @if (isset($modules[$mKey]))
                                            <div class="text-xs text-base-content/70 flex items-center gap-2">
                                                <span class="text-success font-bold">✓</span>
                                                <span>{{ $modules[$mKey]['title'] }}</span>
                                            </div>
                                        @endif
                                    @endforeach
                                @endif
                            </div>
                        </div>

                        <div class="pt-4 border-t border-base-200">
                            <a href="tel:02188884422" class="btn {{ $plan['popular'] ? 'btn-primary' : 'btn-outline' }} btn-sm w-full rounded-xl font-bold">
                                <span>{{ __('سفارش و تمدید اشتراک') }}</span>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Commercial Support Banner -->
            <div class="mt-8 pt-6 border-t border-base-200 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-base-content/70 max-w-4xl mx-auto">
                <div class="flex items-center gap-2">
                    <span class="text-lg">📞</span>
                    <span>{{ __('واحد فروش و امور نمایندگان دخل:') }} <strong class="text-base-content font-mono font-bold">۰۲۱-۸۸۸۸۴۴۲۲</strong></span>
                </div>
                <div>
                    <span>{{ __('پشتیبانی آنلاین تلگرام و بله:') }} <strong class="text-primary font-mono">@DakhlERP_Sales</strong></span>
                </div>
            </div>
        </div>

        <!-- License Generator & Add-on Studio Modal -->
        <dialog id="generator_modal" class="modal">
            <div class="modal-box max-w-3xl rounded-3xl p-6" x-data="{
                issueType: 'base',
                selectedEdition: 'professional',
                hwLockMode: 'current',
                currentHw: '{{ $hardwareFingerprint }}',
                customHw: '',
                validityDays: '365',
                allModules: @js($modules),
                plans: @js($plans),
                selectedModules: [],
                init() {
                    this.syncPlanModules();
                },
                syncPlanModules() {
                    if (this.issueType === 'base' && this.plans[this.selectedEdition]) {
                        this.selectedModules = [...this.plans[this.selectedEdition].modules];
                    }
                },
                getFinalHw() {
                    if (this.hwLockMode === 'current') return this.currentHw;
                    if (this.hwLockMode === 'custom') return this.customHw;
                    return '*';
                }
            }">
                <form method="dialog">
                    <button class="btn btn-sm btn-circle btn-ghost absolute right-4 top-4">✕</button>
                </form>

                <div class="flex items-center gap-3 mb-4">
                    <div class="w-12 h-12 rounded-2xl bg-primary/10 text-primary flex items-center justify-center text-2xl">
                        🛠️
                    </div>
                    <div>
                        <h3 class="text-lg font-black text-base-content">{{ __('کارگاه تخصصی صدور لایسنس و کدهای افزونه') }}</h3>
                        <p class="text-xs text-base-content/60">{{ __('صدور لایسنس پکیج کامل یا کد فعال‌سازی تک‌ماژول با امضای دیجیتال غیرقابل جعل') }}</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('license.generate-custom') }}" class="space-y-4">
                    @csrf

                    <!-- Toggle Base vs Addon -->
                    <div class="grid grid-cols-2 gap-2 p-1 bg-base-200/60 rounded-2xl">
                        <button type="button"
                                @click="issueType = 'base'; syncPlanModules()"
                                :class="issueType === 'base' ? 'bg-base-100 text-primary font-bold shadow-sm' : 'text-base-content/60'"
                                class="py-2 rounded-xl text-xs transition">
                            📦 {{ __('صدور لایسنس پکیج کامل (Base License)') }}
                        </button>
                        <button type="button"
                                @click="issueType = 'addon'; selectedModules = ['tax_moadian']"
                                :class="issueType === 'addon' ? 'bg-base-100 text-primary font-bold shadow-sm' : 'text-base-content/60'"
                                class="py-2 rounded-xl text-xs transition">
                            🧩 {{ __('صدور کد فعال‌سازی افزونه (Add-on Feature Key)') }}
                        </button>
                    </div>

                    <input type="hidden" name="type" :value="issueType" />

                    <!-- Common Inputs -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="label"><span class="label-text text-xs font-bold">{{ __('نام مشتری / شرکت:') }}</span></label>
                            <input type="text" name="customer_name" required value="مشتری رسمی دخل" class="input input-bordered input-sm w-full rounded-xl" />
                        </div>
                        <div>
                            <label class="label"><span class="label-text text-xs font-bold">{{ __('کد ملی / شناسه ملی اقتصادی:') }}</span></label>
                            <input type="text" name="customer_national_id" placeholder="اختیاری" class="input input-bordered input-sm w-full rounded-xl font-mono" />
                        </div>
                    </div>

                    <!-- Addon Name if Addon Mode -->
                    <div x-show="issueType === 'addon'">
                        <label class="label"><span class="label-text text-xs font-bold">{{ __('عنوان افزونه:') }}</span></label>
                        <input type="text" name="addon_name" value="افزونه ماژولار سامانه مودیان" class="input input-bordered input-sm w-full rounded-xl" />
                    </div>

                    <!-- Base Edition Selection -->
                    <div x-show="issueType === 'base'" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="label"><span class="label-text text-xs font-bold">{{ __('پکیج تجاری:') }}</span></label>
                            <select name="edition" class="select select-bordered select-sm w-full rounded-xl" x-model="selectedEdition" @change="syncPlanModules()">
                                <option value="basic">{{ __('نسخه برنزی (پایه اصناف خرد)') }}</option>
                                <option value="professional">{{ __('نسخه نقره‌ای (رشد و بازرگانی)') }}</option>
                                <option value="enterprise">{{ __('نسخه طلایی (سازمانی نامحدود)') }}</option>
                                <option value="custom">{{ __('نسخه سفارشی (انتخاب دستی ماژول‌ها)') }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="label"><span class="label-text text-xs font-bold">{{ __('مدت اعتبار لایسنس:') }}</span></label>
                            <select name="valid_days" class="select select-bordered select-sm w-full rounded-xl" x-model="validityDays">
                                <option value="365">{{ __('۱ ساله (۳۶۵ روز)') }}</option>
                                <option value="180">{{ __('۶ ماهه (۱۸۰ روز)') }}</option>
                                <option value="90">{{ __('۳ ماهه (۹۰ روز)') }}</option>
                                <option value="30">{{ __('۱ ماهه (۳۰ روز)') }}</option>
                                <option value="0">{{ __('لایسنس دائمی (بدون انقضا)') }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- Addon validity if Addon Mode -->
                    <div x-show="issueType === 'addon'" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="label"><span class="label-text text-xs font-bold">{{ __('مدت اعتبار افزونه:') }}</span></label>
                            <select name="valid_days" class="select select-bordered select-sm w-full rounded-xl">
                                <option value="365">{{ __('۱ ساله (۳۶۵ روز)') }}</option>
                                <option value="0">{{ __('دائمی (همگام با لایسنس پایه)') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label"><span class="label-text text-xs font-bold">{{ __('افزودن سقف کاربران:') }}</span></label>
                            <input type="number" name="add_users" value="0" min="0" class="input input-bordered input-sm w-full rounded-xl font-mono" />
                        </div>
                    </div>

                    <!-- Hardware Lock Selection -->
                    <div class="p-3 bg-base-200/50 rounded-2xl space-y-2">
                        <label class="block text-xs font-bold text-base-content/80">{{ __('قفل سخت‌افزاری دستگاه (Node-Lock):') }}</label>
                        <div class="grid grid-cols-3 gap-2">
                            <label class="btn btn-xs rounded-lg flex items-center gap-1 cursor-pointer" :class="hwLockMode === 'current' ? 'btn-primary' : 'btn-ghost'">
                                <input type="radio" x-model="hwLockMode" value="current" class="hidden" />
                                <span>💻 {{ __('سیستم فعلی') }}</span>
                            </label>
                            <label class="btn btn-xs rounded-lg flex items-center gap-1 cursor-pointer" :class="hwLockMode === 'custom' ? 'btn-primary' : 'btn-ghost'">
                                <input type="radio" x-model="hwLockMode" value="custom" class="hidden" />
                                <span>✏️ {{ __('کد دلخواه مشتری') }}</span>
                            </label>
                            <label class="btn btn-xs rounded-lg flex items-center gap-1 cursor-pointer" :class="hwLockMode === 'unlocked' ? 'btn-primary' : 'btn-ghost'">
                                <input type="radio" x-model="hwLockMode" value="unlocked" class="hidden" />
                                <span>🔓 {{ __('بدون قفل (*)') }}</span>
                            </label>
                        </div>

                        <div x-show="hwLockMode === 'current'" class="text-[11px] font-mono text-primary bg-base-100 p-2 rounded-xl border border-base-200">
                            {{ $hardwareFingerprint }}
                        </div>

                        <div x-show="hwLockMode === 'custom'">
                            <input type="text" x-model="customHw" placeholder="DAKHL-XXXX-XXXX-XXXX-XXXX" class="input input-bordered input-xs w-full rounded-xl font-mono text-left" dir="ltr" />
                        </div>

                        <input type="hidden" name="hardware_fingerprint" :value="getFinalHw()" />
                    </div>

                    <!-- Quotas if Base -->
                    <div x-show="issueType === 'base'" class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="label"><span class="label-text text-[11px] font-bold">{{ __('سقف شرکت:') }}</span></label>
                            <input type="number" name="max_companies" value="1" min="1" class="input input-bordered input-xs w-full rounded-xl font-mono" />
                        </div>
                        <div>
                            <label class="label"><span class="label-text text-[11px] font-bold">{{ __('سقف کاربر:') }}</span></label>
                            <input type="number" name="max_users" value="2" min="1" class="input input-bordered input-xs w-full rounded-xl font-mono" />
                        </div>
                        <div>
                            <label class="label"><span class="label-text text-[11px] font-bold">{{ __('سقف پایانه پوز:') }}</span></label>
                            <input type="number" name="max_devices" value="1" min="1" class="input input-bordered input-xs w-full rounded-xl font-mono" />
                        </div>
                    </div>

                    <!-- 16 Modules Selection Grid -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold text-base-content/80">{{ __('انتخاب ماژول‌های فعال:') }}</span>
                            <div class="space-x-1 space-x-reverse text-[11px]">
                                <button type="button" @click="selectedModules = Object.keys(allModules)" class="link link-primary">{{ __('انتخاب همه') }}</button>
                                <span>•</span>
                                <button type="button" @click="selectedModules = []" class="link link-hover">{{ __('پاک کردن') }}</button>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 max-h-48 overflow-y-auto p-2 border border-base-200 rounded-2xl bg-base-100">
                            <template x-for="(mod, key) in allModules" :key="key">
                                <label class="flex items-center gap-2 p-1.5 rounded-xl hover:bg-base-200 cursor-pointer text-xs">
                                    <input type="checkbox" name="modules[]" :value="key" x-model="selectedModules" class="checkbox checkbox-primary checkbox-xs rounded" />
                                    <span class="truncate" x-text="mod.icon + ' ' + mod.title"></span>
                                </label>
                            </template>
                        </div>
                    </div>

                    <!-- Auto Activate Option -->
                    <div class="flex items-center justify-between p-3 bg-base-200/40 rounded-2xl">
                        <label class="label cursor-pointer gap-2">
                            <input type="checkbox" name="auto_activate" value="1" checked class="checkbox checkbox-success checkbox-sm rounded-lg" />
                            <span class="label-text text-xs font-bold">{{ __('فعال‌سازی فوری روی همین سیستم پس از صدور') }}</span>
                        </label>
                    </div>

                    <div class="modal-action">
                        <button type="submit" class="btn btn-primary btn-sm rounded-xl px-6 font-bold shadow-md shadow-primary/20">
                            <span>🔑</span>
                            <span>{{ __('تولید و صدور لایسنس دیجیتال') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </dialog>

    </div>

    <script>
        function licenseManager() {
            return {
                copied: false,
                copyFingerprint(text) {
                    navigator.clipboard.writeText(text);
                    this.copied = true;
                    setTimeout(() => this.copied = false, 2500);
                }
            };
        }
    </script>
</x-app-layout>
