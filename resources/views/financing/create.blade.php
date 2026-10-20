<x-app-layout :title="__('ثبت قرارداد فروش اقساطی جدید')">
    <x-show-message-bags />

    <div class="mx-auto max-w-5xl space-y-6" x-data="{
        totalPrice: 10000000,
        downPayment: 2000000,
        tenorMonths: 10,
        method: 'MARKET_MONTHLY',
        monthlyRate: 3.5,
        annualRate: 23.0,
        calculated: null,
        loading: false,

        get principal() {
            return Math.max(0, this.totalPrice - this.downPayment);
        },

        async recalculate() {
            if (this.principal <= 0 || this.tenorMonths <= 0) return;
            this.loading = true;
            try {
                const res = await fetch('{{ route('financing.calculate') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        principal: this.principal,
                        method: this.method,
                        monthly_rate: this.monthlyRate,
                        annual_rate: this.annualRate,
                        tenor_months: this.tenorMonths
                    })
                });
                const data = await res.json();
                if (data.success) {
                    this.calculated = data.data;
                }
            } catch (e) {
                console.error(e);
            } finally {
                this.loading = false;
            }
        },

        init() {
            this.recalculate();
        }
    }">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold">{{ __('ثبت قرارداد و محاسبه‌گر فروش اقساطی') }}</h1>
                <p class="text-sm text-base-content/60">{{ __('محاسبه آنلاین کارمزد، مبلغ هر قسط و پیش‌نمایش جدول بازپرداخت بر اساس فرمول‌های بازاری و بانکی.') }}</p>
            </div>
            <a href="{{ route('financing.index') }}" class="btn btn-ghost btn-sm">{{ __('بازگشت به لیست') }}</a>
        </div>

        <form method="POST" action="{{ route('financing.store') }}" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Contract Parameters -->
                <div class="today-panel lg:col-span-1 space-y-4">
                    <div class="today-panel-title font-bold text-base">{{ __('مشخصات تسهیلات') }}</div>

                    <div>
                        <label class="label text-xs font-semibold">{{ __('مشتری طرف حساب') }}</label>
                        <select name="customer_id" class="select select-bordered w-full" required>
                            <option value="">{{ __('انتخاب مشتری...') }}</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->mobile }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="label text-xs font-semibold">{{ __('روش محاسبه سود') }}</label>
                        <select name="calculation_method" x-model="method" @change="recalculate()" class="select select-bordered w-full">
                            <option value="MARKET_MONTHLY">{{ __('سود ماهانه بازاری (مرسوم بازار)') }}</option>
                            <option value="BANKING_AMORTIZATION">{{ __('فرمول بانکی (اقساط مساوی PMT)') }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="label text-xs font-semibold">{{ __('مبلغ کل فاکتور / کالا (ریال)') }}</label>
                        <input type="number" name="total_price" x-model.number="totalPrice" @input="recalculate()" class="input input-bordered w-full text-left font-mono" dir="ltr" step="10000" required>
                    </div>

                    <div>
                        <label class="label text-xs font-semibold">{{ __('مبلغ پیش‌پرداخت نقدی (ریال)') }}</label>
                        <input type="number" name="down_payment" x-model.number="downPayment" @input="recalculate()" class="input input-bordered w-full text-left font-mono" dir="ltr" step="10000">
                    </div>

                    <div>
                        <label class="label text-xs font-semibold">{{ __('تعداد اقساط ماهانه') }}</label>
                        <input type="number" name="tenor_months" x-model.number="tenorMonths" @input="recalculate()" class="input input-bordered w-full text-left font-mono" dir="ltr" min="1" max="120" required>
                    </div>

                    <div x-show="method === 'MARKET_MONTHLY'">
                        <label class="label text-xs font-semibold">{{ __('درصد سود ماهانه بازاری (%)') }}</label>
                        <input type="number" step="0.1" name="monthly_interest_rate" x-model.number="monthlyRate" @input="recalculate()" class="input input-bordered w-full text-left font-mono" dir="ltr">
                    </div>

                    <div x-show="method === 'BANKING_AMORTIZATION'">
                        <label class="label text-xs font-semibold">{{ __('نرخ سود سالانه بانکی (%)') }}</label>
                        <input type="number" step="0.5" name="annual_interest_rate" x-model.number="annualRate" @input="recalculate()" class="input input-bordered w-full text-left font-mono" dir="ltr">
                    </div>

                    <div>
                        <label class="label text-xs font-semibold">{{ __('تاریخ شروع اقساط') }}</label>
                        <input type="date" name="start_date" class="input input-bordered w-full" value="{{ now()->toDateString() }}" required>
                    </div>

                    <div>
                        <label class="label text-xs font-semibold">{{ __('توضیحات و یادداشت قرارداد') }}</label>
                        <textarea name="notes" class="textarea textarea-bordered w-full" rows="2" placeholder="{{ __('مثال: ضمانت چک صیادی...') }}"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-full">
                        {{ __('تأیید و صدور جدول اقساط') }}
                    </button>
                </div>

                <!-- Live Preview & Schedule Table -->
                <div class="today-panel lg:col-span-2 space-y-4">
                    <div class="today-panel-title font-bold text-base">{{ __('خلاصه محاسبات و جدول زمان‌بندی') }}</div>

                    <template x-if="calculated">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 bg-base-200 p-4 rounded-lg">
                            <div>
                                <span class="text-xs text-base-content/60 block">{{ __('اصل تسهیلات:') }}</span>
                                <span class="font-bold font-mono text-sm" x-text="Number(calculated.principal).toLocaleString() + ' ریال'"></span>
                            </div>
                            <div>
                                <span class="text-xs text-base-content/60 block">{{ __('کل کارمزد / سود:') }}</span>
                                <span class="font-bold font-mono text-sm text-primary" x-text="Number(calculated.total_interest).toLocaleString() + ' ریال'"></span>
                            </div>
                            <div>
                                <span class="text-xs text-base-content/60 block">{{ __('مبلغ کل قرارداد:') }}</span>
                                <span class="font-bold font-mono text-sm text-secondary" x-text="Number(calculated.total_amount).toLocaleString() + ' ریال'"></span>
                            </div>
                            <div>
                                <span class="text-xs text-base-content/60 block">{{ __('مبلغ هر قسط:') }}</span>
                                <span class="font-bold font-mono text-sm text-success" x-text="Number(calculated.monthly_installment).toLocaleString() + ' ریال'"></span>
                            </div>
                        </div>
                    </template>

                    <div class="overflow-x-auto max-h-96">
                        <table class="table table-zebra table-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('شماره قسط') }}</th>
                                    <th>{{ __('سهم اصل (ریال)') }}</th>
                                    <th>{{ __('سهم سود (ریال)') }}</th>
                                    <th>{{ __('مبلغ کل قسط (ریال)') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="item in (calculated ? calculated.schedule : [])" :key="item.installment_number">
                                    <tr>
                                        <td class="font-mono text-xs" x-text="'قسط ' + item.installment_number"></td>
                                        <td class="font-mono text-xs" x-text="Number(item.principal_portion).toLocaleString()"></td>
                                        <td class="font-mono text-xs text-primary" x-text="Number(item.interest_portion).toLocaleString()"></td>
                                        <td class="font-mono text-xs font-bold" x-text="Number(item.amount).toLocaleString()"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
