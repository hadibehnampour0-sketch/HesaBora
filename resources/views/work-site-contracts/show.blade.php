<x-app-layout :title="__('Work Site Contract') . ' - ' . $workSiteContract->name">
    <div class="card bg-base-100 shadow-xl max-w-2xl mx-auto">
        <div class="card-header bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-700 px-6 py-4 rounded-t-2xl border-b flex items-center justify-between">
            <h2 class="text-xl font-bold flex items-center gap-2">
                <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                {{ __('پیمان کارگاه') }}: {{ $workSiteContract->name }}
            </h2>
            <a href="{{ route('salary.work-site-contracts.index') }}" class="btn btn-ghost btn-sm">{{ __('بازگشت') }}</a>
        </div>
        <div class="card-body space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div class="p-3 bg-base-200/50 rounded-lg">
                    <span class="text-xs text-base-content/60 block">{{ __('کارگاه مربوطه') }}</span>
                    <span class="font-bold text-sm">{{ $workSiteContract->workSite?->name ?? '—' }}</span>
                </div>
                <div class="p-3 bg-base-200/50 rounded-lg">
                    <span class="text-xs text-base-content/60 block">{{ __('کد پیمان') }}</span>
                    <span class="font-bold font-mono">{{ $workSiteContract->code }}</span>
                </div>
                <div class="p-3 bg-base-200/50 rounded-lg">
                    <span class="text-xs text-base-content/60 block">{{ __('وضعیت') }}</span>
                    <span class="badge {{ $workSiteContract->is_active ? 'badge-success' : 'badge-ghost' }}">
                        {{ $workSiteContract->is_active ? __('فعال') : __('غیرفعال') }}
                    </span>
                </div>
                <div class="p-3 bg-base-200/50 rounded-lg">
                    <span class="text-xs text-base-content/60 block">{{ __('تعداد پرسنل اختصاص یافته') }}</span>
                    <span class="font-bold text-sm">{{ $workSiteContract->employees()->count() }}</span>
                </div>
            </div>
            @if ($workSiteContract->description)
                <div class="p-3 bg-base-200/30 rounded-lg border">
                    <span class="text-xs text-base-content/60 block mb-1">{{ __('شرح پیمان') }}</span>
                    <p class="text-sm">{{ $workSiteContract->description }}</p>
                </div>
            @endif
            <div class="pt-4 border-t flex justify-end gap-2">
                @can('salary.work-site-contracts.edit')
                    <a href="{{ route('salary.work-site-contracts.edit', $workSiteContract) }}" class="btn btn-info btn-sm">{{ __('Edit') }}</a>
                @endcan
            </div>
        </div>
    </div>
</x-app-layout>
