<x-app-layout :title="__('Slow-moving stock')">
    <x-show-message-bags />

    <div class="mx-auto max-w-4xl space-y-4">
        <div>
            <h1 class="text-xl font-bold">{{ __('Slow-moving stock') }}</h1>
            <p class="text-sm text-base-content/60">{{ __('Products you hold but have not sold for a while, or never sold. Money tied up on the shelf.') }}</p>
        </div>

        <form method="GET" class="flex items-center gap-2">
            <label class="text-sm">{{ __('Not sold for (days)') }}</label>
            <input type="number" min="1" name="days" value="{{ $days }}" class="input input-bordered input-sm w-24">
            <button class="btn btn-sm btn-outline">{{ __('Show') }}</button>
        </form>

        <div class="today-panel overflow-x-auto p-0">
            <table class="table table-sm">
                <thead><tr><th>{{ __('Product') }}</th><th>{{ __('Quantity') }}</th><th>{{ __('Last sold') }}</th><th>{{ __('Days since last sale') }}</th></tr></thead>
                <tbody>
                    @forelse ($products as $row)
                        <tr>
                            <td>{{ $row['product_name'] }}</td>
                            <td>{{ rtrim(rtrim(number_format($row['quantity'], 3), '0'), '.') }}</td>
                            <td>{{ $row['last_sold_at'] ? formatDate($row['last_sold_at']) : __('Never sold') }}</td>
                            <td>{{ $row['days_since_last_sale'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-sm text-base-content/60">{{ __('Nothing is slow-moving. Good.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
