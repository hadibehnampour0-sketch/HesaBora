<x-app-layout :title="__('مرکز پشتیبان‌گیری و بازیابی اضطراری (Backup Center)')">
    <x-show-message-bags />

    <div class="space-y-6" x-data="{ activeTab: 'backups', showSettingsModal: false, showRestoreModal: false, selectedFile: '' }">
        {{-- Hero Header --}}
        <div class="rounded-3xl p-6 md:p-8 bg-gradient-to-br from-base-100 via-base-100 to-emerald-500/10 border border-base-200 shadow-xl relative overflow-hidden">
            <div class="absolute -left-12 -top-12 w-48 h-48 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -right-12 -bottom-12 w-48 h-48 bg-primary/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="relative z-10 flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6">
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 text-emerald-600 text-xs font-semibold mb-3">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>{{ __('استراتژی امنیتی ۳-۲-۱ ضد باج‌افزار و حذف ناخواسته') }}</span>
                    </div>
                    <h1 class="text-2xl md:text-3xl font-extrabold text-base-content tracking-tight">
                        {{ __('مرکز مدیریت پشتیبان‌گیری و نجات داده‌ها (Disaster Recovery)') }}
                    </h1>
                    <p class="text-sm md:text-base text-base-content/70 mt-2 max-w-2xl leading-relaxed">
                        {{ __('اطلاعات مالی و فاکتورهای شما با ارزش‌ترین دارایی کسب‌وکارتان است. در نپتون، پشتیبان‌گیری به صورت چند مسیره (هارد داخلی، فلش‌مموری اکسترنال و ارسال خودکار به تلگرام/بله) با رمزنگاری AES-256 و قابلیت بازیابی اتمیک انجام می‌شود.') }}
                    </p>
                </div>

                {{-- Quick Actions --}}
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full lg:w-auto shrink-0">
                    <form method="POST" action="{{ route('backups.full-export') }}">
                        @csrf
                        <input type="hidden" name="include_documents" value="1">
                        <input type="hidden" name="send_telegram" value="0">
                        <button type="submit" class="btn btn-success text-white w-full sm:w-auto rounded-2xl shadow-lg hover:shadow-xl font-bold flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                            </svg>
                            <span>{{ __('تهیه و دانلود فول‌بکاپ (.neptune)') }}</span>
                        </button>
                    </form>

                    <button type="button" @click="showSettingsModal = true" class="btn btn-outline btn-neutral rounded-2xl font-semibold flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <span>{{ __('تنظیمات خودکار و تلگرام') }}</span>
                    </button>
                </div>
            </div>

            {{-- 4 Pillar Strategy Cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-6 pt-6 border-t border-base-200/80">
                <div class="bg-base-200/50 backdrop-blur rounded-2xl p-4 border border-base-300 flex items-center gap-3.5">
                    <div class="w-11 h-11 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center text-xl shrink-0">
                        💾
                    </div>
                    <div>
                        <div class="text-xs text-base-content/60 font-medium">{{ __('بکاپ دیسک محلی') }}</div>
                        <div class="text-sm font-bold text-base-content mt-0.5">
                            {{ count($backups) }} {{ __('نسخه آرشیو شده') }}
                        </div>
                    </div>
                </div>

                <div class="bg-base-200/50 backdrop-blur rounded-2xl p-4 border border-base-300 flex items-center gap-3.5">
                    <div class="w-11 h-11 rounded-xl bg-blue-500/10 text-blue-600 flex items-center justify-center text-xl shrink-0">
                        🔌
                    </div>
                    <div>
                        <div class="text-xs text-base-content/60 font-medium">{{ __('فلش مموری / اکسترنال') }}</div>
                        <div class="text-sm font-bold {{ !empty($settings['local_target_path']) ? 'text-blue-600' : 'text-base-content/50' }} mt-0.5">
                            {{ !empty($settings['local_target_path']) ? $settings['local_target_path'] : __('پیکربندی نشده') }}
                        </div>
                    </div>
                </div>

                <div class="bg-base-200/50 backdrop-blur rounded-2xl p-4 border border-base-300 flex items-center gap-3.5">
                    <div class="w-11 h-11 rounded-xl bg-sky-500/10 text-sky-600 flex items-center justify-center text-xl shrink-0">
                        ✈️
                    </div>
                    <div>
                        <div class="text-xs text-base-content/60 font-medium">{{ __('ارسال شبانه به تلگرام/بله') }}</div>
                        <div class="text-sm font-bold {{ $settings['telegram_enabled'] ? 'text-sky-600' : 'text-base-content/50' }} mt-0.5">
                            {{ $settings['telegram_enabled'] ? __('فعال و متصل') : __('غیرفعال') }}
                        </div>
                    </div>
                </div>

                <div class="bg-base-200/50 backdrop-blur rounded-2xl p-4 border border-base-300 flex items-center gap-3.5">
                    <div class="w-11 h-11 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center text-xl shrink-0">
                        🛡️
                    </div>
                    <div>
                        <div class="text-xs text-base-content/60 font-medium">{{ __('بکاپ خودکار خروج') }}</div>
                        <div class="text-sm font-bold text-emerald-600 mt-0.5">
                            {{ $settings['auto_backup_on_exit'] ? __('فعال (Auto-Exit Guard)') : __('غیرفعال') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Main Navigation Tabs & Panels --}}
        <div class="card bg-base-100 shadow-xl border border-base-200">
            <div class="card-body p-5 md:p-6">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-base-200 pb-4">
                    <div class="flex items-center gap-2">
                        <button type="button" @click="activeTab = 'backups'" 
                                :class="activeTab === 'backups' ? 'btn-primary text-primary-content shadow' : 'btn-ghost text-base-content/70'" 
                                class="btn btn-sm rounded-xl font-bold">
                            📦 {{ __('فایل‌های پشتیبان ذخیره‌شده') }} ({{ count($backups) }})
                        </button>
                        <a href="{{ route('backups.create') }}" class="btn btn-sm btn-ghost rounded-xl font-medium text-base-content/70">
                            ⚙️ {{ __('پشتیبان سفارشی سال مالی') }}
                        </a>
                        <a href="{{ route('backups.upload') }}" class="btn btn-sm btn-ghost rounded-xl font-medium text-base-content/70">
                            📥 {{ __('بارگذاری فایل سال مالی قبلی') }}
                        </a>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button" @click="showRestoreModal = true; selectedFile = ''" class="btn btn-sm btn-warning text-warning-content rounded-xl font-bold">
                            ⚠️ {{ __('بازیابی فایل دلخواه (Restore)') }}
                        </button>
                    </div>
                </div>

                {{-- Backups Table --}}
                <div x-show="activeTab === 'backups'" class="mt-4 overflow-x-auto">
                    @if (empty($backups))
                        <div class="text-center py-12">
                            <div class="text-4xl">📂</div>
                            <h3 class="text-base font-bold text-base-content mt-2">{{ __('هیچ نسخه پشتیبانی یافت نشد') }}</h3>
                            <p class="text-xs text-base-content/60 mt-1">{{ __('روی دکمه «تهیه و دانلود فول‌بکاپ» کلیک کنید تا اولین نسخه پشتیبان شما ثبت شود.') }}</p>
                        </div>
                    @else
                        <table class="table w-full">
                            <thead>
                                <tr class="text-xs text-base-content/70 border-b border-base-200">
                                    <th>{{ __('نام فایل پشتیبان') }}</th>
                                    <th>{{ __('تاریخ تهیه') }}</th>
                                    <th>{{ __('حجم') }}</th>
                                    <th>{{ __('نوع / امضای امنیتی') }}</th>
                                    <th class="text-left">{{ __('عملیات') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-base-200 text-sm">
                                @foreach ($backups as $b)
                                    <tr class="hover:bg-base-200/40 transition-colors">
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <span class="text-lg">{{ $b['is_emergency'] ? '🛡️' : '📦' }}</span>
                                                <div>
                                                    <div class="font-bold text-base-content">{{ $b['filename'] }}</div>
                                                    <div class="text-[11px] text-base-content/50 font-mono">SHA-256: {{ substr($b['hash'], 0, 16) }}...</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="font-medium text-base-content/80">
                                            {{ $b['jalali_created_at'] }}
                                        </td>
                                        <td>
                                            <span class="badge badge-sm badge-ghost font-bold">{{ $b['size_mb'] }} MB</span>
                                        </td>
                                        <td>
                                            @if ($b['is_emergency'])
                                                <span class="badge badge-sm badge-warning font-semibold">{{ __('اسنپ‌شات اضطراری') }}</span>
                                            @else
                                                <span class="badge badge-sm badge-success badge-outline font-semibold">{{ __('فول‌بکاپ سیستم') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="flex items-center justify-end gap-2">
                                                <a href="{{ route('backups.download', $b['filename']) }}" 
                                                   class="btn btn-xs btn-outline btn-primary rounded-lg font-bold">
                                                    {{ __('دانلود') }}
                                                </a>

                                                <form method="POST" action="{{ route('backups.full-restore') }}" onsubmit="return confirm('{{ __('آیا از بازیابی این نسخه پشتیبان اطمینان دارید؟ قبل از بازیابی، یک اسنپ‌شات نجات خودکار از وضعیت فعلی سیستم گرفته می‌شود.') }}')">
                                                    @csrf
                                                    <input type="hidden" name="backup_file" value="{{ $b['filename'] }}">
                                                    <button type="submit" class="btn btn-xs btn-ghost text-error rounded-lg hover:bg-error/10 font-bold">
                                                        {{ __('بازیابی امن') }}
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>

        {{-- Settings Modal --}}
        <div class="modal" :class="showSettingsModal ? 'modal-open' : ''">
            <div class="modal-box max-w-xl rounded-3xl p-6">
                <div class="flex items-center justify-between border-b border-base-200 pb-3">
                    <h3 class="font-extrabold text-lg text-base-content flex items-center gap-2">
                        <span>⚙️</span>
                        <span>{{ __('تنظیمات پشتیبان‌گیری خودکار و پیام‌رسان') }}</span>
                    </h3>
                    <button type="button" @click="showSettingsModal = false" class="btn btn-sm btn-circle btn-ghost">✕</button>
                </div>

                <form method="POST" action="{{ route('backups.settings') }}" class="space-y-4 mt-4">
                    @csrf
                    
                    <div class="form-control">
                        <label class="label cursor-pointer justify-start gap-3">
                            <input type="checkbox" name="auto_backup_on_exit" value="1" class="checkbox checkbox-primary" @checked($settings['auto_backup_on_exit'])>
                            <span class="label-text font-bold text-sm">{{ __('پشتیبان‌گیری خودکار هنگام خروج از نرم‌افزار (Auto-Backup on Exit)') }}</span>
                        </label>
                        <span class="text-xs text-base-content/60 mr-7">{{ __('هنگام بستن برنامه، یک نسخه در مسیر تعیین‌شده ذخیره می‌شود.') }}</span>
                    </div>

                    <div class="form-control">
                        <label class="label cursor-pointer justify-start gap-3">
                            <input type="checkbox" name="include_documents" value="1" class="checkbox checkbox-primary" @checked($settings['include_documents'])>
                            <span class="label-text font-bold text-sm">{{ __('پیوست‌ها و عکس فاکتورها در فایل بکاپ قرار گیرد') }}</span>
                        </label>
                    </div>

                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-bold text-sm">{{ __('مسیر ذخیره روی دیسک محلی یا فلش‌مموری (USB Drive)') }}</span>
                        </label>
                        <input type="text" name="local_target_path" value="{{ $settings['local_target_path'] }}" 
                               placeholder="مثال: F:\Neptune_Backups یا D:\Backups" 
                               class="input input-bordered input-sm rounded-xl font-mono text-xs w-full text-left" dir="ltr">
                        <span class="text-xs text-base-content/60 mt-1">{{ __('هر زمان این مسیر در دسترس باشد، کپی بکاپ به صورت خودکار به آن منتقل می‌شود.') }}</span>
                    </div>

                    <div class="border-t border-base-200 pt-3">
                        <div class="form-control">
                            <label class="label cursor-pointer justify-start gap-3">
                                <input type="checkbox" name="telegram_enabled" value="1" class="checkbox checkbox-info" @checked($settings['telegram_enabled'])>
                                <span class="label-text font-bold text-sm text-info">{{ __('ارسال خودکار فایل پشتیبان به بات تلگرام / بله کارفرما') }}</span>
                            </label>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-2">
                            <div class="form-control">
                                <label class="label text-xs font-semibold">{{ __('توکن بات تلگرام (Bot Token)') }}</label>
                                <input type="password" name="telegram_bot_token" value="{{ $settings['telegram_bot_token'] }}" 
                                       placeholder="123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11" 
                                       class="input input-bordered input-sm rounded-xl font-mono text-xs w-full text-left" dir="ltr">
                            </div>
                            <div class="form-control">
                                <label class="label text-xs font-semibold">{{ __('شناسه چت یا کاربر (Chat ID)') }}</label>
                                <input type="text" name="telegram_chat_id" value="{{ $settings['telegram_chat_id'] }}" 
                                       placeholder="مثال: 987654321" 
                                       class="input input-bordered input-sm rounded-xl font-mono text-xs w-full text-left" dir="ltr">
                            </div>
                        </div>
                    </div>

                    <div class="modal-action border-t border-base-200 pt-3">
                        <button type="button" @click="showSettingsModal = false" class="btn btn-sm btn-ghost rounded-xl">{{ __('انصراف') }}</button>
                        <button type="submit" class="btn btn-sm btn-primary rounded-xl font-bold px-5">{{ __('ذخیره تنظیمات') }}</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Custom Restore Modal --}}
        <div class="modal" :class="showRestoreModal ? 'modal-open' : ''">
            <div class="modal-box max-w-lg rounded-3xl p-6">
                <div class="flex items-center justify-between border-b border-base-200 pb-3">
                    <h3 class="font-extrabold text-lg text-error flex items-center gap-2">
                        <span>⚠️</span>
                        <span>{{ __('بازیابی نسخه پشتیبان سیستم (System Restore)') }}</span>
                    </h3>
                    <button type="button" @click="showRestoreModal = false" class="btn btn-sm btn-circle btn-ghost">✕</button>
                </div>

                <form method="POST" action="{{ route('backups.full-restore') }}" enctype="multipart/form-data" class="space-y-4 mt-4" onsubmit="return confirm('{{ __('هشدار: بازیابی نسخه پشتیبان کل دیتابیس را به زمان فایل بازمی‌گرداند. یک اسنپ‌شات محافظتی خودکار از وضعیت فعلی گرفته می‌شود. ادامه می‌دهید؟') }}')">
                    @csrf
                    <div class="alert alert-warning text-xs">
                        {{ __('توجه: قبل از شروع بازیابی، سیستم به صورت خودکار یک اسنپ‌شات نجات از وضعیت فعلی شما می‌گیرد تا در صورت هرگونه خطا، دیتابیس به همان لحظه بازگردانده شود.') }}
                    </div>

                    <div class="form-control">
                        <label class="label font-bold text-xs">{{ __('انتخاب فایل پشتیبان (.neptune یا .zip)') }}</label>
                        <input type="file" name="upload_file" accept=".neptune,.zip" required class="file-input file-input-bordered file-input-sm rounded-xl w-full">
                    </div>

                    <div class="modal-action border-t border-base-200 pt-3">
                        <button type="button" @click="showRestoreModal = false" class="btn btn-sm btn-ghost rounded-xl">{{ __('انصراف') }}</button>
                        <button type="submit" class="btn btn-sm btn-error text-white rounded-xl font-bold px-5">{{ __('شروع بازیابی امن') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
