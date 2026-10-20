<x-app-layout :title="__('قرارداد اقساط: ') . $contract->contract_number">
    <x-show-message-bags />

    <div class="mx-auto max-w-6xl space-y-6" x-data="{
        payingScheduleId: null,
        payAmount: 0,
        payScheduleNumber: 0,
        loading: false,

        openPayModal(id, number, remaining) {
            this.payingScheduleId = id;
            this.payScheduleNumber = number;
            this.payAmount = remaining;
            document.getElementById('pay_modal').showModal();
        },

        async submitPayment() {
            if (this.payAmount <= 0) return;
            this.loading = true;
            try {
                const res = await fetch(`/financing/schedules/${this.payingScheduleId}/pay`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ amount: this.payAmount })
                });
                const data = await res.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'خطا در ثبت پرداخت');
                }
            } catch (e) {
                alert(e.message);
            } finally {
                this.loading = false;
            }
        }
    }">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold">{{ __('قرارداد تسهیلات و فروش اقساطی') }} - <span class="font-mono text-primary">{{ $contract->contract_number }}</span></h1>
                <p class="text-sm text-base-content/60">{{ __('مشتری:') }} <span class="font-semibold">{{ $contract->customer?->name }}</span> | {{ __('تاریخ شروع:') }} <span class="font-mono">{{ $contract->start_date?->format('Y-m-d') }}</span></p>
            </div>
            <div class="flex items-center gap-2">
                <form method="POST" action="{{ route('financing.contracts.penalties', $contract->id) }}">
                    @csrf
                    <button type="submit" class="btn btn-warning btn-outline btn-sm">
                        {{ __('محاسبه خسارت دیرکرد') }}
                    </button>
                </form>
                <a href="{{ route('financing.index') }}" class="btn btn-ghost btn-sm">{{ __('بازگشت به لیست') }}</a>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
            <div class="today-panel text-center">
                <span class="text-xs text-base-content/60 block">{{ __('مبلغ کل فاکتور') }}</span>
                <span class="font-bold font-mono text-sm">{{ number_format($summary['total_price']) }}</span>
            </div>
            <div class="today-panel text-center">
                <span class="text-xs text-base-content/60 block">{{ __('پیش‌پرداخت') }}</span>
                <span class="font-bold font-mono text-sm">{{ number_format($summary['down_payment']) }}</span>
            </div>
            <div class="today-panel text-center">
                <span class="text-xs text-base-content/60 block">{{ __('اصل تسهیلات') }}</span>
                <span class="font-bold font-mono text-sm text-primary">{{ number_format($summary['principal']) }}</span>
            </div>
            <div class="today-panel text-center">
                <span class="text-xs text-base-content/60 block">{{ __('سود و کارمزد') }}</span>
                <span class="font-bold font-mono text-sm text-secondary">{{ number_format($summary['total_interest']) }}</span>
            </div>
            <div class="today-panel text-center">
                <span class="text-xs text-base-content/60 block">{{ __('وصول شده') }}</span>
                <span class="font-bold font-mono text-sm text-success">{{ number_format($summary['total_paid']) }}</span>
            </div>
            <div class="today-panel text-center">
                <span class="text-xs text-base-content/60 block">{{ __('مانده مطالبات') }}</span>
                <span class="font-bold font-mono text-sm text-error">{{ number_format($summary['remaining_balance']) }}</span>
            </div>
        </div>

        <!-- Schedule Table -->
        <div class="today-panel">
            <div class="today-panel-title mb-4 font-bold text-base flex items-center justify-between">
                <span>{{ __('جدول سررسید و وضعیت بازپرداخت اقساط') }}</span>
                <span class="badge badge-info badge-sm font-semibold">
                    {{ $summary['paid_installments_count'] }} از {{ $summary['tenor_months'] }} قسط تسویه شده
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="table table-zebra table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('قسط') }}</th>
                            <th>{{ __('سررسید') }}</th>
                            <th>{{ __('سهم اصل') }}</th>
                            <th>{{ __('سهم سود') }}</th>
                            <th>{{ __('مبلغ قسط') }}</th>
                            <th>{{ __('جریمه دیرکرد') }}</th>
                            <th>{{ __('پرداخت شده') }}</th>
                            <th>{{ __('وضعیت') }}</th>
                            <th>{{ __('عملیات') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($contract->schedules as $s)
                            <tr>
                                <td class="font-mono text-xs font-bold">#{{ $s->installment_number }}</td>
                                <td class="font-mono text-xs">{{ $s->due_date?->format('Y-m-d') }}</td>
                                <td class="font-mono text-xs">{{ number_format($s->principal_portion) }}</td>
                                <td class="font-mono text-xs text-primary">{{ number_format($s->interest_portion) }}</td>
                                <td class="font-mono text-xs font-semibold">{{ number_format($s->amount) }}</td>
                                <td class="font-mono text-xs text-error">{{ number_format($s->late_fee_amount) }}</td>
                                <td class="font-mono text-xs text-success">{{ number_format($s->paid_amount) }}</td>
                                <td>
                                    <span class="badge badge-sm font-semibold {{ match($s->status) {
                                        'PAID' => 'badge-success',
                                        'OVERDUE' => 'badge-error',
                                        'PARTIALLY_PAID' => 'badge-warning',
                                        default => 'badge-ghost',
                                    } }}">
                                        {{ match($s->status) {
                                            'PAID' => 'پرداخت شد',
                                            'OVERDUE' => 'معوق / دارای دیرکرد',
                                            'PARTIALLY_PAID' => 'پرداخت ناقص',
                                            default => 'در انتظار پرداخت',
                                        } }}
                                    </span>
                                </td>
                                <td>
                                    @if($s->status !== 'PAID')
                                        <button @click="openPayModal({{ $s->id }}, {{ $s->installment_number }}, {{ $s->remainingAmount() }})" class="btn btn-primary btn-xs">
                                            {{ __('ثبت وصولی') }}
                                        </button>
                                    @else
                                        <span class="text-xs text-success font-semibold">✓ تسویه</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payment Modal -->
        <dialog id="pay_modal" class="modal">
            <div class="modal-box">
                <h3 class="font-bold text-base mb-3">{{ __('ثبت وصولی قسط شماره ') }} <span x-text="payScheduleNumber"></span></h3>
                <div class="space-y-4">
                    <div>
                        <label class="label text-xs font-semibold">{{ __('مبلغ وصولی (ریال)') }}</label>
                        <input type="number" x-model.number="payAmount" class="input input-bordered w-full font-mono text-left" dir="ltr" min="1000">
                    </div>
                </div>
                <div class="modal-action">
                    <button class="btn btn-primary btn-sm" @click="submitPayment()" :disabled="loading">
                        <span x-show="loading" class="loading loading-spinner loading-xs"></span>
                        {{ __('تأیید و ثبت در حسابداری') }}
                    </button>
                    <form method="dialog">
                        <button class="btn btn-ghost btn-sm">{{ __('انصراف') }}</button>
                    </form>
                </div>
            </div>
        </dialog>
    </div>
</x-app-layout>
