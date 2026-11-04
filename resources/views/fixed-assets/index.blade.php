<x-app-layout :title="__('مدیریت اموال، تجهیزات و لاگ‌بوک تعمیرات')">
    <x-show-message-bags />

    <div class="mx-auto max-w-7xl space-y-6" x-data="{ addAssetModal: false }">
        {{-- Header & Quick Action --}}
        <div class="flex flex-wrap items-center justify-between gap-4 border-b pb-4">
            <div>
                <h1 class="text-2xl font-black text-slate-800 dark:text-white flex items-center gap-2">
                    <svg class="w-7 h-7 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                    <span>{{ __('اموال، تجهیزات و لاگ‌بوک تعمیرات و نگهداری') }}</span>
                </h1>
                <p class="text-sm text-base-content/60 mt-1">
                    {{ __('ثبت تجهیزات، ابزارآلات، وسایل نقلیه و تاسیسات به همراه ردیابی هزینه‌های سرویس و هوش اقتصادی توجیه نگهداری (TCO)') }}
                </p>
            </div>
            @can('fixed-assets.store')
                <button type="button" @click="addAssetModal = true" class="btn btn-primary gap-2 shadow-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>{{ __('ثبت وسیله / تجهیزات جدید') }}</span>
                </button>
            @endcan
        </div>

        {{-- 4 Summary Metric Cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="card bg-base-100 p-4 rounded-2xl border border-base-200 shadow-sm">
                <div class="text-xs text-base-content/60 font-semibold">{{ __('تعداد کل تجهیزات و وسایل') }}</div>
                <div class="text-2xl font-black text-slate-800 dark:text-white mt-1">{{ localizeNumber($metrics['total_assets_count']) }}</div>
                <div class="text-[11px] text-base-content/50 mt-1">اموال فعال و در حال بهره‌برداری</div>
            </div>
            <div class="card bg-base-100 p-4 rounded-2xl border border-base-200 shadow-sm">
                <div class="text-xs text-base-content/60 font-semibold">{{ __('مجموع ارزش دفتری تجهیزات') }}</div>
                <div class="text-lg font-black text-slate-800 dark:text-white font-mono mt-1">
                    {{ number_format($metrics['total_acquisition_cost']) }} <span class="text-xs font-normal text-base-content/60">ریال</span>
                </div>
                <div class="text-[11px] text-success mt-1">بهای تمام‌شده سرمایه‌ای</div>
            </div>
            <div class="card bg-base-100 p-4 rounded-2xl border border-base-200 shadow-sm">
                <div class="text-xs text-base-content/60 font-semibold">{{ __('مجموع هزینه‌های تعمیر و نگهداری') }}</div>
                <div class="text-lg font-black text-amber-600 font-mono mt-1">
                    {{ number_format($metrics['total_maintenance_expenses']) }} <span class="text-xs font-normal text-base-content/60">ریال</span>
                </div>
                <div class="text-[11px] text-amber-700 dark:text-amber-400 mt-1">ثبت شده در اسناد هزینه دوره</div>
            </div>
            <div class="card bg-base-100 p-4 rounded-2xl border border-base-200 shadow-sm">
                <div class="text-xs text-base-content/60 font-semibold">{{ __('تجهیزات در وضعیت فرسایش سرمایه') }}</div>
                <div class="text-2xl font-black {{ $metrics['critical_burn_count'] > 0 ? 'text-error animate-pulse' : 'text-success' }} mt-1">
                    {{ localizeNumber($metrics['critical_burn_count']) }}
                </div>
                <div class="text-[11px] {{ $metrics['critical_burn_count'] > 0 ? 'text-error' : 'text-base-content/50' }} mt-1">
                    {{ $metrics['critical_burn_count'] > 0 ? 'هزینه تعمیر بیش از ۳۵٪ ارزش دارایی' : 'تمامی تجهیزات اقتصادی هستند' }}
                </div>
            </div>
        </div>

        {{-- Assets Table --}}
        <div class="card bg-base-100 shadow border border-base-200 rounded-2xl overflow-hidden">
            <div class="p-4 border-b flex items-center justify-between">
                <h3 class="font-bold text-slate-800 dark:text-white text-base flex items-center gap-2">
                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                    </svg>
                    <span>{{ __('لیست اموال و شناسنامه تجهیزات') }}</span>
                </h3>
            </div>

            <div class="overflow-x-auto">
                <table class="table table-zebra w-full text-sm">
                    <thead>
                        <tr class="bg-base-200/60">
                            <th>{{ __('کد / ردیف') }}</th>
                            <th>{{ __('نام وسیله / تجهیزات') }}</th>
                            <th>{{ __('دسته / پلاک / سریال') }}</th>
                            <th>{{ __('تاریخ خرید') }}</th>
                            <th class="text-left">{{ __('بهای خرید (ریال)') }}</th>
                            <th class="text-left">{{ __('مجموع هزینه تعمیرات') }}</th>
                            <th>{{ __('نسبت فرسایش (MSR)') }}</th>
                            <th>{{ __('شاخص سلامت اقتصادی AI') }}</th>
                            <th class="text-end">{{ __('عملیات') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($assets as $asset)
                            @php
                                $health = $asset->healthStatus();
                                $active = $asset->status === \App\Enums\FixedAssetStatus::ACTIVE;
                                $totalMaint = $asset->totalMaintenanceCost();
                            @endphp
                            <tr class="hover">
                                <td class="font-mono text-xs font-bold">{{ $asset->code }}</td>
                                <td>
                                    <a href="{{ route('fixed-assets.show', $asset) }}" class="font-bold text-primary hover:underline">
                                        {{ $asset->name }}
                                    </a>
                                    @if ($asset->location)
                                        <div class="text-[11px] text-base-content/50">محل: {{ $asset->location }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-outline badge-sm">{{ $asset->categoryLabel() }}</span>
                                    @if ($asset->serial_or_plate)
                                        <span class="font-mono text-xs text-base-content/70 block mt-0.5">{{ $asset->serial_or_plate }}</span>
                                    @endif
                                </td>
                                <td class="text-xs">{{ formatDate($asset->acquisition_date) }}</td>
                                <td class="text-left font-mono font-bold">{{ number_format((float) $asset->acquisition_cost) }}</td>
                                <td class="text-left font-mono {{ $totalMaint > 0 ? 'text-amber-600 font-bold' : 'text-base-content/40' }}">
                                    {{ number_format($totalMaint) }}
                                </td>
                                <td>
                                    <span class="font-mono text-xs font-bold {{ $health['color'] }}">
                                        {{ $health['ratio'] }}%
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-sm {{ $health['badge_class'] }} font-semibold text-[11px]">
                                        {{ $health['label'] }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a href="{{ route('fixed-assets.show', $asset) }}" class="btn btn-xs btn-outline btn-primary gap-1">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            <span>{{ __('پرونده و لاگ‌بوک') }}</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-8 text-base-content/60">
                                    {{ __('هنوز وسیله یا تجهیزاتی ثبت نشده است. از دکمه «ثبت وسیله / تجهیزات جدید» استفاده کنید.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Modal for Quick Registering New Asset --}}
        <div class="modal" :class="{ 'modal-open': addAssetModal }" x-cloak>
            <div class="modal-box max-w-2xl" @click.outside="addAssetModal = false">
                <div class="flex items-center justify-between border-b pb-3">
                    <h3 class="font-black text-lg text-slate-800 dark:text-white flex items-center gap-2">
                        <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        <span>{{ __('ثبت اموال، وسایل و تجهیزات جدید') }}</span>
                    </h3>
                    <button type="button" @click="addAssetModal = false" class="btn btn-sm btn-ghost btn-circle">✕</button>
                </div>

                <form method="POST" action="{{ route('fixed-assets.store') }}" class="space-y-4 pt-4" x-data="{ showAdvanced: false }">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="form-control sm:col-span-2">
                            <label class="label font-bold text-xs">{{ __('نام وسیله یا تجهیزات') }} <span class="text-error">*</span></label>
                            <input type="text" name="name" class="input input-bordered" placeholder="مثلاً: کولر گازی ۲۴۰۰۰ جنرال، پراید وانت، تابلوی چلنیوم، ویترین..." required>
                        </div>

                        <div class="form-control">
                            <label class="label font-bold text-xs">{{ __('دسته‌بندی دارایی') }}</label>
                            <select name="category" class="select select-bordered">
                                @foreach ($categories as $catKey => $catLabel)
                                    <option value="{{ $catKey }}">{{ $catLabel }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-control">
                            <label class="label font-bold text-xs">{{ __('شماره پلاک، سریال یا بارکد') }}</label>
                            <input type="text" name="serial_or_plate" class="input input-bordered" placeholder="مثلاً: ۱۲ ب ۳۴۵ ایران ۶۸ یا سریال دستگاه">
                        </div>

                        <div class="form-control">
                            <label class="label font-bold text-xs">{{ __('تاریخ خرید / شروع بهره‌برداری') }} <span class="text-error">*</span></label>
                            <input type="text" name="acquisition_date" data-jdp autocomplete="off" readonly class="input input-bordered" value="{{ convertToJalali(now(), true) }}" required>
                        </div>

                        <div class="form-control">
                            <label class="label font-bold text-xs">{{ __('قیمت خرید یا ارزش اولیه (ریال)') }} <span class="text-error">*</span></label>
                            <input type="number" step="any" min="1" name="acquisition_cost" class="input input-bordered font-mono" placeholder="مبلغ به ریال" required>
                        </div>

                        <div class="form-control">
                            <label class="label font-bold text-xs">{{ __('محل استقرار') }}</label>
                            <input type="text" name="location" class="input input-bordered" placeholder="مثلاً: مغازه اصلی، کارگاه، خودروی پخش...">
                        </div>

                        <div class="form-control">
                            <label class="label font-bold text-xs">{{ __('کد اموال (اختیاری)') }}</label>
                            <input type="text" name="code" class="input input-bordered font-mono" placeholder="خالی بگذارید تا خودکار ساخته شود">
                        </div>
                    </div>

                    {{-- Collapsible Advanced Accounting Section --}}
                    <div class="pt-2 border-t">
                        <button type="button" @click="showAdvanced = !showAdvanced" class="btn btn-ghost btn-xs text-primary gap-1">
                            <span x-text="showAdvanced ? '− بستن تنظیمات پیشرفته حسابداری' : '+ تنظیمات پیشرفته سرفصل‌های حسابداری (اختیاری)'"></span>
                        </button>

                        <div x-show="showAdvanced" class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-3" x-cloak>
                            <div class="form-control">
                                <label class="label text-[11px] font-bold">{{ __('سرفصل دارایی') }}</label>
                                <select name="asset_subject_id" class="select select-bordered select-sm">
                                    <option value="">{{ __('پیش‌فرض خودکار سیستم') }}</option>
                                    @foreach ($subjects as $subject)
                                        <option value="{{ $subject->id }}">{{ $subject->formattedCode() }} — {{ $subject->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-control">
                                <label class="label text-[11px] font-bold">{{ __('سرفصل هزینه استهلاک') }}</label>
                                <select name="depreciation_expense_subject_id" class="select select-bordered select-sm">
                                    <option value="">{{ __('پیش‌فرض خودکار سیستم') }}</option>
                                    @foreach ($subjects as $subject)
                                        <option value="{{ $subject->id }}">{{ $subject->formattedCode() }} — {{ $subject->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-control">
                                <label class="label text-[11px] font-bold">{{ __('سرفصل استهلاک انباشته') }}</label>
                                <select name="accumulated_depreciation_subject_id" class="select select-bordered select-sm">
                                    <option value="">{{ __('پیش‌فرض خودکار سیستم') }}</option>
                                    @foreach ($subjects as $subject)
                                        <option value="{{ $subject->id }}">{{ $subject->formattedCode() }} — {{ $subject->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-control sm:col-span-3">
                                <label class="label text-[11px] font-bold">{{ __('عمر مفید دفتری (تعداد ماه)') }}</label>
                                <input type="number" min="1" name="useful_life_months" class="input input-bordered input-sm" placeholder="پیش‌فرض: ۶۰ ماه (۵ سال)">
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-4 border-t">
                        <button type="button" @click="addAssetModal = false" class="btn btn-ghost btn-sm">{{ __('انصراف') }}</button>
                        <button type="submit" class="btn btn-primary btn-sm px-6">{{ __('ثبت نهایی وسیله') }}</button>
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
