<x-app-layout :title="$title">
    <x-show-message-bags />

    <main class="mt-8 space-y-4">
        <section>
            <h1 class="text-2xl font-bold text-base-content">{{ $title }}</h1>
            <p class="text-sm text-base-content/60">
                @if ($type === 'payables')
                    {{ __('Outstanding balances owed to suppliers, grouped by invoice age') }}
                @else
                    {{ __('Outstanding balances owed by customers, grouped by invoice age') }}
                @endif
            </p>
        </section>

        <section class="grid grid-cols-2 gap-3 xl:grid-cols-4">
            @foreach ($report['buckets'] as $bucket)
                <article class="card border border-base-300 bg-base-100/90 shadow-sm">
                    <div class="card-body p-4">
                        <p class="text-xs text-base-content/60">{{ $bucket['label'] }}</p>
                        <p class="text-lg font-bold">{{ number_format($bucket['amount']) }}</p>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="card border border-base-300 bg-base-100/90 shadow-sm">
            <div class="card-body">
                <h2 class="card-title text-base">
                    {{ $type === 'payables' ? __('Suppliers with an open balance') : __('Customers with an open balance') }}
                </h2>
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('0-30 days') }}</th>
                                <th>{{ __('31-60 days') }}</th>
                                <th>{{ __('61-90 days') }}</th>
                                <th>{{ __('Over 90 days') }}</th>
                                <th>{{ __('Total') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($report['parties'] as $party)
                                <tr>
                                    <td>{{ $party['name'] }}</td>
                                    @foreach ($party['buckets'] as $bucket)
                                        <td>{{ $bucket['amount'] > 0 ? number_format($bucket['amount']) : '-' }}</td>
                                    @endforeach
                                    <td class="font-bold">{{ number_format($party['total']) }}</td>
                                    <td>
                                        <a href="{{ route('ageing.statement', ['customer' => $party['customer_id'], 'type' => $type]) }}" class="btn btn-xs btn-outline">
                                            {{ __('View statement') }}
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-base-content/50">{{ __('No outstanding balances.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if (count($report['parties']))
                            <tfoot>
                                <tr class="font-bold">
                                    <td>{{ __('Total') }}</td>
                                    <td colspan="4"></td>
                                    <td>{{ number_format($report['total']) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </section>

        <section class="card border border-base-300 bg-base-100/90 shadow-sm">
            <div class="card-body">
                <h2 class="card-title text-base">{{ __('Overdue worklist') }}</h2>
                <p class="text-xs text-base-content/55">{{ __('Invoices older than 30 days, oldest first') }}</p>
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Invoice #') }}</th>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Age (days)') }}</th>
                                <th>{{ __('Remaining') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($overdue as $line)
                                <tr>
                                    <td>{{ $line['name'] }}</td>
                                    <td>{{ $line['number'] }}</td>
                                    <td>{{ $line['date']->format('Y-m-d') }}</td>
                                    <td>{{ $line['age_days'] }}</td>
                                    <td>{{ number_format($line['remaining']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-base-content/50">{{ __('Nothing overdue.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</x-app-layout>
