<x-app-layout :title="__('جزئیات درخواست پرسنلی') . ' #' . $personnelRequest->id">
    <div class="card bg-base-100 shadow-xl max-w-3xl mx-auto">
        <div class="card-header bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-700 px-6 py-4 rounded-t-2xl border-b flex items-center justify-between">
            <h2 class="text-xl font-bold flex items-center gap-2">
                <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                {{ __('درخواست پرسنلی') }} #{{ $personnelRequest->id }}
            </h2>
            <a href="{{ route('hr.personnel-requests.index') }}" class="btn btn-ghost btn-sm">{{ __('بازگشت به لیست') }}</a>
        </div>

        <div class="card-body space-y-4">
            <x-show-message-bags />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="p-3 bg-base-200/50 rounded-lg">
                    <span class="text-xs text-base-content/60 block">{{ __('پرسنل / کارمند') }}</span>
                    <span class="font-bold text-sm">{{ $personnelRequest->employee?->fullName() ?? '—' }} ({{ $personnelRequest->employee?->personnel_code ?? '—' }})</span>
                </div>
                <div class="p-3 bg-base-200/50 rounded-lg">
                    <span class="text-xs text-base-content/60 block">{{ __('نوع درخواست') }}</span>
                    <span class="badge badge-outline font-semibold">{{ $personnelRequest->request_type?->label() ?? '—' }}</span>
                </div>
                <div class="p-3 bg-base-200/50 rounded-lg">
                    <span class="text-xs text-base-content/60 block">{{ __('بازه زمانی / تاریخ') }}</span>
                    <span class="font-bold text-sm">{{ $personnelRequest->start_date }} الی {{ $personnelRequest->end_date }}</span>
                </div>
                <div class="p-3 bg-base-200/50 rounded-lg">
                    <span class="text-xs text-base-content/60 block">{{ __('وضعیت بررسی') }}</span>
                    @php
                        $badgeClass = match($personnelRequest->status?->value) {
                            'approved' => 'badge-success',
                            'rejected' => 'badge-error',
                            default => 'badge-warning',
                        };
                    @endphp
                    <span class="badge {{ $badgeClass }} font-semibold">{{ $personnelRequest->status?->label() ?? 'نامشخص' }}</span>
                </div>
            </div>

            @if ($personnelRequest->reason)
                <div class="p-4 bg-base-200/30 rounded-lg border border-base-200">
                    <span class="text-xs text-base-content/60 block mb-1 font-semibold">{{ __('علت / توضیحات متقاضی:') }}</span>
                    <p class="text-sm">{{ $personnelRequest->reason }}</p>
                </div>
            @endif

            @if ($personnelRequest->rejection_reason)
                <div class="p-4 bg-error/10 border border-error/30 rounded-lg">
                    <span class="text-xs text-error block mb-1 font-semibold">{{ __('علت رد درخواست:') }}</span>
                    <p class="text-sm text-error">{{ $personnelRequest->rejection_reason }}</p>
                </div>
            @endif

            <div class="pt-4 border-t flex flex-wrap justify-between items-center gap-2">
                <div class="text-xs text-base-content/50">
                    {{ __('ثبت شده توسط:') }} {{ $personnelRequest->createdBy?->name ?? '—' }}
                </div>
                <div class="flex gap-2">
                    @if ($personnelRequest->status?->isPending())
                        @can('hr.personnel-requests.approve')
                            <form method="POST" action="{{ route('hr.personnel-requests.approve', $personnelRequest) }}">
                                @csrf
                                <button type="submit" class="btn btn-success btn-sm">{{ __('تایید درخواست') }}</button>
                            </form>
                        @endcan
                        @can('hr.personnel-requests.edit')
                            <a href="{{ route('hr.personnel-requests.edit', $personnelRequest) }}" class="btn btn-outline btn-sm">{{ __('ویرایش') }}</a>
                        @endcan
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
