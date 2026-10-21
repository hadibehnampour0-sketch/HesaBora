@php
    $activeTerminals = \App\Models\PosTerminal::where('company_id', getActiveCompany())
        ->where('is_active', true)
        ->orderByDesc('is_default')
        ->get();
@endphp

<dialog id="pc_pos_modal" class="modal">
    <div class="modal-box max-w-lg rounded-3xl p-6" x-data="invoicePcPosHandler(@js($activeTerminals), '{{ route('pc-pos.pay-invoice', $invoice) }}', {{ $remainingAmount }})">
        <form method="dialog">
            <button class="btn btn-sm btn-circle btn-ghost absolute right-4 top-4">✕</button>
        </form>

        <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 rounded-2xl bg-accent/10 text-accent flex items-center justify-center text-2xl shadow-inner">
                💳
            </div>
            <div>
                <h3 class="text-lg font-black text-base-content">{{ __('ارسال مبلغ به دستگاه کارتخوان (PC-POS)') }}</h3>
                <p class="text-xs text-base-content/60">{{ __('تسویه مستقیم فاکتور شماره') }} #{{ $invoice->number }}</p>
            </div>
        </div>

        @if ($activeTerminals->isEmpty())
            <div class="p-6 text-center border-2 border-dashed border-base-300 rounded-2xl space-y-3">
                <div class="text-3xl">⚠️</div>
                <div class="text-sm font-bold text-base-content">{{ __('هیچ کارتخوان فعالی تعریف نشده است') }}</div>
                <p class="text-xs text-base-content/60">
                    {{ __('برای ارسال خودکار مبلغ فاکتور به کارتخوان‌های به‌پرداخت، سامان‌کیش یا سداد، ابتدا کارتخوان را در بخش مدیریت پایانه‌ها تعریف کنید.') }}
                </p>
                <a href="{{ route('pc-pos.index') }}" class="btn btn-primary btn-sm rounded-xl">
                    {{ __('پیکربندی کارتخوان‌ها') }}
                </a>
            </div>
        @else
            <!-- Ready State -->
            <div x-show="state === 'ready'" class="space-y-4">
                <div class="bg-base-200/50 p-4 rounded-2xl border border-base-200 space-y-2">
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-base-content/60">{{ __('مبلغ مانده فاکتور:') }}</span>
                        <span class="font-bold font-mono text-sm text-base-content">
                            {{ number_format($remainingAmount / 10) }} {{ __('تومان') }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-base-content/60">{{ __('معادل ریالی ارسالی به پوز:') }}</span>
                        <span class="font-bold font-mono text-xs text-primary">
                            {{ number_format($remainingAmount) }} {{ __('ریال') }}
                        </span>
                    </div>
                </div>

                <div>
                    <label class="label"><span class="label-text text-xs font-bold">{{ __('انتخاب کارتخوان بانکی:') }}</span></label>
                    <select class="select select-bordered select-sm w-full rounded-xl" x-model="selectedTerminalId">
                        <template x-for="t in terminals" :key="t.id">
                            <option :value="t.id" x-text="t.name + (t.is_default ? ' (پیش‌فرض)' : '')"></option>
                        </template>
                    </select>
                </div>

                <!-- Simulation Trigger if Simulator selected -->
                <div x-show="isSimulator()" class="p-3 bg-warning/10 border border-warning/20 rounded-xl space-y-2">
                    <div class="text-xs font-bold text-warning flex items-center gap-1.5">
                        <span>🧪</span>
                        <span>{{ __('حالت شبیه‌ساز فعال است:') }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <button type="button" @click="simulateScenario = 'success'" :class="simulateScenario === 'success' ? 'btn-primary' : 'btn-ghost'" class="btn btn-xs rounded-lg">
                            ✅ {{ __('تراکنش موفق') }}
                        </button>
                        <button type="button" @click="simulateScenario = 'cancel'" :class="simulateScenario === 'cancel' ? 'btn-primary' : 'btn-ghost'" class="btn btn-xs rounded-lg">
                            ❌ {{ __('انصراف') }}
                        </button>
                        <button type="button" @click="simulateScenario = 'declined'" :class="simulateScenario === 'declined' ? 'btn-primary' : 'btn-ghost'" class="btn btn-xs rounded-lg">
                            ⚠️ {{ __('کسر موجودی') }}
                        </button>
                    </div>
                </div>

                <div class="modal-action">
                    <button type="button" class="btn btn-sm rounded-xl" onclick="pc_pos_modal.close()">
                        {{ __('انصراف') }}
                    </button>
                    <button type="button" @click="sendToTerminal()" class="btn btn-accent btn-sm rounded-xl px-5 font-bold shadow-md shadow-accent/20">
                        <span>📡</span>
                        <span>{{ __('ارسال مبلغ به کارتخوان') }}</span>
                    </button>
                </div>
            </div>

            <!-- Waiting / Processing State -->
            <div x-show="state === 'waiting'" class="py-8 text-center space-y-4">
                <div class="relative w-20 h-20 mx-auto flex items-center justify-center">
                    <span class="loading loading-spinner text-accent loading-lg"></span>
                    <span class="absolute text-2xl animate-bounce">💳</span>
                </div>
                <div>
                    <h4 class="text-sm font-black text-base-content">{{ __('مبلغ به کارتخوان ارسال شد') }}</h4>
                    <p class="text-xs text-base-content/60 mt-1 max-w-xs mx-auto">
                        {{ __('لطفاً از مشتری بخواهید کارت بانکی را بکشد و رمز عبور را وارد کند...') }}
                    </p>
                </div>
                <div class="text-xs font-mono font-bold text-accent animate-pulse">
                    {{ __('در انتظار تایید سوئیچ شاپرک') }}
                </div>
            </div>

            <!-- Success State -->
            <div x-show="state === 'success'" class="py-6 text-center space-y-4">
                <div class="w-16 h-16 rounded-full bg-success/20 text-success mx-auto flex items-center justify-center text-3xl">
                    ✓
                </div>
                <div>
                    <h4 class="text-base font-black text-success">{{ __('تراکنش با موفقیت انجام شد') }}</h4>
                    <p class="text-xs text-base-content/70 mt-1" x-text="resultMessage"></p>
                </div>
                <div class="bg-base-200/60 p-3 rounded-2xl text-xs font-mono space-y-1 text-right max-w-xs mx-auto">
                    <div class="flex justify-between">
                        <span class="text-base-content/60">{{ __('شماره مرجع (RRN):') }}</span>
                        <span class="font-bold text-primary" x-text="resultRrn"></span>
                    </div>
                    <div class="flex justify-between" x-show="resultCard">
                        <span class="text-base-content/60">{{ __('کارت خریدار:') }}</span>
                        <span class="font-bold" x-text="resultCard"></span>
                    </div>
                </div>
                <div class="text-xs text-success font-medium">
                    {{ __('سند حسابداری صادر و فاکتور تسویه گردید. در حال به‌روزرسانی صفحه...') }}
                </div>
            </div>

            <!-- Failed State -->
            <div x-show="state === 'failed'" class="py-6 text-center space-y-4">
                <div class="w-16 h-16 rounded-full bg-error/20 text-error mx-auto flex items-center justify-center text-3xl">
                    ✕
                </div>
                <div>
                    <h4 class="text-base font-black text-error">{{ __('خطا در انجام تراکنش') }}</h4>
                    <p class="text-xs text-base-content/70 mt-1" x-text="resultMessage"></p>
                </div>
                <div class="modal-action justify-center">
                    <button type="button" @click="state = 'ready'" class="btn btn-sm btn-outline rounded-xl px-5">
                        {{ __('تلاش مجدد') }}
                    </button>
                    <button type="button" class="btn btn-sm rounded-xl" onclick="pc_pos_modal.close()">
                        {{ __('بستن') }}
                    </button>
                </div>
            </div>
        @endif
    </div>

    <form method="dialog" class="modal-backdrop">
        <button>{{ __('close') }}</button>
    </form>
</dialog>

<script>
    function invoicePcPosHandler(terminals, payUrl, remainingAmount) {
        return {
            terminals: terminals || [],
            selectedTerminalId: terminals && terminals.length ? terminals[0].id : null,
            simulateScenario: 'success',
            state: 'ready', // 'ready', 'waiting', 'success', 'failed'
            resultMessage: '',
            resultRrn: '',
            resultCard: '',

            isSimulator() {
                const term = this.terminals.find(t => t.id == this.selectedTerminalId);
                return term && term.psp === 'simulator';
            },

            async sendToTerminal() {
                if (!this.selectedTerminalId) return;

                this.state = 'waiting';

                try {
                    const res = await fetch(payUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            pos_terminal_id: this.selectedTerminalId,
                            amount: remainingAmount,
                            simulate_scenario: this.simulateScenario
                        })
                    });

                    const data = await res.json();

                    if (data.success) {
                        this.state = 'success';
                        this.resultMessage = data.message || 'پرداخت با موفقیت ثبت شد.';
                        this.resultRrn = data.rrn || '---';
                        this.resultCard = data.card_number || '';

                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        this.state = 'failed';
                        this.resultMessage = data.message || 'تراکنش توسط کاربر لغو شد یا در ارتباط خطایی رخ داد.';
                    }
                } catch (e) {
                    this.state = 'failed';
                    this.resultMessage = 'خطا در ارتباط با سرور یا پایانه کارتخوان.';
                }
            }
        };
    }
</script>
