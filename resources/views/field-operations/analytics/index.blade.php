<x-app-layout :title="__('Field Operations Analytics')">
    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <span class="card-title">{{ __('Field Operations Analytics') }}</span>

                <form method="POST" action="{{ route('field-operations-analytics.recalculate') }}" class="flex items-end gap-2">
                    @csrf
                    <input type="hidden" name="period_start" value="{{ $periodStart }}">
                    <input type="hidden" name="period_end" value="{{ $periodEnd }}">
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('Recalculate') }}</button>
                </form>
            </div>

            <form method="GET" action="{{ route('field-operations-analytics.index') }}" class="flex flex-wrap items-end gap-4 mt-4">
                <div>
                    <label for="period_start" class="label text-sm">{{ __('From') }}</label>
                    <input type="date" name="period_start" id="period_start" value="{{ $periodStart }}" class="input">
                </div>
                <div>
                    <label for="period_end" class="label text-sm">{{ __('To') }}</label>
                    <input type="date" name="period_end" id="period_end" value="{{ $periodEnd }}" class="input">
                </div>
                <button type="submit" class="btn btn-sm">{{ __('Filter') }}</button>
            </form>

            <div class="mt-8">
                <h3 class="font-semibold mb-3">{{ __('Rep Commissions') }}</h3>
                <div class="overflow-x-auto">
                    <table class="w-full divide-y">
                        <thead>
                            <tr>
                                <th class="px-2 py-2 text-xs text-gray-500">{{ __('Rep') }}</th>
                                <th class="px-2 py-2 text-xs text-gray-500">{{ __('Total Settled') }}</th>
                                <th class="px-2 py-2 text-xs text-gray-500">{{ __('Tier') }}</th>
                                <th class="px-2 py-2 text-xs text-gray-500">{{ __('Rate') }}</th>
                                <th class="px-2 py-2 text-xs text-gray-500">{{ __('Commission') }}</th>
                            </tr>
                        </thead>
                        <tbody class="text-center">
                            @forelse ($commissions as $commission)
                                <tr>
                                    <td class="px-2 py-1">{{ $commission->user?->name }}</td>
                                    <td class="px-2 py-1">{{ localizeNumber(number_format($commission->total_settled_amount, 2)) }}</td>
                                    <td class="px-2 py-1">{{ $commission->tier_label }}</td>
                                    <td class="px-2 py-1">{{ localizeNumber($commission->commission_rate) }}%</td>
                                    <td class="px-2 py-1 font-semibold">{{ localizeNumber(number_format($commission->commission_amount, 2)) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-gray-500 p-3">{{ __('No commissions calculated for this period yet.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-8">
                <h3 class="font-semibold mb-3">{{ __('Route Metrics') }}</h3>
                <div class="overflow-x-auto">
                    <table class="w-full divide-y">
                        <thead>
                            <tr>
                                <th class="px-2 py-2 text-xs text-gray-500">{{ __('Route') }}</th>
                                <th class="px-2 py-2 text-xs text-gray-500">{{ __('Visit Completion Rate') }}</th>
                                <th class="px-2 py-2 text-xs text-gray-500">{{ __('Average Invoice Value') }}</th>
                                <th class="px-2 py-2 text-xs text-gray-500">{{ __('Cash Collection Ratio') }}</th>
                            </tr>
                        </thead>
                        <tbody class="text-center">
                            @forelse ($routeMetrics as $metric)
                                <tr>
                                    <td class="px-2 py-1">{{ $metric->route?->title }}</td>
                                    <td class="px-2 py-1">{{ localizeNumber($metric->visit_completion_rate) }}%</td>
                                    <td class="px-2 py-1">{{ localizeNumber(number_format($metric->average_invoice_value, 2)) }}</td>
                                    <td class="px-2 py-1">{{ localizeNumber($metric->cash_collection_ratio) }}%</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-gray-500 p-3">{{ __('No route metrics calculated for this period yet.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
