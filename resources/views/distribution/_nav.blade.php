@php
    $tabs = [
        ['can' => 'distribution.index', 'route' => 'distribution.index', 'label' => __('Overview')],
        ['can' => 'distribution.routes.index', 'route' => 'distribution.routes.index', 'label' => __('Distribution routes')],
        ['can' => 'distribution.visitors.index', 'route' => 'distribution.visitors.index', 'label' => __('Visitors')],
        ['can' => 'distribution.plans.index', 'route' => 'distribution.plans.index', 'label' => __('Visit plans')],
        ['can' => 'distribution.settlements.index', 'route' => 'distribution.settlements.index', 'label' => __('Daily settlements')],
        ['can' => 'warehouses.index', 'route' => 'warehouses.index', 'label' => __('Warehouses')],
        ['can' => 'field-operations-analytics.index', 'route' => 'field-operations-analytics.index', 'label' => __('Field Operations Analytics')],
    ];
@endphp
<div>
    <h1 class="text-xl font-bold">{{ __('Capillary distribution') }}</h1>
    <nav class="mt-3 flex flex-wrap gap-2" aria-label="{{ __('Capillary distribution') }}">
        @foreach ($tabs as $tab)
            @can($tab['can'])
                <a href="{{ route($tab['route']) }}" class="btn btn-sm {{ request()->routeIs($tab['route']) ? 'btn-primary' : 'btn-outline' }}">{{ $tab['label'] }}</a>
            @endcan
        @endforeach
    </nav>
</div>
