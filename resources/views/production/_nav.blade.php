@php
    $tabs = [
        ['can' => 'production.orders.index', 'route' => 'production.orders.index', 'label' => __('Produce')],
        ['can' => 'production.recipes.index', 'route' => 'production.recipes.index', 'label' => __('Recipes')],
    ];
@endphp
<div>
    <h1 class="text-xl font-bold">{{ __('Production & cost') }}</h1>
    <nav class="mt-3 flex flex-wrap gap-2" aria-label="{{ __('Production & cost') }}">
        @foreach ($tabs as $tab)
            @can($tab['can'])
                <a href="{{ route($tab['route']) }}" class="btn btn-sm {{ request()->routeIs($tab['route']) ? 'btn-primary' : 'btn-outline' }}">{{ $tab['label'] }}</a>
            @endcan
        @endforeach
    </nav>
</div>
