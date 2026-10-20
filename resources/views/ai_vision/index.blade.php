<x-app-layout :title="__('AI Paper Invoice Ingestion & OCR (Phase 23)')">
    <x-show-message-bags />

    <div class="mx-auto max-w-7xl space-y-6">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold">{{ __('ورود اطلاعات اسنادی با هوش مصنوعی و OCR فاکتورهای کاغذی (Phase 23)') }}</h1>
                <p class="text-sm text-base-content/60">{{ __('استخراج خودکار اقلام، مبالغ، مالیات و مشخصات فروشنده از تصاویر فاکتور و فیش‌های کاغذی با صف راستی‌آزمایی انسانی.') }}</p>
            </div>
            <div>
                <a href="{{ route('ai-invoices.upload') }}" class="btn btn-primary btn-sm gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    {{ __('+ بارگذاری تصویر/فایل فاکتور جدید') }}
                </a>
            </div>
        </div>

        <!-- Metric Badges / Filter Tabs -->
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('ai-invoices.index') }}" class="btn btn-sm {{ empty($status) ? 'btn-active btn-neutral' : 'btn-ghost' }}">
                {{ __('همه اسناد') }} ({{ $counts['all'] }})
            </a>
            <a href="{{ route('ai-invoices.index', ['status' => 'PENDING_REVIEW']) }}" class="btn btn-sm {{ $status === 'PENDING_REVIEW' ? 'btn-active btn-warning' : 'btn-ghost' }}">
                {{ __('در انتظار بررسی حسابدار') }} ({{ $counts['PENDING_REVIEW'] }})
            </a>
            <a href="{{ route('ai-invoices.index', ['status' => 'VERIFIED']) }}" class="btn btn-sm {{ $status === 'VERIFIED' ? 'btn-active btn-info' : 'btn-ghost' }}">
                {{ __('تأیید شده / آماده تبدیل') }} ({{ $counts['VERIFIED'] }})
            </a>
            <a href="{{ route('ai-invoices.index', ['status' => 'CONVERTED']) }}" class="btn btn-sm {{ $status === 'CONVERTED' ? 'btn-active btn-success' : 'btn-ghost' }}">
                {{ __('تبدیل شده به فاکتور خرید') }} ({{ $counts['CONVERTED'] }})
            </a>
            <a href="{{ route('ai-invoices.index', ['status' => 'REJECTED']) }}" class="btn btn-sm {{ $status === 'REJECTED' ? 'btn-active btn-error' : 'btn-ghost' }}">
                {{ __('رد شده / نامعتبر') }} ({{ $counts['REJECTED'] }})
            </a>
        </div>

        <!-- Table -->
        <div class="today-panel overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table table-sm w-full text-right">
                    <thead>
                        <tr class="bg-base-200">
                            <th>{{ __('شناسه') }}</th>
                            <th>{{ __('فروشنده / تأمین‌کننده') }}</th>
                            <th>{{ __('شماره و تاریخ فاکتور') }}</th>
                            <th>{{ __('مبلغ کل (ریال)') }}</th>
                            <th>{{ __('دقت هوش مصنوعی') }}</th>
                            <th>{{ __('وضعیت') }}</th>
                            <th>{{ __('عملیات') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($extractions as $item)
                            <tr class="hover">
                                <td class="font-mono text-xs font-bold">#{{ $item->id }}</td>
                                <td>
                                    <div class="font-bold text-sm">{{ $item->vendor_name ?: 'تأمین‌کننده نامشخص' }}</div>
                                    @if($item->matchedCustomer)
                                        <div class="text-[11px] text-success">
                                            {{ __('تطبیق با طرف‌حساب:') }} {{ $item->matchedCustomer->name }}
                                        </div>
                                    @elseif($item->vendor_phone)
                                        <div class="text-[11px] text-base-content/50 font-mono">{{ $item->vendor_phone }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="font-mono text-xs">{{ $item->invoice_number ?: '-' }}</div>
                                    <div class="text-[11px] text-base-content/60 font-mono">{{ $item->invoice_date ?: '-' }}</div>
                                </td>
                                <td class="font-mono text-xs font-bold">
                                    {{ number_format($item->total_amount) }}
                                    <div class="text-[10px] text-base-content/50 font-normal">مالیات: {{ number_format($item->vat_amount) }}</div>
                                </td>
                                <td>
                                    <div class="flex items-center gap-1 font-mono text-xs">
                                        <span class="badge badge-sm {{ $item->confidence_score >= 0.85 ? 'badge-success' : 'badge-warning' }}">
                                            {{ round($item->confidence_score * 100) }}%
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-sm {{ match($item->status) {
                                        'PENDING_REVIEW' => 'badge-warning',
                                        'VERIFIED' => 'badge-info',
                                        'CONVERTED' => 'badge-success',
                                        'REJECTED' => 'badge-error',
                                        default => 'badge-ghost',
                                    } }}">
                                        {{ match($item->status) {
                                            'PENDING_REVIEW' => 'در انتظار بررسی',
                                            'VERIFIED' => 'تأیید شده',
                                            'CONVERTED' => 'فاکتور خرید صادر شد',
                                            'REJECTED' => 'رد شده',
                                            default => $item->status,
                                        } }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        @if($item->status !== 'CONVERTED')
                                            <a href="{{ route('ai-invoices.review', $item->id) }}" class="btn btn-primary btn-xs">
                                                {{ __('بررسی و تبدیل') }}
                                            </a>
                                        @else
                                            @if($item->convertedInvoice)
                                                <a href="{{ route('invoices.show', $item->converted_invoice_id) }}" class="btn btn-outline btn-xs">
                                                    {{ __('مشاهده فاکتور #' . $item->convertedInvoice->number) }}
                                                </a>
                                            @else
                                                <span class="text-xs text-base-content/50">{{ __('تبدیل شده') }}</span>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-12 text-base-content/50">
                                    {{ __('هیچ سند یا فاکتور کاغذی در صف موجود نیست.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-4">
                {{ $extractions->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
