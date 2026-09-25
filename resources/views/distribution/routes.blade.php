<x-app-layout :title="__('Distribution routes')">
    <x-show-message-bags />

    <div class="mx-auto max-w-5xl space-y-4">
        @include('distribution._nav')

        @can('distribution.routes.store')
            <form method="POST" action="{{ route('distribution.routes.store') }}" class="today-panel grid grid-cols-1 gap-3 sm:grid-cols-4">
                @csrf
                <div class="today-panel-title sm:col-span-4">{{ __('New route') }}</div>
                <input type="text" name="title" class="input input-bordered" placeholder="{{ __('Route name') }}" maxlength="100" required>
                <input type="text" name="code" class="input input-bordered" placeholder="{{ __('Route code') }}" maxlength="20" required>
                <input type="text" name="description" class="input input-bordered sm:col-span-2" placeholder="{{ __('Description') }}" maxlength="1000">
                <div class="sm:col-span-4 flex justify-end"><button class="btn btn-primary btn-sm">{{ __('Add route') }}</button></div>
            </form>
        @endcan

        @forelse ($routes as $route)
            <div class="today-panel">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <span class="font-bold">{{ $route->title }}</span>
                        <span class="badge badge-outline badge-sm">{{ $route->code }}</span>
                        @unless ($route->is_active)<span class="badge badge-warning badge-sm">{{ __('Inactive') }}</span>@endunless
                        <span class="text-xs text-base-content/60">· {{ $route->customers_count }} {{ __('customers') }}</span>
                    </div>
                    @can('distribution.routes.destroy')
                        <form method="POST" action="{{ route('distribution.routes.destroy', $route) }}" onsubmit="return confirm('{{ __('Delete this route?') }}')">
                            @csrf @method('DELETE')
                            <button class="btn btn-ghost btn-xs text-error">{{ __('Delete') }}</button>
                        </form>
                    @endcan
                </div>

                @can('distribution.routes.update')
                    <details class="mt-2">
                        <summary class="cursor-pointer text-xs text-primary">{{ __('Edit route') }}</summary>
                        <form method="POST" action="{{ route('distribution.routes.update', $route) }}" class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-4">
                            @csrf @method('PUT')
                            <input type="text" name="title" value="{{ $route->title }}" class="input input-bordered input-sm" required>
                            <input type="text" name="code" value="{{ $route->code }}" class="input input-bordered input-sm" required>
                            <input type="text" name="description" value="{{ $route->description }}" class="input input-bordered input-sm">
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" class="checkbox checkbox-sm" @checked($route->is_active)>{{ __('Active') }}</label>
                            <div class="sm:col-span-4 flex justify-end"><button class="btn btn-sm btn-primary">{{ __('Save') }}</button></div>
                        </form>
                    </details>
                @endcan

                <ul class="mt-3 divide-y divide-base-200 text-sm">
                    @forelse ($route->customers->sortBy('pivot.visit_priority') as $customer)
                        <li class="flex items-center justify-between py-1">
                            <span>{{ $customer->name }} <span class="text-xs text-base-content/50">#{{ $customer->pivot->visit_priority }} {{ $customer->pivot->preferred_visit_days }}</span></span>
                            @can('distribution.routes.customers.destroy')
                                <form method="POST" action="{{ route('distribution.routes.customers.destroy', [$route, $customer->id]) }}">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-ghost btn-xs">{{ __('Remove') }}</button>
                                </form>
                            @endcan
                        </li>
                    @empty
                        <li class="py-2 text-xs text-base-content/60">{{ __('No customers on this route yet.') }}</li>
                    @endforelse
                </ul>

                @can('distribution.routes.customers.store')
                    <form method="POST" action="{{ route('distribution.routes.customers.store', $route) }}" class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-5">
                        @csrf
                        <select name="customer_id" class="select select-bordered select-sm sm:col-span-2" required>
                            <option value="">{{ __('Add a customer...') }}</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                            @endforeach
                        </select>
                        <input type="number" name="visit_priority" min="0" class="input input-bordered input-sm" placeholder="{{ __('Visit order') }}">
                        <input type="text" name="preferred_visit_days" maxlength="50" class="input input-bordered input-sm" placeholder="{{ __('Visit days') }}">
                        <button class="btn btn-sm btn-outline">{{ __('Add') }}</button>
                    </form>
                @endcan
            </div>
        @empty
            <div class="alert text-sm">{{ __('No routes yet. Create the first one above.') }}</div>
        @endforelse
    </div>
</x-app-layout>
