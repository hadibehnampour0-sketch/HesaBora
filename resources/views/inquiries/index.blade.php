<x-app-layout :title="__('Official & Banking Inquiries (Phase 20)')">
    <x-show-message-bags />

    <div class="mx-auto max-w-6xl space-y-6" x-data="{
        activeTab: 'shahkar',
        loading: false,
        result: null,
        error: null,
        shahkarForm: { national_code: '', mobile: '' },
        shebaForm: { sheba: '' },
        cardForm: { card_number: '' },
        postalForm: { postal_code: '' },

        async submitShahkar() {
            this.loading = true;
            this.result = null;
            this.error = null;
            try {
                const res = await fetch('{{ route('inquiries.shahkar') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(this.shahkarForm)
                });
                const data = await res.json();
                if (data.success) {
                    this.result = data.data;
                } else {
                    this.error = data.message || 'خطایی رخ داد';
                }
            } catch (e) {
                this.error = e.message;
            } finally {
                this.loading = false;
            }
        },

        async submitSheba() {
            this.loading = true;
            this.result = null;
            this.error = null;
            try {
                const res = await fetch('{{ route('inquiries.sheba') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(this.shebaForm)
                });
                const data = await res.json();
                if (data.success) {
                    this.result = data.data;
                } else {
                    this.error = data.message || 'خطایی رخ داد';
                }
            } catch (e) {
                this.error = e.message;
            } finally {
                this.loading = false;
            }
        },

        async submitCard() {
            this.loading = true;
            this.result = null;
            this.error = null;
            try {
                const res = await fetch('{{ route('inquiries.card') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(this.cardForm)
                });
                const data = await res.json();
                if (data.success) {
                    this.result = data.data;
                } else {
                    this.error = data.message || 'خطایی رخ داد';
                }
            } catch (e) {
                this.error = e.message;
            } finally {
                this.loading = false;
            }
        },

        async submitPostal() {
            this.loading = true;
            this.result = null;
            this.error = null;
            try {
                const res = await fetch('{{ route('inquiries.postal-code') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(this.postalForm)
                });
                const data = await res.json();
                if (data.success) {
                    this.result = data.data;
                } else {
                    this.error = data.message || 'خطایی رخ داد';
                }
            } catch (e) {
                this.error = e.message;
            } finally {
                this.loading = false;
            }
        }
    }">
        <div>
            <h1 class="text-xl font-bold">{{ __('سامانه استعلام‌های هویتی و بانکی (شاهکار، شبا، کارت)') }}</h1>
            <p class="text-sm text-base-content/60">{{ __('استعلام آنی تطابق کدملی با شماره موبایل، تأیید نام صاحب حساب شبا، تبدیل شماره کارت به شبا و استعلام نشانی پستی.') }}</p>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Inquiry Form -->
            <div class="today-panel lg:col-span-2">
                <div class="tabs tabs-boxed mb-4 bg-base-200 p-1">
                    <button class="tab flex-1" :class="activeTab === 'shahkar' ? 'tab-active' : ''" @click="activeTab = 'shahkar'; result = null; error = null;">
                        {{ __('استعلام شاهکار') }}
                    </button>
                    <button class="tab flex-1" :class="activeTab === 'sheba' ? 'tab-active' : ''" @click="activeTab = 'sheba'; result = null; error = null;">
                        {{ __('استعلام شماره شبا') }}
                    </button>
                    <button class="tab flex-1" :class="activeTab === 'card' ? 'tab-active' : ''" @click="activeTab = 'card'; result = null; error = null;">
                        {{ __('کارت به شبا') }}
                    </button>
                    <button class="tab flex-1" :class="activeTab === 'postal' ? 'tab-active' : ''" @click="activeTab = 'postal'; result = null; error = null;">
                        {{ __('استعلام کد پستی') }}
                    </button>
                </div>

                <!-- Shahkar Tab -->
                <div x-show="activeTab === 'shahkar'" class="space-y-4">
                    <div class="text-sm text-base-content/70">
                        {{ __('تطابق مالکیت سیم‌کارت و کد ملی اشخاص حقیقی را به صورت برخط بررسی کنید.') }}
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="label text-xs font-semibold">{{ __('کد ملی (۱۰ رقم)') }}</label>
                            <input type="text" x-model="shahkarForm.national_code" class="input input-bordered w-full" placeholder="0012345678" maxlength="10">
                        </div>
                        <div>
                            <label class="label text-xs font-semibold">{{ __('شماره موبایل') }}</label>
                            <input type="text" x-model="shahkarForm.mobile" class="input input-bordered w-full" placeholder="09123456789" maxlength="11">
                        </div>
                    </div>
                    <button @click="submitShahkar()" class="btn btn-primary" :disabled="loading">
                        <span x-show="loading" class="loading loading-spinner loading-xs"></span>
                        {{ __('استعلام شاهکار') }}
                    </button>
                </div>

                <!-- Sheba Tab -->
                <div x-show="activeTab === 'sheba'" class="space-y-4">
                    <div class="text-sm text-base-content/70">
                        {{ __('صحت‌سنجی شماره شبا و استخراج نام صاحب حساب و بانک صادرکننده.') }}
                    </div>
                    <div>
                        <label class="label text-xs font-semibold">{{ __('شماره شبا (با یا بدون IR)') }}</label>
                        <input type="text" x-model="shebaForm.sheba" class="input input-bordered w-full" placeholder="IR000000000000000000000000" maxlength="26" dir="ltr">
                    </div>
                    <button @click="submitSheba()" class="btn btn-primary" :disabled="loading">
                        <span x-show="loading" class="loading loading-spinner loading-xs"></span>
                        {{ __('استعلام نام صاحب حساب') }}
                    </button>
                </div>

                <!-- Card Tab -->
                <div x-show="activeTab === 'card'" class="space-y-4">
                    <div class="text-sm text-base-content/70">
                        {{ __('تبدیل شماره کارت ۱۶ رقمی به شماره شبا، بانک صادرکننده و نام صاحب حساب.') }}
                    </div>
                    <div>
                        <label class="label text-xs font-semibold">{{ __('شماره کارت بانکی (۱۶ رقم)') }}</label>
                        <input type="text" x-model="cardForm.card_number" class="input input-bordered w-full" placeholder="6037990000000000" maxlength="16" dir="ltr">
                    </div>
                    <button @click="submitCard()" class="btn btn-primary" :disabled="loading">
                        <span x-show="loading" class="loading loading-spinner loading-xs"></span>
                        {{ __('تبدیل کارت به شبا') }}
                    </button>
                </div>

                <!-- Postal Tab -->
                <div x-show="activeTab === 'postal'" class="space-y-4">
                    <div class="text-sm text-base-content/70">
                        {{ __('استعلام نشانی پستی استاندارد از شرکت ملی پست بر اساس کد پستی ۱۰ رقمی.') }}
                    </div>
                    <div>
                        <label class="label text-xs font-semibold">{{ __('کد پستی ۱۰ رقمی') }}</label>
                        <input type="text" x-model="postalForm.postal_code" class="input input-bordered w-full" placeholder="1234567890" maxlength="10">
                    </div>
                    <button @click="submitPostal()" class="btn btn-primary" :disabled="loading">
                        <span x-show="loading" class="loading loading-spinner loading-xs"></span>
                        {{ __('استعلام نشانی') }}
                    </button>
                </div>

                <!-- Error Message -->
                <div x-show="error" class="alert alert-error mt-4 text-xs font-medium" x-text="error"></div>
            </div>

            <!-- Result Panel -->
            <div class="today-panel">
                <div class="today-panel-title mb-4 font-bold text-base">{{ __('نتیجه استعلام برخط') }}</div>

                <div x-show="!result && !loading" class="text-center py-10 text-base-content/40 text-sm">
                    {{ __('برای مشاهده اطلاعات، پارامترهای مورد نظر را وارد کرده و دکمه استعلام را بزنید.') }}
                </div>

                <div x-show="loading" class="flex flex-col items-center justify-center py-10 gap-3">
                    <span class="loading loading-bars loading-md text-primary"></span>
                    <span class="text-xs text-base-content/60">{{ __('در حال برقراری ارتباط با سامانه‌های حاکمیتی و بانکی...') }}</span>
                </div>

                <div x-show="result" class="space-y-3">
                    <template x-if="result && result.matched !== undefined">
                        <div class="space-y-2">
                            <div class="badge" :class="result.matched ? 'badge-success' : 'badge-error'" x-text="result.matched ? 'مالکیت تأیید شد' : 'عدم تطابق'"></div>
                            <div class="text-sm font-semibold" x-text="result.message"></div>
                            <div class="text-xs text-base-content/60">کد ملی: <span class="font-mono" x-text="result.national_code"></span></div>
                            <div class="text-xs text-base-content/60">شماره موبایل: <span class="font-mono" x-text="result.mobile"></span></div>
                        </div>
                    </template>

                    <template x-if="result && result.account_owner_name">
                        <div class="space-y-2">
                            <div class="badge badge-success">{{ __('حساب فعال و معتبر') }}</div>
                            <div class="text-xs text-base-content/60">{{ __('صاحب حساب:') }}</div>
                            <div class="text-base font-bold text-primary" x-text="result.account_owner_name"></div>
                            <div class="text-xs text-base-content/60">{{ __('بانک صادرکننده:') }}</div>
                            <div class="text-sm font-medium" x-text="result.bank_name"></div>
                            <div class="text-xs text-base-content/60">{{ __('شماره شبا:') }}</div>
                            <div class="text-xs font-mono bg-base-200 p-2 rounded" dir="ltr" x-text="result.sheba"></div>
                        </div>
                    </template>

                    <template x-if="result && result.province">
                        <div class="space-y-2">
                            <div class="badge badge-info">{{ __('نشانی ثبت‌شده') }}</div>
                            <div class="text-xs text-base-content/60">{{ __('استان / شهر:') }}</div>
                            <div class="text-sm font-semibold" x-text="result.province + ' - ' + result.city + ' (' + result.district + ')'"></div>
                            <div class="text-xs text-base-content/60">{{ __('نشانی دقیق:') }}</div>
                            <div class="text-sm bg-base-200 p-2 rounded" x-text="result.address"></div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- History Table -->
        <div class="today-panel">
            <div class="today-panel-title mb-4 font-bold text-base">{{ __('تاریخچه آخرین استعلام‌های انجام شده') }}</div>
            <div class="overflow-x-auto">
                <table class="table table-zebra table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('نوع استعلام') }}</th>
                            <th>{{ __('مقدار جستجو') }}</th>
                            <th>{{ __('وضعیت') }}</th>
                            <th>{{ __('کاربر') }}</th>
                            <th>{{ __('تاریخ و زمان') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr>
                                <td>
                                    <span class="badge badge-ghost badge-sm font-semibold">
                                        {{ match($log->inquiry_type) {
                                            'shahkar' => 'شاهکار (کدملی/موبایل)',
                                            'sheba' => 'استعلام شبا',
                                            'card' => 'کارت به شبا',
                                            'postal_code' => 'کد پستی',
                                            default => $log->inquiry_type,
                                        } }}
                                    </span>
                                </td>
                                <td class="font-mono text-xs" dir="ltr">{{ $log->query_term }}</td>
                                <td>
                                    @if($log->status === 'success' && $log->is_matched)
                                        <span class="badge badge-success badge-sm">{{ __('تأیید شد') }}</span>
                                    @elseif($log->status === 'mismatch' || ! $log->is_matched)
                                        <span class="badge badge-warning badge-sm">{{ __('عدم تطابق') }}</span>
                                    @else
                                        <span class="badge badge-error badge-sm">{{ __('ناموفق / خطا') }}</span>
                                    @endif
                                </td>
                                <td class="text-xs">{{ $log->user?->name ?? __('سیستم') }}</td>
                                <td class="text-xs font-mono">{{ $log->created_at->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-6 text-xs text-base-content/50">
                                    {{ __('هیچ استعلامی تا کنون ثبت نشده است.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $logs->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
