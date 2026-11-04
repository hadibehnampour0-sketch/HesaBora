<x-app-layout :title="__('قرارداد اقساط: ') . $contract->contract_number">
    <x-show-message-bags />

    <div class="mx-auto max-w-6xl space-y-6" x-data="{
        payingScheduleId: null,
        payAmount: 0,
        payScheduleNumber: 0,
        loading: false,

        // Release modal
        releaseCollateralId: null,
        releaseCollateralTitle: '',

        // Image preview modal
        previewImageUrl: null,
        previewImageTitle: '',

        // Add Collateral modal state
        newCollateralType: 'CHEQUE',

        openPayModal(id, number, remaining) {
            this.payingScheduleId = id;
            this.payScheduleNumber = number;
            this.payAmount = remaining;
            document.getElementById('pay_modal').showModal();
        },

        openReleaseModal(id, title) {
            this.releaseCollateralId = id;
            this.releaseCollateralTitle = title;
            document.getElementById('release_modal').showModal();
        },

        openImagePreview(url, title) {
            this.previewImageUrl = url;
            this.previewImageTitle = title;
            document.getElementById('image_preview_modal').showModal();
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
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold flex items-center gap-2">
                    <span>{{ __('قرارداد تسهیلات و فروش اقساطی') }}</span>
                    <span class="font-mono text-primary font-bold">#{{ $contract->contract_number }}</span>
                    <span class="badge {{ $contract->status === 'SETTLED' ? 'badge-success' : 'badge-primary' }} text-xs font-semibold">
                        {{ $contract->status === 'SETTLED' ? 'تسویه کامل' : 'در حال پرداخت' }}
                    </span>
                </h1>
                <p class="text-sm text-base-content/60 mt-1">
                    {{ __('مشتری:') }} <span class="font-semibold text-base-content">{{ $contract->customer?->name }}</span> | 
                    {{ __('تاریخ شروع:') }} <span class="font-mono">{{ $contract->start_date?->format('Y-m-d') }}</span> |
                    {{ __('روش و نرخ:') }} 
                    <span class="badge badge-sm badge-outline font-mono">
                        {{ $contract->calculation_method === 'BANKING_AMORTIZATION' ? 'بانکی (PMT) ' . ($contract->applied_interest_rate ?? 23) . '٪ سالانه' : 'بازاری ' . ($contract->applied_interest_rate ?? 3.5) . '٪ ماهانه' }}
                    </span>
                </p>
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
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
            <div class="today-panel text-center">
                <span class="text-xs text-base-content/60 block">{{ __('مبلغ کل فاکتور') }}</span>
                <span class="font-bold font-mono text-sm block">{{ number_format($summary['total_price']) }}</span>
                <span class="text-[10px] text-base-content/50 font-mono">{{ number_format(floor($summary['total_price'] / 10)) }} ت</span>
            </div>
            <div class="today-panel text-center">
                <span class="text-xs text-base-content/60 block">{{ __('پیش‌پرداخت نقدی') }}</span>
                <span class="font-bold font-mono text-sm block">{{ number_format($summary['down_payment']) }}</span>
                <span class="text-[10px] text-base-content/50 font-mono">{{ number_format(floor($summary['down_payment'] / 10)) }} ت</span>
            </div>
            <div class="today-panel text-center">
                <span class="text-xs text-base-content/60 block">{{ __('اصل تسهیلات') }}</span>
                <span class="font-bold font-mono text-sm text-primary block">{{ number_format($summary['principal']) }}</span>
                <span class="text-[10px] text-primary/70 font-mono">{{ number_format(floor($summary['principal'] / 10)) }} ت</span>
            </div>
            <div class="today-panel text-center">
                <span class="text-xs text-base-content/60 block">{{ __('سود و کارمزد') }}</span>
                <span class="font-bold font-mono text-sm text-secondary block">{{ number_format($summary['total_interest']) }}</span>
                <span class="text-[10px] text-secondary/70 font-mono">{{ number_format(floor($summary['total_interest'] / 10)) }} ت</span>
            </div>
            <div class="today-panel text-center">
                <span class="text-xs text-base-content/60 block">{{ __('وصول شده') }}</span>
                <span class="font-bold font-mono text-sm text-success block">{{ number_format($summary['total_paid']) }}</span>
                <span class="text-[10px] text-success/70 font-mono">{{ number_format(floor($summary['total_paid'] / 10)) }} ت</span>
            </div>
            <div class="today-panel text-center">
                <span class="text-xs text-base-content/60 block">{{ __('مانده مطالبات') }}</span>
                <span class="font-bold font-mono text-sm text-error block">{{ number_format($summary['remaining_balance']) }}</span>
                <span class="text-[10px] text-error/70 font-mono">{{ number_format(floor($summary['remaining_balance'] / 10)) }} ت</span>
            </div>
        </div>

        <!-- Collaterals & Guarantees Section -->
        <div class="today-panel space-y-4">
            <div class="today-panel-title font-bold text-base flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-warning" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    <span>{{ __('وثایق و تضامین دریافتی قرارداد') }}</span>
                    <span class="badge badge-sm badge-info font-semibold">
                        {{ $contract->collaterals->count() }} فقره وثیقه
                    </span>
                    @if($contract->totalCollateralsEstimatedValue() > 0)
                        <span class="badge badge-sm badge-ghost font-mono">
                            ارزش کل وثایق امانت: {{ number_format(floor($contract->totalCollateralsEstimatedValue() / 10)) }} تومان
                        </span>
                    @endif
                </div>

                <button onclick="document.getElementById('add_collateral_modal').showModal()" class="btn btn-sm btn-primary">
                    <svg class="w-4 h-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ __('افزودن وثیقه جدید (چک، طلا، ملک، خودرو)') }}
                </button>
            </div>

            @if($contract->collaterals->isEmpty())
                <div class="text-center py-8 border-2 border-dashed border-base-300 rounded-xl bg-base-200/20">
                    <p class="text-xs text-base-content/60">{{ __('هنوز هیچ وثیقه‌ای (چک، طلا، ملک، خودرو، سفته) برای این قرارداد ثبت نشده است.') }}</p>
                    <button onclick="document.getElementById('add_collateral_modal').showModal()" class="btn btn-sm btn-outline btn-primary mt-3">
                        {{ __('ثبت اولین وثیقه') }}
                    </button>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($contract->collaterals as $col)
                        <div class="card bg-base-100 border border-base-300 shadow-sm rounded-xl overflow-hidden flex flex-col justify-between">
                            <div class="p-4 space-y-3">
                                <!-- Top Bar -->
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="badge badge-sm font-semibold {{ match($col->type) {
                                                'CHEQUE' => 'badge-primary',
                                                'GOLD' => 'badge-warning',
                                                'PROPERTY' => 'badge-info',
                                                'VEHICLE' => 'badge-success',
                                                'PROMISSORY_NOTE' => 'badge-secondary',
                                                default => 'badge-ghost',
                                            } }}">
                                                {{ $col->type_label }}
                                            </span>
                                            <span class="badge badge-sm badge-outline font-semibold">
                                                {{ $col->purpose_label }}
                                            </span>
                                        </div>
                                        <h3 class="font-bold text-sm mt-1.5">{{ $col->title }}</h3>
                                    </div>
                                    <span class="badge badge-sm font-semibold {{ $col->status === 'RECEIVED' ? 'badge-success badge-outline' : 'badge-ghost' }}">
                                        {{ $col->status_label }}
                                    </span>
                                </div>

                                <!-- Collateral Specific Details -->
                                <div class="bg-base-200/60 p-2.5 rounded-lg text-xs space-y-1.5 font-sans">
                                    @if($col->type === 'CHEQUE')
                                        <div class="flex justify-between">
                                            <span class="text-base-content/60">شناسه صیادی:</span>
                                            <span class="font-mono font-bold">{{ $col->cheque_sayad_number ?: '---' }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-base-content/60">بانک و شعبه:</span>
                                            <span>{{ $col->cheque_bank ?: '---' }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-base-content/60">صاحب حساب:</span>
                                            <span>{{ $col->cheque_account_owner ?: '---' }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-base-content/60">سررسید چک:</span>
                                            <span class="font-mono">{{ $col->cheque_due_date?->format('Y-m-d') ?: '---' }}</span>
                                        </div>
                                        @if($col->cheque_amount)
                                            <div class="flex justify-between">
                                                <span class="text-base-content/60">مبلغ چک:</span>
                                                <span class="font-mono font-bold text-primary">{{ number_format(floor($col->cheque_amount / 10)) }} تومان</span>
                                            </div>
                                        @endif
                                        @if($col->cheque_installment_number)
                                            <div class="flex justify-between">
                                                <span class="text-base-content/60">مربوط به قسط:</span>
                                                <span class="badge badge-xs badge-info font-mono">قسط #{{ $col->cheque_installment_number }}</span>
                                            </div>
                                        @endif

                                    @elseif($col->type === 'GOLD')
                                        <div class="flex justify-between">
                                            <span class="text-base-content/60">وزن دقیق:</span>
                                            <span class="font-mono font-bold text-warning">{{ number_format($col->gold_weight_grams, 3) }} گرم</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-base-content/60">عیار:</span>
                                            <span class="font-mono font-semibold">{{ $col->gold_karat }} عیار</span>
                                        </div>
                                        @if($col->estimated_value)
                                            <div class="flex justify-between">
                                                <span class="text-base-content/60">ارزش تقریبی روز:</span>
                                                <span class="font-mono font-bold text-success">{{ number_format(floor($col->estimated_value / 10)) }} تومان</span>
                                            </div>
                                        @endif

                                    @elseif($col->type === 'PROPERTY')
                                        <div class="flex justify-between">
                                            <span class="text-base-content/60">پلاک ثبتی:</span>
                                            <span class="font-mono font-bold">{{ $col->property_registry_code ?: '---' }}</span>
                                        </div>
                                        @if($col->property_area)
                                            <div class="flex justify-between">
                                                <span class="text-base-content/60">متراژ:</span>
                                                <span class="font-mono">{{ $col->property_area }} متر مربع</span>
                                            </div>
                                        @endif
                                        @if($col->property_address)
                                            <div class="text-[11px] text-base-content/70">
                                                <span class="text-base-content/60 block">آدرس:</span>
                                                {{ $col->property_address }}
                                            </div>
                                        @endif
                                        @if($col->estimated_value)
                                            <div class="flex justify-between">
                                                <span class="text-base-content/60">ارزش کارشناسی:</span>
                                                <span class="font-mono font-bold text-success">{{ number_format(floor($col->estimated_value / 10)) }} تومان</span>
                                            </div>
                                        @endif

                                    @elseif($col->type === 'VEHICLE')
                                        <div class="flex justify-between">
                                            <span class="text-base-content/60">مدل خودرو:</span>
                                            <span class="font-bold">{{ $col->vehicle_model ?: '---' }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-base-content/60">پلاک:</span>
                                            <span class="font-mono font-bold">{{ $col->vehicle_plate ?: '---' }}</span>
                                        </div>
                                        @if($col->vehicle_color)
                                            <div class="flex justify-between">
                                                <span class="text-base-content/60">رنگ:</span>
                                                <span>{{ $col->vehicle_color }}</span>
                                            </div>
                                        @endif
                                        @if($col->vehicle_chassis_number)
                                            <div class="flex justify-between">
                                                <span class="text-base-content/60">شماره شاسی:</span>
                                                <span class="font-mono text-[10px]">{{ $col->vehicle_chassis_number }}</span>
                                            </div>
                                        @endif
                                        @if($col->estimated_value)
                                            <div class="flex justify-between">
                                                <span class="text-base-content/60">ارزش کارشناسی:</span>
                                                <span class="font-mono font-bold text-success">{{ number_format(floor($col->estimated_value / 10)) }} تومان</span>
                                            </div>
                                        @endif

                                    @elseif($col->type === 'PROMISSORY_NOTE')
                                        <div class="flex justify-between">
                                            <span class="text-base-content/60">شماره سفته:</span>
                                            <span class="font-mono font-bold">{{ $col->promissory_note_number ?: '---' }}</span>
                                        </div>
                                        @if($col->estimated_value)
                                            <div class="flex justify-between">
                                                <span class="text-base-content/60">مبلغ تعهد:</span>
                                                <span class="font-mono font-bold text-primary">{{ number_format(floor($col->estimated_value / 10)) }} تومان</span>
                                            </div>
                                        @endif
                                    @endif

                                    @if($col->notes)
                                        <div class="text-[11px] text-base-content/60 border-t border-base-300 pt-1">
                                            {{ $col->notes }}
                                        </div>
                                    @endif
                                </div>

                                <!-- Photo / Attachment Thumbnail if present -->
                                @if($col->attachment_path)
                                    <div class="flex items-center gap-2 p-2 bg-base-200/40 rounded-lg">
                                        <img src="{{ $col->attachment_url }}" alt="{{ $col->title }}" class="w-12 h-12 object-cover rounded-lg border border-base-300 cursor-pointer hover:opacity-80" @click="openImagePreview('{{ $col->attachment_url }}', '{{ $col->title }}')">
                                        <div class="text-xs">
                                            <span class="font-semibold block text-[11px]">تصویر پیوست (طلا/سند/چک)</span>
                                            <button type="button" @click="openImagePreview('{{ $col->attachment_url }}', '{{ $col->title }}')" class="text-primary hover:underline text-[10px]">
                                                مشاهده تصویر در اندازه بزرگ
                                            </button>
                                        </div>
                                    </div>
                                @endif

                                @if($col->status === 'RELEASED')
                                    <div class="text-[10px] bg-base-200 p-2 rounded text-base-content/70">
                                        <span>تاریخ عودت: {{ $col->released_at?->format('Y-m-d') }}</span>
                                        @if($col->release_notes)
                                            <p class="mt-0.5">{{ $col->release_notes }}</p>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            <!-- Card Footer Actions -->
                            <div class="p-3 bg-base-200/50 border-t border-base-300 flex items-center justify-between">
                                @if($col->status === 'RECEIVED')
                                    <button type="button" @click="openReleaseModal({{ $col->id }}, '{{ $col->title }}')" class="btn btn-xs btn-outline btn-warning">
                                        عودت به مشتری
                                    </button>
                                @else
                                    <span class="text-xs text-base-content/50">تحویل داده شد</span>
                                @endif

                                <form method="POST" action="{{ route('financing.collaterals.destroy', $col->id) }}" onsubmit="return confirm('آیا از حذف این وثیقه اطمینان دارید؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-ghost btn-xs text-error">
                                        حذف
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
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
                            <th>{{ __('سهم اصل (ریال)') }}</th>
                            <th>{{ __('سهم سود (ریال)') }}</th>
                            <th>{{ __('مبلغ قسط (ریال)') }}</th>
                            <th>{{ __('مبلغ قسط (تومان)') }}</th>
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
                                <td class="font-mono text-xs text-success font-bold">{{ number_format(floor($s->amount / 10)) }} ت</td>
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

        <!-- Add Collateral Modal -->
        <dialog id="add_collateral_modal" class="modal">
            <div class="modal-box max-w-2xl">
                <h3 class="font-bold text-base mb-3">{{ __('ثبت وثیقه یا تضمین جدید برای قرارداد') }}</h3>
                <form method="POST" action="{{ route('financing.contracts.collaterals.store', $contract->id) }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="label text-xs font-semibold">{{ __('نوع وثیقه') }}</label>
                            <select name="type" x-model="newCollateralType" class="select select-bordered select-sm w-full font-semibold">
                                <option value="CHEQUE">{{ __('چک صیادی') }}</option>
                                <option value="GOLD">{{ __('طلا و مسکوکات') }}</option>
                                <option value="PROPERTY">{{ __('ملک و مستغلات') }}</option>
                                <option value="VEHICLE">{{ __('خودرو و وسایل نقلیه') }}</option>
                                <option value="PROMISSORY_NOTE">{{ __('سفته تجاری') }}</option>
                                <option value="OTHER">{{ __('سایر وثایق / ضامن') }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="label text-xs font-semibold">{{ __('عنوان وثیقه') }}</label>
                            <input type="text" name="title" class="input input-bordered input-sm w-full" placeholder="{{ __('مثال: النگو طلا ۱۸ عیار، چک صیادی بانک ملت...') }}" required>
                        </div>
                    </div>

                    <!-- Dynamic fields based on type -->
                    <template x-if="newCollateralType === 'CHEQUE'">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 bg-base-200/50 p-3 rounded-lg border border-base-300">
                            <div>
                                <label class="label text-xs font-semibold">{{ __('هدف چک') }}</label>
                                <select name="purpose" class="select select-bordered select-sm w-full">
                                    <option value="GUARANTEE">{{ __('وثیقه و ضمانت پرداخت') }}</option>
                                    <option value="PAYMENT">{{ __('پرداخت مستقیم اقساط') }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="label text-xs font-semibold">{{ __('شناسه صیادی (۱۶ رقم)') }}</label>
                                <input type="text" name="cheque_sayad_number" class="input input-bordered input-sm w-full font-mono text-left" dir="ltr" maxlength="16" placeholder="1234567890123456">
                            </div>
                            <div>
                                <label class="label text-xs font-semibold">{{ __('بانک و شعبه') }}</label>
                                <input type="text" name="cheque_bank" class="input input-bordered input-sm w-full" placeholder="ملی، صادرات، تجارت...">
                            </div>
                            <div>
                                <label class="label text-xs font-semibold">{{ __('صاحب حساب') }}</label>
                                <input type="text" name="cheque_account_owner" class="input input-bordered input-sm w-full" placeholder="نام و نام خانوادگی صادرکننده">
                            </div>
                            <div>
                                <label class="label text-xs font-semibold">{{ __('مبلغ چک (ریال)') }}</label>
                                <input type="number" name="cheque_amount" class="input input-bordered input-sm w-full font-mono text-left" dir="ltr" value="{{ $contract->monthly_installment_amount }}">
                            </div>
                            <div>
                                <label class="label text-xs font-semibold">{{ __('تاریخ سررسید چک') }}</label>
                                <input type="date" name="cheque_due_date" class="input input-bordered input-sm w-full">
                            </div>
                            <div>
                                <label class="label text-xs font-semibold">{{ __('شماره قسط متصل (اختیاری)') }}</label>
                                <input type="number" name="cheque_installment_number" class="input input-bordered input-sm w-full font-mono text-left" dir="ltr" min="1" max="120" placeholder="مثلاً: 1">
                            </div>
                        </div>
                    </template>

                    <template x-if="newCollateralType === 'GOLD'">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 bg-base-200/50 p-3 rounded-lg border border-base-300">
                            <div>
                                <label class="label text-xs font-semibold">{{ __('وزن دقیق طلا (گرم و سوت)') }}</label>
                                <input type="number" step="0.001" name="gold_weight_grams" class="input input-bordered input-sm w-full font-mono text-left" dir="ltr" placeholder="مثال: 18.450" required>
                            </div>
                            <div>
                                <label class="label text-xs font-semibold">{{ __('عیار طلا') }}</label>
                                <select name="gold_karat" class="select select-bordered select-sm w-full font-mono">
                                    <option value="18">{{ __('۱۸ عیار (۷۵۰)') }}</option>
                                    <option value="24">{{ __('۲۴ عیار (شمش / سکه)') }}</option>
                                    <option value="21">{{ __('۲۱ عیار') }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="label text-xs font-semibold">{{ __('ارزش تخمینی روز (ریال)') }}</label>
                                <input type="number" name="estimated_value" class="input input-bordered input-sm w-full font-mono text-left" dir="ltr">
                            </div>
                        </div>
                    </template>

                    <template x-if="newCollateralType === 'PROPERTY'">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 bg-base-200/50 p-3 rounded-lg border border-base-300">
                            <div>
                                <label class="label text-xs font-semibold">{{ __('پلاک ثبتی سند') }}</label>
                                <input type="text" name="property_registry_code" class="input input-bordered input-sm w-full" placeholder="پلاک ثبتی سند ملک">
                            </div>
                            <div>
                                <label class="label text-xs font-semibold">{{ __('متراژ (متر مربع)') }}</label>
                                <input type="number" step="0.1" name="property_area" class="input input-bordered input-sm w-full font-mono text-left" dir="ltr">
                            </div>
                            <div>
                                <label class="label text-xs font-semibold">{{ __('ارزش کارشناسی (ریال)') }}</label>
                                <input type="number" name="estimated_value" class="input input-bordered input-sm w-full font-mono text-left" dir="ltr">
                            </div>
                            <div class="sm:col-span-3">
                                <label class="label text-xs font-semibold">{{ __('آدرس دقیق ملک') }}</label>
                                <input type="text" name="property_address" class="input input-bordered input-sm w-full" placeholder="شهر، خیابان، پلاک">
                            </div>
                        </div>
                    </template>

                    <template x-if="newCollateralType === 'VEHICLE'">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 bg-base-200/50 p-3 rounded-lg border border-base-300">
                            <div>
                                <label class="label text-xs font-semibold">{{ __('مدل و نام خودرو') }}</label>
                                <input type="text" name="vehicle_model" class="input input-bordered input-sm w-full" placeholder="مثال: تارا اتومات V4 - ۱۴۰۳">
                            </div>
                            <div>
                                <label class="label text-xs font-semibold">{{ __('شماره پلاک ایران') }}</label>
                                <input type="text" name="vehicle_plate" class="input input-bordered input-sm w-full font-mono text-center" placeholder="مثال: ۱۲ الف ۳۴۵ ایران ۲۲">
                            </div>
                            <div>
                                <label class="label text-xs font-semibold">{{ __('شماره شاسی / VIN') }}</label>
                                <input type="text" name="vehicle_chassis_number" class="input input-bordered input-sm w-full font-mono text-left" dir="ltr">
                            </div>
                            <div>
                                <label class="label text-xs font-semibold">{{ __('ارزش کارشناسی (ریال)') }}</label>
                                <input type="number" name="estimated_value" class="input input-bordered input-sm w-full font-mono text-left" dir="ltr">
                            </div>
                        </div>
                    </template>

                    <template x-if="newCollateralType === 'PROMISSORY_NOTE'">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 bg-base-200/50 p-3 rounded-lg border border-base-300">
                            <div>
                                <label class="label text-xs font-semibold">{{ __('شماره سفته') }}</label>
                                <input type="text" name="promissory_note_number" class="input input-bordered input-sm w-full font-mono text-left" dir="ltr">
                            </div>
                            <div>
                                <label class="label text-xs font-semibold">{{ __('مبلغ تعهد سفته (ریال)') }}</label>
                                <input type="number" name="estimated_value" class="input input-bordered input-sm w-full font-mono text-left" dir="ltr">
                            </div>
                        </div>
                    </template>

                    <!-- Attachment Upload -->
                    <div>
                        <label class="label text-xs font-semibold flex items-center justify-between">
                            <span>{{ __('تصویر مدرک یا عکس طلا/سند/چک') }}</span>
                            <span class="text-base-content/50 text-[10px]">{{ __('حداکثر ۱۰ مگابایت (JPG/PNG)') }}</span>
                        </label>
                        <input type="file" name="attachment" accept="image/*" class="file-input file-input-bordered file-input-sm w-full">
                    </div>

                    <div>
                        <label class="label text-xs font-semibold">{{ __('توضیحات تکمیلی') }}</label>
                        <textarea name="notes" class="textarea textarea-bordered textarea-sm w-full" rows="2" placeholder="{{ __('شرح وضعیت، نام امانت‌دار...') }}"></textarea>
                    </div>

                    <div class="modal-action">
                        <button type="submit" class="btn btn-primary btn-sm">
                            {{ __('ذخیره وثیقه') }}
                        </button>
                        <button type="button" onclick="document.getElementById('add_collateral_modal').close()" class="btn btn-ghost btn-sm">
                            {{ __('انصراف') }}
                        </button>
                    </div>
                </form>
            </div>
        </dialog>

        <!-- Release Collateral Modal -->
        <dialog id="release_modal" class="modal">
            <div class="modal-box">
                <h3 class="font-bold text-base mb-2">{{ __('عودت وثیقه به مشتری') }}</h3>
                <p class="text-xs text-base-content/70 mb-4">
                    {{ __('آیا وثیقه') }} <strong x-text="releaseCollateralTitle"></strong> {{ __('را به مشتری تحویل می‌دهید؟') }}
                </p>

                <form method="POST" :action="'/financing/collaterals/' + releaseCollateralId + '/release'" class="space-y-3">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label class="label text-xs font-semibold">{{ __('توضیحات و مشخصات تحویل‌گیرنده') }}</label>
                        <textarea name="release_notes" class="textarea textarea-bordered w-full text-xs" rows="2" placeholder="{{ __('مثال: تحویل داده شد به شخص مشتری با امضای رسید...') }}"></textarea>
                    </div>

                    <div class="modal-action">
                        <button type="submit" class="btn btn-warning btn-sm">
                            {{ __('تأیید عودت و تحویل به مشتری') }}
                        </button>
                        <button type="button" onclick="document.getElementById('release_modal').close()" class="btn btn-ghost btn-sm">
                            {{ __('انصراف') }}
                        </button>
                    </div>
                </form>
            </div>
        </dialog>

        <!-- Image Preview Modal -->
        <dialog id="image_preview_modal" class="modal">
            <div class="modal-box max-w-3xl">
                <h3 class="font-bold text-base mb-3" x-text="previewImageTitle"></h3>
                <div class="flex justify-center p-2 bg-base-200/50 rounded-xl overflow-hidden">
                    <img :src="previewImageUrl" class="max-h-[70vh] object-contain rounded-lg shadow-lg">
                </div>
                <div class="modal-action">
                    <a :href="previewImageUrl" target="_blank" download class="btn btn-primary btn-sm">
                        {{ __('دانلود تصویر') }}
                    </a>
                    <button type="button" onclick="document.getElementById('image_preview_modal').close()" class="btn btn-ghost btn-sm">
                        {{ __('بستن') }}
                    </button>
                </div>
            </div>
        </dialog>

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
                    <button type="button" onclick="document.getElementById('pay_modal').close()" class="btn btn-ghost btn-sm">{{ __('انصراف') }}</button>
                </div>
            </div>
        </dialog>
    </div>
</x-app-layout>
