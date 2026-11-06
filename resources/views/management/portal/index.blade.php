<x-app-layout>
    <x-slot name="title">{{ __('مرکز مدیریت تجاری و لایسنس نپتون') }}</x-slot>

    <div class="space-y-6" x-data="{
        activeTab: 'leads',
        selectedLead: null,
        selectedOrder: null,
        leadModalOpen: false,
        orderModalOpen: false,
        copiedKey: false,
        copyText(text) {
            navigator.clipboard.writeText(text);
            this.copiedKey = true;
            setTimeout(() => this.copiedKey = false, 2500);
        }
    }">

        <!-- Top Header Banner -->
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-gradient-to-l from-slate-900 via-slate-850 to-slate-900 border border-slate-750 p-6 rounded-3xl shadow-xl text-white">
            <div class="space-y-1">
                <div class="flex items-center gap-2 text-xs text-sky-400 font-bold uppercase tracking-wider">
                    <span>👑</span>
                    <span>{{ __('پنل مدیریت پورتال و عملیات تجاری نپتون') }}</span>
                    <span>•</span>
                    <span class="badge badge-info badge-sm font-mono text-[10px]">{{ $releaseInfo['version'] }}</span>
                </div>
                <h1 class="text-2xl lg:text-3xl font-black flex items-center gap-2">
                    <span>مرکز فرماندهی فروش، سرنخ‌ها و لایسنس‌های نپتون</span>
                </h1>
                <p class="text-xs lg:text-sm text-slate-350 font-normal">
                    مدیریت یکپارچه لیدهای تست رایگان ۱۴ روزه، سفارش‌های آنلاین ۵ پلن تجاری و صدور لایسنس‌های کریپتوگرافیک Ed25519
                </p>
            </div>

            <!-- Quick Links -->
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('landing') }}" target="_blank" class="btn btn-sm btn-outline btn-info rounded-xl text-xs gap-1.5">
                    <span>🌐</span>
                    <span>مشاهده وب‌سایت</span>
                </a>
                <a href="{{ route('pricing') }}" target="_blank" class="btn btn-sm btn-outline btn-accent rounded-xl text-xs gap-1.5">
                    <span>💳</span>
                    <span>صفحه قیمت‌ها</span>
                </a>
                <a href="{{ $releaseInfo['download_url'] }}" target="_blank" class="btn btn-sm btn-primary rounded-xl text-xs gap-1.5 font-bold shadow-md shadow-primary/20">
                    <span>⬇️</span>
                    <span>دانلود مستقیم نصاب ویندوز</span>
                </a>
            </div>
        </div>

        @if (session('generated_key'))
            <div class="bg-emerald-500/10 border-2 border-emerald-500/40 rounded-3xl p-5 shadow-lg space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-black text-emerald-600 dark:text-emerald-400 flex items-center gap-2">
                        <span>✨</span> {{ __('کلید لایسنس رسمی با موفقیت تولید و امضا شد:') }}
                    </span>
                    <button type="button" @click="copyText('{{ session('generated_key') }}')" class="btn btn-success btn-xs rounded-lg font-bold gap-1 text-white">
                        <span x-text="copiedKey ? 'کپی شد! ✓' : 'کپی کلید لایسنس'"></span>
                    </button>
                </div>
                <div class="bg-base-300/80 p-3.5 rounded-2xl font-mono text-xs text-base-content break-all select-all border border-base-content/10">
                    {{ session('generated_key') }}
                </div>
            </div>
        @endif

        @if (session('success'))
            <div class="alert alert-success rounded-2xl text-xs font-bold text-white shadow-sm">
                <span>✓ {{ session('success') }}</span>
            </div>
        @endif

        <!-- KPI Statistic Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Total Leads -->
            <div class="bg-base-100 border border-base-300 p-5 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-base-content/70">سرنخ‌ها و دموهای رایگان</span>
                    <span class="p-2 rounded-xl bg-info/10 text-info text-lg">👥</span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-2xl font-black text-base-content">{{ number_format($totalLeads) }}</span>
                    <span class="text-xs text-base-content/60">مورد</span>
                </div>
                <div class="mt-2 text-[11px] text-info font-bold flex items-center gap-1">
                    <span>●</span>
                    <span>{{ $newLeads }} درخواست جدید در صف بررسی</span>
                </div>
            </div>

            <!-- Total Orders -->
            <div class="bg-base-100 border border-base-300 p-5 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-base-content/70">سفارش‌های آنلاین ثبت‌شده</span>
                    <span class="p-2 rounded-xl bg-warning/10 text-warning text-lg">🛒</span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-2xl font-black text-base-content">{{ number_format($totalOrders) }}</span>
                    <span class="text-xs text-base-content/60">سفارش</span>
                </div>
                <div class="mt-2 text-[11px] text-warning font-bold flex items-center gap-1">
                    <span>●</span>
                    <span>{{ $pendingOrders }} سفارش در انتظار پرداخت</span>
                </div>
            </div>

            <!-- Total Revenue -->
            <div class="bg-base-100 border border-base-300 p-5 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-base-content/70">فروش تحقق‌یافته تجاری</span>
                    <span class="p-2 rounded-xl bg-success/10 text-success text-lg">💰</span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-2xl font-black text-success">{{ number_format($totalRevenue) }}</span>
                    <span class="text-xs text-base-content/60">تومان</span>
                </div>
                <div class="mt-2 text-[11px] text-success font-bold flex items-center gap-1">
                    <span>✓</span>
                    <span>لایسنس‌های رسمی نپتون</span>
                </div>
            </div>

            <!-- Active Release -->
            <div class="bg-base-100 border border-base-300 p-5 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-base-content/70">نسخه نصاب فعال ویندوز</span>
                    <span class="p-2 rounded-xl bg-primary/10 text-primary text-lg">🚀</span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-2xl font-black text-primary font-mono">{{ $releaseInfo['version'] }}</span>
                    <span class="text-xs text-base-content/60">({{ $releaseInfo['size'] }})</span>
                </div>
                <div class="mt-2 text-[11px] text-primary font-bold flex items-center gap-1">
                    <span>☁️</span>
                    <span>میزبانی بر روی CDN گیتهاب</span>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="bg-base-100 border border-base-300 p-2 rounded-2xl shadow-sm flex flex-wrap gap-2">
            <button type="button"
                    @click="activeTab = 'leads'"
                    :class="activeTab === 'leads' ? 'bg-primary text-white font-bold shadow-sm' : 'text-base-content/70 hover:bg-base-200'"
                    class="px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 transition-all">
                <span>📋</span>
                <span>درخواست‌های دمو و تست رایگان</span>
                @if ($newLeads > 0)
                    <span class="badge badge-warning badge-xs font-bold">{{ $newLeads }}</span>
                @endif
            </button>

            <button type="button"
                    @click="activeTab = 'orders'"
                    :class="activeTab === 'orders' ? 'bg-primary text-white font-bold shadow-sm' : 'text-base-content/70 hover:bg-base-200'"
                    class="px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 transition-all">
                <span>🛒</span>
                <span>سفارش‌های خرید لایسنس</span>
                @if ($pendingOrders > 0)
                    <span class="badge badge-error badge-xs font-bold text-white">{{ $pendingOrders }}</span>
                @endif
            </button>

            <button type="button"
                    @click="activeTab = 'license-issuer'"
                    :class="activeTab === 'license-issuer' ? 'bg-primary text-white font-bold shadow-sm' : 'text-base-content/70 hover:bg-base-200'"
                    class="px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 transition-all">
                <span>🔐</span>
                <span>کنسول صدور لایسنس اختصاصی</span>
            </button>

            <button type="button"
                    @click="activeTab = 'release-hub'"
                    :class="activeTab === 'release-hub' ? 'bg-primary text-white font-bold shadow-sm' : 'text-base-content/70 hover:bg-base-200'"
                    class="px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 transition-all">
                <span>📦</span>
                <span>مدیریت فایل نصاب و CDN</span>
            </button>
        </div>

        <!-- Tab 1: Leads & Trial Requests -->
        <div x-show="activeTab === 'leads'" class="space-y-4">
            <div class="bg-base-100 border border-base-300 rounded-3xl overflow-hidden shadow-sm">
                <div class="p-5 border-b border-base-200 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-base-content flex items-center gap-2">
                            <span>👥</span>
                            <span>لیست درخواست‌های تست رایگان و سرنخ‌های ثبت‌شده از سایت</span>
                        </h2>
                        <p class="text-xs text-base-content/60 mt-0.5">مشتریانی که فرم دریافت نسخه دمو و مشاوره رایگان را در لندینگ پیج تکمیل کرده‌اند</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="table table-zebra text-xs">
                        <thead>
                            <tr class="bg-base-200/50 text-base-content/70">
                                <th>#</th>
                                <th>نام مشتری / واحد تجاری</th>
                                <th>شماره موبایل</th>
                                <th>نوع صنف</th>
                                <th>وضعیت پیگیری</th>
                                <th>تاریخ درخواست</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentLeads as $lead)
                                <tr class="hover">
                                    <td class="font-mono text-base-content/60">{{ $lead->id }}</td>
                                    <td class="font-bold text-base-content">{{ $lead->customer_name }}</td>
                                    <td>
                                        <a href="tel:{{ $lead->phone }}" class="font-mono font-bold text-primary dir-ltr inline-block hover:underline">
                                            {{ $lead->phone }}
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge badge-ghost badge-sm text-[11px]">{{ $lead->business_type ?: 'تعیین‌نشده' }}</span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $lead->status_badge_class }} badge-sm text-[11px] font-bold">
                                            {{ $lead->status_label }}
                                        </span>
                                    </td>
                                    <td class="font-mono text-base-content/60">{{ $lead->created_at->format('Y/m/d H:i') }}</td>
                                    <td>
                                        <div class="flex items-center gap-1.5">
                                            <!-- Update Status Form -->
                                            <form action="{{ route('management.portal.leads.update', $lead->id) }}" method="POST" class="flex items-center gap-1">
                                                @csrf
                                                <select name="status" class="select select-bordered select-xs text-[11px] rounded-lg">
                                                    <option value="new" @selected($lead->status === 'new')>جدید</option>
                                                    <option value="contacted" @selected($lead->status === 'contacted')>تماس گرفته شد</option>
                                                    <option value="converted" @selected($lead->status === 'converted')>تبدیل به مشتری</option>
                                                    <option value="cancelled" @selected($lead->status === 'cancelled')>انصراف</option>
                                                </select>
                                                <button type="submit" class="btn btn-ghost btn-xs text-primary font-bold">ثبت</button>
                                            </form>

                                            @if ($lead->trial_license_key)
                                                <button type="button"
                                                        @click="copyText('{{ $lead->trial_license_key }}')"
                                                        title="کپی لایسنس آزمایشی صادرشده"
                                                        class="btn btn-ghost btn-xs text-xs">
                                                    🔑
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-10 text-base-content/50">
                                        هنوز درخواستی از طریق سایت ثبت نشده است. فرم‌های لندینگ فعال و متصل به پایگاه‌داده هستند.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab 2: Orders & License Purchases -->
        <div x-show="activeTab === 'orders'" class="space-y-4">
            <div class="bg-base-100 border border-base-300 rounded-3xl overflow-hidden shadow-sm">
                <div class="p-5 border-b border-base-200 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-base-content flex items-center gap-2">
                            <span>🛒</span>
                            <span>سفارش‌های خرید پلن‌های تجاری نپتون</span>
                        </h2>
                        <p class="text-xs text-base-content/60 mt-0.5">سفارش‌های ثبت‌شده برای ۵ پلن (برنزی ۵م، نقره‌ای ۱۵م، مویرگی ۳۵م، تولیدی ۵۰م، هلدینگ ۸۰م)</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="table table-zebra text-xs">
                        <thead>
                            <tr class="bg-base-200/50 text-base-content/70">
                                <th>کد پیگیری</th>
                                <th>پلن انتخابی</th>
                                <th>نام خریدار</th>
                                <th>شماره تماس</th>
                                <th>مبلغ کل</th>
                                <th>وضعیت</th>
                                <th>تاریخ ثبت</th>
                                <th>عملیات و صدور لایسنس</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentOrders as $order)
                                <tr class="hover">
                                    <td class="font-mono font-bold text-primary">{{ $order->order_reference }}</td>
                                    <td>
                                        <span class="badge badge-outline badge-primary badge-sm font-bold text-[11px]">
                                            {{ $order->plan_label }}
                                        </span>
                                    </td>
                                    <td class="font-bold text-base-content">
                                        {{ $order->customer_name }}
                                        @if ($order->company_name)
                                            <div class="text-[10px] text-base-content/50">{{ $order->company_name }}</div>
                                        @endif
                                    </td>
                                    <td class="font-mono text-base-content/70 dir-ltr">{{ $order->phone }}</td>
                                    <td class="font-bold text-success font-mono">{{ number_format($order->total_price) }} ت</td>
                                    <td>
                                        <span class="badge {{ $order->status_badge_class }} badge-sm text-[11px] font-bold">
                                            {{ $order->status_label }}
                                        </span>
                                    </td>
                                    <td class="font-mono text-base-content/60">{{ $order->created_at->format('Y/m/d H:i') }}</td>
                                    <td>
                                        <div class="flex items-center gap-1.5">
                                            @if (! $order->issued_license_key)
                                                <!-- One-click Issue License Button -->
                                                <form action="{{ route('management.portal.orders.issue-license', $order->id) }}" method="POST">
                                                    @csrf
                                                    <button type="submit"
                                                            onclick="return confirm('آیا از تایید پرداخت و صدور لایسنس دائمی نپتون برای این سفارش اطمینان دارید؟')"
                                                            class="btn btn-primary btn-xs rounded-lg font-bold gap-1 text-[11px]">
                                                        <span>🔑</span>
                                                        <span>صدور لایسنس رسمی</span>
                                                    </button>
                                                </form>
                                            @else
                                                <button type="button"
                                                        @click="copyText('{{ $order->issued_license_key }}')"
                                                        class="btn btn-outline btn-success btn-xs rounded-lg font-bold gap-1 text-[11px]">
                                                    <span x-text="copiedKey ? 'کپی شد! ✓' : 'کپی لایسنس صادرشده'"></span>
                                                </button>
                                            @endif

                                            <!-- Order Status Dropdown -->
                                            <form action="{{ route('management.portal.orders.update', $order->id) }}" method="POST" class="flex items-center gap-1">
                                                @csrf
                                                <select name="status" class="select select-bordered select-xs text-[11px] rounded-lg">
                                                    <option value="pending" @selected($order->status === 'pending')>در انتظار</option>
                                                    <option value="paid" @selected($order->status === 'paid')>پرداخت شده</option>
                                                    <option value="completed" @selected($order->status === 'completed')>تکمیل شده</option>
                                                    <option value="cancelled" @selected($order->status === 'cancelled')>لغو شده</option>
                                                </select>
                                                <button type="submit" class="btn btn-ghost btn-xs text-primary font-bold">ثبت</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-10 text-base-content/50">
                                        هنوز سفارشی ثبت نشده است. پس از انتخاب پلن توسط مشتریان در صفحه قیمت‌گذاری، در این بخش نمایش داده خواهد شد.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab 3: Custom License Issuer Console -->
        <div x-show="activeTab === 'license-issuer'" class="space-y-4">
            <div class="bg-base-100 border border-base-300 rounded-3xl p-6 shadow-sm">
                <div class="mb-6">
                    <h2 class="text-lg font-black text-base-content flex items-center gap-2">
                        <span>🔐</span>
                        <span>کنسول صدور لایسنس تجاری و اختصاصی نپتون (Ed25519 Engine)</span>
                    </h2>
                    <p class="text-xs text-base-content/60 mt-1">تولید کلیدهای معتبر کریپتوگرافیک با قابلیت تعیین سقف کاربران، شرکت‌ها و فعال‌سازی آفلاین برای مشتریان بازار</p>
                </div>

                <form action="{{ route('management.portal.issue-custom-license') }}" method="POST" class="space-y-6">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        <!-- Customer Name -->
                        <div class="form-control">
                            <label class="label text-xs font-bold text-base-content/80">نام مشتری یا عنوان شرکت:</label>
                            <input type="text" name="customer_name" required placeholder="مثال: بازرگانی بهنام‌پور" class="input input-bordered input-sm rounded-xl text-xs">
                        </div>

                        <!-- Commercial Plan -->
                        <div class="form-control">
                            <label class="label text-xs font-bold text-base-content/80">پلن تجاری نپتون:</label>
                            <select name="edition" class="select select-bordered select-sm rounded-xl text-xs font-bold">
                                <option value="basic">پلن برنزی اصناف و فروشگاهی (۵ م.ت)</option>
                                <option value="professional" selected>پلن نقره‌ای تجاری و شرکتی (۱۵ م.ت)</option>
                                <option value="distribution">پلن توزیع و پخش مویرگی (۳۵ م.ت)</option>
                                <option value="manufacturing">پلن صنعتی و تولیدی (۵۰ م.ت)</option>
                                <option value="enterprise">پلن سازمانی و هلدینگ (۸۰ م.ت)</option>
                                <option value="trial">نسخه آزمایشی (Trial)</option>
                            </select>
                        </div>

                        <!-- Hardware Fingerprint -->
                        <div class="form-control">
                            <label class="label text-xs font-bold text-base-content/80">
                                <span>شناسه سخت‌افزاری مشتری (یا * برای تمام سیستم‌ها):</span>
                            </label>
                            <input type="text" name="hardware_fingerprint" value="*" placeholder="مثال: * یا F1A2-B3C4-D5E6" class="input input-bordered input-sm rounded-xl font-mono text-xs">
                        </div>

                        <!-- Max Companies -->
                        <div class="form-control">
                            <label class="label text-xs font-bold text-base-content/80">حداکثر تعداد شرکت‌ها (دیتابیس مجزا):</label>
                            <input type="number" name="max_companies" value="1" min="-1" max="1000" class="input input-bordered input-sm rounded-xl text-xs font-mono">
                            <span class="text-[10px] text-base-content/50 mt-1">-1 برای تعداد نامحدود</span>
                        </div>

                        <!-- Max Users -->
                        <div class="form-control">
                            <label class="label text-xs font-bold text-base-content/80">حداکثر کاربران همزمان در شبکه:</label>
                            <input type="number" name="max_users" value="3" min="-1" max="1000" class="input input-bordered input-sm rounded-xl text-xs font-mono">
                            <span class="text-[10px] text-base-content/50 mt-1">-1 برای تعداد نامحدود</span>
                        </div>

                        <!-- Validity Period -->
                        <div class="form-control">
                            <label class="label text-xs font-bold text-base-content/80">مدت اعتبار لایسنس:</label>
                            <select name="duration_months" class="select select-bordered select-sm rounded-xl text-xs">
                                <option value="0" selected>مادام‌العمر و دائمی (Lifetime)</option>
                                <option value="12">۱۲ ماهه (۱ سال)</option>
                                <option value="24">۲۴ ماهه (۲ سال)</option>
                                <option value="1">۱ ماهه</option>
                            </select>
                        </div>
                    </div>

                    <!-- Enabled Modules -->
                    <div class="space-y-3 pt-2">
                        <label class="text-xs font-black text-base-content">ماژول‌های فعال برای این لایسنس:</label>
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                            @foreach ($modules as $modKey => $mod)
                                <label class="flex items-center gap-2 p-2.5 bg-base-200/50 rounded-xl border border-base-300 cursor-pointer hover:bg-base-200">
                                    <input type="checkbox" name="modules[]" value="{{ $modKey }}" checked class="checkbox checkbox-primary checkbox-xs rounded">
                                    <span class="text-xs font-bold text-base-content">{{ $mod['title'] ?? $mod['name'] ?? $modKey }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="pt-4 border-t border-base-200 flex justify-end">
                        <button type="submit" class="btn btn-primary rounded-xl px-6 font-black text-xs gap-2">
                            <span>🔑</span>
                            <span>تولید و صدور فوری کلید لایسنس</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tab 4: Release & CDN Download Hub -->
        <div x-show="activeTab === 'release-hub'" class="space-y-4">
            <div class="bg-base-100 border border-base-300 rounded-3xl p-6 shadow-sm space-y-6">
                <div>
                    <h2 class="text-lg font-black text-base-content flex items-center gap-2">
                        <span>📦</span>
                        <span>مدیریت فایل نصاب و لینک‌های دانلود CDN</span>
                    </h2>
                    <p class="text-xs text-base-content/60 mt-1">مشخصات نسخه رسمی و نحوه به‌روزرسانی فایل‌های نصبی ویندوز نپتون بر روی بستر پایدار GitHub Releases CDN</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-base-200/50 border border-base-300 rounded-2xl p-4 space-y-3">
                        <span class="text-xs font-bold text-primary flex items-center gap-1.5">
                            <span>🚀</span> مشخصات نسخه جاری:
                        </span>
                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between py-1 border-b border-base-300">
                                <span class="text-base-content/60">شماره نسخه:</span>
                                <span class="font-mono font-bold text-base-content">{{ $releaseInfo['version'] }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-base-300">
                                <span class="text-base-content/60">نام فایل اجرایی:</span>
                                <span class="font-mono font-bold text-base-content">{{ $releaseInfo['installer_name'] }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-base-300">
                                <span class="text-base-content/60">حجم نصاب تک‌کلیکه:</span>
                                <span class="font-bold text-base-content">{{ $releaseInfo['size'] }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-base-300">
                                <span class="text-base-content/60">معماری:</span>
                                <span class="text-base-content">{{ $releaseInfo['architecture'] }}</span>
                            </div>
                            <div class="flex justify-between py-1">
                                <span class="text-base-content/60">تاریخ انتشار:</span>
                                <span class="font-bold text-base-content">{{ $releaseInfo['release_date'] }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-base-200/50 border border-base-300 rounded-2xl p-4 space-y-3">
                        <span class="text-xs font-bold text-success flex items-center gap-1.5">
                            <span>⚡</span> وضعیت زیرساخت توزیع و CDN:
                        </span>
                        <p class="text-xs text-base-content/70 leading-relaxed">
                            فایل نصاب ویندوز با پهنای باند نامحدود مایکروسافت از طریق <strong>GitHub Releases CDN</strong> میزبانی می‌شود. تمامی لینک‌های سایت ورسل (مانند <code class="bg-base-300 px-1 py-0.5 rounded font-mono text-[11px]">/download/windows</code>) به صورت هوشمند به این CDN متصل شده‌اند تا مشتریان بالاترین سرعت دانلود و قابلیت Resume را داشته باشند.
                        </p>
                        <div class="pt-2">
                            <a href="{{ $releaseInfo['download_url'] }}" class="btn btn-success btn-sm rounded-xl text-xs text-white font-bold gap-2">
                                <span>📥</span>
                                <span>تست دانلود زنده از CDN</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- How to publish future updates -->
                <div class="bg-primary/5 border border-primary/20 rounded-2xl p-5 space-y-2">
                    <h3 class="text-xs font-bold text-primary flex items-center gap-2">
                        <span>💡</span>
                        <span>راهنمای انتشار نسخه‌های بعدی نرم‌افزار نپتون:</span>
                    </h3>
                    <p class="text-xs text-base-content/80 leading-relaxed">
                        هر زمان نسخه جدیدی از نپتون ساخته شود (مثلاً <code class="font-mono bg-base-200 px-1 rounded">v2.6.0</code>)، فایل نصاب در بخش Releases مخزن گیت‌هاب بارگذاری شده و تگ جدید ایجاد می‌گردد. وب‌سایت به صورت خودکار نسخه جدید را برای تمامی کاربران و مشتریان دانلود ارائه خواهد داد.
                    </p>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>
