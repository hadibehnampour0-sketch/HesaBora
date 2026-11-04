<x-app-layout :title="__('مرکز بروزرسانی و مدیریت نسخه‌های سیستم')">
    <x-show-message-bags />

    <div class="space-y-6" x-data="{ isChecking: false }">
        {{-- Hero Header --}}
        <div class="rounded-3xl p-6 md:p-8 bg-gradient-to-br from-base-100 via-base-100 to-primary/10 border border-base-200 shadow-xl relative overflow-hidden">
            <div class="absolute -left-12 -top-12 w-48 h-48 bg-primary/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -right-12 -bottom-12 w-48 h-48 bg-accent/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="relative z-10 flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6">
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary/10 text-primary text-xs font-semibold mb-3">
                        <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                        <span>{{ __('موتور بروزرسانی اتمیک و امن (Safe Zero-Loss Updates)') }}</span>
                    </div>
                    <h1 class="text-2xl md:text-3xl font-extrabold text-base-content tracking-tight">
                        {{ __('مدیریت نسخه‌ها و بروزرسانی خودکار نرم‌افزار') }}
                    </h1>
                    <p class="text-sm md:text-base text-base-content/70 mt-2 max-w-2xl leading-relaxed">
                        {{ __('بروزرسانی در دخل به صورت اتمیک و لایه‌بندی‌شده انجام می‌شود. فایل‌های تنظیمات، کلیدهای لایسنس، اطلاعات مالی و دیتابیس مشتری ۱۰۰٪ دست‌نخورده می‌مانند و پیش از اعمال هر آپدیت، اسنپ‌شات نجات اضطراری خودکار ثبت می‌شود.') }}
                    </p>
                </div>

                {{-- Version Status Card --}}
                <div class="bg-base-200/70 backdrop-blur rounded-3xl p-5 border border-base-300 w-full lg:w-auto shrink-0 flex items-center gap-5">
                    <div class="text-center pl-4 border-l border-base-300">
                        <div class="text-xs text-base-content/60 font-semibold">{{ __('نسخه نصب‌شده شما') }}</div>
                        <div class="text-2xl font-black text-primary mt-1 font-mono">v{{ $updateInfo['current_version'] }}</div>
                    </div>

                    <div class="text-center pr-2">
                        <div class="text-xs text-base-content/60 font-semibold">{{ __('آخرین نسخه پایدار') }}</div>
                        <div class="text-2xl font-black text-emerald-500 mt-1 font-mono">v{{ $updateInfo['latest_version'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Update Available Banner & Action --}}
        @if ($updateInfo['has_update'])
            <div class="card bg-base-100 shadow-xl border-2 border-primary/30 rounded-3xl overflow-hidden">
                <div class="h-2 w-full bg-gradient-to-r from-primary via-accent to-emerald-500"></div>
                <div class="card-body p-6 md:p-8">
                    <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="badge badge-primary font-bold text-xs py-2 px-3">
                                    🚀 {{ __('نسخه جدید آماده نصب است') }} (v{{ $updateInfo['latest_version'] }})
                                </span>
                                <span class="text-xs text-base-content/60">
                                    {{ __('تاریخ انتشار') }}: {{ $updateInfo['release_date'] }} • {{ $updateInfo['package_size'] }}
                                </span>
                            </div>
                            <h2 class="text-xl font-black text-base-content mt-2">
                                {{ $updateInfo['summary'] }}
                            </h2>
                            <p class="text-xs md:text-sm text-base-content/70 mt-1">
                                {{ $updateInfo['compatibility'] }}
                            </p>
                        </div>

                        <form method="POST" action="{{ route('updates.apply') }}" onsubmit="return confirm('{{ __('آیا برای شروع بروزرسانی سیستم آماده‌اید؟ نرم‌افزار به صورت خودکار از وضعیت فعلی اسنپ‌شات نجات گرفته و ساختار دیتابیس را به آخرین نسخه ارتقا می‌دهد.') }}')">
                            @csrf
                            <button type="submit" class="btn btn-primary text-primary-content rounded-2xl font-extrabold shadow-lg hover:shadow-xl px-6 flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                </svg>
                                <span>{{ __('شروع بروزرسانی خودکار به نسخه :ver', ['ver' => 'v' . $updateInfo['latest_version']]) }}</span>
                            </button>
                        </form>
                    </div>

                    {{-- Changelog Sections --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mt-6 pt-6 border-t border-base-200">
                        {{-- New Features --}}
                        <div class="bg-base-200/40 rounded-2xl p-4 border border-base-300">
                            <h4 class="font-bold text-sm text-primary flex items-center gap-1.5 mb-3">
                                <span>✨</span>
                                <span>{{ __('امکانات و ماژول‌های جدید') }}</span>
                            </h4>
                            <ul class="space-y-2 text-xs text-base-content/80">
                                @foreach ($updateInfo['changelog']['features'] as $item)
                                    <li class="flex items-start gap-2">
                                        <span class="text-emerald-500 font-bold shrink-0">✓</span>
                                        <span class="leading-relaxed">{{ $item }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        {{-- Improvements --}}
                        <div class="bg-base-200/40 rounded-2xl p-4 border border-base-300">
                            <h4 class="font-bold text-sm text-info flex items-center gap-1.5 mb-3">
                                <span>⚡</span>
                                <span>{{ __('بهبودهای عملکرد و سرعت') }}</span>
                            </h4>
                            <ul class="space-y-2 text-xs text-base-content/80">
                                @foreach ($updateInfo['changelog']['improvements'] as $item)
                                    <li class="flex items-start gap-2">
                                        <span class="text-info font-bold shrink-0">✓</span>
                                        <span class="leading-relaxed">{{ $item }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        {{-- Bug Fixes --}}
                        <div class="bg-base-200/40 rounded-2xl p-4 border border-base-300">
                            <h4 class="font-bold text-sm text-accent flex items-center gap-1.5 mb-3">
                                <span>🛠️</span>
                                <span>{{ __('رفع باگ‌ها و اصلاحات فنی') }}</span>
                            </h4>
                            <ul class="space-y-2 text-xs text-base-content/80">
                                @foreach ($updateInfo['changelog']['fixes'] as $item)
                                    <li class="flex items-start gap-2">
                                        <span class="text-accent font-bold shrink-0">✓</span>
                                        <span class="leading-relaxed">{{ $item }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="card bg-base-100 shadow-md border border-base-200 rounded-3xl p-6 text-center">
                <div class="w-14 h-14 rounded-2xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center text-2xl mx-auto mb-3">
                    ✅
                </div>
                <h3 class="text-lg font-bold text-base-content">{{ __('نرم‌افزار شما کاملاً به روز است') }}</h3>
                <p class="text-xs text-base-content/70 mt-1 max-w-md mx-auto leading-relaxed">
                    {{ __('شما در حال استفاده از آخرین نگارش رسمی دخل (نسخه v:ver) هستید. تمامی پچ‌های امنیتی و اصلاحات قوانین مالیاتی روی سیستم شما فعال است.', ['ver' => $updateInfo['current_version']]) }}
                </p>
            </div>
        @endif

        {{-- Update History --}}
        <div class="card bg-base-100 shadow-xl border border-base-200 rounded-3xl">
            <div class="card-body p-5 md:p-6">
                <h3 class="card-title text-base font-extrabold flex items-center gap-2">
                    <span>📜</span>
                    <span>{{ __('تاریخچه بروزرسانی‌های اعمال‌شده در سیستم') }}</span>
                </h3>

                <div class="overflow-x-auto mt-3">
                    <table class="table w-full">
                        <thead>
                            <tr class="text-xs text-base-content/70 border-b border-base-200">
                                <th>{{ __('نسخه اعمال‌شده') }}</th>
                                <th>{{ __('نسخه قبلی') }}</th>
                                <th>{{ __('تاریخ اعمال') }}</th>
                                <th>{{ __('وضعیت') }}</th>
                                <th>{{ __('اسنپ‌شات پشتیبان') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-base-200 text-xs">
                            @foreach ($history as $h)
                                <tr>
                                    <td class="font-bold text-primary font-mono">v{{ $h['version'] }}</td>
                                    <td class="text-base-content/70 font-mono">v{{ $h['previous_version'] }}</td>
                                    <td>{{ $h['jalali_date'] }}</td>
                                    <td>
                                        <span class="badge badge-sm badge-success font-semibold">{{ __('موفق') }}</span>
                                    </td>
                                    <td class="font-mono text-base-content/50">{{ basename($h['safety_snapshot']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
