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

                <div class="today-panel mt-6">
                    <div class="flex items-center justify-between">
                        <div class="today-panel-title">{{ __('Ledger reconciliation') }}</div>
                        <span class="badge {{ $reconciliation['is_balanced'] ? 'badge-success' : 'badge-error' }}">
                            {{ $reconciliation['is_balanced'] ? __('Matches the ledger') : __('Does not match the ledger') }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-base-content/60">{{ __('This report is computed from invoices. This check independently re-computes the same VAT from the accounting ledger and compares them -- a difference usually means a manual journal entry touched the VAT account directly.') }}</p>

                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="rounded-xl border p-3 {{ $reconciliation['vat_collected_matches'] ? 'border-base-300' : 'border-error' }}">
                            <div class="text-xs opacity-60">{{ __('VAT Collected') }}</div>
                            <div class="mt-1 flex justify-between text-sm"><span>{{ __('From invoices') }}</span><b>{{ number_format($reconciliation['report_vat_collected']) }}</b></div>
                            <div class="flex justify-between text-sm"><span>{{ __('From ledger') }}</span><b>{{ number_format($reconciliation['ledger_vat_collected']) }}</b></div>
                            @unless ($reconciliation['vat_collected_matches'])
                                <div class="mt-1 text-xs text-error">{{ __('Difference') }}: {{ number_format($reconciliation['vat_collected_difference']) }}</div>
                            @endunless
                        </div>
                        <div class="rounded-xl border p-3 {{ $reconciliation['input_vat_matches'] ? 'border-base-300' : 'border-error' }}">
                            <div class="text-xs opacity-60">{{ __('Creditable Input VAT') }}</div>
                            <div class="mt-1 flex justify-between text-sm"><span>{{ __('From invoices') }}</span><b>{{ number_format($reconciliation['report_input_vat']) }}</b></div>
                            <div class="flex justify-between text-sm"><span>{{ __('From ledger') }}</span><b>{{ number_format($reconciliation['ledger_input_vat']) }}</b></div>
                            @unless ($reconciliation['input_vat_matches'])
                                <div class="mt-1 text-xs text-error">{{ __('Difference') }}: {{ number_format($reconciliation['input_vat_difference']) }}</div>
                            @endunless
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
