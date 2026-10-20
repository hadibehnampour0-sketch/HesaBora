<x-app-layout :title="__('Sales Financing & Installment Engine (Phase 21)')">
    <x-show-message-bags />

    <div class="mx-auto max-w-6xl space-y-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold">{{ __('فروش اقساطی، تسهیلات و محاسبه سود و جریمه دیرکرد') }}</h1>
                <p class="text-sm text-base-content/60">{{ __('محاسبه سود ماهانه بازاری و فرمول بانکی، تولید جدول زمان‌بندی اقساط، و محاسبه خودکار خسارت تأخیر تأدیه.') }}</p>
            </div>
            <div>
                <a href="{{ route('financing.create') }}" class="btn btn-primary btn-sm">
                    {{ __('+ ثبت قرارداد اقساط جدید') }}
                </a>
            </div>
        </div>

        <!-- Metrics Overview -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="today-panel text-center">
                <div class="text-xs text-base-content/60 font-semibold mb-1">{{ __('تعداد قراردادهای فعال') }}</div>
                <div class="text-2xl font-bold font-mono text-primary">{{ number_format($metrics['active_count']) }}</div>
            </div>

            <div class="today-panel text-center">
                <div class="text-xs text-base-content/60 font-semibold mb-1">{{ __('مجموع کل مطالبات اقساط') }}</div>
                <div class="text-2xl font-bold font-mono text-secondary">{{ number_format($metrics['total_financing']) }} <span class="text-xs font-normal">ریال</span></div>
            </div>

            <div class="today-panel text-center">
                <div class="text-xs text-base-content/60 font-semibold mb-1">{{ __('مجموع اقساط وصول شده') }}</div>
                <div class="text-2xl font-bold font-mono text-success">{{ number_format($metrics['total_collected']) }} <span class="text-xs font-normal">ریال</span></div>
            </div>

            <div class="today-panel text-center">
                <div class="text-xs text-base-content/60 font-semibold mb-1">{{ __('اقساط دارای سررسید معوق') }}</div>
                <div class="text-2xl font-bold font-mono text-error">{{ number_format($metrics['overdue_count']) }}</div>
            </div>
        </div>

        <!-- Contracts Table -->
        <div class="today-panel">
            <div class="today-panel-title mb-4 font-bold text-base">{{ __('لیست قراردادهای فروش اقساطی') }}</div>

            <div class="overflow-x-auto">
                <table class="table table-zebra table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('شماره قرارداد') }}</th>
                            <th>{{ __('مشتری') }}</th>
                            <th>{{ __('اصل تسهیلات (ریال)') }}</th>
                            <th>{{ __('سود و کارمزد (ریال)') }}</th>
                            <th>{{ __('مبلغ کل قرارداد') }}</th>
                            <th>{{ __('تعداد اقساط') }}</th>
                            <th>{{ __('قسط ماهانه') }}</th>
                            <th>{{ __('وضعیت') }}</th>
                            <th>{{ __('عملیات') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($contracts as $contract)
                            <tr>
                                <td class="font-mono font-bold text-xs" dir="ltr">{{ $contract->contract_number }}</td>
                                <td class="font-semibold">{{ $contract->customer?->name }}</td>
                                <td class="font-mono text-xs">{{ number_format($contract->principal_amount) }}</td>
                                <td class="font-mono text-xs text-primary">{{ number_format($contract->total_interest) }}</td>
                                <td class="font-mono text-xs font-bold">{{ number_format($contract->total_amount) }}</td>
                                <td class="font-mono text-xs">{{ $contract->tenor_months }} ماه</td>
                                <td class="font-mono text-xs">{{ number_format($contract->monthly_installment_amount) }}</td>
                                <td>
                                    <span class="badge badge-sm font-semibold {{ match($contract->status) {
                                        'ACTIVE' => 'badge-info',
                                        'SETTLED' => 'badge-success',
                                        'DEFAULTED' => 'badge-error',
                                        default => 'badge-ghost',
                                    } }}">
                                        {{ match($contract->status) {
                                            'ACTIVE' => 'جاری / فعال',
                                            'SETTLED' => 'تسویه شده کامل',
                                            'DEFAULTED' => 'نکول / معوق',
                                            default => $contract->status,
                                        } }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ route('financing.show', $contract->id) }}" class="btn btn-ghost btn-xs">
                                        {{ __('مشاهده اقساط') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-8 text-base-content/40 text-xs">
                                    {{ __('هیچ قرارداد اقساطی ثبت نشده است.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $contracts->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
