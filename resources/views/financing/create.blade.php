<x-app-layout :title="__('ثبت قرارداد فروش اقساطی جدید')">
    <x-show-message-bags />

    <div class="mx-auto max-w-6xl space-y-6" x-data="{
        totalPrice: 100000000,
        downPayment: 20000000,
        tenorMonths: 10,
        method: 'MARKET_MONTHLY',
        monthlyRate: 3.5,
        annualRate: 23.0,
        calculated: null,
        loading: false,

        // Collaterals array
        collaterals: [],

        get principal() {
            return Math.max(0, this.totalPrice - this.downPayment);
        },

        get principalToman() {
            return Math.floor(this.principal / 10);
        },

        get totalPriceToman() {
            return Math.floor(this.totalPrice / 10);
        },

        get downPaymentToman() {
            return Math.floor(this.downPayment / 10);
        },

        setMonthlyPreset(rate) {
            this.monthlyRate = rate;
            this.computeLocal();
        },

        setAnnualPreset(rate) {
            this.annualRate = rate;
            this.computeLocal();
        },

        // Instant local calculation for 0-latency live reactivity
        computeLocal() {
            const p = this.principal;
            const n = Math.max(1, parseInt(this.tenorMonths) || 1);

            if (p <= 0) {
                this.calculated = {
                    principal: 0,
                    total_interest: 0,
                    total_amount: 0,
                    monthly_installment: 0,
                    schedule: []
                };
                return;
            }

            let totalInterest = 0;
            let totalAmount = 0;
            let monthlyInstallment = 0;
            let schedule = [];

            if (this.method === 'BANKING_AMORTIZATION') {
                const r = ((parseFloat(this.annualRate) || 0) / 100.0) / 12.0;
                if (r <= 0) {
                    totalInterest = 0;
                    totalAmount = p;
                    monthlyInstallment = Math.round(p / n);
                } else {
                    const factor = Math.pow(1 + r, n);
                    monthlyInstallment = Math.round(p * (r * factor) / (factor - 1));
                    totalAmount = monthlyInstallment * n;
                    totalInterest = Math.max(0, totalAmount - p);
                }

                let remaining = p;
                let accP = 0;
                let accI = 0;
                for (let i = 1; i <= n; i++) {
                    const iPortion = Math.round(remaining * r);
                    let pPortion = 0;
                    let amt = monthlyInstallment;
                    if (i === n) {
                        pPortion = p - accP;
                        amt = pPortion + (totalInterest - accI);
                    } else {
                        pPortion = Math.min(remaining, monthlyInstallment - iPortion);
                    }
                    remaining -= pPortion;
                    accP += pPortion;
                    accI += (amt - pPortion);
                    schedule.push({
                        installment_number: i,
                        principal_portion: pPortion,
                        interest_portion: amt - pPortion,
                        amount: amt
                    });
                }
            } else {
                // Market monthly interest
                const mRate = (parseFloat(this.monthlyRate) || 0) / 100.0;
                totalInterest = Math.round(p * mRate * n);
                totalAmount = p + totalInterest;
                monthlyInstallment = Math.round(totalAmount / n);

                const baseP = Math.floor(p / n);
                const baseI = Math.floor(totalInterest / n);
                let accP = 0;
                let accI = 0;

                for (let i = 1; i <= n; i++) {
                    let pPortion = (i === n) ? (p - accP) : baseP;
                    let iPortion = (i === n) ? (totalInterest - accI) : baseI;
                    accP += pPortion;
                    accI += iPortion;
                    schedule.push({
                        installment_number: i,
                        principal_portion: pPortion,
                        interest_portion: iPortion,
                        amount: pPortion + iPortion
                    });
                }
            }

            this.calculated = {
                principal: p,
                total_interest: totalInterest,
                total_amount: totalAmount,
                monthly_installment: monthlyInstallment,
                schedule: schedule
            };
        },

        addCollateral(type) {
            const index = this.collaterals.length + 1;
            let item = {
                id: Date.now() + Math.random(),
                type: type,
                title: '',
                purpose: 'GUARANTEE',
                estimated_value: 0,
                notes: '',
                // Cheque
                cheque_sayad_number: '',
                cheque_bank: '',
                cheque_account_owner: '',
                cheque_due_date: '',
                cheque_amount: this.calculated ? this.calculated.monthly_installment : 0,
                cheque_installment_number: index,
                // Gold
                gold_weight_grams: 0,
                gold_karat: 18,
                // Property
                property_registry_code: '',
                property_address: '',
                property_area: 0,
                // Vehicle
                vehicle_model: '',
                vehicle_plate: '',
                vehicle_chassis_number: '',
                vehicle_color: '',
                // Promissory Note
                promissory_note_number: ''
            };

            if (type === 'CHEQUE') {
                item.title = 'چک صیادی شماره ' + index;
            } else if (type === 'GOLD') {
                item.title = 'طلای وثیقه';
            } else if (type === 'PROPERTY') {
                item.title = 'سند ملک مسکونی/تجاری';
            } else if (type === 'VEHICLE') {
                item.title = 'سند خودرو';
            } else if (type === 'PROMISSORY_NOTE') {
                item.title = 'سفته حسن انجام تعهد';
            }

            this.collaterals.push(item);
        },

        removeCollateral(index) {
            this.collaterals.splice(index, 1);
        },

        init() {
            this.computeLocal();
        }
    }">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold flex items-center gap-2">
                    <svg class="w-6 h-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ __('ثبت قرارداد و محاسبه‌گر هوشمند فروش اقساطی') }}
                </h1>
                <p class="text-sm text-base-content/60">{{ __('محاسبه آنی سود با ورود دستی درصد، پیش‌نمایش جدول اقساط و ثبت وثایق (چک، طلا، ملک و خودرو)') }}</p>
            </div>
            <a href="{{ route('financing.index') }}" class="btn btn-ghost btn-sm">{{ __('بازگشت به لیست اقساط') }}</a>
        </div>

        <form method="POST" action="{{ route('financing.store') }}" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Contract Parameters Form -->
                <div class="today-panel lg:col-span-1 space-y-4">
                    <div class="today-panel-title font-bold text-base flex items-center justify-between">
                        <span>{{ __('مشخصات تسهیلات') }}</span>
                        <span class="badge badge-primary badge-outline text-xs">{{ __('تنظیمات پویا') }}</span>
                    </div>

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
                        <select name="calculation_method" x-model="method" @change="computeLocal()" class="select select-bordered w-full">
                            <option value="MARKET_MONTHLY">{{ __('سود ماهانه بازاری (مرسوم بازار و بنکداران)') }}</option>
                            <option value="BANKING_AMORTIZATION">{{ __('فرمول بانکی (اقساط مساوی PMT - بانک مرکزی)') }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="label text-xs font-semibold">{{ __('مبلغ کل فاکتور / کالا (ریال)') }}</label>
                        <input type="number" name="total_price" x-model.number="totalPrice" @input="computeLocal()" class="input input-bordered w-full text-left font-mono" dir="ltr" step="10000" required>
                        <div class="text-[11px] text-base-content/60 mt-1 flex justify-between">
                            <span>معادل به تومان:</span>
                            <span class="font-bold text-primary font-mono" x-text="Number(totalPriceToman).toLocaleString() + ' تومان'"></span>
                        </div>
                    </div>

                    <div>
                        <label class="label text-xs font-semibold">{{ __('مبلغ پیش‌پرداخت نقدی (ریال)') }}</label>
                        <input type="number" name="down_payment" x-model.number="downPayment" @input="computeLocal()" class="input input-bordered w-full text-left font-mono" dir="ltr" step="10000">
                        <div class="text-[11px] text-base-content/60 mt-1 flex justify-between">
                            <span>معادل به تومان:</span>
                            <span class="font-bold text-success font-mono" x-text="Number(downPaymentToman).toLocaleString() + ' تومان'"></span>
                        </div>
                    </div>

                    <div>
                        <label class="label text-xs font-semibold">{{ __('تعداد اقساط ماهانه') }}</label>
                        <input type="number" name="tenor_months" x-model.number="tenorMonths" @input="computeLocal()" class="input input-bordered w-full text-left font-mono" dir="ltr" min="1" max="120" required>
                    </div>

                    <!-- Market Monthly Rate -->
                    <div x-show="method === 'MARKET_MONTHLY'" class="space-y-2">
                        <label class="label text-xs font-semibold flex items-center justify-between">
                            <span>{{ __('درصد سود ماهانه بازاری (%)') }}</span>
                            <span class="badge badge-info badge-xs" x-text="monthlyRate + ' درصد در ماه'"></span>
                        </label>
                        <input type="number" step="0.1" name="monthly_interest_rate" x-model.number="monthlyRate" @input="computeLocal()" class="input input-bordered w-full text-left font-mono font-bold" dir="ltr">
                        
                        <!-- Quick percentage chips -->
                        <div class="flex flex-wrap gap-1.5 pt-1">
                            <span class="text-[10px] text-base-content/50 self-center">انتخاب سریع:</span>
                            <button type="button" @click="setMonthlyPreset(0)" :class="monthlyRate === 0 ? 'badge-primary' : 'badge-ghost'" class="badge badge-sm cursor-pointer hover:badge-primary">۰٪ (قرض‌الحسنه)</button>
                            <button type="button" @click="setMonthlyPreset(2.5)" :class="monthlyRate === 2.5 ? 'badge-primary' : 'badge-ghost'" class="badge badge-sm cursor-pointer hover:badge-primary">۲.۵٪</button>
                            <button type="button" @click="setMonthlyPreset(3.0)" :class="monthlyRate === 3.0 ? 'badge-primary' : 'badge-ghost'" class="badge badge-sm cursor-pointer hover:badge-primary">۳.۰٪</button>
                            <button type="button" @click="setMonthlyPreset(3.5)" :class="monthlyRate === 3.5 ? 'badge-primary' : 'badge-ghost'" class="badge badge-sm cursor-pointer hover:badge-primary">۳.۵٪</button>
                            <button type="button" @click="setMonthlyPreset(4.0)" :class="monthlyRate === 4.0 ? 'badge-primary' : 'badge-ghost'" class="badge badge-sm cursor-pointer hover:badge-primary">۴.۰٪</button>
                            <button type="button" @click="setMonthlyPreset(5.0)" :class="monthlyRate === 5.0 ? 'badge-primary' : 'badge-ghost'" class="badge badge-sm cursor-pointer hover:badge-primary">۵.۰٪</button>
                        </div>
                    </div>

                    <!-- Banking Annual Rate -->
                    <div x-show="method === 'BANKING_AMORTIZATION'" class="space-y-2">
                        <label class="label text-xs font-semibold flex items-center justify-between">
                            <span>{{ __('نرخ سود سالانه بانکی (%)') }}</span>
                            <span class="badge badge-info badge-xs" x-text="annualRate + ' درصد سالانه'"></span>
                        </label>
                        <input type="number" step="0.5" name="annual_interest_rate" x-model.number="annualRate" @input="computeLocal()" class="input input-bordered w-full text-left font-mono font-bold" dir="ltr">
                        
                        <!-- Quick presets -->
                        <div class="flex flex-wrap gap-1.5 pt-1">
                            <span class="text-[10px] text-base-content/50 self-center">نرخ‌های بانکی:</span>
                            <button type="button" @click="setAnnualPreset(4.0)" :class="annualRate === 4.0 ? 'badge-primary' : 'badge-ghost'" class="badge badge-sm cursor-pointer hover:badge-primary">۴٪ قرض‌الحسنه</button>
                            <button type="button" @click="setAnnualPreset(18.0)" :class="annualRate === 18.0 ? 'badge-primary' : 'badge-ghost'" class="badge badge-sm cursor-pointer hover:badge-primary">۱۸٪</button>
                            <button type="button" @click="setAnnualPreset(21.0)" :class="annualRate === 21.0 ? 'badge-primary' : 'badge-ghost'" class="badge badge-sm cursor-pointer hover:badge-primary">۲۱٪</button>
                            <button type="button" @click="setAnnualPreset(23.0)" :class="annualRate === 23.0 ? 'badge-primary' : 'badge-ghost'" class="badge badge-sm cursor-pointer hover:badge-primary">۲۳٪ بانک مرکزی</button>
                        </div>
                    </div>

                    <div>
                        <label class="label text-xs font-semibold">{{ __('تاریخ شروع اقساط') }}</label>
                        <input type="date" name="start_date" class="input input-bordered w-full" value="{{ now()->toDateString() }}" required>
                    </div>

                    <div>
                        <label class="label text-xs font-semibold">{{ __('توضیحات و شروط قرارداد') }}</label>
                        <textarea name="notes" class="textarea textarea-bordered w-full" rows="2" placeholder="{{ __('مثال: ضمانت چک صیادی و طلا نزد صندوق...') }}"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-full shadow-lg">
                        <svg class="w-5 h-5 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        {{ __('تأیید و صدور نهایی قرارداد اقساط') }}
                    </button>
                </div>

                <!-- Right Column: Live Calculation & Collaterals Section -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Live Calculations Summary -->
                    <div class="today-panel space-y-4">
                        <div class="today-panel-title font-bold text-base flex items-center justify-between">
                            <span>{{ __('خلاصه محاسبات لحظه‌ای اقساط') }}</span>
                            <span class="text-xs text-base-content/60 font-mono">{{ __('واحد: ریال و تومان') }}</span>
                        </div>

                        <template x-if="calculated">
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 bg-base-200/70 p-4 rounded-xl border border-base-300">
                                <div class="bg-base-100 p-3 rounded-lg shadow-sm">
                                    <span class="text-xs text-base-content/60 block">{{ __('اصل تسهیلات:') }}</span>
                                    <span class="font-bold font-mono text-sm block" x-text="Number(calculated.principal).toLocaleString() + ' ریال'"></span>
                                    <span class="text-[11px] text-base-content/60 font-mono block mt-0.5" x-text="'(' + Number(Math.floor(calculated.principal / 10)).toLocaleString() + ' تومان)'"></span>
                                </div>
                                <div class="bg-base-100 p-3 rounded-lg shadow-sm">
                                    <span class="text-xs text-base-content/60 block">{{ __('کل کارمزد و سود:') }}</span>
                                    <span class="font-bold font-mono text-sm text-primary block" x-text="Number(calculated.total_interest).toLocaleString() + ' ریال'"></span>
                                    <span class="text-[11px] text-primary/70 font-mono block mt-0.5" x-text="'(' + Number(Math.floor(calculated.total_interest / 10)).toLocaleString() + ' تومان)'"></span>
                                </div>
                                <div class="bg-base-100 p-3 rounded-lg shadow-sm">
                                    <span class="text-xs text-base-content/60 block">{{ __('کل مبلغ قرارداد:') }}</span>
                                    <span class="font-bold font-mono text-sm text-secondary block" x-text="Number(calculated.total_amount).toLocaleString() + ' ریال'"></span>
                                    <span class="text-[11px] text-secondary/70 font-mono block mt-0.5" x-text="'(' + Number(Math.floor(calculated.total_amount / 10)).toLocaleString() + ' تومان)'"></span>
                                </div>
                                <div class="bg-base-100 p-3 rounded-lg shadow-sm">
                                    <span class="text-xs text-base-content/60 block">{{ __('مبلغ هر قسط ماهانه:') }}</span>
                                    <span class="font-bold font-mono text-sm text-success block" x-text="Number(calculated.monthly_installment).toLocaleString() + ' ریال'"></span>
                                    <span class="text-[11px] text-success/80 font-mono block mt-0.5" x-text="'(' + Number(Math.floor(calculated.monthly_installment / 10)).toLocaleString() + ' تومان)'"></span>
                                </div>
                            </div>
                        </template>

                        <!-- Schedule Preview Table -->
                        <div class="overflow-x-auto max-h-56 rounded-lg border border-base-200">
                            <table class="table table-zebra table-sm">
                                <thead class="bg-base-200/50">
                                    <tr>
                                        <th>{{ __('شماره قسط') }}</th>
                                        <th>{{ __('سهم اصل (ریال)') }}</th>
                                        <th>{{ __('سهم سود (ریال)') }}</th>
                                        <th>{{ __('مبلغ قسط (ریال)') }}</th>
                                        <th>{{ __('مبلغ قسط (تومان)') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="item in (calculated ? calculated.schedule : [])" :key="item.installment_number">
                                        <tr>
                                            <td class="font-mono text-xs font-bold" x-text="'قسط #' + item.installment_number"></td>
                                            <td class="font-mono text-xs" x-text="Number(item.principal_portion).toLocaleString()"></td>
                                            <td class="font-mono text-xs text-primary" x-text="Number(item.interest_portion).toLocaleString()"></td>
                                            <td class="font-mono text-xs font-bold" x-text="Number(item.amount).toLocaleString()"></td>
                                            <td class="font-mono text-xs text-success" x-text="Number(Math.floor(item.amount / 10)).toLocaleString() + ' ت'"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Collateral & Guarantee Registration Section -->
                    <div class="today-panel space-y-4">
                        <div class="today-panel-title font-bold text-base flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-warning" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                                <span>{{ __('وثایق و تضمین‌های دریافتی از مشتری') }}</span>
                                <span class="badge badge-sm badge-ghost" x-text="collaterals.length + ' فقره وثیقه'"></span>
                            </div>
                            
                            <!-- Add Collateral Buttons -->
                            <div class="flex flex-wrap gap-1">
                                <button type="button" @click="addCollateral('CHEQUE')" class="btn btn-xs btn-outline btn-primary">
                                    + چک صیادی
                                </button>
                                <button type="button" @click="addCollateral('GOLD')" class="btn btn-xs btn-outline btn-warning">
                                    + طلا و مسکوکات
                                </button>
                                <button type="button" @click="addCollateral('PROPERTY')" class="btn btn-xs btn-outline btn-info">
                                    + سند ملک
                                </button>
                                <button type="button" @click="addCollateral('VEHICLE')" class="btn btn-xs btn-outline btn-success">
                                    + خودرو
                                </button>
                                <button type="button" @click="addCollateral('PROMISSORY_NOTE')" class="btn btn-xs btn-outline btn-secondary">
                                    + سفته
                                </button>
                            </div>
                        </div>

                        <!-- Empty state -->
                        <div x-show="collaterals.length === 0" class="text-center py-6 border-2 border-dashed border-base-300 rounded-xl bg-base-200/30">
                            <p class="text-xs text-base-content/60">{{ __('هنوز وثیقه‌ای ثبت نشده است. از دکمه‌های بالا برای افزودن چک، طلا، ملک، خودرو یا سفته استفاده کنید.') }}</p>
                            <p class="text-[11px] text-base-content/40 mt-1">{{ __('(همچنین پس از ثبت قرارداد، می‌توانید از صفحه قرارداد نیز وثایق را با تصویر پیوست اضافه کنید)') }}</p>
                        </div>

                        <!-- Collaterals List -->
                        <div class="space-y-3">
                            <template x-for="(col, index) in collaterals" :key="col.id">
                                <div class="bg-base-200/60 p-4 rounded-xl border border-base-300 space-y-3 relative">
                                    <!-- Header & Remove -->
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="badge font-semibold" :class="{
                                                'badge-primary': col.type === 'CHEQUE',
                                                'badge-warning': col.type === 'GOLD',
                                                'badge-info': col.type === 'PROPERTY',
                                                'badge-success': col.type === 'VEHICLE',
                                                'badge-secondary': col.type === 'PROMISSORY_NOTE',
                                            }" x-text="col.type === 'CHEQUE' ? 'چک صیادی' : (col.type === 'GOLD' ? 'طلا' : (col.type === 'PROPERTY' ? 'ملک' : (col.type === 'VEHICLE' ? 'خودرو' : 'سفته')))"></span>
                                            <input type="text" :name="'collaterals['+index+'][title]'" x-model="col.title" class="input input-sm input-bordered font-bold text-xs w-48 sm:w-64" placeholder="عنوان وثیقه" required>
                                            <input type="hidden" :name="'collaterals['+index+'][type]'" :value="col.type">
                                        </div>

                                        <button type="button" @click="removeCollateral(index)" class="btn btn-ghost btn-xs text-error">
                                            ✕ حذف
                                        </button>
                                    </div>

                                    <!-- CHEQUE SPECIFIC FIELDS -->
                                    <template x-if="col.type === 'CHEQUE'">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-2 text-xs">
                                            <div>
                                                <label class="label text-[11px] font-semibold py-0.5">{{ __('هدف چک') }}</label>
                                                <select :name="'collaterals['+index+'][purpose]'" x-model="col.purpose" class="select select-bordered select-xs w-full">
                                                    <option value="GUARANTEE">{{ __('وثیقه / ضمانت') }}</option>
                                                    <option value="PAYMENT">{{ __('پرداخت مستقیم قسط') }}</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="label text-[11px] font-semibold py-0.5">{{ __('شناسه صیادی (۱۶ رقم)') }}</label>
                                                <input type="text" :name="'collaterals['+index+'][cheque_sayad_number]'" x-model="col.cheque_sayad_number" class="input input-bordered input-xs w-full font-mono text-left" dir="ltr" maxlength="16" placeholder="مثال: 1234567890123456">
                                            </div>
                                            <div>
                                                <label class="label text-[11px] font-semibold py-0.5">{{ __('بانک و شعبه') }}</label>
                                                <input type="text" :name="'collaterals['+index+'][cheque_bank]'" x-model="col.cheque_bank" class="input input-bordered input-xs w-full" placeholder="مثال: ملی شعبه بازار">
                                            </div>
                                            <div>
                                                <label class="label text-[11px] font-semibold py-0.5">{{ __('صاحب حساب') }}</label>
                                                <input type="text" :name="'collaterals['+index+'][cheque_account_owner]'" x-model="col.cheque_account_owner" class="input input-bordered input-xs w-full" placeholder="نام صاحب چک">
                                            </div>
                                            <div>
                                                <label class="label text-[11px] font-semibold py-0.5">{{ __('مبلغ چک (ریال)') }}</label>
                                                <input type="number" :name="'collaterals['+index+'][cheque_amount]'" x-model.number="col.cheque_amount" class="input input-bordered input-xs w-full font-mono text-left" dir="ltr">
                                            </div>
                                            <div>
                                                <label class="label text-[11px] font-semibold py-0.5">{{ __('تاریخ سررسید') }}</label>
                                                <input type="date" :name="'collaterals['+index+'][cheque_due_date]'" x-model="col.cheque_due_date" class="input input-bordered input-xs w-full">
                                            </div>
                                            <div x-show="col.purpose === 'PAYMENT'">
                                                <label class="label text-[11px] font-semibold py-0.5">{{ __('شماره قسط متصل') }}</label>
                                                <input type="number" :name="'collaterals['+index+'][cheque_installment_number]'" x-model.number="col.cheque_installment_number" class="input input-bordered input-xs w-full font-mono text-left" dir="ltr" min="1" max="120">
                                            </div>
                                        </div>
                                    </template>

                                    <!-- GOLD SPECIFIC FIELDS -->
                                    <template x-if="col.type === 'GOLD'">
                                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs">
                                            <div>
                                                <label class="label text-[11px] font-semibold py-0.5">{{ __('وزن دقیق (گرم و سوت)') }}</label>
                                                <input type="number" step="0.001" :name="'collaterals['+index+'][gold_weight_grams]'" x-model.number="col.gold_weight_grams" class="input input-bordered input-xs w-full font-mono text-left" dir="ltr" placeholder="مثال: 15.350">
                                            </div>
                                            <div>
                                                <label class="label text-[11px] font-semibold py-0.5">{{ __('عیار طلا') }}</label>
                                                <select :name="'collaterals['+index+'][gold_karat]'" x-model.number="col.gold_karat" class="select select-bordered select-xs w-full font-mono">
                                                    <option value="18">{{ __('۱۸ عیار (۷۵۰) استاندارد') }}</option>
                                                    <option value="24">{{ __('۲۴ عیار (شمش / سکه)') }}</option>
                                                    <option value="21">{{ __('۲۱ عیار') }}</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="label text-[11px] font-semibold py-0.5">{{ __('ارزش تقریبی برآوردی (ریال)') }}</label>
                                                <input type="number" :name="'collaterals['+index+'][estimated_value]'" x-model.number="col.estimated_value" class="input input-bordered input-xs w-full font-mono text-left" dir="ltr" placeholder="ارزش حدودی طلا">
                                            </div>
                                        </div>
                                    </template>

                                    <!-- PROPERTY SPECIFIC FIELDS -->
                                    <template x-if="col.type === 'PROPERTY'">
                                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs">
                                            <div>
                                                <label class="label text-[11px] font-semibold py-0.5">{{ __('پلاک ثبتی سند') }}</label>
                                                <input type="text" :name="'collaterals['+index+'][property_registry_code]'" x-model="col.property_registry_code" class="input input-bordered input-xs w-full" placeholder="مثال: پلاک ۱۲۳۴ فرعی ۵۶">
                                            </div>
                                            <div>
                                                <label class="label text-[11px] font-semibold py-0.5">{{ __('متراژ (متر مربع)') }}</label>
                                                <input type="number" step="0.1" :name="'collaterals['+index+'][property_area]'" x-model.number="col.property_area" class="input input-bordered input-xs w-full font-mono text-left" dir="ltr">
                                            </div>
                                            <div>
                                                <label class="label text-[11px] font-semibold py-0.5">{{ __('ارزش کارشناسی (ریال)') }}</label>
                                                <input type="number" :name="'collaterals['+index+'][estimated_value]'" x-model.number="col.estimated_value" class="input input-bordered input-xs w-full font-mono text-left" dir="ltr">
                                            </div>
                                            <div class="sm:col-span-3">
                                                <label class="label text-[11px] font-semibold py-0.5">{{ __('آدرس دقیق ملک') }}</label>
                                                <input type="text" :name="'collaterals['+index+'][property_address]'" x-model="col.property_address" class="input input-bordered input-xs w-full" placeholder="شهر، خیابان، کوچه، پلاک، طبقه">
                                            </div>
                                        </div>
                                    </template>

                                    <!-- VEHICLE SPECIFIC FIELDS -->
                                    <template x-if="col.type === 'VEHICLE'">
                                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-2 text-xs">
                                            <div>
                                                <label class="label text-[11px] font-semibold py-0.5">{{ __('مدل و نوع خودرو') }}</label>
                                                <input type="text" :name="'collaterals['+index+'][vehicle_model]'" x-model="col.vehicle_model" class="input input-bordered input-xs w-full" placeholder="مثال: تارا اتومات V4 - ۱۴۰۳">
                                            </div>
                                            <div>
                                                <label class="label text-[11px] font-semibold py-0.5">{{ __('شماره پلاک ایران') }}</label>
                                                <input type="text" :name="'collaterals['+index+'][vehicle_plate]'" x-model="col.vehicle_plate" class="input input-bordered input-xs w-full font-mono text-center" placeholder="مثال: ۲۲ ل ۳۴۵ ایران ۶۸">
                                            </div>
                                            <div>
                                                <label class="label text-[11px] font-semibold py-0.5">{{ __('شماره شاسی / VIN') }}</label>
                                                <input type="text" :name="'collaterals['+index+'][vehicle_chassis_number]'" x-model="col.vehicle_chassis_number" class="input input-bordered input-xs w-full font-mono text-left" dir="ltr">
                                            </div>
                                            <div>
                                                <label class="label text-[11px] font-semibold py-0.5">{{ __('ارزش کارشناسی (ریال)') }}</label>
                                                <input type="number" :name="'collaterals['+index+'][estimated_value]'" x-model.number="col.estimated_value" class="input input-bordered input-xs w-full font-mono text-left" dir="ltr">
                                            </div>
                                        </div>
                                    </template>

                                    <!-- PROMISSORY NOTE SPECIFIC FIELDS -->
                                    <template x-if="col.type === 'PROMISSORY_NOTE'">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                                            <div>
                                                <label class="label text-[11px] font-semibold py-0.5">{{ __('شماره سفته / حسن تعهد') }}</label>
                                                <input type="text" :name="'collaterals['+index+'][promissory_note_number]'" x-model="col.promissory_note_number" class="input input-bordered input-xs w-full font-mono text-left" dir="ltr">
                                            </div>
                                            <div>
                                                <label class="label text-[11px] font-semibold py-0.5">{{ __('مبلغ تعهد سفته (ریال)') }}</label>
                                                <input type="number" :name="'collaterals['+index+'][estimated_value]'" x-model.number="col.estimated_value" class="input input-bordered input-xs w-full font-mono text-left" dir="ltr">
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
