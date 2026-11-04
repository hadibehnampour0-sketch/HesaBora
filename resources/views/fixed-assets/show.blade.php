<x-app-layout :title="__('پرونده اموال و لاگ‌بوک تعمیرات') . ' - ' . $asset->name">
    <x-show-message-bags />

    <div class="mx-auto max-w-6xl space-y-6" x-data="{ maintModal: false, depModal: false }">
        {{-- Header & Navigation --}}
        <div class="flex flex-wrap items-center justify-between gap-4 border-b pb-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="badge badge-primary badge-sm">{{ $asset->categoryLabel() }}</span>
                    @if ($asset->serial_or_plate)
                        <span class="badge badge-outline badge-sm font-mono">{{ $asset->serial_or_plate }}</span>
                    @endif
                    <span class="badge {{ $asset->status === \App\Enums\FixedAssetStatus::ACTIVE ? 'badge-success' : 'badge-ghost' }} badge-sm text-white">
                        {{ $asset->status->label() }}
                    </span>
                </div>
                <h1 class="text-2xl font-black text-slate-800 dark:text-white mt-1">{{ $asset->name }}</h1>
                <div class="text-xs text-base-content/60 font-mono mt-0.5">
                    کد اموال: {{ $asset->code }} @if($asset->location) — محل استقرار: {{ $asset->location }} @endif
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('fixed-assets.index') }}" class="btn btn-ghost btn-sm">
                    {{ __('بازگشت به لیست اموال') }}
                </a>
                <button type="button" @click="maintModal = true" class="btn btn-warning btn-sm gap-1.5 shadow">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    <span>{{ __('ثبت هزینه / تعمیر جدید') }}</span>
                </button>
            </div>
        </div>

        {{-- AI CFO Recommendation & Burn Warning Box --}}
        <div class="card {{ $aiAdvice['is_alert'] ? 'bg-gradient-to-r from-red-950 via-rose-900 to-slate-900 border-error/50 text-white' : ($health['status'] === 'moderate' ? 'bg-gradient-to-r from-amber-950 via-yellow-900 to-slate-900 border-warning/50 text-white' : 'bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 border-indigo-500/30 text-white') }} shadow-xl rounded-2xl border overflow-hidden">
            <div class="card-body p-5 md:p-6 space-y-3">
                <div class="flex items-center justify-between pb-2 border-b border-white/10">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center">
                            <svg class="w-5 h-5 text-indigo-300 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <span class="font-black text-sm">{{ $aiAdvice['title'] }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="badge badge-sm font-mono {{ $health['badge_class'] }}">
                            شاخص فرسودگی: {{ $health['ratio'] }}%
                        </span>
                    </div>
                </div>
                <p class="text-xs md:text-sm text-white/90 leading-relaxed">
                    {{ $aiAdvice['message'] }}
                </p>
            </div>
        </div>

        {{-- Financial Summary Metric Cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="card bg-base-100 p-4 rounded-xl border border-base-200 shadow-sm">
                <div class="text-xs text-base-content/60 font-medium">{{ __('بهای خرید اولیه') }}</div>
                <div class="text-lg font-black font-mono text-slate-800 dark:text-white mt-1">
                    {{ number_format((float) $asset->acquisition_cost) }} <span class="text-xs font-normal text-base-content/50">ریال</span>
                </div>
                <div class="text-[11px] text-base-content/50 mt-1">تاریخ: {{ formatDate($asset->acquisition_date) }}</div>
            </div>
            <div class="card bg-base-100 p-4 rounded-xl border border-base-200 shadow-sm">
                <div class="text-xs text-base-content/60 font-medium">{{ __('مجموع هزینه‌های تعمیرات') }}</div>
                <div class="text-lg font-black font-mono text-amber-600 mt-1">
                    {{ number_format($asset->totalMaintenanceCost()) }} <span class="text-xs font-normal text-base-content/50">ریال</span>
                </div>
                <div class="text-[11px] text-amber-700 dark:text-amber-400 mt-1">{{ localizeNumber($asset->maintenanceCount()) }} فقره تعمیر و سرویس</div>
            </div>
            <div class="card bg-base-100 p-4 rounded-xl border border-base-200 shadow-sm">
                <div class="text-xs text-base-content/60 font-medium">{{ __('استهلاک انباشته دفتری') }}</div>
                <div class="text-lg font-black font-mono text-slate-700 dark:text-slate-300 mt-1">
                    {{ number_format($accumulated) }} <span class="text-xs font-normal text-base-content/50">ریال</span>
                </div>
                <div class="text-[11px] text-base-content/50 mt-1">عمر مفید: {{ $asset->useful_life_months }} ماه</div>
            </div>
            <div class="card bg-base-100 p-4 rounded-xl border border-base-200 shadow-sm">
                <div class="text-xs text-base-content/60 font-medium">{{ __('ارزش دفتری خالص فعلی') }}</div>
                <div class="text-lg font-black font-mono text-emerald-600 mt-1">
                    {{ number_format(max(0, (float) $asset->acquisition_cost - $accumulated)) }} <span class="text-xs font-normal text-base-content/50">ریال</span>
                </div>
                <div class="text-[11px] text-emerald-700 dark:text-emerald-400 mt-1">ارزش خالص پس از استهلاک</div>
            </div>
        </div>

        {{-- Maintenance Logbook Table --}}
        <div class="card bg-base-100 shadow border border-base-200 rounded-2xl overflow-hidden">
            <div class="p-4 border-b flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <h3 class="font-black text-slate-800 dark:text-white text-base">
                        {{ __('لاگ‌بوک و تاریخچه تعمیرات و سرویس‌های این وسیله') }}
                    </h3>
                </div>
                <button type="button" @click="maintModal = true" class="btn btn-warning btn-xs gap-1">
                    <span>+ ثبت تعمیر جدید</span>
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="table table-zebra w-full text-xs md:text-sm">
                    <thead>
                        <tr class="bg-base-200/50">
                            <th>{{ __('تاریخ') }}</th>
                            <th>{{ __('عنوان سرویس / شرح خرابی') }}</th>
                            <th>{{ __('نوع اقدام') }}</th>
                            <th class="text-left">{{ __('مبلغ هزینه (ریال)') }}</th>
                            <th>{{ __('تعمیرکار / ارائه‌دهنده') }}</th>
                            <th>{{ __('کیلومتر / کارکرد') }}</th>
                            <th>{{ __('سند مالی') }}</th>
                            <th>{{ __('فاکتور / تصویر') }}</th>
                            <th class="text-end">{{ __('حذف') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($asset->maintenances as $maint)
                            <tr>
                                <td class="font-mono">{{ formatDate($maint->date) }}</td>
                                <td>
                                    <span class="font-bold block">{{ $maint->title }}</span>
                                    @if ($maint->description)
                                        <span class="text-[11px] text-base-content/60">{{ $maint->description }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-outline badge-xs">{{ $maint->typeLabel() }}</span>
                                </td>
                                <td class="text-left font-mono font-bold text-amber-600">
                                    {{ number_format((float) $maint->amount) }}
                                </td>
                                <td>{{ $maint->serviceProvider?->name ?? '—' }}</td>
                                <td class="font-mono text-xs">
                                    {{ $maint->odometer_or_hours ? localizeNumber($maint->odometer_or_hours) : '—' }}
                                </td>
                                <td>
                                    @if ($maint->document_id)
                                        <a href="{{ route('documents.show', $maint->document_id) }}" class="btn btn-ghost btn-xs text-primary gap-1 font-mono">
                                            #{{ $maint->document?->number ?? $maint->document_id }}
                                        </a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if ($maint->attachmentUrl())
                                        <a href="{{ $maint->attachmentUrl() }}" target="_blank" class="btn btn-xs btn-outline btn-info gap-1">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            <span>فایل</span>
                                        </a>
                                    @else
                                        <span class="text-base-content/40 text-xs">ندارد</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('fixed-assets.maintenances.destroy', [$asset, $maint]) }}" onsubmit="return confirm('آیا از حذف این رکورد تعمیرات اطمینان دارید؟');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-xs text-error">✕</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-6 text-base-content/60">
                                    {{ __('تاکنون هیچ هزینه یا تعمیری برای این وسیله ثبت نشده است.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Depreciation & Disposal Section --}}
        <div class="card bg-base-100 shadow border border-base-200 rounded-2xl p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h4 class="font-bold text-sm">{{ __('عملیات استهلاک و اسقاط دفتری') }}</h4>
                    <p class="text-xs text-base-content/60">{{ __('استهلاک ماهیانه طبق فرمول خط مستقیم با صدور سند حسابداری') }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($asset->status === \App\Enums\FixedAssetStatus::ACTIVE)
                        @can('fixed-assets.depreciation.store')
                            <form method="POST" action="{{ route('fixed-assets.depreciation.store', $asset) }}" class="flex items-center gap-1">
                                @csrf
                                <input type="text" name="period_date" data-jdp autocomplete="off" readonly class="input input-bordered input-sm w-32" value="{{ convertToJalali(now(), true) }}">
                                <button type="submit" class="btn btn-outline btn-primary btn-sm">{{ __('ثبت سند استهلاک این ماه') }}</button>
                            </form>
                        @endcan
                        @can('fixed-assets.dispose')
                            <form method="POST" action="{{ route('fixed-assets.dispose', $asset) }}" class="flex items-center gap-1" onsubmit="return confirm('آیا از اسقاط دفتری این دارایی اطمینان دارید؟');">
                                @csrf
                                <input type="text" name="disposed_at" data-jdp autocomplete="off" readonly class="input input-bordered input-sm w-32" value="{{ convertToJalali(now(), true) }}">
                                <button type="submit" class="btn btn-ghost btn-sm text-error">{{ __('اسقاط دارایی') }}</button>
                            </form>
                        @endcan
                    @endif
                </div>
            </div>
        </div>

        {{-- Modal for Recording New Maintenance --}}
        <div class="modal" :class="{ 'modal-open': maintModal }" x-cloak>
            <div class="modal-box max-w-xl" @click.outside="maintModal = false">
                <div class="flex items-center justify-between border-b pb-3">
                    <h3 class="font-black text-lg text-slate-800 dark:text-white flex items-center gap-2">
                        <svg class="w-6 h-6 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        <span>{{ __('ثبت هزینه تعمیر، سرویس یا نگهداری') }}</span>
                    </h3>
                    <button type="button" @click="maintModal = false" class="btn btn-sm btn-ghost btn-circle">✕</button>
                </div>

                <form method="POST" action="{{ route('fixed-assets.maintenances.store', $asset) }}" enctype="multipart/form-data" class="space-y-4 pt-4">
                    @csrf
                    <div class="form-control">
                        <label class="label font-bold text-xs">{{ __('عنوان اقدام / شرح سرویس یا تعمیر') }} <span class="text-error">*</span></label>
                        <input type="text" name="title" class="input input-bordered" placeholder="مثلاً: تعویض روغن و لنت، تعمیر کمپرسور، تعویض تسمه..." required>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="form-control">
                            <label class="label font-bold text-xs">{{ __('نوع هزینه') }} <span class="text-error">*</span></label>
                            <select name="maintenance_type" class="select select-bordered" required>
                                @foreach ($maintenanceTypes as $tKey => $tLabel)
                                    <option value="{{ $tKey }}">{{ $tLabel }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-control">
                            <label class="label font-bold text-xs">{{ __('تاریخ انجام') }} <span class="text-error">*</span></label>
                            <input type="text" name="date" data-jdp autocomplete="off" readonly class="input input-bordered" value="{{ convertToJalali(now(), true) }}" required>
                        </div>

                        <div class="form-control">
                            <label class="label font-bold text-xs">{{ __('مبلغ هزینه (ریال)') }} <span class="text-error">*</span></label>
                            <input type="number" step="any" min="1" name="amount" class="input input-bordered font-mono font-bold" placeholder="مبلغ به ریال" required>
                        </div>

                        <div class="form-control">
                            <label class="label font-bold text-xs">{{ __('کیلومتر کارکرد یا ساعت کار (اختیاری)') }}</label>
                            <input type="number" min="0" name="odometer_or_hours" class="input input-bordered font-mono" placeholder="مثلاً: ۱۲۵۰۰۰">
                        </div>

                        <div class="form-control sm:col-span-2">
                            <label class="label font-bold text-xs">{{ __('تعمیرکار / مکانیک / ارائه‌دهنده خدمات (اختیاری)') }}</label>
                            <select name="service_provider_id" class="select select-bordered">
                                <option value="">— انتخاب طرف‌حساب (اختیاری) —</option>
                                @foreach ($serviceProviders as $sp)
                                    <option value="{{ $sp->id }}">{{ $sp->name }} @if($sp->mobile) ({{ $sp->mobile }}) @endif</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-control sm:col-span-2">
                            <label class="label font-bold text-xs">{{ __('پرداخت هزینه از محل (حساب نقد یا بانک)') }}</label>
                            <select name="payment_subject_id" class="select select-bordered">
                                <option value="">— پیش‌فرض سیستم (صندوق اصلی) —</option>
                                @foreach ($subjects as $sub)
                                    <option value="{{ $sub->id }}">{{ $sub->formattedCode() }} — {{ $sub->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-control sm:col-span-2">
                            <label class="label font-bold text-xs">{{ __('پیوست فاکتور / داغی قطعه / عکس رسید (اختیاری)') }}</label>
                            <input type="file" name="invoice_attachment" class="file-input file-input-bordered file-input-sm w-full" accept="image/*,.pdf">
                        </div>

                        <div class="form-control sm:col-span-2">
                            <label class="label font-bold text-xs">{{ __('توضیحات تکمیلی و قطعات مصرفی') }}</label>
                            <textarea name="description" rows="2" class="textarea textarea-bordered text-xs" placeholder="توضیحات اختیاری درباره شماره قطعه، داغی یا علت خرابی..."></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-4 border-t">
                        <button type="button" @click="maintModal = false" class="btn btn-ghost btn-sm">{{ __('انصراف') }}</button>
                        <button type="submit" class="btn btn-warning btn-sm px-6">{{ __('ثبت هزینه و صدور سند مالی') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
        <script type="module">
            jalaliDatepicker.startWatch({'persianDigits': true});
        </script>
    @endpush
</x-app-layout>
