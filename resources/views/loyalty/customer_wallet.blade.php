<x-app-layout :title="__('کیف پول و امتیازات مشتری: ') . $customer->name">
    <x-show-message-bags />

    <div class="mx-auto max-w-5xl space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold">{{ __('کیف پول و باشگاه مشتریان: ') }} {{ $customer->name }}</h1>
                <p class="text-sm text-base-content/60">{{ __('مدیریت اعتبار نقدی، تبدیل امتیازات به موجودی و مشاهده گردش حساب کیف پول.') }}</p>
            </div>
            <a href="{{ route('loyalty.index') }}" class="btn btn-ghost btn-sm">{{ __('بازگشت به باشگاه مشتریان') }}</a>
        </div>

        <!-- Metric Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="today-panel text-center">
                <div class="text-xs text-base-content/60 font-semibold mb-1">{{ __('موجودی اعتبار کیف پول') }}</div>
                <div class="text-2xl font-bold font-mono text-primary">{{ number_format($wallet->balance) }} <span class="text-xs font-normal">ریال</span></div>
            </div>

            <div class="today-panel text-center">
                <div class="text-xs text-base-content/60 font-semibold mb-1">{{ __('امتیاز وفاداری فعال') }}</div>
                <div class="text-2xl font-bold font-mono text-secondary">{{ number_format($wallet->points) }} <span class="text-xs font-normal">امتیاز</span></div>
            </div>

            <div class="today-panel text-center">
                <div class="text-xs text-base-content/60 font-semibold mb-1">{{ __('سطح وفاداری') }}</div>
                <div class="mt-1">
                    <span class="badge badge-lg font-bold {{ match($wallet->tier) {
                        'VIP' => 'badge-error',
                        'GOLD' => 'badge-warning',
                        'SILVER' => 'badge-info',
                        default => 'badge-ghost',
                    } }}">
                        {{ $wallet->tier }}
                    </span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Points to Credit Conversion -->
            <div class="today-panel">
                <div class="today-panel-title mb-4 font-bold text-base">{{ __('تبدیل امتیاز به اعتبار کیف پول') }}</div>
                <form method="POST" action="{{ route('loyalty.customers.convert-points', $customer->id) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="label text-xs font-semibold">{{ __('تعداد امتیاز جهت تبدیل') }}</label>
                        <input type="number" name="points" max="{{ $wallet->points }}" min="1" class="input input-bordered w-full" placeholder="مثال: ۱۰۰" dir="ltr">
                        <span class="text-xs text-base-content/50 mt-1 block">
                            {{ __('امتیاز در دسترس: :points', ['points' => number_format($wallet->points)]) }}
                        </span>
                    </div>
                    <button type="submit" class="btn btn-secondary btn-sm w-full" {{ $wallet->points <= 0 ? 'disabled' : '' }}>
                        {{ __('تبدیل به موجودی ریالی') }}
                    </button>
                </form>
            </div>

            <!-- Direct Wallet Deposit -->
            <div class="today-panel">
                <div class="today-panel-title mb-4 font-bold text-base">{{ __('شارژ مستقیم کیف پول مشتری') }}</div>
                <form method="POST" action="{{ route('loyalty.customers.deposit', $customer->id) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="label text-xs font-semibold">{{ __('مبلغ شارژ (ریال)') }}</label>
                        <input type="number" name="amount" min="1000" step="1000" class="input input-bordered w-full" placeholder="مثال: ۵۰۰۰۰۰" dir="ltr">
                    </div>
                    <div>
                        <label class="label text-xs font-semibold">{{ __('بابت / توضیحات') }}</label>
                        <input type="text" name="description" class="input input-bordered w-full" placeholder="مثال: واریز نقدی پیش‌دریافت">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-full">
                        {{ __('ثبت افزایش موجودی') }}
                    </button>
                </form>
            </div>
        </div>

        <!-- Wallet History -->
        <div class="today-panel">
            <div class="today-panel-title mb-4 font-bold text-base">{{ __('گردش حساب کیف پول') }}</div>
            <div class="overflow-x-auto">
                <table class="table table-zebra table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('نوع عملیات') }}</th>
                            <th>{{ __('تغییر مبلغ (ریال)') }}</th>
                            <th>{{ __('تغییر امتیاز') }}</th>
                            <th>{{ __('مانده اعتبار بعد از تراکنش') }}</th>
                            <th>{{ __('توضیحات') }}</th>
                            <th>{{ __('تاریخ') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $tx)
                            <tr>
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
                                <td class="font-mono text-xs {{ $tx->amount >= 0 ? 'text-success' : 'text-error' }}">
                                    {{ $tx->amount > 0 ? '+' : '' }}{{ number_format($tx->amount) }}
                                </td>
                                <td class="font-mono text-xs {{ $tx->points_change >= 0 ? 'text-success' : 'text-error' }}">
                                    {{ $tx->points_change > 0 ? '+' : '' }}{{ number_format($tx->points_change) }}
                                </td>
                                <td class="font-mono text-xs font-semibold">{{ number_format($tx->balance_after) }}</td>
                                <td class="text-xs text-base-content/70">{{ $tx->description }}</td>
                                <td class="text-xs font-mono">{{ $tx->created_at->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-6 text-xs text-base-content/40">
                                    {{ __('هیچ تراکنشی در این کیف پول ثبت نشده است.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $transactions->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
