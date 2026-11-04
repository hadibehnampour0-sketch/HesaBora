<x-app-layout :title="__('Yearly Tax Slab')">
    <div class="card bg-base-100 shadow-xl max-w-xl mx-auto">
        <div class="card-header bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-700 px-6 py-4 rounded-t-2xl border-b flex items-center justify-between">
            <h2 class="text-xl font-bold">{{ __('جزئیات پله مالیاتی حقوق') }}</h2>
            <a href="{{ route('salary.tax-slabs.index') }}" class="btn btn-ghost btn-sm">{{ __('بازگشت') }}</a>
        </div>
        <div class="card-body space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div class="p-4 bg-base-200/50 rounded-lg">
                    <span class="text-xs text-base-content/60 block">{{ __('سقف درآمد پله (تا مبلغ)') }}</span>
                    <span class="font-bold text-lg font-mono">{{ $taxSlab->income_to !== null ? formatNumber($taxSlab->income_to) : 'بدون سقف (∞)' }}</span>
                </div>
                <div class="p-4 bg-base-200/50 rounded-lg">
                    <span class="text-xs text-base-content/60 block">{{ __('نرخ مالیات پله') }}</span>
                    <span class="font-bold text-lg text-primary">{{ $taxSlab->tax_rate }}%</span>
                </div>
            </div>
            <div class="pt-4 border-t flex justify-end gap-2">
                @can('salary.tax-slabs.edit')
                    <a href="{{ route('salary.tax-slabs.edit', $taxSlab) }}" class="btn btn-info btn-sm">{{ __('Edit') }}</a>
                @endcan
            </div>
        </div>
    </div>
</x-app-layout>
