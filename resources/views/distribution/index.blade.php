<x-app-layout :title="__('Capillary distribution')">
    <x-show-message-bags />

    <div class="mx-auto max-w-5xl space-y-4">
        @include('distribution._nav')

        <p class="text-sm text-base-content/60">{{ __('Define your distribution network once: routes, visitors with their own van warehouse, daily visit plans, and end-of-day cash settlement.') }}</p>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="today-kpi"><span class="today-kpi-label">{{ __('Active routes') }}</span><span class="today-kpi-value">{{ $routeCount }}</span></div>
            <div class="today-kpi"><span class="today-kpi-label">{{ __('Active visitors') }}</span><span class="today-kpi-value">{{ $visitorCount }}</span></div>
            <div class="today-kpi"><span class="today-kpi-label">{{ __('Open visit plans today') }}</span><span class="today-kpi-value">{{ $todayPlans }}</span></div>
            <div class="today-kpi"><span class="today-kpi-label">{{ __('Settlements waiting for approval') }}</span><span class="today-kpi-value">{{ $pendingSettlements }}</span></div>
        </div>

        <div class="today-panel">
            <div class="today-panel-title">{{ __('Set up in this order') }}</div>
            <ol class="mt-3 list-decimal space-y-2 ps-5 text-sm">
                <li>{{ __('Create your routes and add the customers each route visits.') }}</li>
                <li>{{ __('Add visitors and give each one a van warehouse (created for you with one tick).') }}</li>
                <li>{{ __('Load the van: transfer stock from the main warehouse to the visitor warehouse.') }}</li>
                <li>{{ __('Plan each day for every visitor, then let them sell from the van warehouse.') }}</li>
                <li>{{ __('At the end of the day, record and approve the cash, POS and cheque settlement.') }}</li>
            </ol>
        </div>
    </div>
</x-app-layout>
