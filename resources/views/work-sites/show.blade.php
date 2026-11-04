<x-app-layout :title="__('Work Site') . ' - ' . $workSite->name">
    <div class="card bg-base-100 shadow-xl max-w-2xl mx-auto">
        <div class="card-header bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-700 px-6 py-4 rounded-t-2xl border-b flex items-center justify-between">
            <h2 class="text-xl font-bold flex items-center gap-2">
                <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
                {{ __('کارگاه / محل کار') }}: {{ $workSite->name }}
            </h2>
            <a href="{{ route('salary.work-sites.index') }}" class="btn btn-ghost btn-sm">{{ __('بازگشت') }}</a>
        </div>
        <div class="card-body space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div class="p-3 bg-base-200/50 rounded-lg">
                    <span class="text-xs text-base-content/60 block">{{ __('کد کارگاه') }}</span>
                    <span class="font-bold font-mono">{{ $workSite->code }}</span>
                </div>
                <div class="p-3 bg-base-200/50 rounded-lg">
                    <span class="text-xs text-base-content/60 block">{{ __('وضعیت') }}</span>
                    <span class="badge {{ $workSite->is_active ? 'badge-success' : 'badge-ghost' }}">
                        {{ $workSite->is_active ? __('فعال') : __('غیرفعال') }}
                    </span>
                </div>
                <div class="p-3 bg-base-200/50 rounded-lg">
                    <span class="text-xs text-base-content/60 block">{{ __('تلفن') }}</span>
                    <span class="font-mono text-sm">{{ $workSite->phone ?? '—' }}</span>
                </div>
                <div class="p-3 bg-base-200/50 rounded-lg">
                    <span class="text-xs text-base-content/60 block">{{ __('تعداد پیمان‌ها') }}</span>
                    <span class="font-bold text-sm">{{ $workSite->contracts()->count() }}</span>
                </div>
            </div>
            @if ($workSite->address)
                <div class="p-3 bg-base-200/30 rounded-lg border">
                    <span class="text-xs text-base-content/60 block mb-1">{{ __('آدرس') }}</span>
                    <p class="text-sm">{{ $workSite->address }}</p>
                </div>
            @endif
            <div class="pt-4 border-t flex justify-end gap-2">
                @can('salary.work-sites.edit')
                    <a href="{{ route('salary.work-sites.edit', $workSite) }}" class="btn btn-info btn-sm">{{ __('Edit') }}</a>
                @endcan
            </div>
        </div>
    </div>
</x-app-layout>
