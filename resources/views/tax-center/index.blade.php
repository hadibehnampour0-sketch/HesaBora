<x-app-layout :title="__('Tax Center')">
    <x-show-message-bags />

    @php
        $cards = collect([
            ['can' => 'tax-readiness.index', 'route' => 'tax-readiness.index', 'icon' => 'reports', 'title' => __('Tax Readiness'), 'text' => __('A score that shows how ready your books and Moadian setup are for the tax office, with a list of what to fix.')],
            ['can' => 'seasonal-tax.index', 'route' => 'seasonal-tax.index', 'icon' => 'reports', 'title' => __('Seasonal Tax Report'), 'text' => __('Quarterly VAT and tax figures, ready to check and export as CSV or PDF for your filing.')],
            ['can' => 'invoices.moadian-histories.index', 'route' => 'invoices.moadian-histories.index', 'icon' => 'sales', 'title' => __('Moadian Histories'), 'text' => __('Which sales invoices were sent to the Moadian system, their status, and any rejections.')],
            ['can' => 'tax-alerts.index', 'route' => 'tax-alerts.index', 'icon' => 'reports', 'title' => __('Tax alerts'), 'text' => __('Reminders and warnings about deadlines and problems that could cause trouble with the tax office.')],
            ['can' => 'ancillary-costs.index', 'route' => 'ancillary-costs.index', 'icon' => 'money', 'title' => __('Expense evidence'), 'text' => __('Attach supporting documents to costs so they can be defended as tax-deductible in an audit.')],
            ['can' => 'companies.index', 'route' => 'companies.index', 'icon' => 'customers', 'title' => __('Company tax profile'), 'text' => __('Economic code, national ID and Moadian credentials used when sending invoices.')],
        ])->filter(fn (array $card) => auth()->user()->can($card['can']))->values();
    @endphp

    <div class="mx-auto max-w-5xl space-y-4">
        <div>
            <h1 class="text-xl font-bold">{{ __('Tax Center') }}</h1>
            <p class="text-sm text-base-content/60">{{ __('Everything you need for the tax office in one place: invoices, VAT, Moadian, evidence and audit readiness.') }}</p>
        </div>

        @if ($latest)
            <a href="{{ route('tax-readiness.index') }}" class="today-panel flex items-center justify-between gap-3">
                <span>
                    <span class="today-panel-title">{{ __('Tax Readiness') }}</span>
                    <span class="block text-xs text-base-content/60">{{ $latest->calculated_at->diffForHumans() }}</span>
                </span>
                <span class="text-2xl font-bold">{{ $latest->score }}<span class="text-sm text-base-content/50">/100</span></span>
            </a>
        @endif

        @if ($cards->isEmpty())
            <div class="alert text-sm">{{ __('Nothing to show yet for your permissions.') }}</div>
        @else
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($cards as $card)
                    <a href="{{ route($card['route']) }}" class="today-panel block hover:border-primary/40">
                        <span class="flex items-center gap-2 font-semibold"><x-menu-icon :name="$card['icon']" class="h-5 w-5 text-primary" />{{ $card['title'] }}</span>
                        <span class="mt-2 block text-xs leading-5 text-base-content/60">{{ $card['text'] }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
