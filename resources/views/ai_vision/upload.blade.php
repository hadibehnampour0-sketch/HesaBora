<x-app-layout :title="__('Upload Paper Invoice for AI OCR Extraction')">
    <x-show-message-bags />

    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <a href="{{ route('ai-invoices.index') }}" class="btn btn-ghost btn-sm mb-2">
                &larr; {{ __('بازگشت به صف فاکتورهای هوش مصنوعی') }}
            </a>
            <h1 class="text-xl font-bold">{{ __('بارگذاری تصویر یا فایل PDF فاکتور خرید کاغذی') }}</h1>
            <p class="text-sm text-base-content/60">{{ __('تصویر باکیفیت فاکتور دست‌نویس، پرینت شده، یا فیش خرید را انتخاب کنید تا داده‌های آن به صورت خودکار خوانده شود.') }}</p>
        </div>

        <div class="today-panel">
            <form action="{{ route('ai-invoices.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf

                <div class="border-2 border-dashed border-base-300 hover:border-primary rounded-xl p-8 text-center transition-colors">
                    <svg class="mx-auto h-12 w-12 text-base-content/40 mb-3" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>

                    <label class="cursor-pointer block">
                        <span class="btn btn-primary btn-sm mb-2">{{ __('انتخاب فایل فاکتور (JPEG, PNG, WebP, PDF)') }}</span>
                        <input type="file" name="file" accept="image/*,.pdf" class="hidden" required onchange="document.getElementById('fileNamePreview').textContent = this.files[0]?.name || ''">
                    </label>
                    <div id="fileNamePreview" class="text-xs font-mono font-bold text-primary mt-2"></div>
                    <p class="text-xs text-base-content/50 mt-1">{{ __('حداکثر حجم فایل ۱۰ مگابایت. ترجیحاً فاکتور با نور کافی و بدون سایه عکاسی شود.') }}</p>
                </div>

                <div class="bg-base-200/60 p-4 rounded-lg space-y-2 text-xs text-base-content/70">
                    <div class="font-bold text-base-content flex items-center gap-1">
                        <svg class="w-4 h-4 text-info" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ __('اصل بنیادین راستی‌آزمایی انسانی در دخل (D-114):') }}
                    </div>
                    <p>
                        {{ __('«یک سند مالی قطعی در حسابداری نباید بدون تأیید انسان ثبت شود». هوش مصنوعی فاکتور را برای شما تایپ، محاسبه و آماده می‌کند؛ اما ثبت نهایی در دفاتر پس از بازبینی شما صورت می‌پذیرد.') }}
                    </p>
                </div>

                <div class="flex justify-end gap-2">
                    <a href="{{ route('ai-invoices.index') }}" class="btn btn-ghost btn-sm">{{ __('انصراف') }}</a>
                    <button type="submit" class="btn btn-primary btn-sm">
                        {{ __('شروع استخراج هوشمند و ورود به صف بازبینی') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
