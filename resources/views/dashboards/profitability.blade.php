<x-app-layout :title="__('Profitability by dimension')">
    <x-show-message-bags />

    <div class="mx-auto max-w-4xl space-y-4">
        <div>
            <h1 class="text-xl font-bold">{{ __('Profitability by dimension') }}</h1>
            <p class="text-sm text-base-content/60">{{ __('Revenue, expense and profit for each branch, project, cost center or sales channel.') }}</p>
        </div>

        <form method="GET" class="flex items-center gap-2">
            <select name="type" class="select select-bordered select-sm" onchange="this.form.submit()">
                @foreach (\App\Enums\AnalyticDimensionType::cases() as $case)
                    <option value="{{ $case->value }}" @selected($case === $type)>{{ $case->label() }}</option>
                @endforeach
            </select>
        </form>

        <div class="today-panel overflow-x-auto p-0">
            <table class="table table-sm">
                <thead><tr><th>{{ $type->label() }}</th><th>{{ __('Revenue') }}</th><th>{{ __('Expense') }}</th><th>{{ __('Profit') }}</th></tr></thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row['dimension_name'] }}</td>
                            <td>{{ number_format($row['revenue']) }}</td>
                            <td>{{ number_format($row['expense']) }}</td>
                            <td class="font-semibold {{ $row['profit'] < 0 ? 'text-error' : 'text-success' }}">{{ number_format($row['profit']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-sm text-base-content/60">{{ __('Nothing defined for this dimension yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
