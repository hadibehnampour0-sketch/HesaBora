<x-app-layout :title="__('Stock reservations')">
    <x-show-message-bags />

    <div class="mx-auto max-w-5xl space-y-4">
        <div>
            <h1 class="text-xl font-bold">{{ __('Stock reservations') }}</h1>
            <p class="text-sm text-base-content/60">{{ __('Hold stock aside for a customer or order so it cannot be sold to someone else, without moving it out of the warehouse yet.') }}</p>
        </div>

        @can('stock-reservations.store')
            <form method="POST" action="{{ route('stock-reservations.store') }}" class="today-panel grid grid-cols-1 gap-3 sm:grid-cols-4">
                @csrf
                <div class="today-panel-title sm:col-span-4">{{ __('Reserve stock') }}</div>
                <select name="product_id" class="select select-bordered sm:col-span-2" required>
                    <option value="">{{ __('Product') }}</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                    @endforeach
                </select>
                <select name="warehouse_id" class="select select-bordered" required>
                    <option value="">{{ __('Warehouse') }}</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                    @endforeach
                </select>
                <input type="number" step="any" min="0" name="quantity" class="input input-bordered" placeholder="{{ __('Quantity') }}" required>
                <div class="sm:col-span-4 flex justify-end"><button class="btn btn-primary btn-sm">{{ __('Reserve') }}</button></div>
            </form>
        @endcan

        <div class="today-panel overflow-x-auto p-0">
            <table class="table table-sm">
                <thead><tr><th>{{ __('Product') }}</th><th>{{ __('Warehouse') }}</th><th>{{ __('Quantity') }}</th><th>{{ __('Reserved by') }}</th><th>{{ __('Reserved at') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($reservations as $reservation)
                        <tr>
                            <td>{{ $reservation->product?->name }}</td>
                            <td>{{ $reservation->warehouse?->name }}</td>
                            <td>{{ rtrim(rtrim(number_format((float) $reservation->quantity, 3), '0'), '.') }}</td>
                            <td>{{ $reservation->creator?->name ?? '—' }}</td>
                            <td>{{ formatDateTime($reservation->created_at) }}</td>
                            <td class="text-end">
                                @can('stock-reservations.release')
                                    <form method="POST" action="{{ route('stock-reservations.release', $reservation) }}" onsubmit="return confirm('{{ __('Release this reservation?') }}')">
                                        @csrf
                                        <button class="btn btn-ghost btn-xs">{{ __('Release') }}</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-sm text-base-content/60">{{ __('No active reservations.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
