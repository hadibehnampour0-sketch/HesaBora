<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>راه‌اندازی اولیه سامانه حسابداری دخل</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.14.8/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Vazirmatn', sans-serif; }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen flex flex-col justify-between" x-data="installWizard()">
    <header class="py-6 border-b border-slate-800 bg-slate-900/80 backdrop-blur sticky top-0 z-50">
        <div class="max-w-4xl mx-auto px-4 flex items-center justify-between">
            <div class="flex items-center space-x-3 space-x-reverse">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-500 to-teal-400 flex items-center justify-center font-black text-xl text-slate-950 shadow-lg shadow-emerald-500/20">
                    د
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-white">دَخل (Dakhl)</h1>
                    <p class="text-xs text-slate-400">دستیار هوشمند و سامانه جامع مالی و حسابداری</p>
                </div>
            </div>
            <div class="text-xs bg-slate-800 border border-slate-700 text-emerald-400 px-3 py-1.5 rounded-full font-mono font-medium">
                نسخه ۱.۰.۰ پایدار (Clean-Room)
            </div>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-8 flex-1 w-full">
        <!-- Step Progress Tracker -->
        <div class="mb-10">
            <div class="grid grid-cols-4 gap-2 text-center">
                <div :class="step >= 1 ? 'border-emerald-500 text-emerald-400' : 'border-slate-800 text-slate-500'" class="border-b-2 pb-2 transition-colors duration-300">
                    <span class="text-xs block font-bold">گام ۱</span>
                    <span class="text-xs sm:text-sm font-medium">پیش‌نیازها</span>
                </div>
                <div :class="step >= 2 ? 'border-emerald-500 text-emerald-400' : 'border-slate-800 text-slate-500'" class="border-b-2 pb-2 transition-colors duration-300">
                    <span class="text-xs block font-bold">گام ۲</span>
                    <span class="text-xs sm:text-sm font-medium">پایگاه داده</span>
                </div>
                <div :class="step >= 3 ? 'border-emerald-500 text-emerald-400' : 'border-slate-800 text-slate-500'" class="border-b-2 pb-2 transition-colors duration-300">
                    <span class="text-xs block font-bold">گام ۳</span>
                    <span class="text-xs sm:text-sm font-medium">سرفصل‌ها</span>
                </div>
                <div :class="step >= 4 ? 'border-emerald-500 text-emerald-400' : 'border-slate-800 text-slate-500'" class="border-b-2 pb-2 transition-colors duration-300">
                    <span class="text-xs block font-bold">گام ۴</span>
                    <span class="text-xs sm:text-sm font-medium">مدیر ارشد</span>
                </div>
            </div>
        </div>

        <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl p-6 sm:p-8 backdrop-blur shadow-2xl">
            <!-- STEP 1: Requirements -->
            <div x-show="step === 1" x-transition>
                <div class="mb-6">
                    <h2 class="text-xl font-bold text-white mb-1">بررسی سلامت سرور و پیش‌نیازها</h2>
                    <p class="text-sm text-slate-400">پیش از آغاز راه‌اندازی، اطمینان حاصل می‌کنیم کلیه اکستنشن‌های PHP و دسترسی فایل‌ها معتبر باشند.</p>
                </div>

                <!-- PHP Version -->
                <div class="mb-4 p-4 rounded-xl border flex items-center justify-between {{ $requirements['php']['passed'] ? 'bg-emerald-950/20 border-emerald-500/30' : 'bg-red-950/20 border-red-500/30' }}">
                    <div class="flex items-center space-x-3 space-x-reverse">
                        <span class="font-bold text-sm">نسخه PHP سرور</span>
                        <span class="text-xs font-mono text-slate-400">({{ $requirements['php']['version'] }})</span>
                    </div>
                    <div>
                        @if($requirements['php']['passed'])
                            <span class="text-emerald-400 text-xs font-bold px-2 py-1 rounded bg-emerald-500/10 border border-emerald-500/20">تأیید شد (>= 8.2)</span>
                        @else
                            <span class="text-red-400 text-xs font-bold px-2 py-1 rounded bg-red-500/10 border border-red-500/20">نیازمند PHP 8.2 یا بالاتر</span>
                        @endif
                    </div>
                </div>

                <!-- Extensions -->
                <div class="mb-6">
                    <h3 class="text-sm font-semibold text-slate-300 mb-3">اکستنشن‌های الزامی PHP</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @foreach($requirements['extensions'] as $ext)
                            <div class="p-3 rounded-lg border flex items-center justify-between {{ $ext['passed'] ? 'bg-slate-900/40 border-slate-700/60' : 'bg-red-950/20 border-red-500/40' }}">
                                <span class="font-mono text-xs text-slate-300">{{ $ext['name'] }}</span>
                                @if($ext['passed'])
                                    <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                @else
                                    <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Directories -->
                <div class="mb-6">
                    <h3 class="text-sm font-semibold text-slate-300 mb-3">دسترسی نوشتن پوشه‌ها</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach($requirements['directories'] as $key => $dir)
                            <div class="p-3 rounded-lg border flex items-center justify-between {{ $dir['passed'] ? 'bg-slate-900/40 border-slate-700/60' : 'bg-red-950/20 border-red-500/40' }}">
                                <span class="font-mono text-xs text-slate-300">{{ $key }}</span>
                                @if($dir['passed'])
                                    <span class="text-emerald-400 text-xs">قابل نوشتن</span>
                                @else
                                    <span class="text-red-400 text-xs">غیرقابل نوشتن</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Database status -->
                <div class="p-4 rounded-xl border mb-6 flex items-center justify-between {{ $dbConnected ? 'bg-emerald-950/20 border-emerald-500/30' : 'bg-amber-950/20 border-amber-500/30' }}">
                    <span class="text-sm font-bold">اتصال به پایگاه داده (MariaDB / SQLite)</span>
                    @if($dbConnected)
                        <span class="text-emerald-400 text-xs font-bold px-2 py-1 rounded bg-emerald-500/10">متصل است</span>
                    @else
                        <span class="text-amber-400 text-xs font-bold px-2 py-1 rounded bg-amber-500/10">تنظیمات .env را بررسی فرمایید</span>
                    @endif
                </div>

                <div class="flex justify-end">
                    @if($allPassed && $dbConnected)
                        <button type="button" @click="step = 2" class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-2.5 px-6 rounded-xl transition shadow-lg shadow-emerald-600/30 text-sm flex items-center space-x-2 space-x-reverse">
                            <span>گام بعدی: ایجاد جداول</span>
                            <svg class="w-4 h-4 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </button>
                    @else
                        <div class="text-xs text-amber-400">لطفاً ابتدا خطاهای سرور را برطرف فرمایید.</div>
                    @endif
                </div>
            </div>

            <!-- STEP 2: Database Migration -->
            <div x-show="step === 2" x-transition>
                <div class="mb-6">
                    <h2 class="text-xl font-bold text-white mb-1">راه‌اندازی ساختار پایگاه داده</h2>
                    <p class="text-sm text-slate-400">جداول استاندارد حسابداری، اسناد مالی، انبارداری و تریگرهای تغییرناپذیری (ADR-E) ایجاد می‌شوند.</p>
                </div>

                <div class="bg-slate-900/60 p-5 rounded-xl border border-slate-700/60 mb-6 font-mono text-xs text-slate-300">
                    <p class="mb-2 text-emerald-400 font-bold">> php artisan migrate --force</p>
                    <p class="text-slate-400">موتور پایگاه داده: ساختار سازگار با SQLite و MariaDB 12+ با تضمین ایزولاسیون کامل و تریگرهای حسابداری دوطرفه.</p>
                </div>

                <div x-show="migrationLoading" class="p-6 text-center">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-emerald-500 mb-2"></div>
                    <p class="text-sm text-slate-300">در حال اجرای مایگریشن‌ها و ایجاد تریگرها...</p>
                </div>

                <div x-show="migrationDone" class="p-4 rounded-xl bg-emerald-950/20 border border-emerald-500/30 text-emerald-300 text-sm mb-6 flex items-center space-x-3 space-x-reverse">
                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span x-text="migrationMessage"></span>
                </div>

                <div class="flex justify-between items-center">
                    <button type="button" @click="step = 1" class="text-slate-400 hover:text-white text-sm">بازگشت</button>
                    <button type="button" x-show="!migrationDone && !migrationLoading" @click="runMigrations()" class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-2.5 px-6 rounded-xl transition text-sm">
                        شروع ساخت جداول
                    </button>
                    <button type="button" x-show="migrationDone" @click="step = 3" class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-2.5 px-6 rounded-xl transition text-sm flex items-center space-x-2 space-x-reverse">
                        <span>گام بعدی: اطلاعات پایه</span>
                        <svg class="w-4 h-4 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </button>
                </div>
            </div>

            <!-- STEP 3: Baseline Seeding -->
            <div x-show="step === 3" x-transition>
                <div class="mb-6">
                    <h2 class="text-xl font-bold text-white mb-1">بارگذاری اطلاعات پایه و کدینگ حسابداری</h2>
                    <p class="text-sm text-slate-400">تعریف سطوح دسترسی (نقش‌های مدیر، حسابدار، انباردار، فروشنده) و تنظیمات استاندارد مالی ایران.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                    <div class="p-4 rounded-xl bg-slate-900/40 border border-slate-700/60">
                        <div class="font-bold text-sm text-white mb-1">کدینگ استاندارد حسابداری</div>
                        <p class="text-xs text-slate-400">گروه، کل، معین و تفصیلی بر اساس استانداردهای سازمان حسابرسی و سامانه مؤدیان.</p>
                    </div>
                    <div class="p-4 rounded-xl bg-slate-900/40 border border-slate-700/60">
                        <div class="font-bold text-sm text-white mb-1">تفکیک وظایف (SOD)</div>
                        <p class="text-xs text-slate-400">عدم امکان تایید اسناد خود و نقش‌های مستقل برای فروش و حسابداری.</p>
                    </div>
                </div>

                <div x-show="seedingLoading" class="p-6 text-center">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-emerald-500 mb-2"></div>
                    <p class="text-sm text-slate-300">در حال ثبت اطلاعات پایه و نقش‌ها...</p>
                </div>

                <div x-show="seedingDone" class="p-4 rounded-xl bg-emerald-950/20 border border-emerald-500/30 text-emerald-300 text-sm mb-6 flex items-center space-x-3 space-x-reverse">
                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <span x-text="seedingMessage"></span>
                </div>

                <div class="flex justify-between items-center">
                    <button type="button" @click="step = 2" class="text-slate-400 hover:text-white text-sm">بازگشت</button>
                    <button type="button" x-show="!seedingDone && !seedingLoading" @click="runSeeding()" class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-2.5 px-6 rounded-xl transition text-sm">
                        بارگذاری اطلاعات پایه
                    </button>
                    <button type="button" x-show="seedingDone" @click="step = 4" class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-2.5 px-6 rounded-xl transition text-sm flex items-center space-x-2 space-x-reverse">
                        <span>گام پایانی: ایجاد حساب کاربری</span>
                        <svg class="w-4 h-4 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </button>
                </div>
            </div>

            <!-- STEP 4: Admin & Company Setup -->
            <div x-show="step === 4" x-transition>
                <div class="mb-6">
                    <h2 class="text-xl font-bold text-white mb-1">اطلاعات کسب‌وکار و حساب مدیر ارشد</h2>
                    <p class="text-sm text-slate-400">نام مجموعه و مشخصات حساب مالک سیستم را برای ورود مشخص نمایید.</p>
                </div>

                <form @submit.prevent="submitAdminForm()">
                    <div class="space-y-4 mb-6">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">نام شرکت یا کسب‌وکار</label>
                            <input type="text" x-model="adminForm.company_name" required placeholder="مثال: شرکت بازرگانی نمونه" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-emerald-500">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1">نام و نام خانوادگی مدیر</label>
                                <input type="text" x-model="adminForm.admin_name" required placeholder="مثال: علی احمدی" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-emerald-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1">پست الکترونیکی (ایمیل ورود)</label>
                                <input type="email" x-model="adminForm.email" required placeholder="admin@example.com" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-emerald-500">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1">کلمه عبور امن</label>
                                <input type="password" x-model="adminForm.password" required minlength="8" placeholder="حداقل ۸ کاراکتر" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-emerald-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-300 mb-1">تکرار کلمه عبور</label>
                                <input type="password" x-model="adminForm.password_confirmation" required minlength="8" placeholder="تکرار دقیق کلمه عبور" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-emerald-500">
                            </div>
                        </div>
                    </div>

                    <div x-show="errorMessage" class="p-4 rounded-xl bg-red-950/20 border border-red-500/30 text-red-300 text-xs mb-6" x-text="errorMessage"></div>

                    <div class="flex justify-between items-center">
                        <button type="button" @click="step = 3" class="text-slate-400 hover:text-white text-sm">بازگشت</button>
                        <button type="submit" :disabled="submitting" class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold py-2.5 px-8 rounded-xl transition shadow-lg shadow-emerald-600/30 text-sm flex items-center space-x-2 space-x-reverse disabled:opacity-50">
                            <span x-text="submitting ? 'در حال ثبت نهایی...' : 'تکمیل نصب و ورود به سیستم'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <footer class="py-4 border-t border-slate-800 text-center text-xs text-slate-500">
        توسعه‌یافته با بالاترین استانداردهای مهندسی نرم‌افزار، حسابداری و سامانه مالیاتی ایران &bull; دخل ۱۴۰۵ &bull; صاحب امتیاز: هادی بهنام‌پور
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
                    company_name: 'شرکت دخل',
                    admin_name: 'هادی بهنام‌پور',
                    email: 'admin@dakhl.local',
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
                            alert(data.message || 'خطا در اجرای مایگریشن');
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
