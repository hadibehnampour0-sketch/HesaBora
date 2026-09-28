<x-app-layout :title="__('Integrations')">
    <x-show-message-bags />

    <div class="mx-auto max-w-4xl space-y-4">
        <div>
            <h1 class="text-xl font-bold">{{ __('Integrations') }}</h1>
            <p class="text-sm text-base-content/60">{{ __('Connect an online store so its orders become invoices automatically, and your stock stays in sync both ways.') }}</p>
        </div>

        @can('integrations.connections.store')
            <form method="POST" action="{{ route('integrations.connections.store') }}" class="today-panel grid grid-cols-1 gap-3 sm:grid-cols-2">
                @csrf
                <div class="today-panel-title sm:col-span-2">{{ __('New connection') }}</div>
                <select name="source_system" class="select select-bordered" required>
                    <option value="woocommerce">WooCommerce</option>
                </select>
                <input type="text" name="name" class="input input-bordered" placeholder="{{ __('Connection name') }}" maxlength="150" required>
                <input type="url" name="site_url" class="input input-bordered sm:col-span-2" placeholder="{{ __('Store URL (e.g. https://myshop.com)') }}" required>
                <input type="text" name="consumer_key" class="input input-bordered" placeholder="{{ __('Consumer key') }}" required>
                <input type="text" name="consumer_secret" class="input input-bordered" placeholder="{{ __('Consumer secret') }}" required>
                <input type="text" name="webhook_secret" class="input input-bordered sm:col-span-2" placeholder="{{ __('Webhook secret') }}" required>
                <select name="default_warehouse_id" class="select select-bordered">
                    <option value="">{{ __('Default warehouse (required before orders can import)') }}</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                    @endforeach
                </select>
                <select name="default_customer_group_id" class="select select-bordered">
                    <option value="">{{ __('Default customer group for new online customers') }}</option>
                    @foreach ($customerGroups as $group)
                        <option value="{{ $group->id }}">{{ $group->name }}</option>
                    @endforeach
                </select>
                <input type="number" step="0.01" min="0" max="100" name="price_tolerance_percent" class="input input-bordered" placeholder="{{ __('Price tolerance %') }}" value="2">
                <div class="sm:col-span-2 flex justify-end"><button class="btn btn-primary btn-sm">{{ __('Connect') }}</button></div>
            </form>
        @endcan

        @forelse ($connections as $connection)
            <div class="today-panel">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <span class="font-bold">{{ $connection->name }}</span>
                        <span class="badge badge-outline badge-sm">{{ $connection->source_system }}</span>
                        <span class="badge badge-sm {{ $connection->is_active ? 'badge-success' : 'badge-ghost' }}">{{ $connection->is_active ? __('Active') : __('Inactive') }}</span>
                    </div>
                    @can('integrations.connections.destroy')
                        <form method="POST" action="{{ route('integrations.connections.destroy', $connection) }}" onsubmit="return confirm('{{ __('Remove this connection?') }}')">
                            @csrf @method('DELETE')
                            <button class="btn btn-ghost btn-xs text-error">{{ __('Remove') }}</button>
                        </form>
                    @endcan
                </div>

                <div class="mt-2 grid grid-cols-1 gap-1 text-xs text-base-content/60 sm:grid-cols-2">
                    <div>{{ __('Webhook URL') }}: <code class="select-all">{{ route('integrations.woocommerce.webhook', $connection->webhook_token) }}</code></div>
                    <div>{{ __('Default warehouse') }}: {{ $connection->warehouse?->name ?? __('Not set') }}</div>
                </div>

                @can('integrations.connections.update')
                    <form method="POST" action="{{ route('integrations.connections.update', $connection) }}" class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-4">
                        @csrf @method('PUT')
                        <select name="default_warehouse_id" class="select select-bordered select-sm">
                            <option value="">{{ __('No warehouse yet') }}</option>
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" @selected($connection->default_warehouse_id === $warehouse->id)>{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                        <select name="default_customer_group_id" class="select select-bordered select-sm">
                            <option value="">{{ __('No group yet') }}</option>
                            @foreach ($customerGroups as $group)
                                <option value="{{ $group->id }}" @selected($connection->default_customer_group_id === $group->id)>{{ $group->name }}</option>
                            @endforeach
                        </select>
                        <input type="number" step="0.01" min="0" max="100" name="price_tolerance_percent" value="{{ $connection->price_tolerance_percent }}" class="input input-bordered input-sm">
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" class="checkbox checkbox-sm" @checked($connection->is_active)>{{ __('Active') }}</label>
                        <button class="btn btn-sm btn-outline sm:col-span-4">{{ __('Save') }}</button>
                    </form>
                @endcan

                @can('integrations.connections.push-inventory')
                    <form method="POST" action="{{ route('integrations.connections.push-inventory', $connection) }}" class="mt-2">
                        @csrf
                        <button class="btn btn-xs btn-outline">{{ __('Sync stock now') }}</button>
                    </form>
                @endcan
            </div>
        @empty
            <div class="alert text-sm">{{ __('No connections yet. Add the first one above.') }}</div>
        @endforelse
    </div>
</x-app-layout>
