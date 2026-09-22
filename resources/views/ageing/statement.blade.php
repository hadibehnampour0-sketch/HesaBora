<x-app-layout :title="$title">
    <x-show-message-bags />

    <main class="mt-8 space-y-4">
        <section>
            <h1 class="text-2xl font-bold text-base-content">{{ $title }}</h1>
            <p class="text-sm text-base-content/60">{{ $customer->name }}</p>
        </section>

        <section class="card border border-base-300 bg-base-100/90 shadow-sm">
            <div class="card-body">
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>{{ __('Invoice #') }}</th>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Type') }}</th>
                                <th>{{ __('Amount') }}</th>
                                <th>{{ __('Paid') }}</th>
                                <th>{{ __('Remaining') }}</th>
                                <th>{{ __('Running balance') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($lines as $line)
                                <tr>
                                    <td>
                                        <a href="{{ route('invoices.show', $line['id']) }}" class="link">{{ $line['number'] ?? $line['id'] }}</a>
                                    </td>
                                    <td>{{ $line['date']->format('Y-m-d') }}</td>
                                    <td>{{ $line['invoice_type']->label() }}</td>
                                    <td>{{ number_format($line['amount']) }}</td>
                                    <td>{{ number_format($line['paid']) }}</td>
                                    <td>{{ number_format($line['remaining']) }}</td>
                                    <td class="font-bold">{{ number_format($line['running_balance']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-base-content/50">{{ __('No invoices found.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</x-app-layout>
