<x-app-layout :title="__('Production & cost')">
    <x-show-message-bags />

    <div class="mx-auto max-w-4xl space-y-4">
        @include('production._nav')

        @can('production.orders.store')
            @if ($recipes->isEmpty())
                <div class="alert text-sm">{{ __('No active recipes yet.') }} @can('production.recipes.index')<a href="{{ route('production.recipes.index') }}" class="link">{{ __('Create one first') }}</a>.@endcan</div>
            @else
                <div class="today-panel space-y-3">
                    <div class="today-panel-title">{{ __('Produce now') }}</div>
                    <form method="GET" class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                        <select name="recipe_id" class="select select-bordered" onchange="this.form.submit()" required>
                            <option value="">{{ __('Recipe') }}</option>
                            @foreach ($recipes as $recipe)
                                <option value="{{ $recipe->id }}" @selected($selectedRecipeId === $recipe->id)>{{ $recipe->name }} → {{ $recipe->product?->name }}</option>
                            @endforeach
                        </select>
                        <select name="warehouse_id" class="select select-bordered" onchange="this.form.submit()" required>
                            <option value="">{{ __('Warehouse') }}</option>
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" @selected($selectedWarehouseId === $warehouse->id)>{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                        <input type="number" step="any" min="0" name="quantity" value="{{ $quantity ?: '' }}" class="input input-bordered" placeholder="{{ __('Quantity to produce') }}">
                        <button class="btn btn-outline btn-sm">{{ __('Check materials') }}</button>
                    </form>

                    @if ($estimate)
                        <div class="mt-2 space-y-1">
                            @foreach ($estimate['materials'] as $row)
                                <div class="flex items-center justify-between rounded-lg border px-3 py-1.5 text-sm {{ $row['sufficient'] ? 'border-base-300' : 'border-error' }}">
                                    <span>{{ $row['product']->name }}</span>
                                    <span class="{{ $row['sufficient'] ? '' : 'text-error font-semibold' }}">
                                        {{ __('Need') }} {{ rtrim(rtrim(number_format($row['needed'], 3), '0'), '.') }} — {{ __('Have') }} {{ rtrim(rtrim(number_format($row['available'], 3), '0'), '.') }}
                                    </span>
                                </div>
                            @endforeach
                        </div>

                        @if ($estimate['can_produce'])
                            <form method="POST" action="{{ route('production.orders.store') }}" class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-4">
                                @csrf
                                <input type="hidden" name="recipe_id" value="{{ $selectedRecipeId }}">
                                <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}">
                                <input type="hidden" name="quantity" value="{{ $quantity }}">
                                <input type="number" step="any" min="0" name="labor_cost" class="input input-bordered input-sm" placeholder="{{ __('Labor cost (optional)') }}">
                                <input type="number" step="any" min="0" name="overhead_cost" class="input input-bordered input-sm" placeholder="{{ __('Overhead cost (optional)') }}">
                                <button class="btn btn-primary btn-sm sm:col-span-2">{{ __('Confirm production') }}</button>
                            </form>
                        @else
                            <div class="alert alert-warning mt-2 text-sm">{{ __('Not enough material to produce this quantity.') }}</div>
                        @endif
                    @endif
                </div>
            @endif
        @endcan

        <div class="today-panel overflow-x-auto p-0">
            <table class="table table-sm">
                <thead><tr><th>{{ __('Product') }}</th><th>{{ __('Quantity') }}</th><th>{{ __('Warehouse') }}</th><th>{{ __('Total cost') }}</th><th>{{ __('Unit cost') }}</th><th>{{ __('Date') }}</th></tr></thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td>{{ $order->product?->name }}</td>
                            <td>{{ rtrim(rtrim(number_format((float) $order->quantity_produced, 3), '0'), '.') }}</td>
                            <td>{{ $order->warehouse?->name }}</td>
                            <td>{{ number_format((float) $order->total_cost) }}</td>
                            <td>{{ number_format((float) $order->unit_cost) }}</td>
                            <td>{{ formatDateTime($order->produced_at) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-sm text-base-content/60">{{ __('No production runs yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
