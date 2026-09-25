<x-app-layout :title="__('Inventory valuation')">
    <x-show-message-bags />

    <div class="mx-auto max-w-5xl space-y-4">
        <div>
            <h1 class="text-xl font-bold">{{ __('Inventory valuation') }}</h1>
            <p class="text-sm text-base-content/60">{{ __('What your stock is worth at average cost, per product and warehouse.') }}</p>
        </div>

        <form method="GET" class="flex items-center gap-2">
            <select name="warehouse_id" class="select select-bordered select-sm" onchange="this.form.submit()">
                <option value="">{{ __('All warehouses') }}</option>
                @foreach ($warehouses as $option)
                    <option value="{{ $option->id }}" @selected($warehouse?->id === $option->id)>{{ $option->name }}</option>
                @endforeach
            </select>
        </form>

        <div class="today-kpi"><span class="today-kpi-label">{{ __('Total stock value') }}</span><span class="today-kpi-value">{{ number_format($report['total_value']) }}</span></div>

        <div class="today-panel overflow-x-auto p-0">
            <table class="table table-sm">
                <thead><tr><th>{{ __('Product') }}</th><th>{{ __('Warehouse') }}</th><th>{{ __('Quantity') }}</th><th>{{ __('Average cost') }}</th><th>{{ __('Value') }}</th></tr></thead>
                <tbody>
                    @php $names = $warehouses->pluck('name', 'id'); @endphp
                    @forelse ($report['lines'] as $line)
                        <tr>
                            <td>{{ $line['product_name'] }}</td>
                            <td>{{ $names[$line['warehouse_id']] ?? '—' }}</td>
                            <td>{{ rtrim(rtrim(number_format($line['quantity'], 3), '0'), '.') }}</td>
                            <td>{{ number_format($line['average_cost']) }}</td>
                            <td class="font-semibold">{{ number_format($line['value']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-sm text-base-content/60">{{ __('No stock on hand.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
