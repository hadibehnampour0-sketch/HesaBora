<x-app-layout :title="__('Seasonal Tax Report')">
    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <span class="card-title">{{ __('Seasonal Tax Report') }}</span>

                <div class="flex items-center gap-2">
                    <a href="{{ route('seasonal-tax.export-csv', ['jalali_year' => $jalaliYear, 'quarter' => $quarter]) }}" class="btn btn-sm btn-outline">{{ __('Export') }} CSV</a>
                    <a href="{{ route('seasonal-tax.export-pdf', ['jalali_year' => $jalaliYear, 'quarter' => $quarter]) }}" class="btn btn-sm btn-outline">{{ __('Export') }} PDF</a>
                </div>
            </div>

            <form method="GET" action="{{ route('seasonal-tax.index') }}" class="flex flex-wrap items-end gap-4 mt-4">
                <div>
                    <label for="jalali_year" class="label text-sm">{{ __('Fiscal Year') }}</label>
                    <input type="number" name="jalali_year" id="jalali_year" value="{{ $jalaliYear }}" class="input w-32">
                </div>

                <div role="tablist" class="tabs tabs-boxed">
                    @foreach ([1 => __('Spring'), 2 => __('Summer'), 3 => __('Autumn'), 4 => __('Winter')] as $q => $label)
                        <a role="tab" href="{{ route('seasonal-tax.index', ['jalali_year' => $jalaliYear, 'quarter' => $q]) }}"
                            class="tab {{ $quarter === $q ? 'tab-active' : '' }}">{{ __('Quarter') }} {{ $q }} ({{ $label }})</a>
                    @endforeach
                </div>

                <button type="submit" class="btn btn-sm btn-primary">{{ __('Filter') }}</button>
            </form>

            <div class="mt-6">
                <p class="text-sm opacity-70">{{ __('Date Range') }}: {{ localizeNumber($report['start_date']) }} - {{ localizeNumber($report['end_date']) }}</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
                    <div class="rounded-xl border border-base-300 p-4">
                        <div class="text-xs opacity-60">{{ __('Taxable Sales') }}</div>
                        <div class="text-xl font-semibold">{{ localizeNumber(number_format($report['taxable_sales'], 2)) }}</div>
                    </div>
                    <div class="rounded-xl border border-base-300 p-4">
                        <div class="text-xs opacity-60">{{ __('Exempt Sales') }}</div>
                        <div class="text-xl font-semibold">{{ localizeNumber(number_format($report['exempt_sales'], 2)) }}</div>
                    </div>
                    <div class="rounded-xl border border-base-300 p-4">
                        <div class="text-xs opacity-60">{{ __('VAT Collected') }}</div>
                        <div class="text-xl font-semibold">{{ localizeNumber(number_format($report['vat_collected'], 2)) }}</div>
                    </div>
                    <div class="rounded-xl border border-base-300 p-4">
                        <div class="text-xs opacity-60">{{ __('Taxable Purchases') }}</div>
                        <div class="text-xl font-semibold">{{ localizeNumber(number_format($report['taxable_purchases'], 2)) }}</div>
                    </div>
                    <div class="rounded-xl border border-base-300 p-4">
                        <div class="text-xs opacity-60">{{ __('Creditable Input VAT') }}</div>
                        <div class="text-xl font-semibold">{{ localizeNumber(number_format($report['creditable_input_vat'], 2)) }}</div>
                    </div>
                    <div class="rounded-xl border border-primary p-4 bg-primary/5">
                        <div class="text-xs opacity-60">{{ __('Net VAT Payable') }}</div>
                        <div class="text-xl font-semibold">{{ localizeNumber(number_format($report['net_vat_payable'], 2)) }}</div>
                    </div>
                </div>

                <div class="alert mt-6 text-xs">
                    {{ __('This report is a reasonable, invoice-level approximation of the Article 169 seasonal VAT return. It has not been verified against the exact byte-level TTMS filing format.') }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
