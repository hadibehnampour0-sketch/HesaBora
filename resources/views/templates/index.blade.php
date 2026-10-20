<x-app-layout :title="__('Invoice Print Templates (Phase 22)')">
    <x-show-message-bags />

    <div class="mx-auto max-w-6xl space-y-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold">{{ __('طراح بصری قالب‌های چاپ فاکتور (A4، A5 و فیش‌پرینتر حرارتی)') }}</h1>
                <p class="text-sm text-base-content/60">{{ __('شخصی‌سازی ستون‌ها، لوگو، واترمارک، متن‌های حقوقی، جعبه شماره شبا و امضاء برای انواع ابعاد کاغذ بدون نیاز به کدنویسی.') }}</p>
            </div>
            <div>
                <a href="{{ route('templates.create') }}" class="btn btn-primary btn-sm">
                    {{ __('+ طراحی قالب چاپ جدید') }}
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($templates as $t)
                <div class="today-panel relative flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="badge badge-sm {{ str_starts_with($t->paper_size, 'THERMAL') ? 'badge-accent' : 'badge-primary' }}">
                                {{ match($t->paper_size) {
                                    'A4_PORTRAIT' => 'A4 عمودی',
                                    'A4_LANDSCAPE' => 'A4 افقی',
                                    'A5_PORTRAIT' => 'A5 عمودی',
                                    'A5_LANDSCAPE' => 'A5 افقی',
                                    'THERMAL_80MM' => 'حرارتی ۸۰ میلی‌متر',
                                    'THERMAL_58MM' => 'حرارتی ۵۸ میلی‌متر',
                                    default => $t->paper_size,
                                } }}
                            </span>
                            @if($t->is_default)
                                <span class="badge badge-success badge-sm font-semibold">{{ __('پیش‌فرض') }}</span>
                            @endif
                        </div>

                        <h3 class="text-base font-bold mb-1">{{ $t->name }}</h3>
                        @if($t->watermark_text)
                            <div class="text-xs text-base-content/50 mb-3">
                                {{ __('واترمارک:') }} <span class="font-semibold text-warning">{{ $t->watermark_text }}</span>
                            </div>
                        @endif

                        <div class="text-xs text-base-content/60 space-y-1 my-3 bg-base-200 p-2.5 rounded">
                            <div>عنوان سربرگ: <span class="font-medium text-base-content">{{ $t->header_config['custom_title'] ?? 'فاکتور فروش' }}</span></div>
                            <div>تعداد ستون‌های فعال: <span class="font-mono font-bold">{{ count(array_filter($t->columns_config ?? [], fn($c) => $c['visible'] ?? true)) }}</span></div>
                            <div>امضاء و مهر: <span class="font-medium text-base-content">{{ ($t->footer_config['show_signatures'] ?? true) ? 'فعال' : 'غیرفعال' }}</span></div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-1 mt-4 pt-3 border-t border-base-200">
                        <div class="flex items-center gap-1">
                            <a href="{{ route('templates.preview', $t->id) }}" target="_blank" class="btn btn-outline btn-xs">
                                {{ __('پیش‌نمایش چاپ') }}
                            </a>
                            <a href="{{ route('templates.edit', $t->id) }}" class="btn btn-ghost btn-xs">
                                {{ __('ویرایش') }}
                            </a>
                        </div>
                        <div class="flex items-center gap-1">
                            <form method="POST" action="{{ route('templates.duplicate', $t->id) }}">
                                @csrf
                                <button type="submit" class="btn btn-ghost btn-xs" title="{{ __('کپی از این قالب') }}">
                                    {{ __('کپی') }}
                                </button>
                            </form>
                            @if(! $t->is_default)
                                <form method="POST" action="{{ route('templates.default', $t->id) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-ghost btn-xs text-success" title="{{ __('انتخاب به عنوان پیش‌فرض') }}">
                                        ★
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('templates.destroy', $t->id) }}" onsubmit="return confirm('آیا از حذف این قالب اطمینان دارید؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-ghost btn-xs text-error">
                                        ✕
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="today-panel col-span-3 text-center py-10 text-base-content/40">
                    {{ __('هیچ قالب چاپی یافت نشد.') }}
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
