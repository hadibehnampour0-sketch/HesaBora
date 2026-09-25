@php
    $blocks = $report['blocks'];
    $periods = ['this_month' => __('This month'), 'last_month' => __('Last month'), 'this_year' => __('This fiscal year')];
    $money = fn ($v) => formatNumber($v).' '.__('Rial');
@endphp
<x-app-layout :title="__('How is my business doing?')">
    <div class="mx-auto max-w-4xl space-y-4" x-data="{ show: false }">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold">{{ __('How is my business doing?') }}</h1>
                <p class="text-sm text-base-content/60">{{ formatDate($report['from']) }} — {{ formatDate($report['to']) }}</p>
            </div>
            <div class="flex items-center gap-2">
                <div class="join">
                    @foreach ($periods as $key => $label)
                        <a href="{{ route('reports.plain', ['period' => $key]) }}" class="btn btn-sm join-item {{ $report['period'] === $key ? 'btn-primary' : 'btn-outline' }}">{{ $label }}</a>
                    @endforeach
                </div>
                <button type="button" class="btn btn-sm btn-outline" @click="show = ! show">
                    <span x-show="! show">{{ __('Show amounts') }}</span>
                    <span x-show="show" style="display: none">{{ __('Hide amounts') }}</span>
                </button>
            </div>
        </div>

        @if ($blocks === [])
            <div class="alert">{{ __('Nothing to show yet for your permissions.') }}</div>
        @endif

        @isset($blocks['trade'])
            @php $t = $blocks['trade']; @endphp
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="today-kpi">
                    <span class="today-kpi-label">{{ __('I sold') }}</span>
                    <span class="today-kpi-value"><span x-show="show">{{ $money($t['sell']['amount']) }}</span><span x-show="! show">••••••</span></span>
                    <span class="today-kpi-sub">{{ localizeNumber($t['sell']['count']) }} {{ __('invoices') }}</span>
                </div>
                <div class="today-kpi">
                    <span class="today-kpi-label">{{ __('I bought') }}</span>
                    <span class="today-kpi-value"><span x-show="show">{{ $money($t['buy']['amount']) }}</span><span x-show="! show">••••••</span></span>
                    <span class="today-kpi-sub">{{ localizeNumber($t['buy']['count']) }} {{ __('invoices') }}</span>
                </div>
                <div class="today-kpi">
                    <span class="today-kpi-label">{{ __('Sales minus purchases') }}</span>
                    <span class="today-kpi-value {{ $t['difference'] < 0 ? 'text-error' : '' }}"><span x-show="show">{{ $money($t['difference']) }}</span><span x-show="! show">••••••</span></span>
                    <span class="today-kpi-sub">{{ __('Not the same as profit — stock and expenses are not included.') }}</span>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="today-panel">
                    <div class="today-panel-title">{{ __('My best customers') }}</div>
                    @if ($t['top_customers'] === [])
                        <p class="mt-3 text-sm text-base-content/60">{{ __('No sales in this period.') }}</p>
                    @endif
                    <ul class="mt-3 space-y-2 text-sm">
                        @foreach ($t['top_customers'] as $row)
                            <li class="today-row"><span>{{ $row['name'] }}</span><b><span x-show="show">{{ $money($row['amount']) }}</span><span x-show="! show">••••</span></b></li>
                        @endforeach
                    </ul>
                </div>
                <div class="today-panel">
                    <div class="today-panel-title">{{ __('What sells the most') }}</div>
                    @if ($t['top_products'] === [])
                        <p class="mt-3 text-sm text-base-content/60">{{ __('No sales in this period.') }}</p>
                    @endif
                    <ul class="mt-3 space-y-2 text-sm">
                        @foreach ($t['top_products'] as $row)
                            <li class="today-row"><span>{{ $row['name'] }} <span class="text-xs text-base-content/60">× {{ localizeNumber(rtrim(rtrim(number_format($row['quantity'], 2), '0'), '.')) }}</span></span><b><span x-show="show">{{ $money($row['amount']) }}</span><span x-show="! show">••••</span></b></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endisset

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            @isset($blocks['owed_to_me'])
                <a href="{{ route('ageing.receivables') }}" class="today-kpi">
                    <span class="today-kpi-label">{{ __('Money owed to me') }}</span>
                    <span class="today-kpi-value"><span x-show="show">{{ $money($blocks['owed_to_me']) }}</span><span x-show="! show">••••••</span></span>
                </a>
            @endisset
            @isset($blocks['i_owe'])
                <a href="{{ route('ageing.payables') }}" class="today-kpi">
                    <span class="today-kpi-label">{{ __('Money I owe') }}</span>
                    <span class="today-kpi-value"><span x-show="show">{{ $money($blocks['i_owe']) }}</span><span x-show="! show">••••••</span></span>
                </a>
            @endisset
            @isset($blocks['stock_value'])
                <div class="today-kpi">
                    <span class="today-kpi-label">{{ __('Value of my stock') }}</span>
                    <span class="today-kpi-value"><span x-show="show">{{ $money($blocks['stock_value']) }}</span><span x-show="! show">••••••</span></span>
                </div>
            @endisset
        </div>

        @isset($blocks['books'])
            <div class="today-panel">
                <div class="today-panel-title">{{ __('From my accounting books, this fiscal year so far') }}</div>
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="today-row"><span>{{ __('Profit') }}</span><b class="{{ $blocks['books']['profit'] < 0 ? 'text-error' : 'text-success' }}"><span x-show="show">{{ $money($blocks['books']['profit']) }}</span><span x-show="! show">••••••</span></b></div>
                    <div class="today-row"><span>{{ __('Total expenses') }}</span><b><span x-show="show">{{ $money($blocks['books']['expenses']) }}</span><span x-show="! show">••••••</span></b></div>
                </div>
                <p class="mt-2 text-xs text-base-content/60">{{ __('This is taken from posted accounting documents for the whole fiscal year, not the period selected above.') }}</p>
            </div>
        @endisset
    </div>
</x-app-layout>
