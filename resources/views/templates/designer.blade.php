<x-app-layout :title="__('طراحی و تنظیمات قالب فاکتور')">
    <x-show-message-bags />

    <div class="mx-auto max-w-5xl space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold">{{ $template->exists ? __('ویرایش قالب چاپ: ') . $template->name : __('طراحی قالب چاپ جدید') }}</h1>
                <p class="text-sm text-base-content/60">{{ __('پیکربندی چیدمان، اقلام ستون‌ها، واترمارک، متن‌های حقوقی و امضاء.') }}</p>
            </div>
            <a href="{{ route('templates.index') }}" class="btn btn-ghost btn-sm">{{ __('بازگشت به لیست') }}</a>
        </div>

        <form method="POST" action="{{ $template->exists ? route('templates.update', $template->id) : route('templates.store') }}" class="space-y-6">
            @csrf
            @if($template->exists)
                @method('PUT')
            @endif

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Base Settings -->
                <div class="today-panel space-y-4">
                    <div class="today-panel-title font-bold text-base">{{ __('مشخصات عمومی') }}</div>

                    <div>
                        <label class="label text-xs font-semibold">{{ __('نام قالب') }}</label>
                        <input type="text" name="name" value="{{ old('name', $template->name ?? 'قالب جدید') }}" class="input input-bordered w-full" required>
                    </div>

                    <div>
                        <label class="label text-xs font-semibold">{{ __('ابعاد و جهت کاغذ') }}</label>
                        <select name="paper_size" class="select select-bordered w-full">
                            <option value="A4_PORTRAIT" {{ (old('paper_size', $template->paper_size) === 'A4_PORTRAIT') ? 'selected' : '' }}>A4 عمودی (استاندارد)</option>
                            <option value="A4_LANDSCAPE" {{ (old('paper_size', $template->paper_size) === 'A4_LANDSCAPE') ? 'selected' : '' }}>A4 افقی</option>
                            <option value="A5_PORTRAIT" {{ (old('paper_size', $template->paper_size) === 'A5_PORTRAIT') ? 'selected' : '' }}>A5 عمودی</option>
                            <option value="A5_LANDSCAPE" {{ (old('paper_size', $template->paper_size) === 'A5_LANDSCAPE') ? 'selected' : '' }}>A5 افقی</option>
                            <option value="THERMAL_80MM" {{ (old('paper_size', $template->paper_size) === 'THERMAL_80MM') ? 'selected' : '' }}>فیش‌پرینتر حرارتی ۸۰ میلی‌متر</option>
                            <option value="THERMAL_58MM" {{ (old('paper_size', $template->paper_size) === 'THERMAL_58MM') ? 'selected' : '' }}>فیش‌پرینتر حرارتی ۵۸ میلی‌متر</option>
                        </select>
                    </div>

                    <div>
                        <label class="label text-xs font-semibold">{{ __('متن واترمارک پس‌زمینه (اختیاری)') }}</label>
                        <input type="text" name="watermark_text" value="{{ old('watermark_text', $template->watermark_text) }}" class="input input-bordered w-full" placeholder="مثال: پیش‌فاکتور / نسخه خریدار">
                    </div>

                    <label class="label cursor-pointer justify-start gap-3">
                        <input type="checkbox" name="is_default" value="1" class="checkbox checkbox-primary checkbox-sm" {{ $template->is_default ? 'checked' : '' }}>
                        <span class="label-text font-semibold">{{ __('انتخاب به عنوان قالب پیش‌فرض') }}</span>
                    </label>

                    <button type="submit" class="btn btn-primary w-full mt-4">
                        {{ __('ذخیره تنظیمات قالب') }}
                    </button>
                </div>

                <!-- Header & Columns & Footer Panels -->
                <div class="today-panel md:col-span-2 space-y-6">
                    <!-- Header Settings -->
                    <div>
                        <div class="today-panel-title font-bold text-base mb-3">{{ __('تنظیمات سربرگ و هویت سازمانی') }}</div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="label text-xs font-semibold">{{ __('عنوان سربرگ فاکتور') }}</label>
                                <input type="text" name="header_config[custom_title]" value="{{ $template->header_config['custom_title'] ?? 'صورتحساب فروش کالا و خدمات' }}" class="input input-bordered w-full">
                            </div>

                            <div>
                                <label class="label text-xs font-semibold">{{ __('موقعیت لوگو') }}</label>
                                <select name="header_config[logo_position]" class="select select-bordered w-full">
                                    <option value="right" {{ ($template->header_config['logo_position'] ?? 'right') === 'right' ? 'selected' : '' }}>راست</option>
                                    <option value="center" {{ ($template->header_config['logo_position'] ?? '') === 'center' ? 'selected' : '' }}>وسط</option>
                                    <option value="left" {{ ($template->header_config['logo_position'] ?? '') === 'left' ? 'selected' : '' }}>چپ</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mt-3">
                            <label class="label cursor-pointer justify-start gap-2">
                                <input type="checkbox" name="header_config[show_logo]" value="1" class="checkbox checkbox-sm" {{ ($template->header_config['show_logo'] ?? true) ? 'checked' : '' }}>
                                <span class="label-text text-xs">نمایش لوگو</span>
                            </label>
                            <label class="label cursor-pointer justify-start gap-2">
                                <input type="checkbox" name="header_config[show_company_name]" value="1" class="checkbox checkbox-sm" {{ ($template->header_config['show_company_name'] ?? true) ? 'checked' : '' }}>
                                <span class="label-text text-xs">نام شرکت</span>
                            </label>
                            <label class="label cursor-pointer justify-start gap-2">
                                <input type="checkbox" name="header_config[show_economic_code]" value="1" class="checkbox checkbox-sm" {{ ($template->header_config['show_economic_code'] ?? true) ? 'checked' : '' }}>
                                <span class="label-text text-xs">کد اقتصادی</span>
                            </label>
                            <label class="label cursor-pointer justify-start gap-2">
                                <input type="checkbox" name="header_config[show_national_code]" value="1" class="checkbox checkbox-sm" {{ ($template->header_config['show_national_code'] ?? true) ? 'checked' : '' }}>
                                <span class="label-text text-xs">شناسه ملی</span>
                            </label>
                            <label class="label cursor-pointer justify-start gap-2">
                                <input type="checkbox" name="header_config[show_address]" value="1" class="checkbox checkbox-sm" {{ ($template->header_config['show_address'] ?? true) ? 'checked' : '' }}>
                                <span class="label-text text-xs">نشانی و تلفن</span>
                            </label>
                            <label class="label cursor-pointer justify-start gap-2">
                                <input type="checkbox" name="header_config[show_qr]" value="1" class="checkbox checkbox-sm" {{ ($template->header_config['show_qr'] ?? true) ? 'checked' : '' }}>
                                <span class="label-text text-xs">بارکد QR پرداخت/سامانه مودیان</span>
                            </label>
                        </div>
                    </div>

                    <div class="divider my-1"></div>

                    <!-- Columns Visibility -->
                    <div>
                        <div class="today-panel-title font-bold text-base mb-3">{{ __('ستون‌های جدول اقلام فاکتور') }}</div>
                        <div class="space-y-2">
                            @foreach($template->columns_config ?? \App\Models\InvoicePrintTemplate::defaultColumnsConfig() as $idx => $col)
                                <div class="flex items-center gap-3 p-2 bg-base-200 rounded">
                                    <input type="hidden" name="columns_config[{{ $idx }}][key]" value="{{ $col['key'] }}">
                                    <label class="cursor-pointer flex items-center gap-2">
                                        <input type="checkbox" name="columns_config[{{ $idx }}][visible]" value="1" class="checkbox checkbox-sm" {{ ($col['visible'] ?? true) ? 'checked' : '' }}>
                                        <span class="text-xs font-semibold">{{ $col['key'] }}</span>
                                    </label>
                                    <input type="text" name="columns_config[{{ $idx }}][label]" value="{{ $col['label'] }}" class="input input-bordered input-xs flex-1">
                                    <div class="flex items-center gap-1">
                                        <input type="number" name="columns_config[{{ $idx }}][width_percent]" value="{{ $col['width_percent'] ?? 10 }}" class="input input-bordered input-xs w-16 text-center" min="1" max="100">
                                        <span class="text-xs text-base-content/50">%</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="divider my-1"></div>

                    <!-- Footer Settings -->
                    <div>
                        <div class="today-panel-title font-bold text-base mb-3">{{ __('تنظیمات پاورقی و امضاء') }}</div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                            <label class="label cursor-pointer justify-start gap-2">
                                <input type="checkbox" name="footer_config[show_words_amount]" value="1" class="checkbox checkbox-sm" {{ ($template->footer_config['show_words_amount'] ?? true) ? 'checked' : '' }}>
                                <span class="label-text text-xs">مبلغ کل به حروف</span>
                            </label>
                            <label class="label cursor-pointer justify-start gap-2">
                                <input type="checkbox" name="footer_config[show_bank_sheba]" value="1" class="checkbox checkbox-sm" {{ ($template->footer_config['show_bank_sheba'] ?? true) ? 'checked' : '' }}>
                                <span class="label-text text-xs">اطلاعات حساب و شبا بانکی</span>
                            </label>
                            <label class="label cursor-pointer justify-start gap-2">
                                <input type="checkbox" name="footer_config[show_signatures]" value="1" class="checkbox checkbox-sm" {{ ($template->footer_config['show_signatures'] ?? true) ? 'checked' : '' }}>
                                <span class="label-text text-xs">کادرهای امضاء و مهر</span>
                            </label>
                        </div>

                        <div class="mt-3">
                            <label class="label text-xs font-semibold">{{ __('متن توضیحات / شرایط حقوقی پاورقی') }}</label>
                            <textarea name="footer_config[legal_notice]" class="textarea textarea-bordered w-full" rows="2">{{ $template->footer_config['legal_notice'] ?? '' }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
