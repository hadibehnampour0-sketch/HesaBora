<x-app-layout :title="__('Customer Loyalty & Store Credit Wallet (Phase 19.1)')">
    <x-show-message-bags />

    <div class="mx-auto max-w-6xl space-y-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold">{{ __('باشگاه مشتریان و کیف پول اعتباری (وفاداری)') }}</h1>
                <p class="text-sm text-base-content/60">{{ __('کسب امتیاز خودکار با هر خرید، دسته‌بندی مشتریان در سطوح برنزی، نقره‌ای، طلایی و VIP، و تبدیل امتیاز به اعتبار نقدی.') }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Settings Form -->
            <div class="today-panel lg:col-span-1">
                <div class="today-panel-title mb-4 font-bold text-base">{{ __('تنظیمات قوانین باشگاه') }}</div>
                <form method="POST" action="{{ route('loyalty.settings.update') }}" class="space-y-4">
                    @csrf
                    <label class="label cursor-pointer justify-start gap-3">
                        <input type="checkbox" name="is_enabled" value="1" class="checkbox checkbox-primary checkbox-sm" {{ $settings->is_enabled ? 'checked' : '' }}>
                        <span class="label-text font-semibold">{{ __('فعال‌سازی باشگاه مشتریان') }}</span>
                    </label>

                    <div>
                        <label class="label text-xs font-semibold">{{ __('هر چند ریال خرید = ۱ امتیاز؟') }}</label>
                        <input type="number" name="spend_per_point" value="{{ $settings->spend_per_point }}" class="input input-bordered w-full text-left" dir="ltr" min="1000">
                    </div>

                    <div>
                        <label class="label text-xs font-semibold">{{ __('ارزش ریالی هر امتیاز در تبدیل') }}</label>
                        <input type="number" name="point_value_rials" value="{{ $settings->point_value_rials }}" class="input input-bordered w-full text-left" dir="ltr" min="10">
                    </div>

                    <div class="divider text-xs">{{ __('آستانه امتیاز سطوح مشتریان') }}</div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="label text-xs font-medium">{{ __('نقره‌ای (امتیاز)') }}</label>
                            <input type="number" name="silver_points_threshold" value="{{ $settings->silver_points_threshold }}" class="input input-bordered input-sm w-full" dir="ltr">
                        </div>
                        <div>
                            <label class="label text-xs font-medium">{{ __('تخفیف نقره‌ای (%)') }}</label>
                            <input type="number" step="0.1" name="silver_discount_percent" value="{{ $settings->silver_discount_percent }}" class="input input-bordered input-sm w-full" dir="ltr">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="label text-xs font-medium">{{ __('طلایی (امتیاز)') }}</label>
                            <input type="number" name="gold_points_threshold" value="{{ $settings->gold_points_threshold }}" class="input input-bordered input-sm w-full" dir="ltr">
                        </div>
                        <div>
                            <label class="label text-xs font-medium">{{ __('تخفیف طلایی (%)') }}</label>
                            <input type="number" step="0.1" name="gold_discount_percent" value="{{ $settings->gold_discount_percent }}" class="input input-bordered input-sm w-full" dir="ltr">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="label text-xs font-medium">{{ __('VIP (امتیاز)') }}</label>
                            <input type="number" name="vip_points_threshold" value="{{ $settings->vip_points_threshold }}" class="input input-bordered input-sm w-full" dir="ltr">
                        </div>
                        <div>
                            <label class="label text-xs font-medium">{{ __('تخفیف VIP (%)') }}</label>
                            <input type="number" step="0.1" name="vip_discount_percent" value="{{ $settings->vip_discount_percent }}" class="input input-bordered input-sm w-full" dir="ltr">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm w-full mt-4">{{ __('ذخیره تنظیمات') }}</button>
                </form>
            </div>

            <!-- Wallets List -->
            <div class="today-panel lg:col-span-2 space-y-4">
                <div class="today-panel-title font-bold text-base">{{ __('مشتریان و کیف‌پول‌های اعتباری') }}</div>

                <div class="overflow-x-auto">
                    <table class="table table-zebra table-sm">
                        <thead>
                            <tr>
                                <th>{{ __('مشتری') }}</th>
                                <th>{{ __('سطح کاربری') }}</th>
                                <th>{{ __('امتیاز وفاداری') }}</th>
                                <th>{{ __('موجودی کیف پول (ریال)') }}</th>
                                <th>{{ __('عملیات') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($wallets as $wallet)
                                <tr>
                                    <td class="font-semibold">{{ $wallet->customer?->name }}</td>
                                    <td>
                                        <span class="badge badge-sm font-bold {{ match($wallet->tier) {
                                            'VIP' => 'badge-error',
                                            'GOLD' => 'badge-warning',
                                            'SILVER' => 'badge-info',
                                            default => 'badge-ghost',
                                        } }}">
                                            {{ $wallet->tier }}
                                        </span>
                                    </td>
                                    <td class="font-mono text-primary font-bold">{{ number_format($wallet->points) }}</td>
                                    <td class="font-mono font-semibold">{{ number_format($wallet->balance) }}</td>
                                    <td>
                                        <a href="{{ route('loyalty.customers.wallet', $wallet->customer_id) }}" class="btn btn-ghost btn-xs">
                                            {{ __('مشاهده کیف پول') }}
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-8 text-base-content/40 text-xs">
                                        {{ __('هیچ کیف پولی ثبت نشده است.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $wallets->links() }}
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="today-panel">
            <div class="today-panel-title mb-4 font-bold text-base">{{ __('آخرین تراکنش‌های امتیاز و کیف پول') }}</div>
            <div class="overflow-x-auto">
                <table class="table table-zebra table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('مشتری') }}</th>
                            <th>{{ __('نوع تراکنش') }}</th>
                            <th>{{ __('تغییر امتیاز') }}</th>
                            <th>{{ __('تغییر مبلغ (ریال)') }}</th>
                            <th>{{ __('شرح') }}</th>
                            <th>{{ __('تاریخ') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentTransactions as $tx)
                            <tr>
                                <td class="font-medium">{{ $tx->customer?->name }}</td>
                                <td>
                                    <span class="badge badge-ghost badge-sm">
                                        {{ match($tx->type) {
                                            'POINTS_ACCRUED' => 'کسب امتیاز خرید',
                                            'POINTS_REDEEMED' => 'تبدیل امتیاز به اعتبار',
                                            'WALLET_DEPOSIT' => 'شارژ کیف پول',
                                            'INVOICE_PAYMENT' => 'پرداخت فاکتور',
                                            default => $tx->type,
                                        } }}
                                    </span>
                                </td>
                                <td class="font-mono text-xs {{ $tx->points_change >= 0 ? 'text-success' : 'text-error' }}">
                                    {{ $tx->points_change > 0 ? '+' : '' }}{{ number_format($tx->points_change) }}
                                </td>
                                <td class="font-mono text-xs {{ $tx->amount >= 0 ? 'text-success' : 'text-error' }}">
                                    {{ $tx->amount > 0 ? '+' : '' }}{{ number_format($tx->amount) }}
                                </td>
                                <td class="text-xs text-base-content/70">{{ $tx->description }}</td>
                                <td class="text-xs font-mono">{{ $tx->created_at->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-6 text-xs text-base-content/40">
                                    {{ __('هیچ تراکنشی ثبت نشده است.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
