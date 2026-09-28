<x-app-layout :title="__('Moadian work queue')">
    <x-show-message-bags />

    <div class="mx-auto max-w-5xl space-y-4">
        <div>
            <h1 class="text-xl font-bold">{{ __('Moadian work queue') }}</h1>
            <p class="text-sm text-base-content/60">{{ __('Every approved sales invoice that has never successfully reached Moadian: never sent, or rejected on its last attempt. Fix and resend from here.') }}</p>
        </div>

        @if ($invoices->isEmpty())
            <div class="alert alert-success text-sm">{{ __('Nothing waiting -- every approved sales invoice has reached Moadian successfully.') }}</div>
        @else
            <div class="today-panel overflow-x-auto p-0">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('Invoice Number') }}</th><th>{{ __('Customer Name') }}</th><th>{{ __('Date') }}</th>
                            <th>{{ __('Status') }}</th><th>{{ __('Reason') }}</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoices as $invoice)
                            @php
                                $history = $invoice->latestMoadianHistory;
                                $status = $history ? (\App\Enums\MoadianStatus::fromData($history->data['status'] ?? null) ?? \App\Enums\MoadianStatus::UNKNOWN) : null;
                                $reason = $history?->data['error'] ?? $history?->data['message'] ?? null;
                            @endphp
                            <tr>
                                <td>
                                    @can('invoices.show')
                                        <a class="link" href="{{ route('invoices.show', $invoice) }}">{{ formatDocumentNumber($invoice->number) }}</a>
                                    @else
                                        {{ formatDocumentNumber($invoice->number) }}
                                    @endcan
                                </td>
                                <td>{{ $invoice->customer?->name }}</td>
                                <td>{{ formatDate($invoice->date) }}</td>
                                <td>
                                    @if ($status)
                                        <span class="badge badge-sm {{ $status->color() }}">{{ $status->label() }}</span>
                                    @else
                                        <span class="badge badge-sm badge-ghost">{{ __('Not sent') }}</span>
                                    @endif
                                </td>
                                <td class="max-w-xs truncate text-xs text-base-content/60" title="{{ $reason }}">{{ $reason ?? '—' }}</td>
                                <td class="text-end">
                                    @can('invoices.send-moadian')
                                        <form method="POST" action="{{ route('invoices.send-moadian', $invoice) }}">
                                            @csrf
                                            <button class="btn btn-xs btn-primary">{{ $history ? __('Retry') : __('Send') }}</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{ $invoices->links() }}

            <div class="alert text-xs">
                {{ __('If resending keeps failing, check the company tax profile (economic code, national ID and Moadian credentials).') }}
                @can('companies.index')
                    <a href="{{ route('companies.index') }}" class="link">{{ __('Company tax profile') }}</a>
                @endcan
            </div>
        @endif
    </div>
</x-app-layout>
