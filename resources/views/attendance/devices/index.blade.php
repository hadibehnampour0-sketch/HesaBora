<x-app-layout>
    <x-slot name="title">{{ __('اتصال و مدیریت دستگاه‌های حضور و غیاب') }}</x-slot>

    <div class="space-y-6" x-data="attendanceDevices()">

        <!-- Breadcrumb & Top Bar -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-base-100 p-5 rounded-2xl border border-base-300 shadow-sm">
            <div>
                <div class="flex items-center gap-2 text-xs text-base-content/60 mb-1">
                    <a href="/" class="hover:text-primary">{{ __('پیشخوان') }}</a>
                    <span>/</span>
                    <a href="{{ route('attendance.attendance-logs.index') }}" class="hover:text-primary">{{ __('حضور و غیاب') }}</a>
                    <span>/</span>
                    <span class="text-base-content font-medium">{{ __('دستگاه‌های ثبت ساعت ورود و خروج') }}</span>
                </div>
                <h1 class="text-xl md:text-2xl font-black text-base-content flex items-center gap-2">
                    <span class="text-2xl">⏰</span>
                    {{ __('اتصال مستقیم دستگاه‌های حضور و غیاب به سیستم حسابداری') }}
                </h1>
            </div>

            <div class="flex items-center gap-2">
                <button type="button"
                        onclick="new_device_modal.showModal()"
                        class="btn btn-primary rounded-xl px-5 font-bold shadow-md shadow-primary/20 text-xs md:text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>{{ __('افزودن دستگاه ساعت‌زنی جدید') }}</span>
                </button>
            </div>
        </div>

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

        <!-- Summary KPI Counters -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-base-100 p-4 rounded-2xl border border-base-300 shadow-sm flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center text-xl font-black">
                    📟
                </div>
                <div>
                    <div class="text-xs text-base-content/60">{{ __('دستگاه‌های فعال') }}</div>
                    <div class="text-xl font-black text-base-content tabular-nums">{{ $totalActive }} {{ __('دستگاه') }}</div>
                </div>
            </div>

            <div class="bg-base-100 p-4 rounded-2xl border border-base-300 shadow-sm flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-success/10 text-success flex items-center justify-center text-xl font-black">
                    🔄
                </div>
                <div>
                    <div class="text-xs text-base-content/60">{{ __('همگام‌سازی امروز') }}</div>
                    <div class="text-xl font-black text-success tabular-nums">{{ $totalSyncedToday }} {{ __('دستگاه') }}</div>
                </div>
            </div>

            <div class="bg-base-100 p-4 rounded-2xl border border-base-300 shadow-sm flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-blue-500/10 text-blue-600 flex items-center justify-center text-xl font-black">
                    🌐
                </div>
                <div>
                    <div class="text-xs text-base-content/60">{{ __('پروتکل‌های پشتیبانی‌شده') }}</div>
                    <div class="text-xs font-bold text-base-content">ZKTeco, Kara2000, ADMS, TCP/IP</div>
                </div>
            </div>

            <div class="bg-base-100 p-4 rounded-2xl border border-base-300 shadow-sm flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center text-xl font-black">
                    ⚡
                </div>
                <div>
                    <div class="text-xs text-base-content/60">{{ __('نوع انتقال داده') }}</div>
                    <div class="text-xs font-bold text-base-content">{{ __('سوکت شبکه مستقیم + وب‌پوش خودکار') }}</div>
                </div>
            </div>
        </div>

        <!-- Devices Grid / Cards -->
        @if ($devices->isEmpty())
            <div class="bg-base-100 border border-base-300 rounded-3xl p-12 text-center shadow-sm">
                <div class="text-5xl mb-3">🕒</div>
                <h3 class="text-lg font-bold text-base-content">{{ __('هیچ دستگاه حضور و غیابی ثبت نشده است.') }}</h3>
                <p class="text-xs text-base-content/60 max-w-md mx-auto mt-1 mb-6 leading-relaxed">
                    {{ __('با افزودن مشخصات دستگاه ساعت‌زنی (IP و پورت) می‌توانید داده‌های تردد پرسنل را مستقیماً از حافظه دستگاه خوانده و در کارکرد ماهانه و حقوق حسابداری ثبت کنید.') }}
                </p>
                <button type="button"
                        onclick="new_device_modal.showModal()"
                        class="btn btn-primary rounded-xl font-bold px-6">
                    {{ __('افزودن اولین دستگاه حضور و غیاب') }}
                </button>
            </div>
        @else
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                @foreach ($devices as $device)
                    <div class="bg-base-100 border border-base-300 rounded-3xl p-6 shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
                        <div>
                            <!-- Header Info -->
                            <div class="flex items-start justify-between gap-3 mb-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-primary/10 to-primary/5 text-primary flex items-center justify-center text-2xl border border-primary/10">
                                        ⏱️
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-base text-base-content flex items-center gap-2">
                                            <span>{{ $device->name }}</span>
                                            <span class="badge {{ $device->status_badge_class }} badge-xs py-1.5 px-2 text-[10px]">
                                                {{ $device->status_label }}
                                            </span>
                                        </h3>
                                        <div class="text-xs text-base-content/60 mt-0.5 flex items-center gap-2">
                                            <span>{{ $device->brand_label }}</span>
                                            @if ($device->location)
                                                <span>•</span>
                                                <span>📍 {{ $device->location }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="text-left font-mono text-xs tabular-nums bg-base-200/60 px-2.5 py-1 rounded-lg border border-base-300/50">
                                    {{ $device->ip_address }}:{{ $device->port }}
                                </div>
                            </div>

                            <!-- Status & Counters -->
                            <div class="grid grid-cols-2 gap-3 mb-4 bg-base-200/30 p-3.5 rounded-2xl border border-base-200">
                                <div>
                                    <div class="text-[11px] text-base-content/60">{{ __('آخرین همگام‌سازی:') }}</div>
                                    <div class="text-xs font-bold text-base-content mt-0.5">
                                        {{ $device->last_sync_at ? formatDateTime($device->last_sync_at) : __('هنوز انجام نشده') }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-[11px] text-base-content/60">{{ __('تعداد آخرین لاگ‌های وارد شده:') }}</div>
                                    <div class="text-xs font-black text-primary tabular-nums mt-0.5">
                                        {{ $device->last_log_count }} {{ __('رکورد') }}
                                    </div>
                                </div>
                            </div>

                            @if ($device->last_status_message)
                                <div class="text-[11px] text-base-content/70 bg-base-200/50 p-2.5 rounded-xl border border-base-200 mb-4 flex items-center gap-1.5">
                                    <span>ℹ️</span>
                                    <span>{{ $device->last_status_message }}</span>
                                </div>
                            @endif
                        </div>

                        <!-- Action Buttons -->
                        <div class="pt-4 border-t border-base-200 flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <!-- Direct Sync Button -->
                                <form action="{{ route('attendance.devices.sync', $device) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-primary btn-sm rounded-xl font-bold shadow-sm">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                        <span>{{ __('سینک اطلاعات از دستگاه') }}</span>
                                    </button>
                                </form>

                                <!-- Ping Test -->
                                <form action="{{ route('attendance.devices.ping', $device) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-ghost btn-sm rounded-xl text-xs font-medium border border-base-300">
                                        <span>{{ __('تست اتصال (Ping)') }}</span>
                                    </button>
                                </form>

                                <!-- ADMS Push Details Modal Button -->
                                <button type="button"
                                        @click="openPushModal('{{ $device->name }}', '{{ $device->push_endpoint_url }}', '{{ $device->serial_number ?? '-' }}')"
                                        class="btn btn-ghost btn-sm rounded-xl text-xs font-medium text-primary">
                                    <span>{{ __('تنظیمات خودکار (Push)') }}</span>
                                </button>
                            </div>

                            <div class="flex items-center gap-1">
                                <!-- Simulation Test -->
                                <form action="{{ route('attendance.devices.simulate', $device) }}" method="POST">
                                    @csrf
                                    <button type="submit" title="{{ __('شبیه‌سازی و تولید تردد آزمایشی برای پرسنل') }}" class="btn btn-ghost btn-xs text-base-content/60 hover:text-primary">
                                        🧪 دمو
                                    </button>
                                </form>

                                <!-- Delete -->
                                <form action="{{ route('attendance.devices.destroy', $device) }}" method="POST" onsubmit="return confirm('آیا از حذف این دستگاه اطمینان دارید؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-ghost btn-xs text-error">
                                        🗑️
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4">
                {{ $devices->links() }}
            </div>
        @endif

        <!-- Direct Clock Connection Guide Banner -->
        <div class="bg-gradient-to-tr from-base-100 via-base-100 to-primary/5 border border-base-300 rounded-3xl p-6 shadow-sm">
            <h3 class="text-base font-bold text-base-content mb-2 flex items-center gap-2">
                <span>💡</span>
                {{ __('نحوه اتصال دستگاه ساعت‌زنی به نرم‌افزار حسابداری دخل') }}
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs text-base-content/70 mt-3 leading-relaxed">
                <div class="bg-base-100 p-4 rounded-2xl border border-base-200">
                    <div class="font-bold text-base-content text-sm mb-1.5 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-primary"></span>
                        {{ __('روش ۱: همگام‌سازی مستقیم در شبکه محلی (LAN Pull)') }}
                    </div>
                    <p>
                        {{ __('کافی است دستگاه ثبت ساعت و سرور/کامپیوتر دخل در یک شبکه مشترک (یا سوئیچ/روتر مشترک) باشند. با وارد کردن IP دستگاه (مثلاً 192.168.1.201) و فشردن دکمه «سینک اطلاعات»، نرم‌افزار مستقیماً لاگ‌های تردد را استخراج و در حقوق و دستمزد محاسبه می‌کند.') }}
                    </p>
                </div>

                <div class="bg-base-100 p-4 rounded-2xl border border-base-200">
                    <div class="font-bold text-base-content text-sm mb-1.5 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-success"></span>
                        {{ __('روش ۲: ارسال بلادرنگ ابری (ADMS / Cloud Server Push)') }}
                    </div>
                    <p>
                        {{ __('در منوی دستگاه ساعت‌زنی، به بخش تنظیمات ارتباطی (Comm) و سپس Cloud Server / ADMS رفته و آدرس سرور دخل را وارد کنید. با این کار، هر بار که کارمندی انگشت می‌زند یا چهره‌اش اسکن می‌شود، رکورد تردد همان ثانیه به حسابداری ارسال می‌گردد.') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Add Device Modal -->
        <dialog id="new_device_modal" class="modal">
            <div class="modal-box rounded-3xl max-w-lg bg-base-100">
                <form method="dialog">
                    <button class="btn btn-sm btn-circle btn-ghost absolute right-4 top-4">✕</button>
                </form>

                <h3 class="font-black text-lg text-base-content mb-4 flex items-center gap-2">
                    <span>📟</span>
                    {{ __('افزودن دستگاه حضور و غیاب جدید') }}
                </h3>

                <form action="{{ route('attendance.devices.store') }}" method="POST" class="space-y-4">
                    @csrf
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-base-content/70">{{ __('نام دلخواه دستگاه:') }}</label>
                        <input type="text" name="name" required placeholder="مثال: دستگاه اثر انگشت درب ورودی شرکت" class="input input-bordered w-full rounded-xl text-sm" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-base-content/70">{{ __('برند سازنده دستگاه:') }}</label>
                            <select name="brand" class="select select-bordered w-full rounded-xl text-xs">
                                <option value="zkteco">زد‌کی‌تکو (ZKTeco)</option>
                                <option value="kara2000">کارا ۲۰۰۰ (Kara2000)</option>
                                <option value="palizafzar">پالیز افزار (Palizafzar)</option>
                                <option value="virdi">ویردی (Virdi)</option>
                                <option value="suprema">سوپریما (Suprema)</option>
                                <option value="hikvision">هایک ویژن (Hikvision)</option>
                                <option value="generic">سایر برندهای استاندارد TCP/IP</option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-base-content/70">{{ __('پروتکل ارتباطی:') }}</label>
                            <select name="protocol" class="select select-bordered w-full rounded-xl text-xs">
                                <option value="zk_tcp">پروتکل شبکه مستقیم ZK (TCP/IP)</option>
                                <option value="zk_udp">پروتکل سریع ZK (UDP)</option>
                                <option value="adms_push">وب‌سرویس و ابری (ADMS Push)</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-base-content/70">{{ __('آدرس IP دستگاه:') }}</label>
                            <input type="text" name="ip_address" required value="192.168.1.201" placeholder="192.168.1.201" class="input input-bordered w-full rounded-xl text-sm font-mono dir-ltr" />
                        </div>

                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-base-content/70">{{ __('پورت شبکه:') }}</label>
                            <input type="number" name="port" required value="4370" placeholder="4370" class="input input-bordered w-full rounded-xl text-sm font-mono dir-ltr" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-base-content/70">{{ __('کلید ارتباطی (Comm Key):') }}</label>
                            <input type="text" name="comm_key" value="0" placeholder="0" class="input input-bordered w-full rounded-xl text-sm font-mono dir-ltr" />
                            <span class="text-[10px] text-base-content/50">{{ __('در اکثر دستگاه‌ها پیش‌فرض 0 است') }}</span>
                        </div>

                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-base-content/70">{{ __('محل استقرار فیزیکی:') }}</label>
                            <input type="text" name="location" placeholder="مثال: درب ورودی اصلی" class="input input-bordered w-full rounded-xl text-sm" />
                        </div>
                    </div>

                    <div class="pt-4 border-t border-base-200 flex items-center justify-end gap-2">
                        <button type="button" onclick="new_device_modal.close()" class="btn btn-ghost rounded-xl">{{ __('انصراف') }}</button>
                        <button type="submit" class="btn btn-primary rounded-xl px-6 font-bold">{{ __('ثبت و ذخیره دستگاه') }}</button>
                    </div>
                </form>
            </div>
        </dialog>

        <!-- Push / ADMS Config Modal -->
        <dialog id="push_config_modal" class="modal">
            <div class="modal-box rounded-3xl max-w-lg bg-base-100">
                <form method="dialog">
                    <button class="btn btn-sm btn-circle btn-ghost absolute right-4 top-4">✕</button>
                </form>

                <h3 class="font-black text-lg text-base-content mb-2 flex items-center gap-2">
                    <span>📡</span>
                    {{ __('تنظیمات ارسال خودکار (ADMS Push)') }}
                </h3>
                <p class="text-xs text-base-content/60 mb-4 leading-relaxed">
                    {{ __('برای ارسال آنی ترددها بدون نیاز به فشردن دکمه سینک، کافی است آدرس زیر را در منوی تنظیمات ارتباطی دستگاه ثبت ساعت وارد نمایید:') }}
                </p>

                <div class="space-y-3 bg-base-200/50 p-4 rounded-2xl border border-base-300">
                    <div class="space-y-1">
                        <span class="text-[11px] font-bold text-base-content/70">{{ __('آدرس وب‌هوک اختصاصی این دستگاه:') }}</span>
                        <div class="flex items-center gap-2">
                            <input type="text" readonly x-model="modalPushUrl" class="input input-bordered input-sm w-full font-mono text-xs dir-ltr bg-base-100" />
                            <button type="button" @click="copyPushUrl()" class="btn btn-primary btn-sm rounded-xl">
                                <span x-text="copied ? 'کپی شد! ✓' : 'کپی'"></span>
                            </button>
                        </div>
                    </div>

                    <div class="text-[11px] text-base-content/60 leading-relaxed pt-2 border-t border-base-300/50">
                        <span>💡 در دستگاه‌های ZKTeco، مسیر منو معمولاً: </span>
                        <strong class="text-base-content">Menu &gt; Comm. &gt; Cloud Server Setting &gt; Server Address</strong>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="button" onclick="push_config_modal.close()" class="btn btn-outline rounded-xl btn-sm font-bold">{{ __('بستن') }}</button>
                </div>
            </div>
        </dialog>

    </div>

    <script>
        function attendanceDevices() {
            return {
                modalPushUrl: '',
                modalDeviceName: '',
                copied: false,
                openPushModal(name, url, sn) {
                    this.modalDeviceName = name;
                    this.modalPushUrl = url;
                    this.copied = false;
                    push_config_modal.showModal();
                },
                copyPushUrl() {
                    navigator.clipboard.writeText(this.modalPushUrl);
                    this.copied = true;
                    setTimeout(() => this.copied = false, 2500);
                }
            };
        }
    </script>
</x-app-layout>
